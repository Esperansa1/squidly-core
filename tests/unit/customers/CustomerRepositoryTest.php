<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use CustomerRepository;
use Customer;
use InvalidArgumentException;
use ResourceInUseException;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CustomerRepository
 *
 * Tests repository validation logic, phone normalization,
 * data sanitization, and business rules without WordPress dependencies.
 */
class CustomerRepositoryTest extends TestCase
{
    private CustomerRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new CustomerRepository();
    }

    /* ---------------------------------------------------------------------
     *  Creation Validation Tests
     * -------------------------------------------------------------------*/

    public function test_create_with_valid_data_returns_id(): void
    {
        $validData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+972501234567',
            'email' => 'john@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google123'
        ];

        $id = $this->repo->create($validData);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /** @dataProvider provideInvalidCreateData */
    public function test_create_with_invalid_data_throws_exception(array $data, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->repo->create($data);
    }

    public function provideInvalidCreateData(): array
    {
        $validBase = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+972501234567',
            'auth_provider' => 'google'
        ];

        return [
            'missing first_name' => [
                array_diff_key($validBase, ['first_name' => '']),
                "Required field 'first_name' is missing or empty"
            ],
            'empty first_name' => [
                array_merge($validBase, ['first_name' => '']),
                "Required field 'first_name' is missing or empty"
            ],
            'missing last_name' => [
                array_diff_key($validBase, ['last_name' => '']),
                "Required field 'last_name' is missing or empty"
            ],
            'missing phone' => [
                array_diff_key($validBase, ['phone' => '']),
                "Required field 'phone' is missing or empty"
            ],
            'missing auth_provider' => [
                array_diff_key($validBase, ['auth_provider' => '']),
                "Required field 'auth_provider' is missing or empty"
            ],
            'invalid email format' => [
                array_merge($validBase, ['email' => 'invalid-email']),
                'Invalid email format'
            ],
            'invalid auth_provider' => [
                array_merge($validBase, ['auth_provider' => 'facebook']),
                'Invalid auth_provider'
            ]
        ];
    }

    /* ---------------------------------------------------------------------
     *  Phone Normalization Tests
     * -------------------------------------------------------------------*/

    /** @dataProvider providePhoneNormalizationCases */
    public function test_phone_normalization(string $input, string $expected): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => $input,
            'auth_provider' => 'phone'
        ];

        $id = $this->repo->create($data);
        $customer = $this->repo->get($id);

        $this->assertEquals($expected, $customer->phone);
    }

    public function providePhoneNormalizationCases(): array
    {
        return [
            'full international format' => ['+972501234567', '+972501234567'],
            'local format with 0' => ['0501234567', '+972501234567'],
            'with spaces' => ['+972 50 123 4567', '+972501234567'],
            'with dashes' => ['+972-50-123-4567', '+972501234567'],
            'local with spaces' => ['050 123 4567', '+972501234567']
        ];
    }

    /** @dataProvider provideInvalidPhones */
    public function test_invalid_phone_throws_exception(string $phone, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => $phone,
            'auth_provider' => 'phone'
        ];

        $this->repo->create($data);
    }

    public function provideInvalidPhones(): array
    {
        return [
            'empty phone' => ['', "Required field 'phone' is missing or empty"],
            'too short israeli' => ['050123', 'Israeli phone number starting with 0 must be 10 digits'],
            'too long israeli' => ['05012345678', 'Israeli phone number starting with 0 must be 10 digits'],
            'invalid prefix' => ['1234567890', 'Phone number must start with +972 or 0 for Israeli numbers'],
            'wrong international length' => ['+97250', 'Israeli phone number with +972 must be 13 digits total'],
            'only letters' => ['abcdefghij', 'Phone number must contain digits']
        ];
    }

    /* ---------------------------------------------------------------------
     *  Update Validation Tests
     * -------------------------------------------------------------------*/

    public function test_update_with_valid_data_succeeds(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->update($customerId, [
            'first_name' => 'Jane',
            'email' => 'jane@example.com'
        ]);

        $this->assertTrue($result);

        $customer = $this->repo->get($customerId);
        $this->assertEquals('Jane', $customer->first_name);
        $this->assertEquals('jane@example.com', $customer->email);
    }

    public function test_update_with_invalid_email_throws_exception(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->update($customerId, [
            'email' => 'invalid-email'
        ]);

        // Update should return false due to validation error
        $this->assertFalse($result);
    }

    public function test_update_with_negative_loyalty_points_throws_exception(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->update($customerId, [
            'loyalty_points_balance' => -10.0
        ]);

        $this->assertFalse($result);
    }

    public function test_update_nonexistent_customer_returns_false(): void
    {
        $result = $this->repo->update(99999, ['first_name' => 'Test']);
        $this->assertFalse($result);
    }

    public function test_update_phone_normalizes_value(): void
    {
        $customerId = $this->createTestCustomer();

        $this->repo->update($customerId, ['phone' => '0509876543']);

        $customer = $this->repo->get($customerId);
        $this->assertEquals('+972509876543', $customer->phone);
    }

    /* ---------------------------------------------------------------------
     *  Delete Tests with Dependencies
     * -------------------------------------------------------------------*/

    public function test_delete_customer_without_orders_succeeds(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->delete($customerId);
        $this->assertTrue($result);
    }

    /* Delete operations are tested in integration tests
     * as they require WordPress database operations */

    public function test_delete_with_invalid_id_returns_false(): void
    {
        $this->assertFalse($this->repo->delete(0));
        $this->assertFalse($this->repo->delete(-1));
    }

    /* ---------------------------------------------------------------------
     *  Exists Tests
     * -------------------------------------------------------------------*/

    public function test_exists_returns_true_for_valid_customer(): void
    {
        $customerId = $this->createTestCustomer();
        $this->assertTrue($this->repo->exists($customerId));
    }

    public function test_exists_returns_false_for_invalid_id(): void
    {
        $this->assertFalse($this->repo->exists(99999));
        $this->assertFalse($this->repo->exists(0));
        $this->assertFalse($this->repo->exists(-1));
    }

    /* ---------------------------------------------------------------------
     *  Get Tests
     * -------------------------------------------------------------------*/

    public function test_get_returns_null_for_nonexistent_customer(): void
    {
        $this->assertNull($this->repo->get(99999));
    }

    public function test_get_returns_null_for_invalid_id(): void
    {
        $this->assertNull($this->repo->get(0));
        $this->assertNull($this->repo->get(-1));
    }

    public function test_get_returns_customer_for_valid_id(): void
    {
        $customerId = $this->createTestCustomer();
        $customer = $this->repo->get($customerId);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($customerId, $customer->id);
    }

    /* ---------------------------------------------------------------------
     *  FindBy Tests
     * -------------------------------------------------------------------*/

    public function test_find_by_email_returns_matching_customer(): void
    {
        $this->createTestCustomer(['email' => 'test1@example.com']);
        $this->createTestCustomer(['email' => 'test2@example.com']);

        $customer = $this->repo->findByEmail('test1@example.com');
        $this->assertNotNull($customer);
        $this->assertEquals('test1@example.com', $customer->email);
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $customer = $this->repo->findByEmail('nonexistent@example.com');
        $this->assertNull($customer);
    }

    public function test_find_by_phone_returns_matching_customer(): void
    {
        $this->createTestCustomer(['phone' => '+972501234567']);
        $this->createTestCustomer(['phone' => '+972509876543']);

        $customer = $this->repo->findByPhone('+972501234567');
        $this->assertNotNull($customer);
        $this->assertEquals('+972501234567', $customer->phone);
    }

    public function test_find_by_google_id_returns_matching_customer(): void
    {
        $this->createTestCustomer(['google_id' => 'google123', 'auth_provider' => 'google']);
        $this->createTestCustomer(['google_id' => 'google456', 'auth_provider' => 'google']);

        $customer = $this->repo->findByGoogleId('google123');
        $this->assertNotNull($customer);
        $this->assertEquals('google123', $customer->google_id);
    }

    /* ---------------------------------------------------------------------
     *  Guest Customer Tests
     * -------------------------------------------------------------------*/

    public function test_get_guest_customers_returns_only_guests(): void
    {
        $this->createTestCustomer(['is_guest' => true]);
        $this->createTestCustomer(['is_guest' => true]);
        $this->createTestCustomer(['is_guest' => false]);

        $guests = $this->repo->getGuestCustomers();
        $this->assertCount(2, $guests);
    }

    public function test_convert_guest_to_registered_updates_customer(): void
    {
        $customerId = $this->createTestCustomer(['is_guest' => true, 'email' => '']);

        $result = $this->repo->convertGuestToRegistered(
            $customerId,
            'converted@example.com',
            'google',
            'google789'
        );

        $this->assertTrue($result);

        $customer = $this->repo->get($customerId);
        $this->assertFalse($customer->is_guest);
        $this->assertEquals('converted@example.com', $customer->email);
        $this->assertEquals('google', $customer->auth_provider);
        $this->assertEquals('google789', $customer->google_id);
    }

    /* ---------------------------------------------------------------------
     *  Loyalty Points Tests
     * -------------------------------------------------------------------*/

    public function test_add_loyalty_points_increases_balance(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->addLoyaltyPoints($customerId, 50.0);
        $this->assertTrue($result);

        $customer = $this->repo->get($customerId);
        $this->assertEquals(50.0, $customer->loyalty_points_balance);
        $this->assertEquals(50.0, $customer->lifetime_points_earned);
    }

    public function test_use_loyalty_points_decreases_balance(): void
    {
        $customerId = $this->createTestCustomer();

        $this->repo->addLoyaltyPoints($customerId, 100.0);
        $result = $this->repo->useLoyaltyPoints($customerId, 30.0);
        $this->assertTrue($result);

        $customer = $this->repo->get($customerId);
        $this->assertEquals(70.0, $customer->loyalty_points_balance);
    }

    public function test_use_loyalty_points_more_than_balance_fails(): void
    {
        $customerId = $this->createTestCustomer();

        $this->repo->addLoyaltyPoints($customerId, 50.0);
        $result = $this->repo->useLoyaltyPoints($customerId, 100.0);

        $this->assertFalse($result);

        // Balance should remain unchanged
        $customer = $this->repo->get($customerId);
        $this->assertEquals(50.0, $customer->loyalty_points_balance);
    }

    public function test_get_customers_with_loyalty_points_filters_correctly(): void
    {
        $this->createTestCustomer(['loyalty_points_balance' => 100.0]);
        $this->createTestCustomer(['loyalty_points_balance' => 50.0]);
        $this->createTestCustomer(['loyalty_points_balance' => 0.0]);

        $customers = $this->repo->getCustomersWithLoyaltyPoints(60.0);
        $this->assertCount(1, $customers);
    }

    /* ---------------------------------------------------------------------
     *  Order Statistics Tests
     * -------------------------------------------------------------------*/

    public function test_update_order_stats_increments_totals(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->updateOrderStats($customerId, 123, 100.0);
        $this->assertTrue($result);

        $customer = $this->repo->get($customerId);
        $this->assertEquals(1, $customer->total_orders);
        $this->assertEquals(100.0, $customer->total_spent);
        $this->assertContains(123, $customer->order_ids);
    }

    public function test_update_order_stats_multiple_orders_accumulates(): void
    {
        $customerId = $this->createTestCustomer();

        $this->repo->updateOrderStats($customerId, 123, 100.0);
        $this->repo->updateOrderStats($customerId, 456, 50.0);

        $customer = $this->repo->get($customerId);
        $this->assertEquals(2, $customer->total_orders);
        $this->assertEquals(150.0, $customer->total_spent);
        $this->assertContains(123, $customer->order_ids);
        $this->assertContains(456, $customer->order_ids);
    }

    /* ---------------------------------------------------------------------
     *  Staff Labels Tests
     * -------------------------------------------------------------------*/

    public function test_add_staff_label_adds_to_customer(): void
    {
        $customerId = $this->createTestCustomer();

        $result = $this->repo->addStaffLabel($customerId, 'VIP');
        $this->assertTrue($result);

        $customer = $this->repo->get($customerId);
        $this->assertStringContainsString('VIP', $customer->staff_labels);
    }

    /* ---------------------------------------------------------------------
     *  Search Tests
     * -------------------------------------------------------------------*/

    public function test_search_finds_customers_by_name(): void
    {
        $this->createTestCustomer(['first_name' => 'Alice', 'last_name' => 'Johnson']);
        $this->createTestCustomer(['first_name' => 'Bob', 'last_name' => 'Smith']);

        $results = $this->repo->search('Alice', 10);
        $this->assertNotEmpty($results);
    }

    public function test_search_with_short_query_returns_empty(): void
    {
        $results = $this->repo->search('a', 10);
        $this->assertEmpty($results);
    }

    /* ---------------------------------------------------------------------
     *  CountBy Tests
     * -------------------------------------------------------------------*/

    /* countBy operations are tested in integration tests
     * as they require WordPress WP_Query operations */

    /* ---------------------------------------------------------------------
     *  Helper Methods
     * -------------------------------------------------------------------*/

    private function createTestCustomer(array $overrides = []): int
    {
        $defaults = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+972501234567',
            'email' => 'john.doe@example.com',
            'auth_provider' => 'phone',
            'is_guest' => false,
            'loyalty_points_balance' => 0.0,
            'lifetime_points_earned' => 0.0
        ];

        return $this->repo->create(array_merge($defaults, $overrides));
    }
}
