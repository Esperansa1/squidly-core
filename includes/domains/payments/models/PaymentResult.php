<?php
declare(strict_types=1);

/**
 * Payment Result Model
 * 
 * Contains the result of a payment operation (process, capture, refund, void)
 */
class PaymentResult
{
    public bool $success;
    public string $gatewayId;
    public string $status;
    public ?string $transactionId;
    public ?string $gatewayTransactionId;
    public float $amount;
    public string $currency;
    public ?string $message;
    public ?string $errorCode;
    public array $gatewayData;
    public string $processedAt;

    // Payment Result Statuses
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_VOIDED = 'voided';

    public function __construct(
        bool $success,
        string $gatewayId,
        string $transactionId,
        string $gatewayTransactionId,
        float $amount,
        string $currency,
        string $status,
        string $message,
        ?string $errorCode = null,
        array $gatewayData = [],
        ?string $processedAt = null
    ) {
        $this->success = $success;
        $this->gatewayId = $gatewayId;
        $this->status = $status;
        $this->transactionId = $transactionId;
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->message = $message;
        $this->errorCode = $errorCode;
        $this->gatewayData = $gatewayData;
        $this->processedAt = $processedAt ?: date('Y-m-d H:i:s');
    }

    /**
     * Create successful payment result
     */
    public static function success(
        float $amount,
        string $transactionId,
        string $gatewayTransactionId,
        string $currency = 'ILS',
        string $message = 'Payment processed successfully',
        array $gatewayData = []
    ): self {
        return new self(
            success: true,
            status: self::STATUS_SUCCESS,
            amount: $amount,
            currency: $currency,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            message: $message,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create pending payment result
     */
    public static function pending(
        float $amount,
        string $transactionId,
        string $gatewayTransactionId,
        string $currency = 'ILS',
        string $message = 'Payment is being processed',
        array $gatewayData = []
    ): self {
        return new self(
            success: false, // Pending is not success until confirmed
            status: self::STATUS_PENDING,
            amount: $amount,
            currency: $currency,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            message: $message,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create failed payment result
     */
    public static function failed(
        float $amount,
        string $message,
        ?string $errorCode = null,
        string $currency = 'ILS',
        ?string $transactionId = null,
        ?string $gatewayTransactionId = null,
        array $gatewayData = []
    ): self {
        return new self(
            success: false,
            status: self::STATUS_FAILED,
            amount: $amount,
            currency: $currency,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            message: $message,
            errorCode: $errorCode,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create declined payment result
     */
    public static function declined(
        float $amount,
        string $message,
        ?string $errorCode = null,
        string $currency = 'ILS',
        ?string $transactionId = null,
        ?string $gatewayTransactionId = null,
        array $gatewayData = []
    ): self {
        return new self(
            success: false,
            status: self::STATUS_DECLINED,
            amount: $amount,
            currency: $currency,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            message: $message,
            errorCode: $errorCode,
            gatewayData: $gatewayData
        );
    }

    /**
     * Check if the payment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->success && $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the payment failed
     */
    public function hasFailed(): bool
    {
        return !$this->success && in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ]);
    }

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_SUCCESS,
            self::STATUS_PENDING,
            self::STATUS_FAILED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
            self::STATUS_REFUNDED,
            self::STATUS_PARTIALLY_REFUNDED,
            self::STATUS_VOIDED,
        ];
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'transaction_id' => $this->transactionId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'error_code' => $this->errorCode,
            'gateway_data' => $this->gatewayData,
            'processed_at' => $this->processedAt,
        ];
    }
}