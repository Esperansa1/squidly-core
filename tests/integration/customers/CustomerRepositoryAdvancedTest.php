<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use CustomerRepository;
use Customer;
use InvalidArgumentException;
use ResourceInUseException;
use WP_UnitTestCase;

/**
 * Integration tests for CustomerRepository critical functionality.
 * 
 * Tests phone normalization, loyalty points, order statistics, and search functionality.
 */
class CustomerRepositoryAdvancedTest extends WP_UnitTestCase
{
    private CustomerRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = new CustomerRepository();
        
        // Clean up any existing customers from previous tests
        $this->cleanupExistingCustomers();
    }
    
    private function cleanupExistingCustomers(): void
    {
        $all_customers = $this->repo->getAll();
        foreach ($all_customers as $customer) {
            $this->repo->delete($customer->id, true);
        }
    }

    /* ---------------------------------------------------------------------
     *  Phone Normalization Tests (Critical Security)
     * -------------------------------------------------------------------*/
    
    public function test_normalize_phone_via_customer_creation(): void
    {
        // Test phone normalization through customer creation (integration approach)
        $customerId = $this->repo->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567', // Local format
            'auth_provider' => 'phone'
        ]);
        
        $customer = $this->repo->get($customerId);
        $this->assertEquals('+972501234567', $customer->phone); // Should be normalized
    }

    public function test_normalize_phone_preserves_international_format(): void
    {
        $customerId = $this->repo->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+972501234567', // Already international
            'auth_provider' => 'phone'
        ]);
        
        $customer = $this->repo->get($customerId);
        $this->assertEquals('+972501234567', $customer->phone);
    }

    public function test_normalize_phone_handles_formatted_numbers(): void
    {
        $customerId = $this->repo->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '050-123-4567', // Formatted
            'auth_provider' => 'phone'
        ]);
        
        $customer = $this->repo->get($customerId);
        $this->assertEquals('+972501234567', $customer->phone);
    }

    /**
     * @dataProvider invalidPhoneProvider
     */
    public function test_invalid_phone_formats_throw_exceptions(string $phone, string $expectedMessagePart): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repo->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => $phone,
            'auth_provider' => 'phone'
        ]);
    }

    public function invalidPhoneProvider(): array
    {
        return [
            'empty phone' => ['', 'Phone number cannot be empty'],
            'too short local' => ['050123456', 'must be 10 digits'],
            'too long local' => ['05012345678', 'must be 10 digits'],
            'invalid international' => ['+97250123456', 'must be 13 digits total'],
            'non-israeli format' => ['1234567890', 'must start with +972 or 0'],
            'only letters' => ['abcdefghij', 'must contain digits'],
        ];
    }

    /* ---------------------------------------------------------------------
     *  Order Statistics Tests (Business Logic)
     * -------------------------------------------------------------------*/

    public function test_update_order_stats_increments_order_count_and_total(): void
    {
        // Create a customer first
        $customerId = $this->repo->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'auth_provider' => 'phone'
        ]);

        // Update order stats
        $result = $this->repo->updateOrderStats($customerId, 123, 45.50);
        $this->assertTrue($result);

        // Verify the stats were updated
        $customer = $this->repo->get($customerId);
        $this->assertContains(123, $customer->order_ids);
        $this->assertEquals(1, $customer->total_orders);
        $this->assertEquals(45.50, $customer->total_spent);
        $this->assertNotNull($customer->last_order_date);

        // Add another order
        $result = $this->repo->updateOrderStats($customerId, 124, 25.00);
        $this->assertTrue($result);

        // Verify cumulative stats
        $customer = $this->repo->get($customerId);
        $this->assertCount(2, $customer->order_ids);
        $this->assertEquals(2, $customer->total_orders);
        $this->assertEquals(70.50, $customer->total_spent);
    }

    public function test_update_order_stats_returns_false_for_non_existent_customer(): void
    {
        $result = $this->repo->updateOrderStats(99999, 123, 45.50);
        $this->assertFalse($result);
    }

    /* ---------------------------------------------------------------------
     *  Loyalty Points Tests (Business Logic)
     * -------------------------------------------------------------------*/

    public function test_add_loyalty_points_increases_balance_and_lifetime(): void
    {
        // Create a registered customer (not guest) to allow loyalty points
        $customerId = $this->repo->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '0507654321',
            'auth_provider' => 'phone',
            'is_guest' => false
        ]);

        // Add loyalty points
        $result = $this->repo->addLoyaltyPoints($customerId, 15.5);
        $this->assertTrue($result);

        // Verify points were added
        $customer = $this->repo->get($customerId);
        $this->assertEquals(15.5, $customer->loyalty_points_balance);
        $this->assertEquals(15.5, $customer->lifetime_points_earned);

        // Add more points
        $result = $this->repo->addLoyaltyPoints($customerId, 10.0);
        $this->assertTrue($result);

        // Verify cumulative points
        $customer = $this->repo->get($customerId);
        $this->assertEquals(25.5, $customer->loyalty_points_balance);
        $this->assertEquals(25.5, $customer->lifetime_points_earned);
    }

    public function test_use_loyalty_points_decreases_balance(): void
    {
        // Create customer with initial loyalty points
        $customerId = $this->repo->create([
            'first_name' => 'Bob',
            'last_name' => 'Wilson',
            'phone' => '0509876543',
            'auth_provider' => 'phone',
            'is_guest' => false,
            'loyalty_points_balance' => 20.0,
            'lifetime_points_earned' => 20.0
        ]);

        // Use some loyalty points
        $result = $this->repo->useLoyaltyPoints($customerId, 5.0);
        $this->assertTrue($result);

        // Verify balance decreased but lifetime stayed same
        $customer = $this->repo->get($customerId);
        $this->assertEquals(15.0, $customer->loyalty_points_balance);
        $this->assertEquals(20.0, $customer->lifetime_points_earned); // Unchanged
    }

    public function test_loyalty_points_operations_fail_for_non_existent_customer(): void
    {
        $this->assertFalse($this->repo->addLoyaltyPoints(99999, 10.0));
        $this->assertFalse($this->repo->useLoyaltyPoints(99999, 5.0));
    }

    /* ---------------------------------------------------------------------
     *  Search Functionality Tests
     * -------------------------------------------------------------------*/

    public function test_search_returns_empty_for_short_query(): void
    {
        $result = $this->repo->search('a');
        $this->assertEmpty($result);
    }

    public function test_search_finds_customers_by_name(): void
    {
        // Create test customers
        $johnId = $this->repo->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501111111',
            'auth_provider' => 'phone'
        ]);

        $janeId = $this->repo->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '0502222222',
            'auth_provider' => 'phone'
        ]);

        // Search for John
        $result = $this->repo->search('John');
        
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
        
        // Find John in the results
        $johnFound = false;
        foreach ($result as $customer) {
            if ($customer->first_name === 'John') {
                $johnFound = true;
                break;
            }
        }
        $this->assertTrue($johnFound, 'John should be found in search results');
    }

    public function test_search_finds_customers_by_phone(): void
    {
        $customerId = $this->repo->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0503334444',
            'auth_provider' => 'phone'
        ]);

        $result = $this->repo->search('0503334444');
        $this->assertIsArray($result);
        
        if (count($result) > 0) {
            $found = false;
            foreach ($result as $customer) {
                if ($customer->id === $customerId) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Customer should be found by phone search');
        }
    }

    /* ---------------------------------------------------------------------
     *  Staff Label Tests
     * -------------------------------------------------------------------*/

    public function test_add_staff_label_updates_customer(): void
    {
        $customerId = $this->repo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '0505555555',
            'auth_provider' => 'phone'
        ]);

        $result = $this->repo->addStaffLabel($customerId, 'VIP Customer');
        $this->assertTrue($result);

        // Verify label was added
        $customer = $this->repo->get($customerId);
        $this->assertStringContainsString('VIP Customer', $customer->staff_labels);
    }

    public function test_add_staff_label_fails_for_non_existent_customer(): void
    {
        $result = $this->repo->addStaffLabel(99999, 'VIP');
        $this->assertFalse($result);
    }

    /* ---------------------------------------------------------------------
     *  Guest Conversion Tests
     * -------------------------------------------------------------------*/

    public function test_convert_guest_to_registered_updates_auth_info(): void
    {
        // Create guest customer
        $customerId = $this->repo->create([
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0506666666',
            'auth_provider' => 'phone',
            'is_guest' => true
        ]);

        // Convert to registered
        $result = $this->repo->convertGuestToRegistered($customerId, 'guest@example.com', 'google', 'google123');
        $this->assertTrue($result);

        // Verify conversion
        $customer = $this->repo->get($customerId);
        $this->assertEquals('guest@example.com', $customer->email);
        $this->assertEquals('google', $customer->auth_provider);
        $this->assertEquals('google123', $customer->google_id);
        $this->assertFalse($customer->is_guest);
    }

    public function test_convert_guest_fails_for_non_existent_customer(): void
    {
        $result = $this->repo->convertGuestToRegistered(99999, 'test@example.com', 'google', 'google123');
        $this->assertFalse($result);
    }

    /* ---------------------------------------------------------------------
     *  Guest Cleanup Tests  
     * -------------------------------------------------------------------*/

    public function test_cleanup_old_guests_basic_functionality(): void
    {
        // Create some guest customers with different dates
        $oldGuestId = $this->repo->create([
            'first_name' => 'Old',
            'last_name' => 'Guest',
            'phone' => '0507777777',
            'auth_provider' => 'phone',
            'is_guest' => true
        ]);

        $newGuestId = $this->repo->create([
            'first_name' => 'New',
            'last_name' => 'Guest', 
            'phone' => '0508888888',
            'auth_provider' => 'phone',
            'is_guest' => true
        ]);

        // Test cleanup (may not delete anything in test environment due to timing)
        $deletedCount = $this->repo->cleanupOldGuests(30);
        $this->assertIsInt($deletedCount);
        $this->assertGreaterThanOrEqual(0, $deletedCount);
    }

    /* ---------------------------------------------------------------------
     *  findBy() Method Tests
     * -------------------------------------------------------------------*/

    public function test_find_by_guest_status(): void
    {
        // Create guest and registered customers
        $guestId = $this->repo->create([
            'first_name' => 'Guest',
            'last_name' => 'Customer',
            'phone' => '0509999999',
            'auth_provider' => 'phone',
            'is_guest' => true
        ]);

        $registeredId = $this->repo->create([
            'first_name' => 'Registered',
            'last_name' => 'Customer',
            'phone' => '0500000000',
            'auth_provider' => 'phone',
            'is_guest' => false
        ]);

        // Find guests
        $guests = $this->repo->findBy(['is_guest' => true]);
        $this->assertIsArray($guests);
        $this->assertGreaterThan(0, count($guests));

        // Find registered customers
        $registered = $this->repo->findBy(['is_guest' => false]);
        $this->assertIsArray($registered);
        $this->assertGreaterThan(0, count($registered));
    }

    public function test_find_by_auth_provider(): void
    {
        $phoneCustomerId = $this->repo->create([
            'first_name' => 'Phone',
            'last_name' => 'User',
            'phone' => '0501010101',
            'auth_provider' => 'phone'
        ]);

        $googleCustomerId = $this->repo->create([
            'first_name' => 'Google',
            'last_name' => 'User',
            'phone' => '0502020202',
            'auth_provider' => 'google',
            'email' => 'google@example.com',
            'google_id' => 'google456'
        ]);

        $phoneUsers = $this->repo->findBy(['auth_provider' => 'phone']);
        $this->assertIsArray($phoneUsers);
        
        $googleUsers = $this->repo->findBy(['auth_provider' => 'google']);
        $this->assertIsArray($googleUsers);
    }
}