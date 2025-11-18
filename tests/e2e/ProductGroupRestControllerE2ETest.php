<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\E2E;

use ProductGroupRestController;
use ProductGroupRepository;
use WP_UnitTestCase;
use WP_REST_Request;

/**
 * End-to-End tests for ProductGroupRestController
 *
 * Tests complete security boundaries and permission enforcement through REST API.
 * Ensures unauthorized users cannot access or modify product group data.
 *
 * @covers ProductGroupRestController
 * @group e2e
 * @group security
 */
class ProductGroupRestControllerE2ETest extends WP_UnitTestCase
{
    private ProductGroupRestController $controller;
    private ProductGroupRepository $repository;
    private int $admin_user_id;
    private int $regular_user_id;
    private array $test_group_ids = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new ProductGroupRestController();
        $this->repository = new ProductGroupRepository();

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
        foreach ($this->test_group_ids as $id) {
            $this->repository->delete($id, true);
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

        // Build the route path
        $route = '/squidly/v1/product-groups' . $endpoint;

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

    /* =====================================================================
     *  SECURITY AND AUTHENTICATION TESTS
     * ===================================================================*/

    public function testSecurityAndPermissionsWorkflow(): void
    {
        // Scenario: Testing security boundaries and permission enforcement through REST API

        // Step 1: Create product group as admin through REST API
        $admin_group_response = $this->make_rest_request('POST', '', [
            'name' => 'Security Test Group',
            'description' => 'A product group for testing security',
            'type' => 'product',
            'status' => 'active'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($admin_group_response));
        $group_data = $this->getResponseData($admin_group_response);
        $group_id = $group_data['id'];
        $this->test_group_ids[] = $group_id;

        // Step 2: Test completely unauthenticated requests (should return 401)
        wp_set_current_user(0); // No user logged in

        $unauth_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_response),
            'Unauthenticated GET request should return 401');

        $unauth_post_response = $this->make_rest_request('POST', '', [
            'name' => 'Unauthorized Group',
            'description' => 'This should not be created'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_post_response),
            'Unauthenticated POST request should return 401');

        $unauth_get_item_response = $this->make_rest_request('GET', "/{$group_id}", [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_item_response),
            'Unauthenticated GET item request should return 401');

        // Step 3: Test insufficient permissions (subscriber role - should return 403)
        wp_set_current_user($this->regular_user_id); // Subscriber user

        $subscriber_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_get_response),
            'Subscriber GET request should return 403');

        $subscriber_post_response = $this->make_rest_request('POST', '', [
            'name' => 'Subscriber Group',
            'description' => 'Subscriber should not create this'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_post_response),
            'Subscriber POST request should return 403');

        $subscriber_put_response = $this->make_rest_request('PUT', "/{$group_id}", [
            'name' => 'Hacked Group Name'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_put_response),
            'Subscriber PUT request should return 403');

        $subscriber_delete_response = $this->make_rest_request('DELETE', "/{$group_id}", [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_delete_response),
            'Subscriber DELETE request should return 403');

        // Step 4: Verify admin can still access and modify (should return 200)
        wp_set_current_user($this->admin_user_id); // Admin user

        $admin_get_response = $this->make_rest_request('GET', "/{$group_id}");
        $this->assertEquals(200, $this->getResponseStatus($admin_get_response),
            'Admin GET request should return 200');

        $admin_update_response = $this->make_rest_request('PUT', "/{$group_id}", [
            'name' => 'Admin Updated Group'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_update_response),
            'Admin PUT request should return 200');

        $updated_data = $this->getResponseData($admin_update_response);
        $this->assertEquals('Admin Updated Group', $updated_data['name'],
            'Group name should be updated by admin');

        // Step 5: Test admin can perform all CRUD operations
        $admin_get_all_response = $this->make_rest_request('GET', '');
        $this->assertEquals(200, $this->getResponseStatus($admin_get_all_response),
            'Admin GET all groups should return 200');

        $admin_create_response = $this->make_rest_request('POST', '', [
            'name' => 'Second Admin Group',
            'description' => 'Another group created by admin',
            'type' => 'product',
            'status' => 'active'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_create_response),
            'Admin POST request should return 200');

        $second_group_data = $this->getResponseData($admin_create_response);
        $this->test_group_ids[] = $second_group_data['id'];

        echo "\n✓ Product group security and permissions workflow completed successfully\n";
    }

    public function testProductGroupProductManagementSecurity(): void
    {
        // Scenario: Test security for group-product relationship endpoints

        wp_set_current_user($this->admin_user_id);

        // Create test group
        $group_response = $this->make_rest_request('POST', '', [
            'name' => 'Product Management Test Group',
            'description' => 'Testing product management security',
            'type' => 'product',
            'status' => 'active'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($group_response));
        $group_data = $this->getResponseData($group_response);
        $group_id = $group_data['id'];
        $this->test_group_ids[] = $group_id;

        // Test unauthenticated access to group products (if endpoint exists)
        wp_set_current_user(0);

        $unauth_products_response = $this->make_rest_request('GET', "/{$group_id}/products", [], false);
        $status = $this->getResponseStatus($unauth_products_response);

        // Should be either 401 (unauthorized) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [401, 404]),
            'Unauthenticated group products request should return 401 or 404, got: ' . $status);

        // Test subscriber access
        wp_set_current_user($this->regular_user_id);

        $subscriber_products_response = $this->make_rest_request('GET', "/{$group_id}/products", [], false);
        $status = $this->getResponseStatus($subscriber_products_response);

        // Should be either 403 (forbidden) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [403, 404]),
            'Subscriber group products request should return 403 or 404, got: ' . $status);

        // Test adding products to group without permission
        wp_set_current_user($this->regular_user_id);

        $subscriber_add_product_response = $this->make_rest_request('POST', "/{$group_id}/products", [
            'product_id' => 123
        ], false);
        $status = $this->getResponseStatus($subscriber_add_product_response);

        // Should be either 403 (forbidden) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [403, 404]),
            'Subscriber add product request should return 403 or 404, got: ' . $status);

        echo "\n✓ Product group product management security workflow completed successfully\n";
    }

    public function testProductGroupDataIntegrityAndValidation(): void
    {
        // Scenario: Test that security doesn't interfere with data validation and business logic

        wp_set_current_user($this->admin_user_id);

        // Test valid group creation
        $valid_group_response = $this->make_rest_request('POST', '', [
            'name' => 'Valid Group',
            'description' => 'A properly formatted product group',
            'type' => 'product',
            'status' => 'active'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($valid_group_response));
        $group_data = $this->getResponseData($valid_group_response);
        $this->test_group_ids[] = $group_data['id'];

        // Test invalid group creation (should fail validation, not permissions)
        $invalid_group_response = $this->make_rest_request('POST', '', [
            'name' => '', // Empty name should fail validation
            'display_order' => -1, // Invalid display order should fail validation
        ]);

        // Should return 400 (validation error) not 401/403 (auth error)
        $this->assertEquals(400, $this->getResponseStatus($invalid_group_response),
            'Invalid group data should return 400 validation error, not auth error');

        echo "\n✓ Product group data integrity and validation workflow completed successfully\n";
    }

    public function testNonExistentGroupSecurity(): void
    {
        // Scenario: Test security responses for non-existent groups

        // Test unauthenticated access to non-existent group
        wp_set_current_user(0);
        $unauth_nonexistent = $this->make_rest_request('GET', '/99999', [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_nonexistent),
            'Unauthenticated request for non-existent group should return 401, not 404');

        // Test insufficient permissions for non-existent group
        wp_set_current_user($this->regular_user_id);
        $subscriber_nonexistent = $this->make_rest_request('GET', '/99999', [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_nonexistent),
            'Subscriber request for non-existent group should return 403, not 404');

        // Test admin access to non-existent group (should return 404)
        wp_set_current_user($this->admin_user_id);
        $admin_nonexistent = $this->make_rest_request('GET', '/99999');
        $this->assertEquals(404, $this->getResponseStatus($admin_nonexistent),
            'Admin request for non-existent group should return 404');

        echo "\n✓ Non-existent product group security workflow completed successfully\n";
    }

    public function testGroupDisplayOrderSecurity(): void
    {
        // Scenario: Test security for group ordering functionality

        wp_set_current_user($this->admin_user_id);

        // Create multiple test groups
        $group_ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $group_response = $this->make_rest_request('POST', '', [
                'name' => "Test Group {$i}",
                'description' => "Group {$i} for display order testing",
                'type' => 'product',
                'status' => 'active'
            ]);

            $this->assertEquals(200, $this->getResponseStatus($group_response));
            $group_data = $this->getResponseData($group_response);
            $group_ids[] = $group_data['id'];
            $this->test_group_ids[] = $group_data['id'];
        }

        // Test unauthenticated reordering (if endpoint exists)
        wp_set_current_user(0);

        $unauth_reorder_response = $this->make_rest_request('PUT', '/reorder', [
            'order' => array_reverse($group_ids)
        ], false);
        $status = $this->getResponseStatus($unauth_reorder_response);

        // Should be either 401 (unauthorized) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [401, 404]),
            'Unauthenticated reorder request should return 401 or 404, got: ' . $status);

        // Test subscriber reordering
        wp_set_current_user($this->regular_user_id);

        $subscriber_reorder_response = $this->make_rest_request('PUT', '/reorder', [
            'order' => array_reverse($group_ids)
        ], false);
        $status = $this->getResponseStatus($subscriber_reorder_response);

        // Should be either 403 (forbidden) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [403, 404]),
            'Subscriber reorder request should return 403 or 404, got: ' . $status);

        echo "\n✓ Group display order security workflow completed successfully\n";
    }
}