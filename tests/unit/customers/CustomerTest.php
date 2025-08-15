<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use Customer;
use Address;
use InvalidArgumentException;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Customer model
 * @covers \Customer
 */
class CustomerTest extends TestCase
{
    public function test_constructor_with_complete_registered_customer_data(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'google_id' => 'google_123',
            'phone_verified_at' => null,
            'addresses' => [],
            'allow_sms_notifications' => true,
            'allow_email_notifications' => true,
            'order_ids' => [1001, 1002],
            'total_orders' => 2,
            'total_spent' => 150.50,
            'last_order_date' => '2024-01-15 10:30:00',
            'loyalty_points_balance' => 50.25,
            'lifetime_points_earned' => 75.50,
            'staff_labels' => 'VIP customer',
            'is_active' => true,
            'registration_date' => '2023-06-01 09:00:00',
            'is_guest' => false,
        ];

        $customer = new Customer($data);

        $this->assertEquals(1, $customer->id);
        $this->assertEquals('John', $customer->first_name);
        $this->assertEquals('Doe', $customer->last_name);
        $this->assertEquals('john.doe@example.com', $customer->email);
        $this->assertEquals('+972501234567', $customer->phone);
        $this->assertEquals('google', $customer->auth_provider);
        $this->assertEquals('google_123', $customer->google_id);
        $this->assertNull($customer->phone_verified_at);
        $this->assertTrue($customer->allow_sms_notifications);
        $this->assertTrue($customer->allow_email_notifications);
        $this->assertEquals([1001, 1002], $customer->order_ids);
        $this->assertEquals(2, $customer->total_orders);
        $this->assertEquals(150.50, $customer->total_spent);
        $this->assertInstanceOf(DateTime::class, $customer->last_order_date);
        $this->assertEquals(50.25, $customer->loyalty_points_balance);
        $this->assertEquals(75.50, $customer->lifetime_points_earned);
        $this->assertEquals('VIP customer', $customer->staff_labels);
        $this->assertTrue($customer->is_active);
        $this->assertFalse($customer->is_guest);
        $this->assertTrue($customer->isAuthenticated());
        $this->assertTrue($customer->canEarnLoyaltyPoints());
    }

    public function test_constructor_with_guest_customer_data(): void
    {
        $data = [
            'id' => 2,
            'first_name' => 'Jane',
            'last_name' => 'Guest',
            'phone' => '0521234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ];

        $customer = new Customer($data);

        $this->assertEquals(2, $customer->id);
        $this->assertEquals('Jane', $customer->first_name);
        $this->assertEquals('Guest', $customer->last_name);
        $this->assertEquals('+972521234567', $customer->phone);
        $this->assertEquals('phone', $customer->auth_provider);
        $this->assertTrue($customer->is_guest);
        $this->assertEmpty($customer->email);
        $this->assertNull($customer->google_id);
        $this->assertNull($customer->phone_verified_at);
        $this->assertFalse($customer->isAuthenticated());
        $this->assertFalse($customer->canEarnLoyaltyPoints());
        $this->assertEquals(0.0, $customer->loyalty_points_balance);
        $this->assertEquals(0.0, $customer->lifetime_points_earned);
    }

    public function test_phone_number_formatting(): void
    {
        // Test with 0 prefix
        $customer1 = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0541234567',
            'auth_provider' => 'phone',
        ]);
        $this->assertEquals('+972541234567', $customer1->phone);

        // Test with +972 prefix
        $customer2 = new Customer([
            'id' => 2,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+972541234567',
            'auth_provider' => 'phone',
        ]);
        $this->assertEquals('+972541234567', $customer2->phone);
    }

    public function test_getFullName(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('John Doe', $customer->getFullName());
    }

    public function test_address_management(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
        ]);

        // Initially no addresses
        $this->assertEmpty($customer->addresses);
        $this->assertNull($customer->getPrimaryAddress());

        // Add first address - should become default
        $address1 = new Address([
            'street' => '123 Main St',
            'city' => 'Tel Aviv',
            'is_default' => false,
        ]);
        $customer->addAddress($address1);

        $this->assertCount(1, $customer->addresses);
        $this->assertTrue($customer->addresses[0]->is_default);
        $this->assertNotNull($customer->getPrimaryAddress());

        // Add second address as default
        $address2 = new Address([
            'street' => '456 Side St',
            'city' => 'Jerusalem',
            'is_default' => true,
        ]);
        $customer->addAddress($address2);

        $this->assertCount(2, $customer->addresses);
        $this->assertFalse($customer->addresses[0]->is_default);
        $this->assertTrue($customer->addresses[1]->is_default);
        $this->assertEquals('456 Side St', $customer->getPrimaryAddress()->street);
    }

    public function test_loyalty_points_operations(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
            'is_guest' => false,
        ]);

        // Add points
        $customer->addLoyaltyPoints(100.50);
        $this->assertEquals(100.50, $customer->loyalty_points_balance);
        $this->assertEquals(100.50, $customer->lifetime_points_earned);

        // Add more points
        $customer->addLoyaltyPoints(50.25);
        $this->assertEquals(150.75, $customer->loyalty_points_balance);
        $this->assertEquals(150.75, $customer->lifetime_points_earned);

        // Use points
        $result = $customer->useLoyaltyPoints(30.25);
        $this->assertTrue($result);
        $this->assertEquals(120.50, $customer->loyalty_points_balance);
        $this->assertEquals(150.75, $customer->lifetime_points_earned);
    }

    public function test_adding_loyalty_points_to_guest_throws_exception(): void
    {
        $guest = new Customer([
            'id' => 1,
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer cannot earn loyalty points (guest or inactive)');
        
        $guest->addLoyaltyPoints(10.0);
    }

    public function test_using_more_points_than_available_throws_exception(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
            'loyalty_points_balance' => 50.0,
            'lifetime_points_earned' => 50.0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient loyalty points balance');
        
        $customer->useLoyaltyPoints(100.0);
    }

    public function test_authentication_status(): void
    {
        // Google authenticated
        $googleCustomer = new Customer([
            'id' => 1,
            'first_name' => 'Google',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'google_id' => 'google_123',
            'email' => 'google@example.com',
        ]);
        $this->assertTrue($googleCustomer->isAuthenticated());

        // Phone verified
        $phoneCustomer = new Customer([
            'id' => 2,
            'first_name' => 'Phone',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'phone_verified_at' => '2024-01-01 10:00:00',
        ]);
        $this->assertTrue($phoneCustomer->isAuthenticated());

        // Phone not verified
        $unverifiedPhone = new Customer([
            'id' => 3,
            'first_name' => 'Unverified',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'phone_verified_at' => null,
        ]);
        $this->assertFalse($unverifiedPhone->isAuthenticated());
    }

    public function test_update_order_statistics(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
        ]);

        // First order
        $customer->updateOrderStats(1001, 100.50);
        $this->assertEquals([1001], $customer->order_ids);
        $this->assertEquals(1, $customer->total_orders);
        $this->assertEquals(100.50, $customer->total_spent);
        $this->assertNotNull($customer->last_order_date);

        // Second order
        $customer->updateOrderStats(1002, 75.25);
        $this->assertEquals([1001, 1002], $customer->order_ids);
        $this->assertEquals(2, $customer->total_orders);
        $this->assertEquals(175.75, $customer->total_spent);
    }

    public function test_duplicate_order_id_throws_exception(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
            'last_order_date' => '2025/1/1',
            'order_ids' => [1001],
            'total_orders' => 1,
            'total_spent' => 100.0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order ID already exists in customer history');
        
        $customer->updateOrderStats(1001, 50.0);
    }

    public function test_convert_guest_to_registered(): void
    {
        $guest = new Customer([
            'id' => 1,
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        $this->assertTrue($guest->is_guest);
        $this->assertEmpty($guest->email);
        $this->assertFalse($guest->canEarnLoyaltyPoints());

        // Convert to registered with Google
        $guest->convertToRegistered('guest.converted@example.com', 'google', 'google_456');

        $this->assertFalse($guest->is_guest);
        $this->assertEquals('guest.converted@example.com', $guest->email);
        $this->assertEquals('google', $guest->auth_provider);
        $this->assertEquals('google_456', $guest->google_id);
        $this->assertTrue($guest->canEarnLoyaltyPoints());
        $this->assertTrue($guest->isAuthenticated());
    }

    public function test_convert_already_registered_throws_exception(): void
    {
        $registered = new Customer([
            'id' => 1,
            'first_name' => 'Registered',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'registered@example.com',
            'google_id' => 'google_123',
            'is_guest' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer is already registered');
        
        $registered->convertToRegistered('new@example.com', 'google', 'google_789');
    }

    public function test_add_staff_label(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
        ]);

        $customer->addStaffLabel('First interaction - polite customer');
        $this->assertStringContainsString('First interaction - polite customer', $customer->staff_labels);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}:/', $customer->staff_labels);

        $customer->addStaffLabel('Complained about delivery');
        $this->assertStringContainsString('First interaction - polite customer', $customer->staff_labels);
        $this->assertStringContainsString('Complained about delivery', $customer->staff_labels);
    }

    public function test_empty_staff_label_throws_exception(): void
    {
        $customer = new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Staff label cannot be empty');
        
        $customer->addStaffLabel('   ');
    }

    public function test_create_order_snapshot(): void
    {
        $customer = new Customer([
            'id' => 123,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'google_id' => 'google_123',
            'is_guest' => false,
        ]);

        $snapshot = $customer->createOrderSnapshot();

        $this->assertEquals(123, $snapshot['customer_id']);
        $this->assertEquals('John Doe', $snapshot['customer_name']);
        $this->assertEquals('john@example.com', $snapshot['customer_email']);
        $this->assertEquals('+972501234567', $snapshot['customer_phone']);
        $this->assertFalse($snapshot['is_guest']);
        $this->assertArrayHasKey('snapshot_created_at', $snapshot);
    }

    public function test_toArray_round_trip(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'google_id' => 'google_123',
            'phone_verified_at' => '2024-01-01 10:00:00',
            'addresses' => [],
            'allow_sms_notifications' => true,
            'allow_email_notifications' => false,
            'order_ids' => [1001, 1002],
            'total_orders' => 2,
            'total_spent' => 200.50,
            'last_order_date' => '2024-01-15 15:30:00',
            'loyalty_points_balance' => 100.25,
            'lifetime_points_earned' => 150.75,
            'staff_labels' => 'Test label',
            'is_active' => true,
            'registration_date' => '2023-01-01 09:00:00',
            'is_guest' => false,
        ];

        $customer = new Customer($data);
        $array = $customer->toArray();

        // Note: Phone is formatted, so we need to check the formatted version
        $this->assertEquals('+972501234567', $array['phone']);
        
        // Check other critical fields
        $this->assertEquals($data['id'], $array['id']);
        $this->assertEquals($data['first_name'], $array['first_name']);
        $this->assertEquals($data['last_name'], $array['last_name']);
        $this->assertEquals($data['email'], $array['email']);
        $this->assertEquals($data['auth_provider'], $array['auth_provider']);
        $this->assertEquals($data['google_id'], $array['google_id']);
        $this->assertEquals($data['loyalty_points_balance'], $array['loyalty_points_balance']);
        $this->assertEquals($data['is_guest'], $array['is_guest']);
    }

    /**
     * @dataProvider invalidPhoneNumbers
     */
    public function test_invalid_phone_formats_throw_exception(string $phone, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        
        new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => $phone,
            'auth_provider' => 'phone',
        ]);
    }

    public function invalidPhoneNumbers(): array
    {
        return [
            'empty' => ['', 'Phone number cannot be empty'],
            'too short' => ['054123', 'Israeli phone number starting with 0 must be 10 digits'],
            'invalid start' => ['1234567890', 'Phone number must start with +972 or 0 for Israeli numbers'],
            'non-numeric' => ['abc-def-ghij', 'Phone number must contain digits'],
        ];
    }

    public function test_business_rules_validation(): void
    {
        // Guest with loyalty points should fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Guest customers cannot have loyalty points balance');
        
        new Customer([
            'id' => 1,
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
            'loyalty_points_balance' => 10.0,
        ]);
    }

    public function test_loyalty_balance_exceeds_lifetime_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Current points balance cannot exceed lifetime points earned');
        
        new Customer([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'test@example.com',
            'loyalty_points_balance' => 100.0,
            'lifetime_points_earned' => 50.0,
        ]);
    }
}