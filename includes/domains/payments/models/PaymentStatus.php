<?php
declare(strict_types=1);

/**
 * Payment Status Model
 * 
 * Represents the current status of a payment as reported by the gateway
 */
class PaymentStatus
{
    public string $transactionId;
    public string $gatewayTransactionId;
    public string $status;
    public float $amount;
    public float $amountCaptured;
    public float $amountRefunded;
    public string $currency;
    public ?string $lastUpdated;
    public array $gatewayData;

    // Payment Statuses
    public const STATUS_CREATED = 'created';
    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_PARTIALLY_CAPTURED = 'partially_captured';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_EXPIRED = 'expired';

    public function __construct(
        string $transactionId,
        string $gatewayTransactionId,
        string $status,
        float $amount,
        float $amountCaptured = 0.0,
        float $amountRefunded = 0.0,
        string $currency = 'ILS',
        ?string $lastUpdated = null,
        array $gatewayData = []
    ) {
        $this->transactionId = $transactionId;
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->status = $status;
        $this->amount = $amount;
        $this->amountCaptured = $amountCaptured;
        $this->amountRefunded = $amountRefunded;
        $this->currency = $currency;
        $this->lastUpdated = $lastUpdated ?: date('Y-m-d H:i:s');
        $this->gatewayData = $gatewayData;
    }

    /**
     * Check if payment is completed (captured)
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [
            self::STATUS_CAPTURED,
            self::STATUS_PARTIALLY_CAPTURED
        ]);
    }

    /**
     * Check if payment is authorized but not captured
     */
    public function isAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment has failed
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ]);
    }

    /**
     * Check if payment has been refunded
     */
    public function isRefunded(): bool
    {
        return in_array($this->status, [
            self::STATUS_REFUNDED,
            self::STATUS_PARTIALLY_REFUNDED
        ]) || $this->amountRefunded > 0;
    }

    /**
     * Check if payment is voided
     */
    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * Get remaining amount that can be captured
     */
    public function getRemainingCaptureAmount(): float
    {
        if (!$this->isAuthorized()) {
            return 0.0;
        }
        
        return $this->amount - $this->amountCaptured;
    }

    /**
     * Get remaining amount that can be refunded
     */
    public function getRemainingRefundAmount(): float
    {
        if (!$this->isCompleted()) {
            return 0.0;
        }
        
        return $this->amountCaptured - $this->amountRefunded;
    }

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_PENDING,
            self::STATUS_AUTHORIZED,
            self::STATUS_CAPTURED,
            self::STATUS_PARTIALLY_CAPTURED,
            self::STATUS_FAILED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
            self::STATUS_PARTIALLY_REFUNDED,
            self::STATUS_VOIDED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'status' => $this->status,
            'amount' => $this->amount,
            'amount_captured' => $this->amountCaptured,
            'amount_refunded' => $this->amountRefunded,
            'currency' => $this->currency,
            'last_updated' => $this->lastUpdated,
            'gateway_data' => $this->gatewayData,
        ];
    }
}