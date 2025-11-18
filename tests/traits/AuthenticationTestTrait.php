<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Traits;

/**
 * Authentication Test Trait
 *
 * Provides reusable authentication testing patterns for E2E REST API tests.
 * This trait standardizes security testing across all REST controllers.
 *
 * Usage:
 * - Use this trait in E2E test classes
 * - Call setupAuthenticationTesting() in setUp()
 * - Use make_rest_request() for all REST API calls
 * - Use assertSecurityResponse() for consistent assertions
 *
 * @package SquidlyCore\Tests\Traits
 */
trait AuthenticationTestTrait
{
    protected int $admin_user_id;
    protected int $regular_user_id;

    /**
     * Setup authentication testing users and WordPress REST environment
     */
    protected function setupAuthenticationTesting(): void
    {
        // Create test users if not already created
        if (!isset($this->admin_user_id)) {
            $this->admin_user_id = $this->factory->user->create([
                'role' => 'administrator'
            ]);
        }

        if (!isset($this->regular_user_id)) {
            $this->regular_user_id = $this->factory->user->create([
                'role' => 'subscriber'
            ]);
        }
    }

    /**
     * Make REST API request through WordPress internal server
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $namespace REST namespace (e.g., 'squidly/v1')
     * @param string $route REST route (e.g., 'products', 'orders/123')
     * @param array $data Request data
     * @param bool $auto_authenticate Whether to auto-authenticate as admin if no user set
     * @return \WP_REST_Response
     */
    protected function makeRestRequest(string $method, string $namespace, string $route, array $data = [], bool $auto_authenticate = true): \WP_REST_Response
    {
        global $wp_rest_server;

        // Ensure REST server is initialized
        if (!$wp_rest_server) {
            $wp_rest_server = new \WP_REST_Server();
            do_action('rest_api_init');
        }

        // Build the full route path
        $full_route = "/{$namespace}/{$route}";

        // Create a proper WP_REST_Request
        $request = new \WP_REST_Request($method, $full_route);

        // Set authentication context
        if ($auto_authenticate && !get_current_user_id()) {
            wp_set_current_user($this->admin_user_id);
        }

        // Add request data
        if ($method === 'GET' && !empty($data)) {
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
        } else if (!empty($data)) {
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
     * Get response status from WP_REST_Response
     */
    protected function getResponseStatus($response): int
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_status();
        }

        return wp_remote_retrieve_response_code($response);
    }

