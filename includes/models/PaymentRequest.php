<?php
declare(strict_types=1);

/**
 * Payment Request Model
 * 
 * Contains all information needed to initiate a payment
 */
class PaymentRequest
{
    public int $orderId;
    public int $customerId;
    public float $amount;
    public string $currency;
    public string $description;
    public array $metadata;
    public ?string $customerEmail;
    public ?string $customerPhone;
    public ?Address $billingAddress;
    public bool $captureImmediately;
    public ?string $returnUrl;
    public ?string $cancelUrl;

    public function __construct(
        int $orderId,
        int $customerId,
        float $amount,
        string $currency = 'ILS',
        string $description = '',
        array $metadata = [],
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?Address $billingAddress = null,
        bool $captureImmediately = true,
        ?string $returnUrl = null,
        ?string $cancelUrl = null
    ) {
        $this->orderId = $orderId;
        $this->customerId = $customerId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->description = $description ?: "Order #{$orderId}";
        $this->metadata = $metadata;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        $this->billingAddress = $billingAddress;
        $this->captureImmediately = $captureImmediately;
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
    }

    /**
     * Create from Order object
     */
    public static function fromOrder(Order $order, Customer $customer): self
    {
        return new self(
            orderId: $order->id,
            customerId: $customer->id,
            amount: $order->total_amount,
            currency: 'ILS',
            description: $order->getDisplayName(),
            metadata: [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'order_date' => $order->order_date,
                'items_count' => count($order->order_items)
            ],
            customerEmail: $customer->email,
            customerPhone: $customer->phone
        );
    }

    /**
     * Validate the payment request
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->amount <= 0) {
            $errors[] = 'Payment amount must be greater than 0';
        }

        if (empty($this->currency)) {
            $errors[] = 'Currency is required';
        }

        if ($this->orderId <= 0) {
            $errors[] = 'Valid order ID is required';
        }

        if ($this->customerId <= 0) {
            $errors[] = 'Valid customer ID is required';
        }

        return $errors;
    }

    /**
     * Get amount in smallest currency unit (agorot for ILS)
     */
    public function getAmountInCents(): int
    {
        return (int) round($this->amount * 100);
    }

    /**
     * Convert to array for API calls
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'customer_id' => $this->customerId,
            'amount' => $this->amount,
            'amount_cents' => $this->getAmountInCents(),
            'currency' => $this->currency,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'billing_address' => $this->billingAddress?->toArray(),
            'capture_immediately' => $this->captureImmediately,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
        ];
    }
}