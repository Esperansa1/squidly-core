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
    public ?int $branch_id = null;
    public string $status;
    public string $order_date;
    public float $total_amount;
    public float $subtotal;
    public float $tax_amount;
    public float $delivery_fee;
    public float $delivery_base_fee = 0.0;
    public float $delivery_time_surcharge = 0.0;
    public float $delivery_loyalty_discount = 0.0;
    public string $payment_status;
    public string $payment_method;
    public string $notes;
    public array $order_items; // Array of OrderItem objects
    public ?string $delivery_address;
    public ?string $delivery_type; // 'pickup' or 'delivery'
    public ?string $tracking_token; // Guest order tracking token
    public ?string $gateway_transaction_id;
    public ?string $pickup_time;
    public ?string $special_instructions;

    // Loyalty
    public float $loyalty_points_earned = 0.0;
    public float $loyalty_points_used = 0.0;
    public float $loyalty_discount = 0.0;

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
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    // Payment methods
    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_ONLINE = 'online';
    public const PAYMENT_WOOCOMMERCE = 'woocommerce';

    /**
     * Create Order from WordPress post data
     */
    public static function fromWordPress(\WP_Post $post): self
    {
        // Batch load all meta fields in a single query
        $meta = get_post_meta($post->ID);

        $order = new self();
        $order->id = $post->ID;
        $order->customer_id = isset($meta['_customer_id'][0]) ? (int) $meta['_customer_id'][0] : 0;
        $order->branch_id = isset($meta['_branch_id'][0]) && $meta['_branch_id'][0] ? (int) $meta['_branch_id'][0] : null;
        $order->status = isset($meta['_status'][0]) ? $meta['_status'][0] : self::STATUS_PENDING;
        $order->order_date = $post->post_date;
        $order->total_amount = isset($meta['_total_amount'][0]) ? (float) $meta['_total_amount'][0] : 0.0;
        $order->subtotal = isset($meta['_subtotal'][0]) ? (float) $meta['_subtotal'][0] : 0.0;
        $order->tax_amount = isset($meta['_tax_amount'][0]) ? (float) $meta['_tax_amount'][0] : 0.0;
        $order->delivery_fee = isset($meta['_delivery_fee'][0]) ? (float) $meta['_delivery_fee'][0] : 0.0;
        $order->delivery_base_fee = isset($meta['_delivery_base_fee'][0]) ? (float) $meta['_delivery_base_fee'][0] : 0.0;
        $order->delivery_time_surcharge = isset($meta['_delivery_time_surcharge'][0]) ? (float) $meta['_delivery_time_surcharge'][0] : 0.0;
        $order->delivery_loyalty_discount = isset($meta['_delivery_loyalty_discount'][0]) ? (float) $meta['_delivery_loyalty_discount'][0] : 0.0;
        $order->payment_status = isset($meta['_payment_status'][0]) ? $meta['_payment_status'][0] : self::PAYMENT_PENDING;
        $order->payment_method = isset($meta['_payment_method'][0]) ? $meta['_payment_method'][0] : self::PAYMENT_CASH;
        $order->notes = isset($meta['_notes'][0]) ? $meta['_notes'][0] : '';
        $order->delivery_address = isset($meta['_delivery_address'][0]) ? $meta['_delivery_address'][0] : null;
        $order->delivery_type = isset($meta['_delivery_type'][0]) ? $meta['_delivery_type'][0] : null;
        $order->tracking_token = isset($meta['_tracking_token'][0]) ? $meta['_tracking_token'][0] : null;
        $order->gateway_transaction_id = isset($meta['_gateway_transaction_id'][0]) ? $meta['_gateway_transaction_id'][0] : null;
        $order->pickup_time = isset($meta['_pickup_time'][0]) ? $meta['_pickup_time'][0] : null;
        $order->special_instructions = isset($meta['_special_instructions'][0]) ? $meta['_special_instructions'][0] : null;

        // Loyalty
        $order->loyalty_points_earned = isset($meta['_loyalty_points_earned'][0]) ? (float) $meta['_loyalty_points_earned'][0] : 0.0;
        $order->loyalty_points_used = isset($meta['_loyalty_points_used'][0]) ? (float) $meta['_loyalty_points_used'][0] : 0.0;
        $order->loyalty_discount = isset($meta['_loyalty_discount'][0]) ? (float) $meta['_loyalty_discount'][0] : 0.0;

        // Load order items
        $items_data = isset($meta['_order_items'][0]) ? maybe_unserialize($meta['_order_items'][0]) : [];
        $items_data = is_array($items_data) ? $items_data : [];
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
            self::PAYMENT_PARTIALLY_REFUNDED,
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
            self::PAYMENT_WOOCOMMERCE,
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
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->delivery_fee - $this->loyalty_discount;
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

    /**
     * Convert order to array for updates
     */
    public function toArray(): array
    {
        return [
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
            'order_date' => $this->order_date,
            'total_amount' => $this->total_amount,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'delivery_fee' => $this->delivery_fee,
            'delivery_base_fee' => $this->delivery_base_fee,
            'delivery_time_surcharge' => $this->delivery_time_surcharge,
            'delivery_loyalty_discount' => $this->delivery_loyalty_discount,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'notes' => $this->notes,
            'order_items' => $this->order_items,
            'delivery_address' => $this->delivery_address,
            'delivery_type' => $this->delivery_type,
            'tracking_token' => $this->tracking_token,
            'pickup_time' => $this->pickup_time,
            'special_instructions' => $this->special_instructions,
            'loyalty_points_earned' => $this->loyalty_points_earned,
            'loyalty_points_used' => $this->loyalty_points_used,
            'loyalty_discount' => $this->loyalty_discount,
        ];
    }
}