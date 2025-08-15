<?php
declare(strict_types=1);

/**
 * Payment Intent Model
 * 
 * Represents a payment intent created by a gateway, containing all necessary
 * information to complete the payment process
 */
class PaymentIntent
{
    public string $id;
    public string $gatewayId;
    public string $gatewayIntentId;
    public int $orderId;
    public int $customerId;
    public float $amount;
    public string $currency;
    public string $status;
    public array $metadata;
    public ?string $clientSecret;
    public ?string $paymentUrl;
    public array $gatewayData;
    public string $createdAt;
    public ?string $expiresAt;

    // Payment Intent Statuses
    public const STATUS_CREATED = 'created';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public function __construct(
        string $gatewayId,
        string $gatewayIntentId,
        ?string $clientSecret,
        float $amount,
        string $currency,
        string $status = self::STATUS_CREATED,
        array $metadata = [],
        ?string $createdAt = null
    ) {
        $this->id = uniqid('pi_');
        $this->gatewayId = $gatewayId;
        $this->gatewayIntentId = $gatewayIntentId;
        $this->orderId = 0; // Will be set when linked to order
        $this->customerId = 0; // Will be set when linked to customer
        $this->amount = $amount;
        $this->currency = $currency;
        $this->status = $status;
        $this->metadata = $metadata;
        $this->clientSecret = $clientSecret;
        $this->paymentUrl = null;
        $this->gatewayData = [];
        $this->createdAt = $createdAt ?: date('Y-m-d H:i:s');
        $this->expiresAt = null;
    }

    /**
     * Create from PaymentRequest
     */
    public static function fromRequest(
        PaymentRequest $request,
        string $gatewayId,
        string $gatewayIntentId,
        array $gatewayData = []
    ): self {
        return new self(
            id: self::generateId(),
            gatewayId: $gatewayId,
            gatewayIntentId: $gatewayIntentId,
            orderId: $request->orderId,
            customerId: $request->customerId,
            amount: $request->amount,
            currency: $request->currency,
            metadata: $request->metadata,
            gatewayData: $gatewayData
        );
    }

    /**
     * Check if the payment intent is active (can be processed)
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATED,
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ]);
    }

    /**
     * Check if the payment intent is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /**
     * Check if the payment intent has failed
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ]);
    }

    /**
     * Check if the payment intent is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        return strtotime($this->expiresAt) < time();
    }

    /**
     * Update status
     */
    public function updateStatus(string $status): void
    {
        if (!in_array($status, self::getValidStatuses())) {
            throw new InvalidArgumentException("Invalid payment intent status: {$status}");
        }

        $this->status = $status;
    }

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SUCCEEDED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * Generate unique payment intent ID
     */
    private static function generateId(): string
    {
        return 'pi_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Convert to array for storage/API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gateway_id' => $this->gatewayId,
            'gateway_intent_id' => $this->gatewayIntentId,
            'order_id' => $this->orderId,
            'customer_id' => $this->customerId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'client_secret' => $this->clientSecret,
            'payment_url' => $this->paymentUrl,
            'gateway_data' => $this->gatewayData,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}