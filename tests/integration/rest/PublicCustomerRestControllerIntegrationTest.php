<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicCustomerRestController;
use CustomerRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for PublicCustomerRestController
 *
 * Tests the public customer API including:
 * - Guest customer creation
 * - Phone number normalization
 * - Duplicate detection (return existing guest)
 * - Validation (required fields, email format)
 *
 * @covers \PublicCustomerRestController
 */
class PublicCustomerRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicCustomerRestController $controller;
    private CustomerRepository $customerRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicCustomerRestController();
        $this->customerRepo = new CustomerRepository();

        // Set up REST API
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
        $this->controller->register_routes();
    }

    protected function tearDown(): void
    {
        // Clean up all test customers
        $all_customers = $this->customerRepo->getAll();
        foreach ($all_customers as $customer) {
            try {
                wp_delete_post($customer->id, true);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }

        parent::tearDown();
    }

    // ========================================================================
    // GUEST CUSTOMER CREATION TESTS
    // ========================================================================

    public function test_create_guest_customer_success(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'email' => 'john@example.com',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('customer_id', $data);
        $this->assertArrayHasKey('existing', $data);
        $this->assertFalse($data['existing']);

        // Verify customer was created
        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertNotNull($customer);
        $this->assertEquals('John', $customer->first_name);
        $this->assertEquals('Doe', $customer->last_name);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertTrue($customer->is_guest);
    }

    public function test_create_guest_customer_without_email(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '0507654321',
            // Email is optional for guests
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertEmpty($customer->email);
    }

    // ========================================================================
    // PHONE NORMALIZATION TESTS
    // ========================================================================

    public function test_phone_normalization_adds_country_code(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567', // Israeli format without +972
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $customer = $this->customerRepo->get($data['customer_id']);

        // Should be normalized to +972501234567
        $this->assertEquals('+972501234567', $customer->phone);
    }

    public function test_phone_normalization_preserves_country_code(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+972501234567', // Already has country code
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertEquals('+972501234567', $customer->phone);
    }

    // ========================================================================
    // DUPLICATE DETECTION TESTS
    // ========================================================================

    public function test_returns_existing_guest_for_same_phone(): void
    {
        // Create first guest
        $request1 = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request1->set_header('Content-Type', 'application/json');
        $request1->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
        ]));

        $response1 = rest_get_server()->dispatch($request1);
        $data1 = $response1->get_data();
        $first_customer_id = $data1['customer_id'];

        // Try to create second guest with same phone
        $request2 = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request2->set_header('Content-Type', 'application/json');
        $request2->set_body(json_encode([
            'first_name' => 'Jane', // Different name
            'last_name' => 'Smith',
            'phone' => '0501234567', // Same phone
        ]));

        $response2 = rest_get_server()->dispatch($request2);
        $data2 = $response2->get_data();

        // Should return existing customer
        $this->assertEquals(200, $response2->get_status());
        $this->assertEquals($first_customer_id, $data2['customer_id']);
        $this->assertTrue($data2['existing']);

        // Original customer details should be preserved
        $customer = $this->customerRepo->get($data2['customer_id']);
        $this->assertEquals('John', $customer->first_name);
        $this->assertEquals('Doe', $customer->last_name);
    }

    public function test_duplicate_detection_works_with_normalized_phone(): void
    {
        // Create guest with format 1
        $request1 = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request1->set_header('Content-Type', 'application/json');
        $request1->set_body(json_encode([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '0501234567',
        ]));

        $response1 = rest_get_server()->dispatch($request1);
        $data1 = $response1->get_data();

        // Try with format 2 (with country code)
        $request2 = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request2->set_header('Content-Type', 'application/json');
        $request2->set_body(json_encode([
            'first_name' => 'Another',
            'last_name' => 'User',
            'phone' => '+972501234567', // Same phone, different format
        ]));

        $response2 = rest_get_server()->dispatch($request2);
        $data2 = $response2->get_data();

        // Should return same customer
        $this->assertEquals($data1['customer_id'], $data2['customer_id']);
        $this->assertTrue($data2['existing']);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_create_guest_fails_without_first_name(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'last_name' => 'Doe',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_create_guest_fails_without_last_name(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_create_guest_fails_without_phone(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_create_guest_validates_email_format(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'email' => 'invalid-email', // Invalid format
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_create_guest_accepts_valid_email(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'email' => 'valid@example.com',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
    }

    // ========================================================================
    // RESPONSE STRUCTURE TESTS
    // ========================================================================

    public function test_guest_creation_response_structure(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Verify response structure
        $this->assertArrayHasKey('customer_id', $data);
        $this->assertArrayHasKey('existing', $data);
        $this->assertIsInt($data['customer_id']);
        $this->assertIsBool($data['existing']);
    }

    // ========================================================================
    // GUEST FLAG TESTS
    // ========================================================================

    public function test_created_customer_is_marked_as_guest(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0509999999',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertTrue($customer->is_guest);
        $this->assertEquals('phone', $customer->auth_provider);
    }

    // ========================================================================
    // SPECIAL CHARACTERS TESTS
    // ========================================================================

    public function test_handles_names_with_special_characters(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'José',
            'last_name' => "O'Brien",
            'phone' => '0508888888',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertEquals('José', $customer->first_name);
        $this->assertEquals("O'Brien", $customer->last_name);
    }

    // ========================================================================
    // PHONE FORMAT VARIATIONS TESTS
    // ========================================================================

    public function test_handles_phone_with_spaces(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '050 123 4567', // With spaces
        ]));

        $response = rest_get_server()->dispatch($request);

        // API validation rejects phone numbers with spaces
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('message', $data);
    }

    public function test_handles_phone_with_dashes(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '050-123-4567', // With dashes
        ]));

        $response = rest_get_server()->dispatch($request);

        // API validation rejects phone numbers with dashes
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('message', $data);
    }
}
