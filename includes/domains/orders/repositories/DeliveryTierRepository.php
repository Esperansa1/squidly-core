<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Tier Repository
 * Manages order-value based delivery pricing tiers
 */
class DeliveryTierRepository implements RepositoryInterface
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'squidly_delivery_tiers';
    }

    /**
     * Create a new delivery tier
     *
     * @param array $data [branch_id, min_order_value, delivery_fee]
     * @return int Created tier ID
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        foreach (['branch_id', 'min_order_value', 'delivery_fee'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required key: $key");
            }
        }

        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'branch_id' => (int) $data['branch_id'],
                'min_order_value' => (float) $data['min_order_value'],
                'delivery_fee' => (float) $data['delivery_fee'],
            ],
            ['%d', '%f', '%f']
        );

        if ($inserted === false) {
            throw new RuntimeException('Failed to create delivery tier');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get delivery tier by ID
     *
     * @param int $id
     * @return DeliveryTier|null
     */
    public function get(int $id): ?DeliveryTier
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new DeliveryTier($row) : null;
    }

    /**
     * Get all delivery tiers
     *
     * @return DeliveryTier[]
     */
    public function getAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY branch_id ASC, min_order_value ASC",
            ARRAY_A
        );

        return array_map(fn($row) => new DeliveryTier($row), $rows);
    }

    /**
     * Update delivery tier
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

        if (isset($data['branch_id'])) {
            $updates['branch_id'] = (int) $data['branch_id'];
            $formats[] = '%d';
        }
        if (isset($data['min_order_value'])) {
            $updates['min_order_value'] = (float) $data['min_order_value'];
            $formats[] = '%f';
        }
        if (isset($data['delivery_fee'])) {
            $updates['delivery_fee'] = (float) $data['delivery_fee'];
            $formats[] = '%f';
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
     * Delete delivery tier
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
     * Find delivery tiers by criteria
     *
     * @param array $criteria ['branch_id' => int]
     * @param int|null $limit
     * @param int $offset
     * @return DeliveryTier[]
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        global $wpdb;

        $where = [];
        $values = [];

        if (isset($criteria['branch_id'])) {
            $where[] = 'branch_id = %d';
            $values[] = (int) $criteria['branch_id'];
        }

        $sql = "SELECT * FROM {$this->table_name}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY min_order_value ASC';

        if ($limit !== null) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(fn($row) => new DeliveryTier($row), $rows);
    }

    /**
     * Count delivery tiers matching criteria
     *
     * @param array $criteria
     * @return int
     */
    public function countBy(array $criteria): int
    {
        global $wpdb;

        $where = [];
        $values = [];

        if (isset($criteria['branch_id'])) {
            $where[] = 'branch_id = %d';
            $values[] = (int) $criteria['branch_id'];
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
     * Check if delivery tier exists
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
     * Find applicable tier for branch and order value
     * Returns the tier with the highest min_order_value that the order qualifies for
     *
     * @param int $branch_id
     * @param float $order_value
     * @return DeliveryTier|null
     */
    public function findByBranchAndOrderValue(int $branch_id, float $order_value): ?DeliveryTier
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE branch_id = %d AND min_order_value <= %f
                ORDER BY min_order_value DESC
                LIMIT 1",
                $branch_id,
                $order_value
            ),
            ARRAY_A
        );

        return $row ? new DeliveryTier($row) : null;
    }
}
