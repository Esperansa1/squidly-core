<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Tier Model
 * Represents order-value based delivery pricing tier
 *
 * Example: Orders over ₪100 get free delivery, orders over ₪50 get ₪15 fee
 */
class DeliveryTier
{
    public int $id;
    public int $branch_id;
    public float $min_order_value;
    public float $delivery_fee;
    public string $created_at;

    /**
     * Constructor
     *
     * @param array $data Associative array from database
     */
    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->branch_id = (int) ($data['branch_id'] ?? 0);
        $this->min_order_value = (float) ($data['min_order_value'] ?? 0.0);
        $this->delivery_fee = (float) ($data['delivery_fee'] ?? 0.0);
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
            'branch_id' => $this->branch_id,
            'min_order_value' => $this->min_order_value,
            'delivery_fee' => $this->delivery_fee,
            'created_at' => $this->created_at,
        ];
    }
}