    /**
     * Get response data from WP_REST_Response
     */
    protected function getResponseData($response): array
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_data();
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true) ?? [];
    }

    /**
     * Assert security response matches expected status
     *
     * @param \WP_REST_Response $response
     * @param int $expected_status
     * @param string $message
     */
    protected function assertSecurityResponse($response, int $expected_status, string $message = ''): void
    {
        $actual_status = $this->getResponseStatus($response);

        if (empty($message)) {
            $message = "Expected {$expected_status}, got {$actual_status}";
        }

        $this->assertEquals($expected_status, $actual_status, $message);
    }

    /**
     * Test complete authentication workflow for a given endpoint
     *
     * @param string $namespace REST namespace
     * @param string $route REST route
     * @param array $test_data Data for testing CRUD operations
     * @param array $methods HTTP methods to test (default: all CRUD)
     */
    protected function runAuthenticationWorkflow(string $namespace, string $route, array $test_data = [], array $methods = ['GET', 'POST', 'PUT', 'DELETE']): void
    {
        // Step 1: Test unauthenticated requests (should return 401)
        wp_set_current_user(0);

        foreach ($methods as $method) {
            $response = $this->makeRestRequest($method, $namespace, $route, $test_data, false);
            $this->assertSecurityResponse($response, 401,
                "Unauthenticated {$method} request to {$route} should return 401");
        }

        // Step 2: Test insufficient permissions (should return 403)
        wp_set_current_user($this->regular_user_id);

        foreach ($methods as $method) {
            $response = $this->makeRestRequest($method, $namespace, $route, $test_data, false);
            $this->assertSecurityResponse($response, 403,
                "Subscriber {$method} request to {$route} should return 403");
        }

        // Step 3: Test authorized requests (should return 200 or appropriate business response)
        wp_set_current_user($this->admin_user_id);

        $get_response = $this->makeRestRequest('GET', $namespace, $route);
        $status = $this->getResponseStatus($get_response);
        $this->assertTrue(in_array($status, [200, 404]),
            "Admin GET request to {$route} should return 200 or 404, got {$status}");
    }

    /**
     * Test non-existent resource security
     *
     * @param string $namespace REST namespace
     * @param string $base_route Base route (e.g., 'products')
     * @param int $fake_id Non-existent resource ID
     */
    protected function runNonExistentResourceSecurity(string $namespace, string $base_route, int $fake_id = 99999): void
    {
        $route = "{$base_route}/{$fake_id}";

        // Test unauthenticated access to non-existent resource (should return 401, not 404)
        wp_set_current_user(0);
        $unauth_response = $this->makeRestRequest('GET', $namespace, $route, [], false);
        $this->assertSecurityResponse($unauth_response, 401,
            "Unauthenticated request for non-existent {$base_route} should return 401, not 404");

        // Test insufficient permissions for non-existent resource (should return 403, not 404)
        wp_set_current_user($this->regular_user_id);
        $subscriber_response = $this->makeRestRequest('GET', $namespace, $route, [], false);
        $this->assertSecurityResponse($subscriber_response, 403,
            "Subscriber request for non-existent {$base_route} should return 403, not 404");

        // Test admin access to non-existent resource (should return 404)
        wp_set_current_user($this->admin_user_id);
        $admin_response = $this->makeRestRequest('GET', $namespace, $route);
        $this->assertSecurityResponse($admin_response, 404,
            "Admin request for non-existent {$base_route} should return 404");
    }

    /**
     * Assert that response data doesn't contain sensitive information
     *
     * @param array $data Response data to check
     * @param array $sensitive_fields List of sensitive field names
     */
    protected function assertNoSensitiveData(array $data, array $sensitive_fields = []): void
    {
        $default_sensitive_fields = [
            'password', 'api_key', 'secret_key', 'private_key', 'access_token',
            'card_number', 'cvv', 'ssn', 'credit_card', 'bank_account'
        ];

        $all_sensitive_fields = array_merge($default_sensitive_fields, $sensitive_fields);

        foreach ($all_sensitive_fields as $field) {
            $this->assertArrayNotHasKey($field, $data,
                "Response should not contain sensitive field: {$field}");
        }

        // Check nested arrays recursively
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->assertNoSensitiveData($value, $sensitive_fields);
            }
        }
    }

    /**
     * Test data validation vs authentication (ensure validation errors return 400, not 401/403)
     *
     * @param string $namespace REST namespace
     * @param string $route REST route
     * @param array $invalid_data Invalid data that should fail validation
     */
    protected function runValidationVsAuthenticationTest(string $namespace, string $route, array $invalid_data): void
    {
        wp_set_current_user($this->admin_user_id);

        $response = $this->makeRestRequest('POST', $namespace, $route, $invalid_data);
        $status = $this->getResponseStatus($response);

        // Should return 400 (validation error) not 401/403 (auth error)
        $this->assertEquals(400, $status,
            "Invalid data should return 400 validation error, not auth error. Got: {$status}");
    }

    /**
     * Create standardized test data for different resource types
     *
     * @param string $resource_type Type of resource (product, ingredient, etc.)
     * @return array Test data
     */
    protected function getTestData(string $resource_type): array
    {
        $test_data = [
            'product' => [
                'name' => 'Test Product',
                'price' => 19.99,
                'description' => 'A test product for security testing',
                'is_active' => true
            ],
            'ingredient' => [
                'name' => 'Test Ingredient',
                'price' => 2.50,
                'is_active' => true,
                'type' => 'test'
            ],
            'product_group' => [
                'name' => 'Test Product Group',
                'description' => 'A test product group',
                'is_active' => true,
                'display_order' => 1
            ],
            'ingredient_group' => [
                'name' => 'Test Ingredient Group',
                'description' => 'A test ingredient group',
                'is_active' => true
            ],
            'branch' => [
                'name' => 'Test Branch',
                'phone' => '555-TEST',
                'city' => 'Test City',
                'address' => 'Test Address',
                'is_open' => true
            ],
            'order' => [
                'customer_id' => 1,
                'status' => 'pending',
                'total_amount' => 99.99,
                'subtotal' => 89.99,
                'tax_amount' => 10.00,
                'payment_status' => 'pending',
                'payment_method' => 'card'
            ]
        ];

        return $test_data[$resource_type] ?? [];
    }

    /**
     * Get invalid test data for validation testing
     *
     * @param string $resource_type Type of resource
     * @return array Invalid test data
     */
    protected function getInvalidTestData(string $resource_type): array
    {
        $invalid_data = [
            'product' => [
                'name' => '', // Empty name
                'price' => -5.00, // Negative price
            ],
            'ingredient' => [
                'name' => '', // Empty name
                'price' => -2.00, // Negative price
            ],
            'product_group' => [
                'name' => '', // Empty name
                'display_order' => -1, // Invalid order
            ],
            'ingredient_group' => [
                'name' => '', // Empty name
            ],
            'branch' => [
                'name' => '', // Empty name
                'phone' => '', // Empty phone
            ],
            'order' => [
                'customer_id' => -1, // Invalid customer ID
                'total_amount' => -99.99, // Negative amount
            ]
        ];

        return $invalid_data[$resource_type] ?? ['name' => ''];
    }
}