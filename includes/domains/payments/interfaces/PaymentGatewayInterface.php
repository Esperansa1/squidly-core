<?php
declare(strict_types=1);

/**
 * Payment Gateway Interface
 * 
 * Defines the contract for all payment gateway implementations.
 * This allows easy addition of new payment providers (Stripe, PayPal, Sibus, etc.)
 */
interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier (stripe, paypal, sibus, etc.)
     */
    public function getGatewayId(): string;

    /**
     * Get the gateway display name
     */
    public function getDisplayName(): string;

    /**
     * Get supported currencies for this gateway
     */
    public function getSupportedCurrencies(): array;

    /**
     * Check if gateway supports authorization/capture flow
     */
    public function supportsAuthorization(): bool;

    /**
     * Check if gateway supports capture operations
     */
    public function supportsCapture(): bool;

    /**
     * Check if gateway supports refund operations
     */
    public function supportsRefunds(): bool;

    /**
     * Check if gateway supports void operations
     */
    public function supportsVoid(): bool;

    /**
     * Create a payment intent/session for processing
     * 
     * @param PaymentRequest $request Payment details and amount
     * @return PaymentIntent The created payment intent
     * @throws PaymentException When payment intent creation fails
     */
    public function createPaymentIntent(PaymentRequest $request): PaymentIntent;

    /**
     * Process a payment
     * 
     * @param PaymentIntent $intent The payment intent to process
     * @param array $paymentData Gateway-specific payment data (tokens, card details, etc.)
     * @return PaymentResult The result of the payment processing
     * @throws PaymentException When payment processing fails
     */
    public function processPayment(PaymentIntent $intent, array $paymentData): PaymentResult;

    /**
     * Capture a previously authorized payment
     * 
     * @param string $transactionId The transaction ID to capture
     * @param float|null $amount Amount to capture (null for full amount)
     * @return PaymentResult The result of the capture
     * @throws PaymentException When capture fails
     */
    public function capturePayment(string $transactionId, ?float $amount = null): PaymentResult;

    /**
     * Refund a payment (full or partial)
     * 
     * @param string $transactionId The transaction ID to refund
     * @param float|null $amount Amount to refund (null for full refund)
     * @param string|null $reason Reason for refund
     * @return PaymentResult The result of the refund
     * @throws PaymentException When refund fails
     */
    public function refundPayment(string $transactionId, float $amount, string $reason = ''): PaymentResult;

    /**
     * Void/cancel an authorized but not captured payment
     * 
     * @param string $transactionId The transaction ID to void
     * @return PaymentResult The result of the void operation
     * @throws PaymentException When void fails
     */
    public function voidPayment(string $transactionId): PaymentResult;

    /**
     * Get payment status from the gateway
     * 
     * @param string $transactionId The transaction ID to check
     * @return PaymentStatus Current status of the payment
     * @throws PaymentException When status check fails
     */
    public function getPaymentStatus(string $transactionId): PaymentStatus;

    /**
     * Handle webhook notifications from the payment gateway
     * 
     * @param array $webhookData Raw webhook data from the gateway
     * @return PaymentWebhookResult Result of webhook processing
     * @throws PaymentException When webhook processing fails
     */
    public function handleWebhook(array $webhookData): PaymentWebhookResult;
}