<?php
declare(strict_types=1);

/**
 * Stripe Payment Gateway Implementation
 * 
 * Concrete implementation of PaymentGatewayInterface for Stripe
 */
class StripePaymentGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $publishableKey;
    private bool $testMode;
    private string $apiVersion = '2023-10-16';

    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'] ?? '';
        $this->publishableKey = $config['publishable_key'] ?? '';
        $this->testMode = (bool) ($config['test_mode'] ?? true);

        if (empty($this->secretKey) || empty($this->publishableKey)) {
            throw PaymentException::gatewayNotConfigured('stripe');
        }
    }

    public function getGatewayId(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function getSupportedCurrencies(): array
    {
        return ['ILS', 'USD', 'EUR', 'GBP'];
    }

    public function supportsAuthorization(): bool
    {
        return true;
    }

    public function supportsCapture(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function supportsVoid(): bool
    {
        return true;
    }

    public function createPaymentIntent(PaymentRequest $request): PaymentIntent
    {
        try {
            $data = [
                'amount' => $request->getAmountInCents(),
                'currency' => strtolower($request->currency),
                'description' => $request->description,
                'metadata' => array_merge($request->metadata, [
                    'order_id' => (string) $request->orderId,
                    'customer_id' => (string) $request->customerId,
                ]),
                'capture_method' => $request->captureImmediately ? 'automatic' : 'manual',
            ];

            if ($request->customerEmail) {
                $data['receipt_email'] = $request->customerEmail;
            }

            $response = $this->makeApiRequest('POST', 'payment_intents', $data);

            return new PaymentIntent(
                gatewayId: $this->getGatewayId(),
                gatewayIntentId: $response['id'],
                clientSecret: $response['client_secret'],
                amount: $request->amount,
                currency: $request->currency,
                status: $this->mapStripeStatus($response['status']),
                metadata: $request->metadata,
                createdAt: date('Y-m-d H:i:s', $response['created'])
            );

        } catch (Exception $e) {
            throw $this->handleStripeError($e);
        }
    }

    public function processPayment(PaymentIntent $intent, array $paymentData): PaymentResult
    {
        try {
            // For Stripe, payment processing is typically handled on the frontend
            // This method would be used for server-side confirmation if needed
            $response = $this->makeApiRequest('GET', "payment_intents/{$intent->gatewayIntentId}");

            $success = in_array($response['status'], ['succeeded', 'requires_capture']);
            $status = $this->mapStripeStatus($response['status']);

            return new PaymentResult(
                success: $success,
                gatewayId: $this->getGatewayId(),
                transactionId: $this->generateTransactionId($response['id']),
                gatewayTransactionId: $response['id'],
                amount: $response['amount'] / 100,
                currency: strtoupper($response['currency']),
                status: $status,
                message: $success ? 'Payment processed successfully' : 'Payment failed',
                gatewayData: $response
            );

        } catch (Exception $e) {
            throw $this->handleStripeError($e);
        }
    }

    public function capturePayment(string $transactionId, ?float $amount = null): PaymentResult
    {
        try {
            $gatewayId = $this->extractGatewayId($transactionId);
            $data = [];
            
            if ($amount !== null) {
                $data['amount_to_capture'] = (int) round($amount * 100);
            }

            $response = $this->makeApiRequest('POST', "payment_intents/{$gatewayId}/capture", $data);

            return new PaymentResult(
                success: $response['status'] === 'succeeded',
                gatewayId: $this->getGatewayId(),
                transactionId: $transactionId,
                gatewayTransactionId: $response['id'],
                amount: $response['amount_received'] / 100,
                currency: strtoupper($response['currency']),
                status: $this->mapStripeStatus($response['status']),
                message: 'Payment captured successfully',
                gatewayData: $response
            );

        } catch (Exception $e) {
            throw $this->handleStripeError($e);
        }
    }

    public function refundPayment(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        try {
            $gatewayId = $this->extractGatewayId($transactionId);
            $data = [
                'payment_intent' => $gatewayId,
                'amount' => (int) round($amount * 100),
            ];

            if ($reason) {
                $data['reason'] = $reason;
            }

            $response = $this->makeApiRequest('POST', 'refunds', $data);

            return new PaymentResult(
                success: $response['status'] === 'succeeded',
                gatewayId: $this->getGatewayId(),
                transactionId: $transactionId,
                gatewayTransactionId: $response['payment_intent'],
                amount: $response['amount'] / 100,
                currency: strtoupper($response['currency']),
                status: PaymentStatus::STATUS_REFUNDED,
                message: 'Payment refunded successfully',
                gatewayData: $response
            );

        } catch (Exception $e) {
            throw $this->handleStripeError($e);
        }
    }

    public function voidPayment(string $transactionId): PaymentResult
    {
        try {
            $gatewayId = $this->extractGatewayId($transactionId);
            $response = $this->makeApiRequest('POST', "payment_intents/{$gatewayId}/cancel");

            return new PaymentResult(
                success: $response['status'] === 'canceled',
                gatewayId: $this->getGatewayId(),
                transactionId: $transactionId,
                gatewayTransactionId: $response['id'],
                amount: $response['amount'] / 100,
                currency: strtoupper($response['currency']),
                status: PaymentStatus::STATUS_VOIDED,
                message: 'Payment voided successfully',
                gatewayData: $response
            );

        } catch (Exception $e) {
            throw $this->handleStripeError($e);
        }
    }

    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            $gatewayId = $this->extractGatewayId($transactionId);
            $response = $this->makeApiRequest('GET', "payment_intents/{$gatewayId}");

            return new PaymentStatus(
                transactionId: $transactionId,
                gatewayTransactionId: $response['id'],
                status: $this->mapStripeStatus($response['status']),
                amount: $response['amount'] / 100,
                amountCaptured: ($response['amount_received'] ?? 0) / 100,
                amountRefunded: 0.0, // Would need to fetch refunds separately
                currency: strtoupper($response['currency']),
                lastUpdated: date('Y-m-d H:i:s'),
                gatewayData: $response
            );

        } catch (Exception $e) {
            throw $this->handleStripeError($e);
        }
    }

    public function handleWebhook(array $webhookData): PaymentWebhookResult
    {
        try {
            // Verify webhook signature (simplified - in production, use Stripe's webhook signature verification)
            $event = $webhookData;
            
            if (!isset($event['type']) || !isset($event['data']['object'])) {
                return PaymentWebhookResult::failed(
                    PaymentWebhookResult::EVENT_UNKNOWN,
                    'Invalid webhook payload'
                );
            }

            $eventType = $this->mapStripeWebhookEvent($event['type']);
            $paymentIntent = $event['data']['object'];
            
            $transactionId = $this->generateTransactionId($paymentIntent['id']);

            $result = PaymentWebhookResult::success(
                eventType: $eventType,
                data: $event,
                transactionId: $transactionId,
                gatewayTransactionId: $paymentIntent['id']
            );

            $result->addAction('webhook_processed', [
                'stripe_event_id' => $event['id'] ?? null,
                'stripe_event_type' => $event['type']
            ]);

            return $result;

        } catch (Exception $e) {
            return PaymentWebhookResult::failed(
                PaymentWebhookResult::EVENT_UNKNOWN,
                'Webhook processing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Make API request to Stripe
     */
    private function makeApiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = "https://api.stripe.com/v1/{$endpoint}";
        
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: ' . $this->apiVersion,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw PaymentException::networkError("Stripe API request failed: {$error}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw PaymentException::gatewayError('Invalid JSON response from Stripe');
        }

        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Unknown Stripe error';
            throw PaymentException::gatewayError($message, $decoded);
        }

        return $decoded;
    }

    /**
     * Map Stripe status to our status constants
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'requires_payment_method', 'requires_confirmation' => PaymentStatus::STATUS_CREATED,
            'requires_action', 'processing' => PaymentStatus::STATUS_PENDING,
            'requires_capture' => PaymentStatus::STATUS_AUTHORIZED,
            'succeeded' => PaymentStatus::STATUS_CAPTURED,
            'canceled' => PaymentStatus::STATUS_CANCELLED,
            default => PaymentStatus::STATUS_FAILED,
        };
    }

    /**
     * Map Stripe webhook events to our event types
     */
    private function mapStripeWebhookEvent(string $stripeEvent): string
    {
        return match ($stripeEvent) {
            'payment_intent.succeeded' => PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED,
            'payment_intent.payment_failed' => PaymentWebhookResult::EVENT_PAYMENT_FAILED,
            'payment_intent.canceled' => PaymentWebhookResult::EVENT_PAYMENT_CANCELLED,
            'payment_intent.requires_action' => PaymentWebhookResult::EVENT_PAYMENT_AUTHORIZED,
            'charge.dispute.created' => PaymentWebhookResult::EVENT_PAYMENT_DISPUTED,
            default => PaymentWebhookResult::EVENT_UNKNOWN,
        };
    }

    /**
     * Generate internal transaction ID from Stripe payment intent ID
     */
    private function generateTransactionId(string $gatewayId): string
    {
        return "stripe_{$gatewayId}";
    }

    /**
     * Extract Stripe payment intent ID from internal transaction ID
     */
    private function extractGatewayId(string $transactionId): string
    {
        if (strpos($transactionId, 'stripe_') === 0) {
            return substr($transactionId, 7);
        }
        return $transactionId;
    }

    /**
     * Handle Stripe-specific errors
     */
    private function handleStripeError(Exception $e): PaymentException
    {
        if ($e instanceof PaymentException) {
            return $e;
        }

        // Map common Stripe errors to our exception types
        $message = $e->getMessage();
        
        if (strpos($message, 'card_declined') !== false) {
            return PaymentException::cardDeclined($message);
        }
        
        if (strpos($message, 'insufficient_funds') !== false) {
            return PaymentException::insufficientFunds($message);
        }
        
        if (strpos($message, 'expired_card') !== false) {
            return PaymentException::expiredCard();
        }
        
        if (strpos($message, 'invalid_number') !== false || strpos($message, 'invalid_cvc') !== false) {
            return PaymentException::invalidCard($message);
        }

        return PaymentException::gatewayError($message);
    }
}