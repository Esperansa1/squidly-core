<?php

namespace SquidlyCore\Tests\Integration;

use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;
use CustomerRepository;
use PublicCustomerRestController;

/**
 * Integration tests for PublicCustomerRestController
 *
 * Tests guest customer creation endpoint with validation, rate limiting,
 * duplicate detection, and security measures.
 */
class PublicCustomerRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicCustomerRestController $controller;
    private CustomerRepository $customerRepo;

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new PublicCustomerRestController();
        $this->customerRepo = new CustomerRepository();

        // Register controller routes during rest_api_init action
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');
    }

    /* ------------------------------------------------------------------ */
    /*  Valid Guest Customer Creation                                     */
    /* ------------------------------------------------------------------ */

    public function test_create_guest_customer_with_all_fields_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'email' => 'john.doe@example.com',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('customer_id', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['existing']);

        // Verify customer was created
        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertEquals('John', $customer->first_name);
        $this->assertEquals('Doe', $customer->last_name);
        $this->assertEquals('+972501234567', $customer->phone); // Normalized
        $this->assertEquals('john.doe@example.com', $customer->email);
        $this->assertTrue($customer->is_guest);
        $this->assertEquals('phone', $customer->auth_provider);
    }

    public function test_create_guest_customer_without_email_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '0521234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $customer = $this->customerRepo->get($data['customer_id']);
        // Email is stored as empty string when not provided
        $this->assertTrue(empty($customer->email));
        $this->assertTrue($customer->is_guest);
    }

    /* ------------------------------------------------------------------ */
    /*  Duplicate Detection                                               */
    /* ------------------------------------------------------------------ */

    public function test_duplicate_guest_phone_returns_existing_customer(): void
    {
        // Create first guest
        $first_customer_id = $this->customerRepo->create([
            'first_name' => 'First',
            'last_name' => 'Customer',
            'phone' => '0531234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        // Try to create another guest with same phone
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Second',
            'last_name' => 'Customer',
            'phone' => '0531234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals($first_customer_id, $data['customer_id']);
        $this->assertTrue($data['existing']);
        $this->assertStringContainsString('already exists', $data['message']);
    }

    public function test_registered_customer_phone_rejects_guest_creation(): void
    {
        // Create registered (non-guest) customer
        $this->customerRepo->create([
            'first_name' => 'Registered',
            'last_name' => 'Customer',
            'phone' => '0541234567',
            'email' => 'registered@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google123',
            'is_guest' => false,
        ]);

        // Try to create guest with same phone
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Guest',
            'last_name' => 'Attempt',
            'phone' => '0541234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('registered customer', $data['error']);
    }

    /* ------------------------------------------------------------------ */
    /*  Validation Failures                                               */
    /* ------------------------------------------------------------------ */

    public function test_missing_first_name_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'last_name' => 'Doe',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('first_name', $data['message']);
    }

    public function test_missing_last_name_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('last_name', $data['message']);
    }

    public function test_missing_phone_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('phone', $data['message']);
    }

    public function test_invalid_phone_format_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567', // Invalid format
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('phone', strtolower($data['message']));
    }

    public function test_invalid_email_format_returns_400(): void
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
        $data = $response->get_data();
        $this->assertStringContainsString('email', strtolower($data['message']));
    }

    public function test_first_name_too_short_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'J', // Only 1 character
            'last_name' => 'Doe',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        // WordPress REST API returns "Invalid parameter(s): first_name" for validation failures
        $this->assertStringContainsString('first_name', $data['message']);
    }

    public function test_last_name_too_short_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'D', // Only 1 character
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        // WordPress REST API returns "Invalid parameter(s): last_name" for validation failures
        $this->assertStringContainsString('last_name', $data['message']);
    }

    /* ------------------------------------------------------------------ */
    /*  Input Sanitization                                                */
    /* ------------------------------------------------------------------ */

    public function test_input_sanitization_removes_html(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John<script>alert("xss")</script>',
            'last_name' => 'Doe<b>Bold</b>',
            'phone' => '0501234567',
            'email' => 'test@example.com',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $customer = $this->customerRepo->get($data['customer_id']);

        // Verify HTML/scripts were stripped (sanitize_text_field removes tags but keeps content)
        $this->assertEquals('John', $customer->first_name); // Script and content removed
        $this->assertEquals('DoeBold', $customer->last_name); // Bold tags removed, content preserved
    }

    /* ------------------------------------------------------------------ */
    /*  Phone Number Formats                                              */
    /* ------------------------------------------------------------------ */

    public function test_phone_with_plus_972_prefix_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+972501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $customer = $this->customerRepo->get($data['customer_id']);
        $this->assertEquals('+972501234567', $customer->phone);
    }

    public function test_phone_with_zero_prefix_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $customer = $this->customerRepo->get($data['customer_id']);
        // Should be normalized to +972 format
        $this->assertEquals('+972501234567', $customer->phone);
    }

    /* ------------------------------------------------------------------ */
    /*  Public Access                                                     */
    /* ------------------------------------------------------------------ */

    public function test_endpoint_accessible_without_authentication(): void
    {
        // This test verifies the endpoint is public
        // No authentication headers or nonces required
        $request = new WP_REST_Request('POST', '/squidly/v1/public/guest-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'first_name' => 'Public',
            'last_name' => 'User',
            'phone' => '0551234567',
        ]));

        $response = rest_get_server()->dispatch($request);

        // Should succeed without any authentication
        $this->assertEquals(201, $response->get_status());
    }
}
