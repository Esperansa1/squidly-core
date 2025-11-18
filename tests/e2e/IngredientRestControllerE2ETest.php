<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\E2E;

use IngredientRestController;
use IngredientRepository;
use WP_UnitTestCase;
use WP_REST_Request;

/**
 * End-to-End tests for IngredientRestController
 *
 * Tests complete security boundaries and permission enforcement through REST API.
 * Ensures unauthorized users cannot access or modify ingredient data.
 *
 * @covers IngredientRestController
 * @group e2e
 * @group security
 */
class IngredientRestControllerE2ETest extends WP_UnitTestCase
{
    private IngredientRestController $controller;
    private IngredientRepository $repository;
    private int $admin_user_id;
    private int $regular_user_id;
    private array $test_ingredient_ids = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new IngredientRestController();
        $this->repository = new IngredientRepository();

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
        foreach ($this->test_ingredient_ids as $id) {
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
        $route = '/squidly/v1/ingredients' . $endpoint;

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

        // Step 1: Create ingredient as admin through REST API
        $admin_ingredient_response = $this->make_rest_request('POST', '', [
            'name' => 'Security Test Ingredient',
            'price' => 2.50,
            'is_active' => true,
            'type' => 'vegetable'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($admin_ingredient_response));
        $ingredient_data = $this->getResponseData($admin_ingredient_response);
        $ingredient_id = $ingredient_data['id'];
        $this->test_ingredient_ids[] = $ingredient_id;

        // Step 2: Test completely unauthenticated requests (should return 401)
        wp_set_current_user(0); // No user logged in

        $unauth_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_response),
            'Unauthenticated GET request should return 401');

        $unauth_post_response = $this->make_rest_request('POST', '', [
            'name' => 'Unauthorized Ingredient',
            'price' => 5.99,
            'type' => 'unauthorized'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_post_response),
            'Unauthenticated POST request should return 401');

        $unauth_get_item_response = $this->make_rest_request('GET', "/{$ingredient_id}", [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_item_response),
            'Unauthenticated GET item request should return 401');

        // Step 3: Test insufficient permissions (subscriber role - should return 403)
        wp_set_current_user($this->regular_user_id); // Subscriber user

