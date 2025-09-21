<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientGroupRestController;
use ProductGroupRepository;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for IngredientGroupRestController.
 * Tests the REST API endpoints for ingredient group management.
 *
 * @covers IngredientGroupRestController
 */
class IngredientGroupRestControllerIntegrationTest extends WP_UnitTestCase
{
    private IngredientGroupRestController $controller;
    private ProductGroupRepository $repository;
    private int $admin_user_id;
    private int $regular_user_id;

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new IngredientGroupRestController();
        $this->repository = new ProductGroupRepository();

        // Create test users
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        $this->regular_user_id = $this->factory->user->create([
            'role' => 'subscriber'
        ]);
    }

    public function tear_down(): void
    {
        parent::tear_down();
    }

    private function createAuthenticatedRequest(string $method = 'GET', array $params = []): WP_REST_Request
    {
        wp_set_current_user($this->admin_user_id);

        $request = new WP_REST_Request($method);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return $request;
    }

    private function createUnauthenticatedRequest(string $method = 'GET', array $params = []): WP_REST_Request
    {
        wp_set_current_user($this->regular_user_id);

        $request = new WP_REST_Request($method);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return $request;
    }

    /* =====================================================================
     *  PERMISSION TESTS
     * ===================================================================*/

    public function test_get_items_requires_admin_permission(): void
    {
        $request = $this->createUnauthenticatedRequest();
        $can_access = $this->controller->get_items_permissions_check($request);

        $this->assertFalse($can_access);
    }

    public function test_create_item_requires_admin_permission(): void
    {
        $request = $this->createUnauthenticatedRequest();
        $can_access = $this->controller->create_item_permissions_check($request);

        $this->assertFalse($can_access);
    }

    public function test_admin_has_all_permissions(): void
    {
        $request = $this->createAuthenticatedRequest();

        $this->assertTrue($this->controller->get_items_permissions_check($request));
        $this->assertTrue($this->controller->create_item_permissions_check($request));
        $this->assertTrue($this->controller->update_item_permissions_check($request));
        $this->assertTrue($this->controller->delete_item_permissions_check($request));
    }

    /* =====================================================================
     *  CREATE INGREDIENT GROUP TESTS
     * ===================================================================*/

    public function test_create_ingredient_group_success(): void
    {
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Test Toppings Group',
            'description' => 'A group for various toppings',
            'type' => 'ingredient'
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Test Toppings Group', $data['name']);
        $this->assertEquals('A group for various toppings', $data['description']);
        $this->assertEquals('ingredient', $data['type']);
        $this->assertIsArray($data['group_item_ids']);
    }

    public function test_create_ingredient_group_validation_failures(): void
    {
        // Test missing name
        $request = $this->createAuthenticatedRequest('POST', [
            'description' => 'A group without name'
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        // Test empty name
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => '',
            'description' => 'A group with empty name'
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());
    }

    /* =====================================================================
     *  GET INGREDIENT GROUPS TESTS
     * ===================================================================*/

    public function test_get_ingredient_groups_empty_list(): void
    {
        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($data['data']);
        $this->assertEmpty($data['data']);
    }

    public function test_get_ingredient_groups_with_data(): void
    {
        // Create test ingredient groups
        $group1_id = $this->repository->create([
            'name' => 'Toppings Group',
            'description' => 'Various toppings',
            'type' => 'ingredient'
        ]);
        $group2_id = $this->repository->create([
            'name' => 'Sauces Group',
            'description' => 'Various sauces',
            'type' => 'ingredient'
        ]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        $names = array_column($data['data'], 'name');
        $this->assertContains('Toppings Group', $names);
        $this->assertContains('Sauces Group', $names);
    }

    public function test_get_ingredient_groups_with_search_filter(): void
    {
        // Create test ingredient groups
        $this->repository->create([
            'name' => 'Toppings Group',
            'description' => 'Various toppings',
            'type' => 'ingredient'
        ]);
        $this->repository->create([
            'name' => 'Sauces Group',
            'description' => 'Various sauces',
            'type' => 'ingredient'
        ]);
        $this->repository->create([
            'name' => 'Special Toppings',
            'description' => 'Premium toppings',
            'type' => 'ingredient'
        ]);

        $request = $this->createAuthenticatedRequest('GET', [
            'search' => 'Toppings'
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        foreach ($data['data'] as $group) {
            $this->assertStringContainsString('Toppings', $group['name']);
        }
    }

    /* =====================================================================
     *  GET SINGLE INGREDIENT GROUP TESTS
     * ===================================================================*/

    public function test_get_single_ingredient_group_success(): void
    {
        $group_id = $this->repository->create([
            'name' => 'Single Group',
            'description' => 'Test description',
            'type' => 'ingredient'
        ]);

        $request = $this->createAuthenticatedRequest('GET', ['id' => $group_id]);
        $response = $this->controller->get_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Single Group', $data['name']);
        $this->assertEquals('Test description', $data['description']);
        $this->assertEquals('ingredient', $data['type']);
    }

    public function test_get_single_ingredient_group_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('GET', ['id' => 99999]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  UPDATE INGREDIENT GROUP TESTS
     * ===================================================================*/

    public function test_update_ingredient_group_success(): void
    {
        $group_id = $this->repository->create([
            'name' => 'Original Name',
            'description' => 'Original description',
            'type' => 'ingredient'
        ]);

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $group_id,
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals('Updated description', $data['description']);
    }

    public function test_update_ingredient_group_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => 99999,
            'name' => 'Updated Name'
        ]);

        $response = $this->controller->update_item($request);
        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  DELETE INGREDIENT GROUP TESTS
     * ===================================================================*/

    public function test_delete_ingredient_group_success(): void
    {
        $group_id = $this->repository->create([
            'name' => 'To Be Deleted',
            'description' => 'This will be deleted',
            'type' => 'ingredient'
        ]);

        $request = $this->createAuthenticatedRequest('DELETE', ['id' => $group_id]);
        $response = $this->controller->delete_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);

        // Verify group is actually deleted
        $deleted_group = $this->repository->get($group_id);
        $this->assertNull($deleted_group);
    }

    public function test_delete_ingredient_group_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('DELETE', ['id' => 99999]);
        $response = $this->controller->delete_item($request);

        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  SCHEMA AND VALIDATION TESTS
     * ===================================================================*/

    public function test_collection_params_structure(): void
    {
        $params = $this->controller->get_collection_params();

        $this->assertArrayHasKey('search', $params);
        $this->assertArrayHasKey('type', $params);
        $this->assertArrayHasKey('per_page', $params);
    }

    public function test_item_schema_structure(): void
    {
        $args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);

        $this->assertArrayHasKey('name', $args);
        $this->assertArrayHasKey('description', $args);
        $this->assertArrayHasKey('type', $args);
        $this->assertArrayHasKey('group_item_ids', $args);

        // Check required fields for creation
        $this->assertTrue($args['name']['required']);
        $this->assertFalse($args['description']['required']);
    }

    /* =====================================================================
     *  ERROR HANDLING TESTS
     * ===================================================================*/

    public function test_handles_repository_exceptions(): void
    {
        // Test creating group with invalid data that causes repository exception
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => '', // Empty name should trigger repository validation
            'type' => 'ingredient'
        ]);

        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Validation failed', $data['error']);
    }

    /* =====================================================================
     *  PREPARE RESPONSE TESTS
     * ===================================================================*/

    public function test_prepare_item_for_response_structure(): void
    {
        $group_id = $this->repository->create([
            'name' => 'Test Group',
            'description' => 'Test description',
            'type' => 'ingredient'
        ]);

        $group = $this->repository->get($group_id);
        $request = new WP_REST_Request();

        $response = $this->controller->prepare_item_for_response($group, $request);
        $data = $response->get_data();

        // Check all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('group_item_ids', $data);

        // Check data types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertEquals('ingredient', $data['type']);
        $this->assertIsArray($data['group_item_ids']);
    }
}