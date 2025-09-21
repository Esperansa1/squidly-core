<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientRestController;
use IngredientRepository;
use StoreBranchRepository;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for IngredientRestController.
 * Tests the REST API endpoints for ingredient management.
 *
 * @covers IngredientRestController
 */
class IngredientRestControllerIntegrationTest extends WP_UnitTestCase
{
    private IngredientRestController $controller;
    private IngredientRepository $repository;
    private StoreBranchRepository $branchRepository;
    private int $admin_user_id;
    private int $regular_user_id;

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new IngredientRestController();
        $this->repository = new IngredientRepository();
        $this->branchRepository = new StoreBranchRepository();

        // Create test users
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        $this->regular_user_id = $this->factory->user->create([
            'role' => 'subscriber'
        ]);

        // Create test branches
        $this->createTestBranches();
    }

    public function tear_down(): void
    {
        parent::tear_down();
    }

    private function createTestBranches(): void
    {
        // Create a couple of test branches for availability testing
        $this->branchRepository->create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'city' => 'Test City',
            'phone' => '555-0001',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['09:00', '21:00'],
                'MONDAY' => ['09:00', '21:00']
            ],
            'kosher_type' => 'mehadrin',
            'accessibility_list' => ['wheelchair_accessible']
        ]);

        $this->branchRepository->create([
            'name' => 'Secondary Branch',
            'address' => '456 Oak Ave',
            'city' => 'Test City',
            'phone' => '555-0002',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['10:00', '22:00'],
                'MONDAY' => ['10:00', '22:00']
            ],
            'kosher_type' => 'regular',
            'accessibility_list' => ['wheelchair_accessible', 'hearing_impaired_friendly']
        ]);
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
     *  CREATE INGREDIENT TESTS
     * ===================================================================*/

    public function test_create_ingredient_success(): void
    {
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Fresh Tomatoes',
            'price' => 2.50
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Fresh Tomatoes', $data['name']);
        $this->assertEquals(2.50, $data['price']);
        $this->assertIsArray($data['availability']);
    }

    public function test_create_ingredient_with_availability(): void
    {
        $branches = $this->branchRepository->getAll();
        $availability = [];
        foreach ($branches as $branch) {
            $availability[$branch->id] = true;
        }

        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Available Everywhere',
            'price' => 1.25,
            'availability' => $availability
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Available Everywhere', $data['name']);

        // Check that availability was set correctly
        foreach ($branches as $branch) {
            $this->assertTrue($data['availability'][$branch->id]);
        }
    }

    public function test_create_ingredient_free_price(): void
    {
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Free Salt',
            'price' => 0
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Free Salt', $data['name']);
        $this->assertEquals(0.0, $data['price']);
    }

    public function test_create_ingredient_validation_failures(): void
    {
        // Test missing name
        $request = $this->createAuthenticatedRequest('POST', [
            'price' => 1.00
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        // Test empty name
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => '',
            'price' => 1.00
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        // Test negative price
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Test Ingredient',
            'price' => -1.00
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());
    }

    /* =====================================================================
     *  GET INGREDIENTS TESTS
     * ===================================================================*/

    public function test_get_ingredients_empty_list(): void
    {
        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($data['data']);
        $this->assertEmpty($data['data']);
    }

    public function test_get_ingredients_with_data(): void
    {
        // Create test ingredients
        $ingredient1_id = $this->repository->create([
            'name' => 'Cheese',
            'price' => 3.00
        ]);
        $ingredient2_id = $this->repository->create([
            'name' => 'Lettuce',
            'price' => 1.50
        ]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        $names = array_column($data['data'], 'name');
        $this->assertContains('Cheese', $names);
        $this->assertContains('Lettuce', $names);
    }

    public function test_get_ingredients_with_search_filter(): void
    {
        // Create test ingredients
        $this->repository->create(['name' => 'Mozzarella Cheese', 'price' => 4.00]);
        $this->repository->create(['name' => 'Cheddar Cheese', 'price' => 3.50]);
        $this->repository->create(['name' => 'Fresh Basil', 'price' => 2.00]);

        $request = $this->createAuthenticatedRequest('GET', [
            'search' => 'Cheese'
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        foreach ($data['data'] as $ingredient) {
            $this->assertStringContainsString('Cheese', $ingredient['name']);
        }
    }

    public function test_get_ingredients_with_price_filters(): void
    {
        // Create test ingredients with different prices
        $this->repository->create(['name' => 'Cheap Item', 'price' => 1.00]);
        $this->repository->create(['name' => 'Medium Item', 'price' => 5.00]);
        $this->repository->create(['name' => 'Expensive Item', 'price' => 10.00]);

        // Test price_min filter
        $request = $this->createAuthenticatedRequest('GET', [
            'price_min' => 5.00
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        foreach ($data['data'] as $ingredient) {
            $this->assertGreaterThanOrEqual(5.00, $ingredient['price']);
        }

        // Test price_max filter
        $request = $this->createAuthenticatedRequest('GET', [
            'price_max' => 5.00
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertCount(2, $data['data']);

        foreach ($data['data'] as $ingredient) {
            $this->assertLessThanOrEqual(5.00, $ingredient['price']);
        }
    }

    /* =====================================================================
     *  GET SINGLE INGREDIENT TESTS
     * ===================================================================*/

    public function test_get_single_ingredient_success(): void
    {
        $ingredient_id = $this->repository->create([
            'name' => 'Single Ingredient',
            'price' => 2.75
        ]);

        $request = $this->createAuthenticatedRequest('GET', ['id' => $ingredient_id]);
        $response = $this->controller->get_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Single Ingredient', $data['name']);
        $this->assertEquals(2.75, $data['price']);
    }

    public function test_get_single_ingredient_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('GET', ['id' => 99999]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  UPDATE INGREDIENT TESTS
     * ===================================================================*/

    public function test_update_ingredient_success(): void
    {
        $ingredient_id = $this->repository->create([
            'name' => 'Original Name',
            'price' => 1.00
        ]);

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $ingredient_id,
            'name' => 'Updated Name',
            'price' => 2.00
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals(2.00, $data['price']);
    }

    public function test_update_ingredient_availability(): void
    {
        $ingredient_id = $this->repository->create([
            'name' => 'Test Ingredient',
            'price' => 1.50
        ]);

        $branches = $this->branchRepository->getAll();
        $availability = [];
        foreach ($branches as $i => $branch) {
            // Make first branch available, second unavailable
            $availability[$branch->id] = $i === 0;
        }

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $ingredient_id,
            'availability' => $availability
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        foreach ($branches as $i => $branch) {
            $expected = $i === 0;
            $this->assertEquals($expected, $data['availability'][$branch->id]);
        }
    }

    public function test_update_ingredient_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => 99999,
            'name' => 'Updated Name'
        ]);

        $response = $this->controller->update_item($request);
        $this->assertEquals(404, $response->get_status());
    }

    public function test_update_ingredient_validation_failures(): void
    {
        $ingredient_id = $this->repository->create([
            'name' => 'Test Ingredient',
            'price' => 1.00
        ]);

        // Test empty name
        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $ingredient_id,
            'name' => ''
        ]);
        $response = $this->controller->update_item($request);
        $this->assertEquals(400, $response->get_status());

        // Test negative price
        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $ingredient_id,
            'price' => -5.00
        ]);
        $response = $this->controller->update_item($request);
        $this->assertEquals(400, $response->get_status());
    }

    /* =====================================================================
     *  DELETE INGREDIENT TESTS
     * ===================================================================*/

    public function test_delete_ingredient_success(): void
    {
        $ingredient_id = $this->repository->create([
            'name' => 'To Be Deleted',
            'price' => 1.00
        ]);

        $request = $this->createAuthenticatedRequest('DELETE', ['id' => $ingredient_id]);
        $response = $this->controller->delete_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);

        // Verify ingredient is actually deleted
        $deleted_ingredient = $this->repository->get($ingredient_id);
        $this->assertNull($deleted_ingredient);
    }

    public function test_delete_ingredient_not_found(): void
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

        $this->assertArrayHasKey('branch_id', $params);
        $this->assertArrayHasKey('search', $params);
        $this->assertArrayHasKey('price_min', $params);
        $this->assertArrayHasKey('price_max', $params);

        // Check sanitize callbacks are properly set
        $this->assertEquals('absint', $params['branch_id']['sanitize_callback']);
        $this->assertEquals('sanitize_text_field', $params['search']['sanitize_callback']);
    }

    public function test_item_schema_structure(): void
    {
        $args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);

        $this->assertArrayHasKey('name', $args);
        $this->assertArrayHasKey('price', $args);
        $this->assertArrayHasKey('availability', $args);

        // Check required fields for creation
        $this->assertTrue($args['name']['required']);
        $this->assertFalse($args['price']['required']); // Has default value
        $this->assertFalse($args['availability']['required']);

        // Check default values
        $this->assertEquals(0, $args['price']['default']);
    }

    public function test_availability_validation(): void
    {
        $args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);
        $availability_validator = $args['availability']['validate_callback'];

        // Test valid availability data
        $this->assertTrue($availability_validator(['1' => true, '2' => false]));
        $this->assertTrue($availability_validator(['1' => 1, '2' => 0]));
        $this->assertTrue($availability_validator(['1' => 'true', '2' => 'false']));

        // Test invalid availability data
        $this->assertFalse($availability_validator(['invalid_key' => true]));
        $this->assertFalse($availability_validator(['1' => 'invalid_value']));
        $this->assertFalse($availability_validator('not_an_array'));

        // Test empty data (should be valid)
        $this->assertTrue($availability_validator([]));
        $this->assertTrue($availability_validator(null));
    }

    /* =====================================================================
     *  ERROR HANDLING TESTS
     * ===================================================================*/

    public function test_handles_repository_exceptions(): void
    {
        // Test creating ingredient with invalid data that causes repository exception
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => '', // Empty name should trigger repository validation
            'price' => 1.00
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
        $ingredient_id = $this->repository->create([
            'name' => 'Test Ingredient',
            'price' => 3.25
        ]);

        $ingredient = $this->repository->get($ingredient_id);
        $request = new WP_REST_Request();

        $response = $this->controller->prepare_item_for_response($ingredient, $request);
        $data = $response->get_data();

        // Check all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('availability', $data);

        // Check data types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsFloat($data['price']);
        $this->assertIsArray($data['availability']);
    }

    public function test_prepare_item_includes_branch_availability(): void
    {
        $branches = $this->branchRepository->getAll();
        $this->assertNotEmpty($branches, 'Test branches should exist');

        $ingredient_id = $this->repository->create([
            'name' => 'Test Ingredient',
            'price' => 2.00
        ]);

        // Set availability for specific branches
        $availability = [];
        foreach ($branches as $i => $branch) {
            $availability[$branch->id] = $i === 0; // First branch available, others not
        }
        $this->repository->updateAvailability($ingredient_id, $availability);

        $ingredient = $this->repository->get($ingredient_id);
        $request = new WP_REST_Request();

        $response = $this->controller->prepare_item_for_response($ingredient, $request);
        $data = $response->get_data();

        // Check that availability data matches what we set
        foreach ($branches as $i => $branch) {
            $expected = $i === 0;
            $this->assertEquals($expected, $data['availability'][$branch->id]);
        }
    }
}