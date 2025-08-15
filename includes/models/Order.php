<?php
declare(strict_types=1);

/**
 * Order Model
 * 
 * Represents a customer order in the restaurant management system.
 * Contains order details, items, payment information, and status tracking.
 */
class Order
{
    public int $id;
    public int $customer_id;
    public string $status;
    public string $order_date;
    public float $total_amount;
    public float $subtotal;
    public float $tax_amount;
    public float $delivery_fee;
    public string $payment_status;
    public string $payment_method;
    public string $notes;
    public array $order_items; // Array of OrderItem objects
    public ?string $delivery_address;
    public ?string $pickup_time;
    public ?string $special_instructions;

    // Order statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // Payment statuses
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';

    // Payment methods
    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_ONLINE = 'online';

    /**
     * Create Order from WordPress post data
     */
    public static function fromWordPress(\WP_Post $post): self
    {
        $order = new self();
        $order->id = $post->ID;
        $order->customer_id = (int) get_post_meta($post->ID, '_customer_id', true);
        $order->status = get_post_meta($post->ID, '_status', true) ?: self::STATUS_PENDING;
        $order->order_date = $post->post_date;
        $order->total_amount = (float) get_post_meta($post->ID, '_total_amount', true);
        $order->subtotal = (float) get_post_meta($post->ID, '_subtotal', true);
        $order->tax_amount = (float) get_post_meta($post->ID, '_tax_amount', true);
        $order->delivery_fee = (float) get_post_meta($post->ID, '_delivery_fee', true);
        $order->payment_status = get_post_meta($post->ID, '_payment_status', true) ?: self::PAYMENT_PENDING;
        $order->payment_method = get_post_meta($post->ID, '_payment_method', true) ?: self::PAYMENT_CASH;
        $order->notes = get_post_meta($post->ID, '_notes', true) ?: '';
        $order->delivery_address = get_post_meta($post->ID, '_delivery_address', true) ?: null;
        $order->pickup_time = get_post_meta($post->ID, '_pickup_time', true) ?: null;
        $order->special_instructions = get_post_meta($post->ID, '_special_instructions', true) ?: null;

        // Load order items
        $items_data = get_post_meta($post->ID, '_order_items', true) ?: [];
        $order->order_items = array_map([OrderItem::class, 'fromArray'], $items_data);

        return $order;
    }

    /**
     * Get all valid order statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_READY,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get all valid payment statuses
     */
    public static function getValidPaymentStatuses(): array
    {
        return [
            self::PAYMENT_PENDING,
            self::PAYMENT_PAID,
            self::PAYMENT_FAILED,
            self::PAYMENT_REFUNDED,
        ];
    }

    /**
     * Get all valid payment methods
     */
    public static function getValidPaymentMethods(): array
    {
        return [
            self::PAYMENT_CASH,
            self::PAYMENT_CARD,
            self::PAYMENT_ONLINE,
        ];
    }

    /**
     * Calculate totals from order items
     */
    public function calculateTotals(float $tax_rate = 0.17): void
    {
        $this->subtotal = array_reduce(
            $this->order_items,
            fn($sum, $item) => $sum + $item->total_price,
            0.0
        );

        $this->tax_amount = $this->subtotal * $tax_rate;
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->delivery_fee;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get order display name for admin
     */
    public function getDisplayName(): string
    {
        return "Order #{$this->id} - " . date('M j, Y', strtotime($this->order_date));
    }
}