        $subscriber_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_get_response),
            'Subscriber GET request should return 403');

        $subscriber_post_response = $this->make_rest_request('POST', '', [
            'name' => 'Subscriber Ingredient',
            'price' => 3.99,
            'type' => 'unauthorized'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_post_response),
            'Subscriber POST request should return 403');

        $subscriber_put_response = $this->make_rest_request('PUT', "/{$ingredient_id}", [
            'name' => 'Hacked Ingredient Name'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_put_response),
            'Subscriber PUT request should return 403');

        $subscriber_delete_response = $this->make_rest_request('DELETE', "/{$ingredient_id}", [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_delete_response),
            'Subscriber DELETE request should return 403');

        // Step 4: Verify admin can still access and modify (should return 200)
        wp_set_current_user($this->admin_user_id); // Admin user

        $admin_get_response = $this->make_rest_request('GET', "/{$ingredient_id}");
        $this->assertEquals(200, $this->getResponseStatus($admin_get_response),
            'Admin GET request should return 200');

        $admin_update_response = $this->make_rest_request('PUT', "/{$ingredient_id}", [
            'name' => 'Admin Updated Ingredient'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_update_response),
            'Admin PUT request should return 200');

        $updated_data = $this->getResponseData($admin_update_response);
        $this->assertEquals('Admin Updated Ingredient', $updated_data['name'],
            'Ingredient name should be updated by admin');

        // Step 5: Test admin can perform all CRUD operations
        $admin_get_all_response = $this->make_rest_request('GET', '');
        $this->assertEquals(200, $this->getResponseStatus($admin_get_all_response),
            'Admin GET all ingredients should return 200');

        $admin_create_response = $this->make_rest_request('POST', '', [
            'name' => 'Second Admin Ingredient',
            'price' => 4.75,
            'is_active' => true,
            'type' => 'spice'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_create_response),
            'Admin POST request should return 200');

        $second_ingredient_data = $this->getResponseData($admin_create_response);
        $this->test_ingredient_ids[] = $second_ingredient_data['id'];

        // Step 6: Test admin can delete ingredients
        $admin_delete_response = $this->make_rest_request('DELETE', "/{$ingredient_id}");
        $this->assertEquals(200, $this->getResponseStatus($admin_delete_response),
            'Admin DELETE request should return 200');

        // Remove from cleanup since it's deleted
        $this->test_ingredient_ids = array_filter(
            $this->test_ingredient_ids,
            fn($id) => $id !== $ingredient_id
        );

        echo "\n✓ Ingredient security and permissions workflow completed successfully\n";
    }

    public function testIngredientAvailabilitySecurityWorkflow(): void
    {
        // Scenario: Test security for ingredient availability endpoints (if they exist)

        wp_set_current_user($this->admin_user_id);

        // Create test ingredient
        $ingredient_response = $this->make_rest_request('POST', '', [
            'name' => 'Availability Test Ingredient',
            'price' => 1.99,
            'is_active' => true
        ]);

        $this->assertEquals(200, $this->getResponseStatus($ingredient_response));
        $ingredient_data = $this->getResponseData($ingredient_response);
        $ingredient_id = $ingredient_data['id'];
        $this->test_ingredient_ids[] = $ingredient_id;

        // Test unauthenticated access to ingredient availability (if endpoint exists)
        wp_set_current_user(0);

        // Note: This may return 404 if availability endpoint doesn't exist, which is fine
        $unauth_availability_response = $this->make_rest_request('GET', "/{$ingredient_id}/availability", [], false);
        $status = $this->getResponseStatus($unauth_availability_response);

        // Should be either 401 (unauthorized) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [401, 404]),
            'Unauthenticated availability request should return 401 or 404, got: ' . $status);

        // Test subscriber access
        wp_set_current_user($this->regular_user_id);

        $subscriber_availability_response = $this->make_rest_request('GET', "/{$ingredient_id}/availability", [], false);
        $status = $this->getResponseStatus($subscriber_availability_response);

        // Should be either 403 (forbidden) or 404 (endpoint doesn't exist)
        $this->assertTrue(in_array($status, [403, 404]),
            'Subscriber availability request should return 403 or 404, got: ' . $status);

        echo "\n✓ Ingredient availability security workflow completed successfully\n";
    }

    public function testIngredientDataIntegrityAndValidation(): void
    {
        // Scenario: Test that security doesn't interfere with data validation and business logic

        wp_set_current_user($this->admin_user_id);

        // Test valid ingredient creation
        $valid_ingredient_response = $this->make_rest_request('POST', '', [
            'name' => 'Valid Ingredient',
            'price' => 3.49,
            'is_active' => true,
            'type' => 'herb'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($valid_ingredient_response));
        $ingredient_data = $this->getResponseData($valid_ingredient_response);
        $this->test_ingredient_ids[] = $ingredient_data['id'];

        // Test invalid ingredient creation (should fail validation, not permissions)
        $invalid_ingredient_response = $this->make_rest_request('POST', '', [
            'name' => '', // Empty name should fail validation
            'price' => -2.00, // Negative price should fail validation
        ]);

        // Should return 400 (validation error) not 401/403 (auth error)
        $this->assertEquals(400, $this->getResponseStatus($invalid_ingredient_response),
            'Invalid ingredient data should return 400 validation error, not auth error');

        echo "\n✓ Ingredient data integrity and validation workflow completed successfully\n";
    }

    public function testNonExistentIngredientSecurity(): void
    {
        // Scenario: Test security responses for non-existent ingredients

        // Test unauthenticated access to non-existent ingredient
        wp_set_current_user(0);
        $unauth_nonexistent = $this->make_rest_request('GET', '/99999', [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_nonexistent),
            'Unauthenticated request for non-existent ingredient should return 401, not 404');

        // Test insufficient permissions for non-existent ingredient
        wp_set_current_user($this->regular_user_id);
        $subscriber_nonexistent = $this->make_rest_request('GET', '/99999', [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_nonexistent),
            'Subscriber request for non-existent ingredient should return 403, not 404');

        // Test admin access to non-existent ingredient (should return 404)
        wp_set_current_user($this->admin_user_id);
        $admin_nonexistent = $this->make_rest_request('GET', '/99999');
        $this->assertEquals(404, $this->getResponseStatus($admin_nonexistent),
            'Admin request for non-existent ingredient should return 404');

        echo "\n✓ Non-existent ingredient security workflow completed successfully\n";
    }
}