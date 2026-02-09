<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Loyalty Discount Model
 * Represents loyalty-based delivery fee discount
 *
 * Example: Customers with 100+ points get ₪5 off delivery
 * Example: Customers with 1000+ points get 100% off delivery
 */
class DeliveryLoyaltyDiscount
{
    public int $id;
    public int $min_loyalty_points;    // Minimum points required to qualify
    public float $discount_amount;     // Discount amount (fixed or percentage)
    public string $discount_type;      // 'fixed' or 'percentage'
    public bool $is_active;
    public string $created_at;

    /**
     * Constructor
     *
     * @param array $data Associative array from database
     */
    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->min_loyalty_points = (int) ($data['min_loyalty_points'] ?? 0);
        $this->discount_amount = (float) ($data['discount_amount'] ?? 0.0);
        $this->discount_type = $data['discount_type'] ?? 'fixed';
        $this->is_active = (bool) ($data['is_active'] ?? true);
        $this->created_at = $data['created_at'] ?? '';
    }

    /**
     * Convert model to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'min_loyalty_points' => $this->min_loyalty_points,
            'discount_amount' => $this->discount_amount,
            'discount_type' => $this->discount_type,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Check if customer qualifies for this discount
     *
     * @param int $customer_points Current loyalty points balance
     * @return bool
     */
    public function qualifiesFor(int $customer_points): bool
    {
        return $this->is_active && $customer_points >= $this->min_loyalty_points;
    }
}
