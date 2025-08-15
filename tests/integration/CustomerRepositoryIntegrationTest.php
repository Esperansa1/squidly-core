<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use CustomerRepository;
use Customer;
use Address;
use InvalidArgumentException;
use ResourceInUseException;
use WP_UnitTestCase;

/**
 * Integration tests for CustomerRepository
 * Tests complete customer lifecycle and complex interactions
 */
class CustomerRepositoryIntegrationTest extends WP_UnitTestCase
{
    private CustomerRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->repository = new CustomerRepository();
        
        // Clean up any existing customers from previous tests
        $this->cleanupExistingCustomers();
    }
    
    private function cleanupExistingCustomers(): void
    {
        $all_customers = $this->repository->getAll();
        foreach ($all_customers as $customer) {
            $this->repository->delete($customer->id, true);
        }
    }

    /* =====================================================================
     * COMPLETE CUSTOMER LIFECYCLE
     * ===================================================================*/

    public function test_complete_registered_customer_lifecycle(): void
    {
        // Create registered customer with Google auth
        $customer_data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'google_id' => 'google_123456',
            'allow_sms_notifications' => true,
            'allow_email_notifications' => true,
            'is_guest' => false,
            'is_active' => true,
        ];

        $customer_id = $this->repository->create($customer_data);
        $this->assertIsInt($customer_id);
        $this->assertGreaterThan(0, $customer_id);

        // Retrieve and verify customer
        $customer = $this->repository->get($customer_id);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('John', $customer->first_name);
        $this->assertEquals('Doe', $customer->last_name);
        $this->assertEquals('john.doe@example.com', $customer->email);
        $this->assertEquals('+972501234567', $customer->phone); // Should be formatted
        $this->assertEquals('google', $customer->auth_provider);
        $this->assertEquals('google_123456', $customer->google_id);
        $this->assertTrue($customer->allow_sms_notifications);
        $this->assertTrue($customer->allow_email_notifications);
        $this->assertFalse($customer->is_guest);
        $this->assertTrue($customer->is_active);
        $this->assertTrue($customer->isAuthenticated());

        // Update customer data
        $update_success = $this->repository->update($customer_id, [
            'email' => 'john.updated@example.com',
            'allow_sms_notifications' => false,
        ]);
        $this->assertTrue($update_success);

        $updated_customer = $this->repository->get($customer_id);
        $this->assertEquals('john.updated@example.com', $updated_customer->email);
        $this->assertFalse($updated_customer->allow_sms_notifications);

        // Add loyalty points
        $points_added = $this->repository->addLoyaltyPoints($customer_id, 100.5);
        $this->assertTrue($points_added);

        $customer_with_points = $this->repository->get($customer_id);
        $this->assertEquals(100.5, $customer_with_points->loyalty_points_balance);
        $this->assertEquals(100.5, $customer_with_points->lifetime_points_earned);

        // Use loyalty points
        $points_used = $this->repository->useLoyaltyPoints($customer_id, 30.5);
        $this->assertTrue($points_used);

        $customer_after_use = $this->repository->get($customer_id);
        $this->assertEquals(70.0, $customer_after_use->loyalty_points_balance);
        $this->assertEquals(100.5, $customer_after_use->lifetime_points_earned);

        // Update order statistics
        $order_updated = $this->repository->updateOrderStats($customer_id, 1001, 250.75);
        $this->assertTrue($order_updated);

        $customer_with_order = $this->repository->get($customer_id);
        $this->assertEquals(1, $customer_with_order->total_orders);
        $this->assertEquals(250.75, $customer_with_order->total_spent);
        $this->assertContains(1001, $customer_with_order->order_ids);
        $this->assertNotNull($customer_with_order->last_order_date);

        // Add staff label
        $label_added = $this->repository->addStaffLabel($customer_id, 'VIP customer - frequent buyer');
        $this->assertTrue($label_added);

        $customer_with_label = $this->repository->get($customer_id);
        $this->assertStringContainsString('VIP customer - frequent buyer', $customer_with_label->staff_labels);

        // Delete customer
        $deleted = $this->repository->delete($customer_id, true);
        $this->assertTrue($deleted);

        $deleted_customer = $this->repository->get($customer_id);
        $this->assertNull($deleted_customer);
    }

    public function test_complete_guest_customer_lifecycle(): void
    {
        // Create guest customer with phone auth
        $guest_data = [
            'first_name' => 'Jane',
            'last_name' => 'Guest',
            'phone' => '0521234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
            'is_active' => true,
        ];

        $guest_id = $this->repository->create($guest_data);
        $this->assertIsInt($guest_id);

        $guest = $this->repository->get($guest_id);
        $this->assertInstanceOf(Customer::class, $guest);
        $this->assertEquals('+972521234567', $guest->phone); // check phone number (formatted)
        $this->assertTrue($guest->is_guest);
        $this->assertEquals('phone', $guest->auth_provider);
        $this->assertEmpty($guest->email);
        $this->assertFalse($guest->canEarnLoyaltyPoints());
        $this->assertFalse($guest->isAuthenticated()); // Phone not verified yet

        // Convert guest to registered customer
        $converted = $this->repository->convertGuestToRegistered(
            $guest_id,
            'jane.registered@example.com',
            'google',
            'google_jane_789'
        );
        $this->assertTrue($converted);

        $registered = $this->repository->get($guest_id);
        $this->assertFalse($registered->is_guest);
        $this->assertEquals('jane.registered@example.com', $registered->email);
        $this->assertEquals('google', $registered->auth_provider);
        $this->assertEquals('google_jane_789', $registered->google_id);
        $this->assertTrue($registered->canEarnLoyaltyPoints());
        $this->assertTrue($registered->isAuthenticated());
    }

    /* =====================================================================
     * SEARCH AND FIND OPERATIONS
     * ===================================================================*/

    public function test_find_customers_by_various_criteria(): void
    {
        $this->cleanupExistingCustomers();
        // Create test customers
        $customer1_id = $this->repository->create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'phone' => '0501111111',
            'auth_provider' => 'google',
            'google_id' => 'google_alice',
            'is_guest' => false,
            'loyalty_points_balance' => 150.0,
            'lifetime_points_earned' => 200.0,
            'total_spent' => 500.0,
            'total_orders' => 5,
            'order_ids' => [1001, 1002, 1003, 1004, 1005],
            'last_order_date' => '2025-08-10 14:30:00',
        ]);

        $customer2_id = $this->repository->create([
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob@example.com',
            'phone' => '0502222222',
            'auth_provider' => 'phone',
            'is_guest' => false,
            'loyalty_points_balance' => 50.0,
            'lifetime_points_earned' => 75.0,
            'total_spent' => 200.0,
            'total_orders' => 2,
            'order_ids' => [2001, 2002],
            'last_order_date' => '2025-08-12 10:15:00',
        ]);

        $guest_id = $this->repository->create([
            'first_name' => 'Charlie',
            'last_name' => 'Guest',
            'phone' => '0503333333',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);
        

        // Test findByEmail
        $found_by_email = $this->repository->findByEmail('alice@example.com');
        $this->assertInstanceOf(Customer::class, $found_by_email);
        $this->assertEquals($customer1_id, $found_by_email->id);

        // Test findByPhone
        $found_by_phone = $this->repository->findByPhone('+972502222222');
        $this->assertInstanceOf(Customer::class, $found_by_phone);
        $this->assertEquals($customer2_id, $found_by_phone->id);

        // Test findByGoogleId
        $found_by_google = $this->repository->findByGoogleId('google_alice');
        $this->assertInstanceOf(Customer::class, $found_by_google);
        $this->assertEquals($customer1_id, $found_by_google->id);

        // Test getGuestCustomers
        $guests = $this->repository->getGuestCustomers();
        $this->assertCount(1, $guests);
        $this->assertEquals($guest_id, $guests[0]->id);

        // Test getCustomersWithLoyaltyPoints
        $with_points = $this->repository->getCustomersWithLoyaltyPoints(100.0);
        $this->assertCount(1, $with_points);
        $this->assertEquals($customer1_id, $with_points[0]->id);

        // Test findBy with various criteria
        $high_spenders = $this->repository->findBy(['min_total_spent' => 400.0]);
        $this->assertCount(1, $high_spenders);
        $this->assertEquals($customer1_id, $high_spenders[0]->id);

        $frequent_buyers = $this->repository->findBy(['min_orders' => 3]);
        $this->assertCount(1, $frequent_buyers);
        $this->assertEquals($customer1_id, $frequent_buyers[0]->id);

        $phone_auth_customers = $this->repository->findBy(['auth_provider' => 'phone', 'is_guest' => false]);
        $this->assertCount(1, $phone_auth_customers);
        $this->assertEquals($customer2_id, $phone_auth_customers[0]->id);

        // Test search functionality
        $search_results = $this->repository->search('Alice');
        $this->assertCount(1, $search_results);
        $this->assertEquals($customer1_id, $search_results[0]->id);

        // Test countBy
        $total_guests = $this->repository->countBy(['is_guest' => true]);
        $this->assertEquals(1, $total_guests);

        $total_registered = $this->repository->countBy(['is_guest' => false]);
        $this->assertEquals(2, $total_registered);

        // Test exists
        $this->assertTrue($this->repository->exists($customer1_id));
        $this->assertFalse($this->repository->exists(99999));
    }

    /* =====================================================================
     * ERROR HANDLING AND VALIDATION
     * ===================================================================*/

    public function test_create_with_invalid_data_throws_exceptions(): void
    {
        // Missing required field
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'first_name' is missing or empty");
        
        $this->repository->create([
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'auth_provider' => 'google',
        ]);
    }

    public function test_invalid_phone_format_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number must start with +972 or 0 for Israeli numbers');
        
        $this->repository->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '1234567890', // Invalid format
            'auth_provider' => 'phone',
        ]);
    }

    public function test_invalid_email_format_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');
        
        $this->repository->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'not-an-email',
            'phone' => '0501234567',
            'auth_provider' => 'google',
        ]);
    }

    public function test_invalid_auth_provider_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid auth_provider');
        
        $this->repository->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'invalid',
        ]);
    }

    public function test_use_more_loyalty_points_than_available_throws_exception(): void
    {
        $customer_id = $this->repository->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'is_guest' => false,
            'loyalty_points_balance' => 50.0,
            'lifetime_points_earned' => 50.0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient loyalty points balance');
        
        $customer = $this->repository->get($customer_id);
        $customer->useLoyaltyPoints(100.0);
    }

    public function test_add_loyalty_points_to_guest_throws_exception(): void
    {
        $guest_id = $this->repository->create([
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer cannot earn loyalty points (guest or inactive)');
        
        $guest = $this->repository->get($guest_id);
        $guest->addLoyaltyPoints(50.0);
    }

    /* =====================================================================
     * GUEST CLEANUP FUNCTIONALITY
     * ===================================================================*/

    public function test_cleanup_old_guests(): void
    {
        // Create old guest (manually set post date)
        $old_guest_id = wp_insert_post([
            'post_title' => 'Old Guest (0501234567)',
            'post_type' => 'customer',
            'post_status' => 'publish',
            'post_date' => date('Y-m-d H:i:s', strtotime('-40 days')),
            'post_date_gmt' => date('Y-m-d H:i:s', strtotime('-40 days')),
        ]);
        
        update_post_meta($old_guest_id, '_is_guest', true);
        update_post_meta($old_guest_id, '_phone', '0501234567');
        update_post_meta($old_guest_id, '_auth_provider', 'phone');
        update_post_meta($old_guest_id, '_is_active', true);

        // Create recent guest
        $recent_guest_id = $this->repository->create([
            'first_name' => 'Recent',
            'last_name' => 'Guest',
            'phone' => '0502222222',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        // Run cleanup for guests older than 30 days
        $deleted_count = $this->repository->cleanupOldGuests(30);
        
        $this->assertEquals(1, $deleted_count);
        $this->assertNull(get_post($old_guest_id));
        $this->assertNotNull(get_post($recent_guest_id));
    }

    /* =====================================================================
     * COMPLEX BUSINESS LOGIC
     * ===================================================================*/

    public function test_customer_order_flow_with_loyalty_points(): void
    {
        // Create customer
        $customer_id = $this->repository->create([
            'first_name' => 'Order',
            'last_name' => 'Test',
            'email' => 'order@test.com',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'google_id' => 'google_order_test',
            'is_guest' => false,
        ]);

        // Simulate first order
        $this->repository->updateOrderStats($customer_id, 1001, 100.00);
        
        // Award loyalty points (2% of order total)
        $loyalty_rate = 2.0;
        $points_to_add = 100.00 * ($loyalty_rate / 100);
        $this->repository->addLoyaltyPoints($customer_id, $points_to_add);

        $customer = $this->repository->get($customer_id);
        $this->assertEquals(1, $customer->total_orders);
        $this->assertEquals(100.00, $customer->total_spent);
        $this->assertEquals(2.0, $customer->loyalty_points_balance);

        // Simulate second order with partial loyalty points redemption
        $this->repository->updateOrderStats($customer_id, 1002, 150.00);
        $points_to_add = 150.00 * ($loyalty_rate / 100);
        $this->repository->addLoyaltyPoints($customer_id, $points_to_add);
        
        // Use 1 point as discount
        $this->repository->useLoyaltyPoints($customer_id, 1.0);

        $customer = $this->repository->get($customer_id);
        $this->assertEquals(2, $customer->total_orders);
        $this->assertEquals(250.00, $customer->total_spent);
        $this->assertEquals(4.0, $customer->loyalty_points_balance); // 2 + 3 - 1
        $this->assertEquals(5.0, $customer->lifetime_points_earned); // 2 + 3
    }

    public function test_customer_address_management(): void
    {
        $customer_id = $this->repository->create([
            'first_name' => 'Address',
            'last_name' => 'Test',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'address@test.com',
        ]);

        $customer = $this->repository->get($customer_id);
        
        // Add primary address
        $address1 = new Address([
            'street' => '123 Main St',
            'city' => 'Tel Aviv',
            'zip' => '12345',
            'is_default' => true,
        ]);
        $customer->addAddress($address1);

        // Add secondary address
        $address2 = new Address([
            'street' => '456 Side St',
            'city' => 'Jerusalem',
            'zip' => '67890',
            'is_default' => false,
        ]);
        $customer->addAddress($address2);

        $this->repository->update($customer_id, [
            'addresses' => array_map(fn($addr) => $addr->toArray(), $customer->addresses),
        ]);

        $updated_customer = $this->repository->get($customer_id);
        $this->assertCount(2, $updated_customer->addresses);
        
        $primary = $updated_customer->getPrimaryAddress();
        $this->assertNotNull($primary);
        $this->assertEquals('123 Main St', $primary->street);
        $this->assertTrue($primary->is_default);
    }

    public function test_concurrent_order_updates_maintain_consistency(): void
    {
        $customer_id = $this->repository->create([
            'first_name' => 'Concurrent',
            'last_name' => 'Test',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'is_guest' => false,
        ]);

        // Simulate multiple orders being processed
        $orders = [
            [1001, 100.00],
            [1002, 150.00],
            [1003, 75.50],
        ];

        foreach ($orders as [$order_id, $amount]) {
            $this->repository->updateOrderStats($customer_id, $order_id, $amount);
        }

        $customer = $this->repository->get($customer_id);
        $this->assertEquals(3, $customer->total_orders);
        $this->assertEquals(325.50, $customer->total_spent);
        $this->assertCount(3, $customer->order_ids);
        $this->assertContains(1001, $customer->order_ids);
        $this->assertContains(1002, $customer->order_ids);
        $this->assertContains(1003, $customer->order_ids);
    }

    public function test_staff_labels_accumulate_with_timestamps(): void
    {
        $customer_id = $this->repository->create([
            'first_name' => 'Label',
            'last_name' => 'Test',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'label@test.com',
        ]);

        // Add multiple staff labels
        $this->repository->addStaffLabel($customer_id, 'First interaction - polite customer');
        sleep(1); // Ensure different timestamps
        $this->repository->addStaffLabel($customer_id, 'Complained about delivery time');
        sleep(1);
        $this->repository->addStaffLabel($customer_id, 'VIP status granted');

        $customer = $this->repository->get($customer_id);
        $labels = $customer->staff_labels;
        
        $this->assertStringContainsString('First interaction - polite customer', $labels);
        $this->assertStringContainsString('Complained about delivery time', $labels);
        $this->assertStringContainsString('VIP status granted', $labels);
        
        // Check that timestamps are included
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}:/', $labels);
    }
}