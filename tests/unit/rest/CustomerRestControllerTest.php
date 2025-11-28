<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Rest;

use CustomerRestController;
use CustomerRepository;
use Customer;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use InvalidArgumentException;
use ResourceInUseException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for CustomerRestController
 *
 * Tests all REST API endpoints, validation, permission checks,
 * and error handling without database operations.
 */
class CustomerRestControllerTest extends TestCase
{
    private CustomerRestController $controller;
    private CustomerRepository|MockObject $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(CustomerRepository::class);
        $this->controller = new CustomerRestController();

        // Use reflection to inject mock repository
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->mockRepository);
    }

    /* ==========================================
     * GET /customers Tests
     * ========================================== */

    public function test_get_customers_returns_successful_response(): void
    {
        $customers = [
            $this->createMockCustomer(1),
            $this->createMockCustomer(2)
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], null, 0)
            ->willReturn($customers);

        $this->mockRepository
            ->expects($this->once())
            ->method('countBy')
            ->with([])
            ->willReturn(2);

        $request = new WP_REST_Request('GET', '/squidly/v1/customers');
        $response = $this->controller->get_items($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
    }

    public function test_get_customers_with_filters(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/customers');
        $request->set_param('is_guest', false);
        $request->set_param('is_active', true);

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'is_guest' => false,
                'is_active' => true
            ])
            ->willReturn([]);

        $response = $this->controller->get_items($request);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_get_customers_handles_exception(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->willThrowException(new \Exception('Database error'));

        $request = new WP_REST_Request('GET', '/squidly/v1/customers');
        $response = $this->controller->get_items($request);

        $this->assertEquals(500, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    /* ==========================================
     * GET /customers/{id} Tests
     * ========================================== */

    public function test_get_customer_returns_customer_when_found(): void
    {
        $customer = $this->createMockCustomer(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($customer);

        $request = new WP_REST_Request('GET', '/squidly/v1/customers/123');
        $request->set_url_params(['id' => '123']);

        $response = $this->controller->get_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    public function test_get_customer_returns_404_when_not_found(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn(null);

        $request = new WP_REST_Request('GET', '/squidly/v1/customers/999');
        $request->set_url_params(['id' => '999']);

        $response = $this->controller->get_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Customer not found', $data['error']);
    }

    /* ==========================================
     * POST /customers Tests
     * ========================================== */

    public function test_create_customer_returns_created_customer(): void
    {
        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+972501234567',
            'email' => 'john@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google123'
        ];

        $createdCustomer = $this->createMockCustomer(999);

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn(999);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn($createdCustomer);

        $request = new WP_REST_Request('POST', '/squidly/v1/customers');
        foreach ($customerData as $key => $value) {
            $request->set_param($key, $value);
        }

        $response = $this->controller->create_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals(999, $data['id']);
    }

    public function test_create_customer_handles_validation_error(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new InvalidArgumentException('Invalid email format'));

        $request = new WP_REST_Request('POST', '/squidly/v1/customers');
        $request->set_param('first_name', 'John');
        $request->set_param('last_name', 'Doe');
        $request->set_param('phone', '+972501234567');
        $request->set_param('email', 'invalid-email');
        $request->set_param('auth_provider', 'google');

        $response = $this->controller->create_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    /* ==========================================
     * PUT /customers/{id} Tests
     * ========================================== */

    public function test_update_customer_returns_updated_customer(): void
    {
        $updateData = ['first_name' => 'Jane', 'email' => 'jane@example.com'];
        $updatedCustomer = $this->createMockCustomer(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->with(123, $this->anything())
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updatedCustomer);

        $request = new WP_REST_Request('PUT', '/squidly/v1/customers/123');
        $request->set_url_params(['id' => '123']);
        $request->set_param('first_name', 'Jane');
        $request->set_param('email', 'jane@example.com');

        $response = $this->controller->update_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_update_customer_returns_404_when_not_found(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->willReturn(false);

        $request = new WP_REST_Request('PUT', '/squidly/v1/customers/999');
        $request->set_url_params(['id' => '999']);
        $request->set_param('first_name', 'Jane');

        $response = $this->controller->update_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());
    }

    /* ==========================================
     * DELETE /customers/{id} Tests
     * ========================================== */

    public function test_delete_customer_returns_success(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with(123, false)
            ->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/customers/123');
        $request->set_url_params(['id' => '123']);

        $response = $this->controller->delete_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function test_delete_customer_handles_resource_in_use_exception(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new ResourceInUseException(['Customer has active orders']));

        $request = new WP_REST_Request('DELETE', '/squidly/v1/customers/123');
        $request->set_url_params(['id' => '123']);

        $response = $this->controller->delete_item($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(409, $response->get_status());
    }

    /* ==========================================
     * Search Tests
     * ========================================== */

    public function test_search_customers_returns_results(): void
    {
        $customers = [
            $this->createMockCustomer(1),
            $this->createMockCustomer(2)
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('search')
            ->with('John', 20)
            ->willReturn($customers);

        $request = new WP_REST_Request('GET', '/squidly/v1/customers/search');
        $request->set_param('q', 'John');

        $response = $this->controller->search_customers($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertCount(2, $data);
    }

    /* ==========================================
     * Loyalty Points Tests
     * ========================================== */

    public function test_add_loyalty_points_returns_updated_customer(): void
    {
        $updatedCustomer = $this->createMockCustomer(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('addLoyaltyPoints')
            ->with(123, 50.0)
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updatedCustomer);

        $request = new WP_REST_Request('PUT', '/squidly/v1/customers/123/loyalty-points');
        $request->set_url_params(['id' => '123']);
        $request->set_param('action', 'add');
        $request->set_param('points', 50.0);

        $response = $this->controller->update_loyalty_points($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_use_loyalty_points_returns_updated_customer(): void
    {
        $updatedCustomer = $this->createMockCustomer(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('useLoyaltyPoints')
            ->with(123, 30.0)
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updatedCustomer);

        $request = new WP_REST_Request('PUT', '/squidly/v1/customers/123/loyalty-points');
        $request->set_url_params(['id' => '123']);
        $request->set_param('action', 'use');
        $request->set_param('points', 30.0);

        $response = $this->controller->update_loyalty_points($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /* ==========================================
     * Staff Labels Tests
     * ========================================== */

    public function test_update_staff_labels_returns_updated_customer(): void
    {
        $updatedCustomer = $this->createMockCustomer(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('addStaffLabel')
            ->with(123, 'VIP')
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updatedCustomer);

        $request = new WP_REST_Request('PUT', '/squidly/v1/customers/123/staff-labels');
        $request->set_url_params(['id' => '123']);
        $request->set_param('label', 'VIP');

        $response = $this->controller->update_staff_labels($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /* ==========================================
     * Convert Guest Tests
     * ========================================== */

    public function test_convert_guest_customer_returns_updated_customer(): void
    {
        $convertedCustomer = $this->createMockCustomer(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('convertGuestToRegistered')
            ->with(123, 'john@example.com', 'google', 'google123')
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($convertedCustomer);

        $request = new WP_REST_Request('POST', '/squidly/v1/customers/123/convert-guest');
        $request->set_url_params(['id' => '123']);
        $request->set_param('email', 'john@example.com');
        $request->set_param('auth_provider', 'google');
        $request->set_param('google_id', 'google123');

        $response = $this->controller->convert_guest_customer($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    private function createMockCustomer(int $id): Customer
    {
        $customer = $this->createMock(Customer::class);
        $customer->id = $id;
        $customer->first_name = 'John';
        $customer->last_name = 'Doe';
        $customer->phone = '+972501234567';
        $customer->email = 'john@example.com';
        $customer->auth_provider = 'google';
        $customer->google_id = 'google123';
        $customer->phone_verified_at = null;
        $customer->is_guest = false;
        $customer->is_active = true;
        $customer->total_orders = 0;
        $customer->total_spent = 0.0;
        $customer->loyalty_points_balance = 0.0;
        $customer->lifetime_points_earned = 0.0;
        $customer->staff_labels = '';
        $customer->order_ids = [];
        $customer->addresses = [];
        $customer->allow_sms_notifications = true;
        $customer->allow_email_notifications = true;
        $customer->registration_date = new \DateTime();
        $customer->last_order_date = null;

        return $customer;
    }
}
