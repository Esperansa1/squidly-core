<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Time Surcharge Repository
 * Manages time-based delivery pricing surcharges
 */
class DeliveryTimeSurchargeRepository implements RepositoryInterface
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'squidly_delivery_time_surcharges';
    }

    /**
     * Create a new time surcharge
     *
     * @param array $data [branch_id, day_of_week, start_time, end_time, surcharge_amount, surcharge_type, is_active]
     * @return int Created surcharge ID
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        foreach (['branch_id', 'day_of_week', 'start_time', 'end_time', 'surcharge_amount', 'surcharge_type'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required key: $key");
            }
        }

        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'branch_id' => (int) $data['branch_id'],
                'day_of_week' => (int) $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'surcharge_amount' => (float) $data['surcharge_amount'],
                'surcharge_type' => sanitize_text_field($data['surcharge_type']),
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ],
            ['%d', '%d', '%s', '%s', '%f', '%s', '%d']
        );

        if ($inserted === false) {
            throw new RuntimeException('Failed to create delivery time surcharge');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get time surcharge by ID
     *
     * @param int $id
     * @return DeliveryTimeSurcharge|null
     */
    public function get(int $id): ?DeliveryTimeSurcharge
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new DeliveryTimeSurcharge($row) : null;
    }

    /**
     * Get all time surcharges
     *
     * @return DeliveryTimeSurcharge[]
     */
    public function getAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY branch_id ASC, day_of_week ASC, start_time ASC",
            ARRAY_A
        );

        return array_map(fn($row) => new DeliveryTimeSurcharge($row), $rows);
    }

    /**
     * Update time surcharge
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
        if (isset($data['day_of_week'])) {
            $updates['day_of_week'] = (int) $data['day_of_week'];
            $formats[] = '%d';
        }
        if (isset($data['start_time'])) {
            $updates['start_time'] = $data['start_time'];
            $formats[] = '%s';
        }
        if (isset($data['end_time'])) {
            $updates['end_time'] = $data['end_time'];
            $formats[] = '%s';
        }
        if (isset($data['surcharge_amount'])) {
            $updates['surcharge_amount'] = (float) $data['surcharge_amount'];
            $formats[] = '%f';
        }
        if (isset($data['surcharge_type'])) {
            $updates['surcharge_type'] = sanitize_text_field($data['surcharge_type']);
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
     * Delete time surcharge
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
     * Find time surcharges by criteria
     *
     * @param array $criteria ['branch_id' => int, 'is_active' => bool]
     * @param int|null $limit
     * @param int $offset
     * @return DeliveryTimeSurcharge[]
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

        if (isset($criteria['is_active'])) {
            $where[] = 'is_active = %d';
            $values[] = (bool) $criteria['is_active'] ? 1 : 0;
        }

        $sql = "SELECT * FROM {$this->table_name}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY day_of_week ASC, start_time ASC';

        if ($limit !== null) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(fn($row) => new DeliveryTimeSurcharge($row), $rows);
    }

    /**
     * Count time surcharges matching criteria
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
     * Check if time surcharge exists
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
     * Find active surcharges applicable to datetime
     *
     * @param int $branch_id
     * @param string $datetime DateTime string (Y-m-d H:i:s)
     * @return DeliveryTimeSurcharge[]
     */
    public function findActiveForDateTime(int $branch_id, string $datetime): array
    {
        $dt = new DateTime($datetime);
        $day = (int) $dt->format('w'); // 0=Sunday, 6=Saturday
        $time = $dt->format('H:i:s');

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE branch_id = %d
                AND day_of_week = %d
                AND is_active = 1
                AND %s BETWEEN start_time AND end_time",
                $branch_id,
                $day,
                $time
            ),
            ARRAY_A
        );

        return array_map(fn($row) => new DeliveryTimeSurcharge($row), $rows);
    }
}
