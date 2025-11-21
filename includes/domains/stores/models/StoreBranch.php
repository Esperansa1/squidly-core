<?php
declare(strict_types=1);

/**
 * Represents a physical branch (store / restaurant).
 *
 * – All DB I/O stays in StoreBranchRepository; this DTO is pure PHP.
 * – Activity times are normalised to 24-hour “HH:MM” strings.
 */
class StoreBranch
{
    public int    $id;
    public string $name;
    public string $phone;
    public string $city;
    public string $address;
    public bool   $is_open;                 // true = open, false = closed

    /** @var array<string, string[]>  e.g. 'Sunday' => ['08:00-13:00','16:00-21:00'] */
    public array  $activity_times = [];

    public string $kosher_type;             // single choice
    /** @var string[] */
    public array  $accessibility_list = [];

    /** @var Product[] */
    public array  $products = [];
    /** @var Ingredient[] */
    public array  $ingredients = [];

    /** @var array<int, bool> [product_id => available?] */
    public array  $product_availability = [];
    /** @var array<int, bool> [ingredient_id => available?] */
    public array  $ingredient_availability = [];

    public function __construct(array $data)
    {
        $this->id        = (int)    $data['id'];
        $this->name      = (string) $data['name'];
        $this->phone     = (string) $data['phone'];
        $this->city      = (string) $data['city'];
        $this->address   = (string) $data['address'];
        $this->is_open   = (bool)   $data['is_open'];

        $this->activity_times      = $data['activity_times']      ?? [];
        $this->kosher_type         = $data['kosher_type']         ?? '';
        $this->accessibility_list  = $data['accessibility_list']  ?? [];

        $this->products            = array_map(
            fn($p) => $p instanceof Product ? $p : new Product($p),
            $data['products'] ?? []
        );
        $this->ingredients         = array_map(
            fn($i) => $i instanceof Ingredient ? $i : new Ingredient($i),
            $data['ingredients'] ?? []
        );

        $this->product_availability    = $data['product_availability']    ?? [];
        $this->ingredient_availability = $data['ingredient_availability'] ?? [];
    }

    /* ---------- Helper look-ups ---------- */

    public function isProductAvailable(int $product_id): bool
    {
        return $this->product_availability[$product_id] ?? false;
    }

    public function isIngredientAvailable(int $ingredient_id): bool
    {
        return $this->ingredient_availability[$ingredient_id] ?? false;
    }

    /**
     * Check if the branch is currently open based on activity_times.
     *
     * @return bool True if currently open, false otherwise
     */
    public function isCurrentlyOpen(): bool
    {
        if (!$this->is_open) {
            return false;
        }

        $current_time = current_time('H:i');
        $current_day = current_time('l'); // e.g., 'Monday', 'Tuesday'

        if (!isset($this->activity_times[$current_day]) || empty($this->activity_times[$current_day])) {
            return false;
        }

        foreach ($this->activity_times[$current_day] as $time_range) {
            if ($this->isTimeInRange($current_time, $time_range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the next opening time for this branch.
     *
     * @return string|null Next opening time in format 'Day HH:MM' or null if no opening times
     */
    public function getNextOpeningTime(): ?string
    {
        if (!$this->is_open || empty($this->activity_times)) {
            return null;
        }

        $current_time = current_time('H:i');
        $current_day = current_time('l');

        // Check remaining times today
        if (isset($this->activity_times[$current_day])) {
            foreach ($this->activity_times[$current_day] as $time_range) {
                $start_time = $this->extractStartTime($time_range);
                if ($start_time > $current_time) {
                    return "$current_day $start_time";
                }
            }
        }

        // Check next 7 days
        $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $current_day_index = array_search($current_day, $days_of_week);

        for ($i = 1; $i <= 7; $i++) {
            $next_day_index = ($current_day_index + $i) % 7;
            $next_day = $days_of_week[$next_day_index];

            if (isset($this->activity_times[$next_day]) && !empty($this->activity_times[$next_day])) {
                $first_range = $this->activity_times[$next_day][0];
                $start_time = $this->extractStartTime($first_range);
                return "$next_day $start_time";
            }
        }

        return null;
    }

    /**
     * Check if a time falls within a time range.
     *
     * @param string $time Time in HH:MM format
     * @param string $range Time range in 'HH:MM-HH:MM' format
     * @return bool True if time is within range
     */
    private function isTimeInRange(string $time, string $range): bool
    {
        $parts = explode('-', $range);
        if (count($parts) !== 2) {
            return false;
        }

        [$start, $end] = $parts;
        return $time >= $start && $time <= $end;
    }

    /**
     * Extract start time from a time range.
     *
     * @param string $range Time range in 'HH:MM-HH:MM' format
     * @return string Start time in HH:MM format
     */
    private function extractStartTime(string $range): string
    {
        $parts = explode('-', $range);
        return $parts[0] ?? '00:00';
    }

    /** Flatten everything to an array for JSON / API use. */
    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'phone'                  => $this->phone,
            'city'                   => $this->city,
            'address'                => $this->address,
            'is_open'                => $this->is_open,
            'activity_times'         => $this->activity_times,
            'kosher_type'            => $this->kosher_type,
            'accessibility_list'     => $this->accessibility_list,
            'products'               => array_map(fn(Product $p)    => $p->toArray(), $this->products),
            'ingredients'            => array_map(fn(Ingredient $i) => $i->toArray(), $this->ingredients),
            'product_availability'   => $this->product_availability,
            'ingredient_availability'=> $this->ingredient_availability,
            'is_currently_open'      => $this->isCurrentlyOpen(),
            'next_opening_time'      => $this->getNextOpeningTime(),
        ];
    }
}
