<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Time Surcharge Model
 * Represents time-based delivery pricing surcharge
 *
 * Example: +₪10 during Friday evenings (18:00-23:00)
 * Example: +20% during peak hours
 */
class DeliveryTimeSurcharge
{
    public int $id;
    public int $branch_id;
    public int $day_of_week;           // 0=Sunday, 1=Monday, ..., 6=Saturday
    public string $start_time;         // HH:MM:SS format
    public string $end_time;           // HH:MM:SS format
    public float $surcharge_amount;    // Amount to add (fixed or percentage)
    public string $surcharge_type;     // 'fixed' or 'percentage'
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
        $this->branch_id = (int) ($data['branch_id'] ?? 0);
        $this->day_of_week = (int) ($data['day_of_week'] ?? 0);
        $this->start_time = $data['start_time'] ?? '00:00:00';
        $this->end_time = $data['end_time'] ?? '23:59:59';
        $this->surcharge_amount = (float) ($data['surcharge_amount'] ?? 0.0);
        $this->surcharge_type = $data['surcharge_type'] ?? 'fixed';
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
            'branch_id' => $this->branch_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'surcharge_amount' => $this->surcharge_amount,
            'surcharge_type' => $this->surcharge_type,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Check if this surcharge applies to a given datetime
     *
     * @param string $datetime DateTime string (Y-m-d H:i:s)
     * @return bool
     */
    public function appliesTo(string $datetime): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $dt = new DateTime($datetime);
        $day = (int) $dt->format('w'); // 0=Sunday, 6=Saturday
        $time = $dt->format('H:i:s');

        return $day === $this->day_of_week
            && $time >= $this->start_time
            && $time <= $this->end_time;
    }
}
