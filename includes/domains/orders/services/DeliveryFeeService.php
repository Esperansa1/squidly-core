<?php
declare(strict_types=1);

/**
 * Delivery Fee Calculation Service
 *
 * Handles multi-factor delivery fee calculation with:
 * - Order-value based pricing tiers
 * - Time-based surcharges (peak hours)
 * - Loyalty-based discounts
 * - Distance-based zone pricing (future)
 */
class DeliveryFeeService
{
    private StoreBranchRepository $branchRepository;
    private DeliveryTierRepository $tierRepository;
    private DeliveryTimeSurchargeRepository $surchargeRepository;
    private DeliveryLoyaltyDiscountRepository $discountRepository;

    public function __construct(
        ?StoreBranchRepository $branchRepository = null,
        ?DeliveryTierRepository $tierRepository = null,
        ?DeliveryTimeSurchargeRepository $surchargeRepository = null,
        ?DeliveryLoyaltyDiscountRepository $discountRepository = null
    ) {
        $this->branchRepository = $branchRepository ?? new StoreBranchRepository();
        $this->tierRepository = $tierRepository ?? new DeliveryTierRepository();
        $this->surchargeRepository = $surchargeRepository ?? new DeliveryTimeSurchargeRepository();
        $this->discountRepository = $discountRepository ?? new DeliveryLoyaltyDiscountRepository();
    }

    /**
     * Calculate delivery fee with detailed breakdown
     *
     * @param int $branch_id Branch ID
     * @param float $order_value Order subtotal
     * @param string|null $delivery_time Delivery datetime (Y-m-d H:i:s) or null for ASAP
     * @param int|null $customer_id Customer ID for loyalty discount, null for guests
     * @param string|null $delivery_address Delivery address (null for pickup)
     * @return array ['base_fee' => float, 'time_surcharge' => float, 'loyalty_discount' => float, 'final_fee' => float]
     * @throws InvalidArgumentException
     */
    public function calculateDetailedFee(
        int $branch_id,
        float $order_value,
        ?string $delivery_time = null,
        ?int $customer_id = null,
        ?string $delivery_address = null
    ): array {
        // Not a delivery order (pickup)
        if (empty($delivery_address)) {
            return [
                'base_fee' => 0.0,
                'time_surcharge' => 0.0,
                'loyalty_discount' => 0.0,
                'final_fee' => 0.0,
            ];
        }

        $branch = $this->branchRepository->get($branch_id);
        if (!$branch) {
            throw new InvalidArgumentException("Branch not found: {$branch_id}");
        }

        if (!$branch->delivery_enabled) {
            throw new InvalidArgumentException("Delivery not available at this branch");
        }

        // Step 1: Calculate base fee using order-value tiers
        $base_fee = $this->calculateBaseFee($branch_id, $order_value, $branch);

        // Step 2: Apply time-based surcharges
        $time_surcharge = $this->calculateTimeSurcharge($branch_id, $delivery_time, $base_fee);

        // Step 3: Apply loyalty discounts
        $loyalty_discount = $this->calculateLoyaltyDiscount($customer_id, $base_fee + $time_surcharge);

        // Step 4: Calculate final fee
        $final_fee = max(0.0, $base_fee + $time_surcharge - $loyalty_discount);

        return [
            'base_fee' => round($base_fee, 2),
            'time_surcharge' => round($time_surcharge, 2),
            'loyalty_discount' => round($loyalty_discount, 2),
            'final_fee' => round($final_fee, 2),
        ];
    }

    /**
     * Calculate delivery fee for an order (backward compatible)
     *
     * @param int $branch_id Branch ID
     * @param string|null $delivery_address Delivery address (null for pickup)
     * @param float $subtotal Order subtotal before delivery fee
     * @return float Delivery fee amount (0.0 for pickup or free delivery)
     * @throws InvalidArgumentException If delivery not available or address out of range
     */
    public function calculateFee(int $branch_id, ?string $delivery_address, float $subtotal): float
    {
        $breakdown = $this->calculateDetailedFee($branch_id, $subtotal, null, null, $delivery_address);
        return $breakdown['final_fee'];
    }

    /**
     * Calculate base delivery fee using order-value tiers
     *
     * @param int $branch_id
     * @param float $order_value
     * @param StoreBranch $branch
     * @return float
     */
    private function calculateBaseFee(int $branch_id, float $order_value, StoreBranch $branch): float
    {
        // Check if branch-specific tiers exist
        $tier = $this->tierRepository->findByBranchAndOrderValue($branch_id, $order_value);

        if ($tier) {
            return $tier->delivery_fee;
        }

        // Fallback to old branch configuration (backward compatibility)
        if ($branch->delivery_free_threshold > 0 && $order_value >= $branch->delivery_free_threshold) {
            return 0.0;
        }

        return $branch->delivery_base_fee;
    }

    /**
     * Calculate time-based surcharge
     *
     * @param int $branch_id
     * @param string|null $delivery_time
     * @param float $base_fee
     * @return float
     */
    private function calculateTimeSurcharge(int $branch_id, ?string $delivery_time, float $base_fee): float
    {
        if (!$delivery_time) {
            $delivery_time = current_time('mysql'); // Use current time for ASAP orders
        }

        $surcharges = $this->surchargeRepository->findActiveForDateTime($branch_id, $delivery_time);

        $total_surcharge = 0.0;

        foreach ($surcharges as $surcharge) {
            if ($surcharge->surcharge_type === 'fixed') {
                $total_surcharge += $surcharge->surcharge_amount;
            } elseif ($surcharge->surcharge_type === 'percentage') {
                $total_surcharge += ($base_fee * $surcharge->surcharge_amount / 100);
            }
        }

        return $total_surcharge;
    }

    /**
     * Calculate loyalty-based discount
     *
     * @param int|null $customer_id
     * @param float $delivery_fee (base + surcharges)
     * @return float
     */
    private function calculateLoyaltyDiscount(?int $customer_id, float $delivery_fee): float
    {
        if (!$customer_id) {
            return 0.0; // Guest customers don't get loyalty discounts
        }

        $customerRepo = new CustomerRepository();
        $customer = $customerRepo->get($customer_id);

        if (!$customer) {
            return 0.0;
        }

        $discount = $this->discountRepository->findByLoyaltyPoints((int) $customer->loyalty_points_balance);

        if (!$discount) {
            return 0.0;
        }

        if ($discount->discount_type === 'fixed') {
            return min($discount->discount_amount, $delivery_fee); // Can't discount more than fee
        } elseif ($discount->discount_type === 'percentage') {
            return ($delivery_fee * $discount->discount_amount / 100);
        }

        return 0.0;
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
