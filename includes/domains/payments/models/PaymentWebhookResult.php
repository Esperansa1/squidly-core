<?php
declare(strict_types=1);

/**
 * Payment Webhook Result Model
 * 
 * Contains the result of processing a webhook from a payment gateway
 */
class PaymentWebhookResult
{
    public bool $success;
    public string $eventType;
    public ?string $transactionId;
    public ?string $gatewayTransactionId;
    public array $data;
    public ?string $message;
    public array $actions;
    public string $processedAt;

    // Webhook Event Types
    public const EVENT_PAYMENT_SUCCEEDED = 'payment.succeeded';
    public const EVENT_PAYMENT_FAILED = 'payment.failed';
    public const EVENT_PAYMENT_CANCELLED = 'payment.cancelled';
    public const EVENT_PAYMENT_REFUNDED = 'payment.refunded';
    public const EVENT_PAYMENT_DISPUTED = 'payment.disputed';
    public const EVENT_PAYMENT_CAPTURED = 'payment.captured';
    public const EVENT_PAYMENT_AUTHORIZED = 'payment.authorized';
    public const EVENT_UNKNOWN = 'unknown';

    public function __construct(
        bool $success,
        string $eventType,
        array $data = [],
        ?string $transactionId = null,
        ?string $gatewayTransactionId = null,
        ?string $message = null,
        array $actions = [],
        ?string $processedAt = null
    ) {
        $this->success = $success;
        $this->eventType = $eventType;
        $this->transactionId = $transactionId;
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->data = $data;
        $this->message = $message;
        $this->actions = $actions;
        $this->processedAt = $processedAt ?: date('Y-m-d H:i:s');
    }

    /**
     * Create successful webhook result
     */
    public static function success(
        string $eventType,
        array $data = [],
        ?string $transactionId = null,
        ?string $gatewayTransactionId = null,
        string $message = 'Webhook processed successfully',
        array $actions = []
    ): self {
        return new self(
            success: true,
            eventType: $eventType,
            data: $data,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            message: $message,
            actions: $actions
        );
    }

    /**
     * Create failed webhook result
     */
    public static function failed(
        string $eventType,
        string $message,
        array $data = []
    ): self {
        return new self(
            success: false,
            eventType: $eventType,
            data: $data,
            message: $message
        );
    }

    /**
     * Add an action that was taken as a result of this webhook
     */
    public function addAction(string $action, array $details = []): void
    {
        $this->actions[] = [
            'action' => $action,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Check if this is a payment completion event
     */
    public function isPaymentCompleted(): bool
    {
        return in_array($this->eventType, [
            self::EVENT_PAYMENT_SUCCEEDED,
            self::EVENT_PAYMENT_CAPTURED
        ]);
    }

    /**
     * Check if this is a payment failure event
     */
    public function isPaymentFailed(): bool
    {
        return in_array($this->eventType, [
            self::EVENT_PAYMENT_FAILED,
            self::EVENT_PAYMENT_CANCELLED
        ]);
    }

    /**
     * Get all valid event types
     */
    public static function getValidEventTypes(): array
    {
        return [
            self::EVENT_PAYMENT_SUCCEEDED,
            self::EVENT_PAYMENT_FAILED,
            self::EVENT_PAYMENT_CANCELLED,
            self::EVENT_PAYMENT_REFUNDED,
            self::EVENT_PAYMENT_DISPUTED,
            self::EVENT_PAYMENT_CAPTURED,
            self::EVENT_PAYMENT_AUTHORIZED,
            self::EVENT_UNKNOWN,
        ];
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'event_type' => $this->eventType,
            'transaction_id' => $this->transactionId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'data' => $this->data,
            'message' => $this->message,
            'actions' => $this->actions,
            'processed_at' => $this->processedAt,
        ];
    }
}