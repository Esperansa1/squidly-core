<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Services;

use DeliveryFeeService;
use StoreBranch;
use StoreBranchRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Unit tests for DeliveryFeeService
 * @covers \DeliveryFeeService
 */
class DeliveryFeeServiceTest extends TestCase
{
    private $branchRepoMock;
    private DeliveryFeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchRepoMock = Mockery::mock(StoreBranchRepository::class);
        $this->service = new DeliveryFeeService($this->branchRepoMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to create a mock branch with delivery configuration
     */
    private function createMockBranch(array $overrides = []): StoreBranch
    {
        $defaults = [
            'id' => 1,
            'name' => 'Test Branch',
            'phone' => '0501234567',
            'city' => 'Tel Aviv',
            'address' => '123 Test St',
            'is_open' => true,
            'delivery_enabled' => true,
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
            'delivery_max_distance' => 5.0,
            'min_order_amount' => 50.0,
            'delivery_zones' => [],
        ];

        return new StoreBranch(array_merge($defaults, $overrides));
    }

    // ========================================================================
    // PICKUP ORDERS (No Delivery Address)
    // ========================================================================

    public function test_calculate_fee_returns_zero_for_pickup_order(): void
    {
        $result = $this->service->calculateFee(1, null, 75.0);

        $this->assertEquals(0.0, $result);
    }

    public function test_calculate_fee_returns_zero_for_empty_delivery_address(): void
    {
        $result = $this->service->calculateFee(1, '', 75.0);

        $this->assertEquals(0.0, $result);
    }

    // ========================================================================
    // BRANCH VALIDATION
    // ========================================================================

    public function test_calculate_fee_throws_exception_for_invalid_branch(): void
    {
        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(999)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Branch not found: 999');

        $this->service->calculateFee(999, '123 Main St', 75.0);
    }

    public function test_calculate_fee_throws_exception_when_delivery_disabled(): void
    {
        $branch = $this->createMockBranch(['delivery_enabled' => false]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery not available at this branch');

        $this->service->calculateFee(1, '123 Main St', 75.0);
    }

    // ========================================================================
    // FLAT FEE CALCULATION
    // ========================================================================

    public function test_calculate_fee_returns_base_fee_for_delivery_order(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 20.0,
            'delivery_free_threshold' => 0.0, // No free delivery
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', 75.0);

        $this->assertEquals(20.0, $result);
    }

    public function test_calculate_fee_applies_different_base_fees_per_branch(): void
    {
        $branch1 = $this->createMockBranch([
            'id' => 1,
            'delivery_base_fee' => 10.0,
            'delivery_free_threshold' => 0.0,
        ]);

        $branch2 = $this->createMockBranch([
            'id' => 2,
            'delivery_base_fee' => 25.0,
            'delivery_free_threshold' => 0.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch1);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(2)
            ->andReturn($branch2);

        $fee1 = $this->service->calculateFee(1, '123 Main St', 75.0);
        $fee2 = $this->service->calculateFee(2, '456 Elm St', 75.0);

        $this->assertEquals(10.0, $fee1);
        $this->assertEquals(25.0, $fee2);
    }

    // ========================================================================
    // FREE DELIVERY THRESHOLD
    // ========================================================================

    public function test_calculate_fee_returns_zero_when_above_free_threshold(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', 150.0);

        $this->assertEquals(0.0, $result);
    }

    public function test_calculate_fee_returns_zero_when_exactly_at_free_threshold(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', 100.0);

        $this->assertEquals(0.0, $result);
    }

    public function test_calculate_fee_charges_base_fee_when_below_free_threshold(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', 99.99);

        $this->assertEquals(15.0, $result);
    }

    public function test_calculate_fee_ignores_threshold_when_set_to_zero(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 0.0, // Threshold disabled
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        // High subtotal but threshold is 0, so fee is still charged
        $result = $this->service->calculateFee(1, '123 Main St', 500.0);

        $this->assertEquals(15.0, $result);
    }

    // ========================================================================
    // ADDRESS IN RANGE VALIDATION
    // ========================================================================

    public function test_is_address_in_range_returns_false_for_invalid_branch(): void
    {
        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->isAddressInRange(999, '123 Main St');

        $this->assertFalse($result);
    }

    public function test_is_address_in_range_returns_false_when_delivery_disabled(): void
    {
        $branch = $this->createMockBranch(['delivery_enabled' => false]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->isAddressInRange(1, '123 Main St');

        $this->assertFalse($result);
    }

    public function test_is_address_in_range_returns_true_when_no_max_distance_configured(): void
    {
        $branch = $this->createMockBranch(['delivery_max_distance' => 0.0]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->isAddressInRange(1, '123 Main St');

        $this->assertTrue($result);
    }

    public function test_is_address_in_range_returns_true_when_delivery_enabled_and_max_distance_set(): void
    {
        $branch = $this->createMockBranch(['delivery_max_distance' => 5.0]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        // TODO: When distance calculation is implemented, this will check actual distance
        $result = $this->service->isAddressInRange(1, '123 Main St');

        $this->assertTrue($result);
    }

    // ========================================================================
    // DELIVERY TIME ESTIMATION
    // ========================================================================

    public function test_estimate_delivery_time_returns_zero_for_invalid_branch(): void
    {
        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->estimateDeliveryTime(999, '123 Main St');

        $this->assertEquals(0, $result);
    }

    public function test_estimate_delivery_time_returns_base_prep_time(): void
    {
        $branch = $this->createMockBranch();

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->estimateDeliveryTime(1, '123 Main St');

        // Default base prep time is 30 minutes
        $this->assertEquals(30, $result);
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function test_calculate_fee_handles_zero_subtotal(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', 0.0);

        $this->assertEquals(15.0, $result);
    }

    public function test_calculate_fee_handles_negative_subtotal(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', -50.0);

        // Negative subtotal won't meet threshold, so charge fee
        $this->assertEquals(15.0, $result);
    }

    public function test_calculate_fee_handles_very_large_subtotal(): void
    {
        $branch = $this->createMockBranch([
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
        ]);

        $this->branchRepoMock
            ->shouldReceive('get')
            ->with(1)
            ->andReturn($branch);

        $result = $this->service->calculateFee(1, '123 Main St', 999999.99);

        // Way above threshold, should be free
        $this->assertEquals(0.0, $result);
    }
}
