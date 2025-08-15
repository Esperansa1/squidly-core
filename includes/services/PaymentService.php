<?php
declare(strict_types=1);

/**
 * Payment Service
 * 
 * Orchestrates payment operations using the configured payment gateway
 */
class PaymentService
{
    private PaymentGatewayInterface $gateway;
    private OrderRepository $orderRepo;

    public function __construct(PaymentGatewayInterface $gateway, OrderRepository $orderRepo)
    {
        $this->gateway = $gateway;
        $this->orderRepo = $orderRepo;
    }

    /**
     * Process a payment for an order
     */
    public function processPayment(PaymentRequest $request, array $paymentData): PaymentResult
    {
        try {
            // Validate the payment request
            $errors = $request->validate();
            if (!empty($errors)) {
                throw PaymentException::invalidRequest(implode(', ', $errors));
            }

            // Create payment intent
            $intent = $this->gateway->createPaymentIntent($request);
            
            // Process the payment
            $result = $this->gateway->processPayment($intent, $paymentData);
            
            // Update order based on payment result
            $this->updateOrderFromPaymentResult($request->orderId, $result);
            
            return $result;
            
        } catch (PaymentException $e) {
            // Log payment error
            error_log('Payment failed: ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            // Convert generic exceptions to payment exceptions
            error_log('Payment system error: ' . $e->getMessage());
            throw PaymentException::processingError($e->getMessage());
        }
    }

    /**
     * Capture an authorized payment
     */
    public function capturePayment(string $transactionId, ?float $amount = null): PaymentResult
    {
        try {
            return $this->gateway->capturePayment($transactionId, $amount);
        } catch (PaymentException $e) {
            error_log('Payment capture failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refund a captured payment
     */
    public function refundPayment(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        try {
            if ($amount <= 0) {
                throw PaymentException::invalidAmount($amount);
            }

            return $this->gateway->refundPayment($transactionId, $amount, $reason);
        } catch (PaymentException $e) {
            error_log('Payment refund failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Void an authorized but uncaptured payment
     */
    public function voidPayment(string $transactionId): PaymentResult
    {
        try {
            return $this->gateway->voidPayment($transactionId);
        } catch (PaymentException $e) {
            error_log('Payment void failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get current payment status
     */
    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            return $this->gateway->getPaymentStatus($transactionId);
        } catch (PaymentException $e) {
            error_log('Failed to get payment status: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle webhook from payment gateway
     */
    public function handleWebhook(array $webhookData): PaymentWebhookResult
    {
        try {
            $result = $this->gateway->handleWebhook($webhookData);
            
            // Update order if webhook contains transaction info
            if ($result->success && $result->transactionId) {
                $this->updateOrderFromWebhook($result);
            }
            
            return $result;
        } catch (PaymentException $e) {
            error_log('Webhook processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update order status based on payment result
     */
    private function updateOrderFromPaymentResult(int $orderId, PaymentResult $result): void
    {
        $order = $this->orderRepo->get($orderId);
        if (!$order) {
            error_log("Order not found for payment update: {$orderId}");
            return;
        }

        // Update payment info
        $order->payment_method = $result->gatewayId;
        $order->gateway_transaction_id = $result->gatewayTransactionId;
        
        // Update order status based on payment status
        if ($result->success) {
            switch ($result->status) {
                case PaymentStatus::STATUS_CAPTURED:
                    $order->status = Order::STATUS_CONFIRMED;
                    $order->payment_status = Order::PAYMENT_PAID;
                    break;
                    
                case PaymentStatus::STATUS_AUTHORIZED:
                    $order->status = Order::STATUS_PENDING;
                    $order->payment_status = Order::PAYMENT_AUTHORIZED;
                    break;
                    
                case PaymentStatus::STATUS_PENDING:
                    $order->status = Order::STATUS_PENDING;
                    $order->payment_status = Order::PAYMENT_PENDING;
                    break;
            }
        } else {
            $order->status = Order::STATUS_CANCELLED;
            $order->payment_status = Order::PAYMENT_FAILED;
        }

        $this->orderRepo->update($order->id, $order->toArray());
    }

    /**
     * Update order status based on webhook result
     */
    private function updateOrderFromWebhook(PaymentWebhookResult $result): void
    {
        if (!$result->transactionId) {
            return;
        }

        // Find order by gateway transaction ID
        $orders = $this->orderRepo->findBy(['gateway_transaction_id' => $result->gatewayTransactionId]);
        if (empty($orders)) {
            error_log("Order not found for webhook: {$result->gatewayTransactionId}");
            return;
        }

        $order = $orders[0];

        // Update order based on webhook event
        switch ($result->eventType) {
            case PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED:
            case PaymentWebhookResult::EVENT_PAYMENT_CAPTURED:
                $order->status = Order::STATUS_CONFIRMED;
                $order->payment_status = Order::PAYMENT_PAID;
                break;

            case PaymentWebhookResult::EVENT_PAYMENT_FAILED:
            case PaymentWebhookResult::EVENT_PAYMENT_CANCELLED:
                $order->status = Order::STATUS_CANCELLED;
                $order->payment_status = Order::PAYMENT_FAILED;
                break;

            case PaymentWebhookResult::EVENT_PAYMENT_REFUNDED:
                $order->payment_status = Order::PAYMENT_REFUNDED;
                break;

            case PaymentWebhookResult::EVENT_PAYMENT_AUTHORIZED:
                $order->payment_status = Order::PAYMENT_AUTHORIZED;
                break;
        }

        $this->orderRepo->update($order->id, $order->toArray());
        $result->addAction('order_updated', ['order_id' => $order->id, 'new_status' => $order->status]);
    }

    /**
     * Get gateway information
     */
    public function getGatewayInfo(): array
    {
        return [
            'gateway_id' => $this->gateway->getGatewayId(),
            'display_name' => $this->gateway->getDisplayName(),
            'supported_currencies' => $this->gateway->getSupportedCurrencies(),
            'supports_authorize' => $this->gateway->supportsAuthorization(),
            'supports_capture' => $this->gateway->supportsCapture(),
            'supports_refund' => $this->gateway->supportsRefunds(),
            'supports_void' => $this->gateway->supportsVoid(),
        ];
    }
}