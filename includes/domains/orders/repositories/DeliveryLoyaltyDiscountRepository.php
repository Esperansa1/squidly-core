<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Loyalty Discount Repository
 * Manages loyalty-based delivery fee discounts
 */
class DeliveryLoyaltyDiscountRepository implements RepositoryInterface
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'squidly_delivery_loyalty_discounts';
    }

    /**
     * Create a new loyalty discount
     *
     * @param array $data [min_loyalty_points, discount_amount, discount_type, is_active]
     * @return int Created discount ID
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        foreach (['min_loyalty_points', 'discount_amount', 'discount_type'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required key: $key");
            }
        }

        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'min_loyalty_points' => (int) $data['min_loyalty_points'],
                'discount_amount' => (float) $data['discount_amount'],
                'discount_type' => sanitize_text_field($data['discount_type']),
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ],
            ['%d', '%f', '%s', '%d']
        );

        if ($inserted === false) {
            throw new RuntimeException('Failed to create delivery loyalty discount');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get loyalty discount by ID
     *
     * @param int $id
     * @return DeliveryLoyaltyDiscount|null
     */
    public function get(int $id): ?DeliveryLoyaltyDiscount
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new DeliveryLoyaltyDiscount($row) : null;
    }

    /**
     * Get all loyalty discounts
     *
     * @return DeliveryLoyaltyDiscount[]
     */
    public function getAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY min_loyalty_points ASC",
            ARRAY_A
        );

        return array_map(fn($row) => new DeliveryLoyaltyDiscount($row), $rows);
    }

    /**
     * Update loyalty discount
     *
     * @param int $id
     * @param array $data Partial data to update
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updates = [];
        $formats = [];

        if (isset($data['min_loyalty_points'])) {
            $updates['min_loyalty_points'] = (int) $data['min_loyalty_points'];
            $formats[] = '%d';
        }
        if (isset($data['discount_amount'])) {
            $updates['discount_amount'] = (float) $data['discount_amount'];
            $formats[] = '%f';
        }
        if (isset($data['discount_type'])) {
            $updates['discount_type'] = sanitize_text_field($data['discount_type']);
            $formats[] = '%s';
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (bool) $data['is_active'];
            $formats[] = '%d';
        }

        if (empty($updates)) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            $updates,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete loyalty discount
     *
     * @param int $id
     * @param bool $force (ignored for custom tables)
     * @return bool
     */
    public function delete(int $id, bool $force = false): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );

        return $deleted !== false;
    }

    /**
     * Find loyalty discounts by criteria
     *
     * @param array $criteria ['is_active' => bool]
     * @param int|null $limit
     * @param int $offset
     * @return DeliveryLoyaltyDiscount[]
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        global $wpdb;

        $where = [];
        $values = [];

        if (isset($criteria['is_active'])) {
            $where[] = 'is_active = %d';
            $values[] = (bool) $criteria['is_active'] ? 1 : 0;
        }

        $sql = "SELECT * FROM {$this->table_name}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY min_loyalty_points ASC';

        if ($limit !== null) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(fn($row) => new DeliveryLoyaltyDiscount($row), $rows);
    }

    /**
     * Count loyalty discounts matching criteria
     *
     * @param array $criteria
     * @return int
     */
    public function countBy(array $criteria): int
    {
        global $wpdb;

        $where = [];
        $values = [];

        if (isset($criteria['is_active'])) {
            $where[] = 'is_active = %d';
            $values[] = (bool) $criteria['is_active'] ? 1 : 0;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Check if loyalty discount exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d", $id)
        );

        return (int) $exists > 0;
    }

    /**
     * Find applicable discount for customer's loyalty points
     * Returns the highest discount the customer qualifies for
     *
     * @param int $customer_points Current loyalty points balance
     * @return DeliveryLoyaltyDiscount|null
     */
    public function findByLoyaltyPoints(int $customer_points): ?DeliveryLoyaltyDiscount
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE is_active = 1 AND min_loyalty_points <= %d
                ORDER BY min_loyalty_points DESC
                LIMIT 1",
                $customer_points
            ),
            ARRAY_A
        );

        return $row ? new DeliveryLoyaltyDiscount($row) : null;
    }
}
