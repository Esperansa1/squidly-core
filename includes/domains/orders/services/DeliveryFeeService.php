<?php
declare(strict_types=1);

/**
 * Delivery Fee Calculation Service
 *
 * Handles delivery fee calculation, address validation, and delivery time estimation.
 * Supports both flat fee and distance-based zone pricing.
 */
class DeliveryFeeService
{
    private StoreBranchRepository $branchRepository;

    public function __construct(?StoreBranchRepository $branchRepository = null)
    {
        $this->branchRepository = $branchRepository ?? new StoreBranchRepository();
    }

    /**
     * Calculate delivery fee for an order
     *
     * @param int $branch_id Branch ID
     * @param string|null $delivery_address Delivery address (null for pickup)
     * @param float $subtotal Order subtotal before delivery fee
     * @return float Delivery fee amount (0.0 for pickup or free delivery)
     * @throws InvalidArgumentException If delivery not available or address out of range
     */
    public function calculateFee(int $branch_id, ?string $delivery_address, float $subtotal): float
    {
        // Not a delivery order
        if (empty($delivery_address)) {
            return 0.0;
        }

        $branch = $this->branchRepository->get($branch_id);
        if (!$branch) {
            throw new InvalidArgumentException("Branch not found: {$branch_id}");
        }

        // Delivery disabled for this branch
        if (!$branch->delivery_enabled) {
            throw new InvalidArgumentException("Delivery not available at this branch");
        }

        // Free delivery above threshold
        if ($branch->delivery_free_threshold > 0 && $subtotal >= $branch->delivery_free_threshold) {
            return 0.0;
        }

        // TODO: Distance-based calculation (future enhancement)
        // For now, return flat fee
        return $branch->delivery_base_fee;
    }

    /**
     * Check if delivery address is within branch service area
     *
     * @param int $branch_id Branch ID
     * @param string $delivery_address Delivery address
     * @return bool True if address is within range
     */
    public function isAddressInRange(int $branch_id, string $delivery_address): bool
    {
        $branch = $this->branchRepository->get($branch_id);
        if (!$branch || !$branch->delivery_enabled) {
            return false;
        }

        // If no max distance configured, accept all addresses (unlimited range)
        if ($branch->delivery_max_distance <= 0) {
            return true;
        }

        // TODO: Implement distance calculation with geocoding API
        // For now, accept all addresses if delivery is enabled
        return true;
    }

    /**
     * Estimate delivery time in minutes
     *
     * @param int $branch_id Branch ID
     * @param string $delivery_address Delivery address
     * @return int Estimated delivery time in minutes
     */
    public function estimateDeliveryTime(int $branch_id, string $delivery_address): int
    {
        $branch = $this->branchRepository->get($branch_id);
        if (!$branch) {
            return 0;
        }

        // Base preparation time (default: 30 minutes)
        $base_prep_time = 30;

        // TODO: Add distance-based calculation
        // Simple formula: base_prep_time + (distance_km × 3 minutes)
        // For now, return base time
        return $base_prep_time;
    }

    /**
     * Calculate distance between two addresses using geocoding
     *
     * @param string $from_address Starting address
     * @param string $to_address Destination address
     * @return float Distance in kilometers
     */
    private function getDistance(string $from_address, string $to_address): float
    {
        // Check cache first
        $cache_key = 'squidly_distance_' . md5($from_address . $to_address);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return (float) $cached;
        }

        // TODO: Implement geocoding API call (Google Maps, etc.)
        // For now, return 0 (will be implemented in Phase 2)
        $distance = 0.0;

        // Cache result for 24 hours
        set_transient($cache_key, $distance, DAY_IN_SECONDS);

        return $distance;
    }

    /**
     * Calculate delivery fee using zone-based pricing
     *
     * @param StoreBranch $branch Branch object
     * @param float $distance Distance in kilometers
     * @param float $subtotal Order subtotal
     * @return float Delivery fee
     */
    private function calculateZoneFee(StoreBranch $branch, float $distance, float $subtotal): float
    {
        // Check free delivery threshold
        if ($branch->delivery_free_threshold > 0 && $subtotal >= $branch->delivery_free_threshold) {
            return 0.0;
        }

        // Check if distance exceeds max range
        if ($branch->delivery_max_distance > 0 && $distance > $branch->delivery_max_distance) {
            throw new InvalidArgumentException("Address outside delivery range ({$branch->delivery_max_distance} km)");
        }

        // Zone-based pricing
        if (!empty($branch->delivery_zones)) {
            foreach ($branch->delivery_zones as $zone) {
                if ($distance <= $zone['max_distance']) {
                    return $zone['fee'];
                }
            }
        }

        // Fallback to base fee
        return $branch->delivery_base_fee;
    }
}
