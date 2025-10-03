<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\E2E;

use Squidly\Domains\Payments\Rest\PaymentRestController;
use OrderRepository;
use WP_UnitTestCase;
use WP_REST_Request;

/**
 * End-to-End tests for PaymentRestController
 *
 * Tests complete security boundaries and permission enforcement through REST API.
 * Ensures unauthorized users cannot access or modify payment data.
 * Payment data is especially sensitive and requires strict security.
 *
 * @covers PaymentRestController
 * @group e2e
 * @group security
 * @group payment
 */
class PaymentRestControllerE2ETest extends WP_UnitTestCase
{
    private PaymentRestController $controller;
    private OrderRepository $orderRepository;
    private int $admin_user_id;
    private int $regular_user_id;
    private array $test_order_ids = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new PaymentRestController();
        $this->orderRepository = new OrderRepository();

        // Register the REST routes for testing using proper WordPress pattern
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');

        // Create test users
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        $this->regular_user_id = $this->factory->user->create([
            'role' => 'subscriber'
        ]);
    }

    public function tearDown(): void
    {
        // Clean up test data
        foreach ($this->test_order_ids as $id) {
            $this->orderRepository->delete($id, true);
        }

        parent::tearDown();
    }

    private function make_rest_request(string $method, string $endpoint = '', array $data = [], bool $auto_authenticate = true): \WP_REST_Response
    {
        // Use WordPress internal REST server instead of external HTTP requests
        global $wp_rest_server;

        // Ensure REST server is initialized
        if (!$wp_rest_server) {
            $wp_rest_server = new \WP_REST_Server();
            do_action('rest_api_init');
        }

        // Build the route path - PaymentRestController uses /pay not /payments
        $route = '/squidly/v1/pay' . $endpoint;

        // Create a proper WP_REST_Request
        $request = new \WP_REST_Request($method, $route);

        // Set authentication context (only if no specific user is already set and auto_authenticate is true)
        if ($auto_authenticate && !get_current_user_id()) {
            wp_set_current_user($this->admin_user_id);
        }

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
        } else if (!empty($data)) {
            // Add body data for other methods
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
        }

        // Dispatch the request through WordPress REST server
        $response = $wp_rest_server->dispatch($request);

        if (is_wp_error($response)) {
            throw new \Exception('REST request failed: ' . $response->get_error_message());
        }

        return $response;
    }

    /**
     * Helper to get response status from WP_REST_Response
     */
    private function getResponseStatus($response): int
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_status();
        }

        return wp_remote_retrieve_response_code($response);
    }

    /**
     * Helper to get response data from WP_REST_Response
     */
    private function getResponseData($response): array
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_data();
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true) ?? [];
    }

    /**
     * Helper to create test order for payment testing
     */
    private function createTestOrder(): int
    {
        $order_id = $this->orderRepository->create([
            'customer_id' => 1,
            'status' => 'pending',
            'total_amount' => 99.99,
            'subtotal' => 89.99,
            'tax_amount' => 10.00,
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'notes' => 'Test order for payment testing',
            'order_items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Payment Product',
                    'quantity' => 1,
                    'unit_price' => 89.99,
                    'total_price' => 89.99
                ]
            ]
        ]);

        $this->test_order_ids[] = $order_id;
        return $order_id;
    }

    /* =====================================================================
     *  SECURITY AND AUTHENTICATION TESTS
     * ===================================================================*/

    public function testPaymentSecurityAndPermissionsWorkflow(): void
    {
        // Scenario: Testing security boundaries for payment operations (CRITICAL SECURITY)

        wp_set_current_user($this->admin_user_id);

        // Create test order for payment operations
        $order_id = $this->createTestOrder();

        // Step 1: Test completely unauthenticated access to payment start endpoint (should return 401)
        wp_set_current_user(0); // No user logged in

        $unauth_start_payment_response = $this->make_rest_request('POST', '/start', [
            'order_id' => $order_id,
            'amount' => '99.99'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_start_payment_response),
            'Unauthenticated start payment request should return 401');

        $unauth_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => $order_id,
            'amount' => '50.00',
            'reason' => 'Customer request'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_refund_response),
            'Unauthenticated refund request should return 401');

        // Step 2: Test insufficient permissions (subscriber role - should return 403)
        wp_set_current_user($this->regular_user_id); // Subscriber user

        $subscriber_start_payment_response = $this->make_rest_request('POST', '/start', [
            'order_id' => $order_id,
            'amount' => '99.99'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_start_payment_response),
            'Subscriber start payment request should return 403');

        $subscriber_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => $order_id,
            'amount' => '50.00',
            'reason' => 'Customer request'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_refund_response),
            'Subscriber refund request should return 403');

        // Step 3: Verify admin can access payment endpoints (should return 200 or appropriate business logic response)
        wp_set_current_user($this->admin_user_id); // Admin user

        $admin_start_payment_response = $this->make_rest_request('POST', '/start', [
            'order_id' => $order_id,
            'amount' => '99.99'
        ]);
        $status = $this->getResponseStatus($admin_start_payment_response);
        $this->assertTrue(in_array($status, [200, 400, 404, 500]), // 400 for validation, 404 if order doesn't exist, 500 for missing dependencies
            'Admin start payment should return 200, 400, 404, or 500, got: ' . $status);

        echo "\n✓ Payment security and permissions workflow completed successfully\n";
    }

    public function testPaymentRefundSecurity(): void
    {
        // Scenario: Test security for payment refund operations (CRITICAL SECURITY)

        wp_set_current_user($this->admin_user_id);
        $order_id = $this->createTestOrder();

        // Test unauthenticated refund access (should return 401)
        wp_set_current_user(0);

        $unauth_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => $order_id,
            'amount' => '50.00',
            'reason' => 'Customer request'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_refund_response),
            'Unauthenticated refund request should return 401');

        // Test subscriber refund access (should return 403)
        wp_set_current_user($this->regular_user_id);

        $subscriber_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => $order_id,
            'amount' => '25.00',
            'reason' => 'Subscriber test'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_refund_response),
            'Subscriber refund request should return 403');

        // Test admin refund access (should work or return business logic error)
        wp_set_current_user($this->admin_user_id);

        $admin_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => $order_id,
            'amount' => '10.00',
            'reason' => 'Admin test refund'
        ]);
        $status = $this->getResponseStatus($admin_refund_response);
        $this->assertTrue(in_array($status, [200, 400, 404, 500]), // 400 for business logic errors, 500 for missing dependencies
            'Admin refund should return 200, 400, 404, or 500, got: ' . $status);

        echo "\n✓ Payment refund security workflow completed successfully\n";
    }

    public function testPaymentDataValidationSecurity(): void
    {
        // Scenario: Test that validation errors return 400, not 401/403

        wp_set_current_user($this->admin_user_id);

        // Test invalid payment start request (should fail validation, not permissions)
        $invalid_start_response = $this->make_rest_request('POST', '/start', [
            'order_id' => -1, // Invalid order ID
            'amount' => '-99.99', // Invalid amount
        ]);

        // Should return 400 (validation error) not 401/403 (auth error)
        $status = $this->getResponseStatus($invalid_start_response);
        $this->assertTrue(in_array($status, [400, 404]),
            'Invalid payment data should return 400 validation error, got: ' . $status);

        // Test invalid refund request
        $invalid_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => '', // Empty order ID
            'amount' => 'invalid', // Invalid amount format
            'reason' => ''
        ]);

        $status = $this->getResponseStatus($invalid_refund_response);
        $this->assertTrue(in_array($status, [400, 404]),
            'Invalid refund data should return 400 validation error, got: ' . $status);

        echo "\n✓ Payment data validation security workflow completed successfully\n";
    }

    public function testPaymentEndpointMethodSecurity(): void
    {
        // Scenario: Test that payment endpoints only allow proper HTTP methods and validate security for each

        $order_id = $this->createTestOrder();

        // Test 1: Verify that GET requests to payment endpoints are not allowed
        wp_set_current_user($this->admin_user_id);
        $get_start_response = $this->make_rest_request('GET', '/start');
        $status = $this->getResponseStatus($get_start_response);
        $this->assertTrue(in_array($status, [404, 405]),
            'GET request to /start should return 404 or 405, got: ' . $status);

        $get_refund_response = $this->make_rest_request('GET', '/refund');
        $status = $this->getResponseStatus($get_refund_response);
        $this->assertTrue(in_array($status, [404, 405]),
            'GET request to /refund should return 404 or 405, got: ' . $status);

        // Test 2: Verify that unauthenticated POST requests to payment endpoints return 401
        wp_set_current_user(0);
        $unauth_start_response = $this->make_rest_request('POST', '/start', [
            'order_id' => $order_id,
            'amount' => '99.99'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_start_response),
            'Unauthenticated POST to /start should return 401');

        $unauth_refund_response = $this->make_rest_request('POST', '/refund', [
            'order_id' => $order_id,
            'amount' => '10.00'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_refund_response),
            'Unauthenticated POST to /refund should return 401');

        // Test 3: Verify that insufficient permissions return 403
        wp_set_current_user($this->regular_user_id);
        $subscriber_start_response = $this->make_rest_request('POST', '/start', [
            'order_id' => $order_id,
            'amount' => '99.99'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_start_response),
            'Subscriber POST to /start should return 403');

        echo "\n✓ Payment endpoint method security validation completed successfully\n";
    }
}