<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use StoreBranchRestController;
use StoreBranchRepository;
use ProductRepository;
use IngredientRepository;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for StoreBranchRestController.
 * Tests the REST API endpoints for store branch management.
 *
 * @covers StoreBranchRestController
 */
class StoreBranchRestControllerIntegrationTest extends WP_UnitTestCase
{
    private StoreBranchRestController $controller;
    private StoreBranchRepository $repository;
    private ProductRepository $productRepository;
    private IngredientRepository $ingredientRepository;
    private int $admin_user_id;
    private int $regular_user_id;
    private array $test_branch_ids = [];
    private array $test_product_ids = [];
    private array $test_ingredient_ids = [];

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new StoreBranchRestController();
        $this->repository = new StoreBranchRepository();
        $this->productRepository = new ProductRepository();
        $this->ingredientRepository = new IngredientRepository();

        // Create test users
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        $this->regular_user_id = $this->factory->user->create([
            'role' => 'subscriber'
        ]);

        // Create test data
        $this->createTestProducts();
        $this->createTestIngredients();
    }

    public function tear_down(): void
    {
        // Clean up test data
        foreach ($this->test_branch_ids as $id) {
            $this->repository->delete($id, true);
        }
        foreach ($this->test_product_ids as $id) {
            $this->productRepository->delete($id);
        }
        foreach ($this->test_ingredient_ids as $id) {
            $this->ingredientRepository->delete($id);
        }

        parent::tear_down();
    }

    private function createTestProducts(): void
    {
        $this->test_product_ids[] = $this->productRepository->create([
            'name' => 'Test Burger',
            'price' => 25.99,
            'description' => 'A test burger'
        ]);

        $this->test_product_ids[] = $this->productRepository->create([
            'name' => 'Test Pizza',
            'price' => 30.50,
            'description' => 'A test pizza'
        ]);
    }

    private function createTestIngredients(): void
    {
        $this->test_ingredient_ids[] = $this->ingredientRepository->create([
            'name' => 'Test Lettuce',
            'price' => 2.50
        ]);

        $this->test_ingredient_ids[] = $this->ingredientRepository->create([
            'name' => 'Test Tomato',
            'price' => 3.00
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

    private function createTestBranch(array $override_data = []): int
    {
        $default_data = [
            'name' => 'Test Branch',
            'phone' => '555-0123',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['09:00-17:00'],
                'MONDAY' => ['09:00-17:00']
            ],
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => ['wheelchair_accessible']
        ];

        $data = array_merge($default_data, $override_data);
        $branch_id = $this->repository->create($data);
        $this->test_branch_ids[] = $branch_id;

        return $branch_id;
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
     *  CREATE BRANCH TESTS
     * ===================================================================*/

    public function test_create_branch_success(): void
    {
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'New Test Branch',
            'phone' => '555-9999',
            'city' => 'New City',
            'address' => '999 New Street',
            'is_open' => true,
            'kosher_type' => 'Kosher Meat'
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('New Test Branch', $data['name']);
        $this->assertEquals('555-9999', $data['phone']);
        $this->assertEquals('New City', $data['city']);
        $this->assertEquals('999 New Street', $data['address']);
        $this->assertTrue($data['is_open']);
        $this->assertEquals('Kosher Meat', $data['kosher_type']);
        $this->assertIsArray($data['activity_times']);
        $this->assertIsArray($data['accessibility_list']);
        $this->assertIsArray($data['products']);
        $this->assertIsArray($data['ingredients']);

        // Store for cleanup
        $this->test_branch_ids[] = $data['id'];
    }

    public function test_create_branch_with_activity_times(): void
    {
        $activity_times = [
            'SUNDAY' => ['08:00-14:00', '18:00-23:00'],
            'MONDAY' => ['09:00-21:00'],
            'TUESDAY' => ['09:00-21:00']
        ];

        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Branch with Hours',
            'phone' => '555-8888',
            'city' => 'Test City',
            'address' => '888 Test Avenue',
            'activity_times' => $activity_times
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($activity_times, $data['activity_times']);

        $this->test_branch_ids[] = $data['id'];
    }

    public function test_create_branch_with_accessibility_features(): void
    {
        $accessibility_features = ['wheelchair_accessible', 'braille_menu', 'hearing_loop'];

        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Accessible Branch',
            'phone' => '555-7777',
            'city' => 'Accessible City',
            'address' => '777 Accessible Street',
            'accessibility_list' => $accessibility_features
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($accessibility_features, $data['accessibility_list']);

        $this->test_branch_ids[] = $data['id'];
    }

    public function test_create_branch_validation_failures(): void
    {
        // Test missing name
        $request = $this->createAuthenticatedRequest('POST', [
            'phone' => '555-0000'
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        // Test invalid activity times (invalid day)
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Test Branch',
            'activity_times' => [
                'INVALIDDAY' => ['09:00-17:00']
            ]
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());
    }

    /* =====================================================================
     *  GET BRANCHES TESTS
     * ===================================================================*/

    public function test_get_branches_empty_list(): void
    {
        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($data);
        // Should contain "All Branches" option
        $this->assertCount(1, $data);
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals('כל הסניפים', $data[0]['name']);
    }

    public function test_get_branches_with_data(): void
    {
        // Create test branches
        $branch1_id = $this->createTestBranch(['name' => 'Branch 1', 'city' => 'City 1']);
        $branch2_id = $this->createTestBranch(['name' => 'Branch 2', 'city' => 'City 2']);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(3, $data); // 2 branches + "All Branches" option

        $branch_names = array_column($data, 'name');
        $this->assertContains('Branch 1', $branch_names);
        $this->assertContains('Branch 2', $branch_names);
        $this->assertContains('כל הסניפים', $branch_names);
    }

    public function test_get_branches_with_city_filter(): void
    {
        $this->createTestBranch(['name' => 'Tel Aviv Branch', 'city' => 'Tel Aviv']);
        $this->createTestBranch(['name' => 'Jerusalem Branch', 'city' => 'Jerusalem']);
        $this->createTestBranch(['name' => 'Haifa Branch', 'city' => 'Haifa']);

        $request = $this->createAuthenticatedRequest('GET', [
            'city' => 'Tel Aviv'
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(1, $data); // No "All Branches" when filtered
        $this->assertEquals('Tel Aviv Branch', $data[0]['name']);
        $this->assertEquals('Tel Aviv', $data[0]['city']);
    }

    public function test_get_branches_with_is_open_filter(): void
    {
        $this->createTestBranch(['name' => 'Open Branch', 'is_open' => true]);
        $this->createTestBranch(['name' => 'Closed Branch', 'is_open' => false]);

        $request = $this->createAuthenticatedRequest('GET', [
            'is_open' => true
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(1, $data);
        $this->assertEquals('Open Branch', $data[0]['name']);
        $this->assertTrue($data[0]['is_open']);
    }

    public function test_get_branches_with_kosher_type_filter(): void
    {
        $this->createTestBranch(['name' => 'Dairy Branch', 'kosher_type' => 'Kosher Dairy']);
        $this->createTestBranch(['name' => 'Meat Branch', 'kosher_type' => 'Kosher Meat']);
        $this->createTestBranch(['name' => 'Mehadrin Branch', 'kosher_type' => 'Kosher Mehadrin']);

        $request = $this->createAuthenticatedRequest('GET', [
            'kosher_type' => 'Kosher Meat'
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(1, $data);
        $this->assertEquals('Meat Branch', $data[0]['name']);
        $this->assertEquals('Kosher Meat', $data[0]['kosher_type']);
    }

    public function test_get_branches_with_search_filter(): void
    {
        $this->createTestBranch(['name' => 'Main Downtown Branch']);
        $this->createTestBranch(['name' => 'Airport Location']);
        $this->createTestBranch(['name' => 'Main Mall Branch']);

        $request = $this->createAuthenticatedRequest('GET', [
            'search' => 'Main'
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        // Should include "All Branches" option when only search filter is used
        $this->assertCount(3, $data); // 2 matching branches + "All Branches"

        $found_main_branches = 0;
        foreach ($data as $branch) {
            if (str_contains($branch['name'], 'Main')) {
                $found_main_branches++;
            }
        }
        $this->assertEquals(2, $found_main_branches);
    }

    /* =====================================================================
     *  GET SINGLE BRANCH TESTS
     * ===================================================================*/

    public function test_get_single_branch_success(): void
    {
        $branch_id = $this->createTestBranch([
            'name' => 'Single Branch Test',
            'phone' => '555-1111',
            'city' => 'Single City',
            'address' => '111 Single Street',
            'kosher_type' => 'Kosher Dairy'
        ]);

        $request = $this->createAuthenticatedRequest('GET', ['id' => $branch_id]);
        $response = $this->controller->get_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Single Branch Test', $data['name']);
        $this->assertEquals('555-1111', $data['phone']);
        $this->assertEquals('Single City', $data['city']);
        $this->assertEquals('111 Single Street', $data['address']);
        $this->assertEquals('Kosher Dairy', $data['kosher_type']);
        $this->assertIsArray($data['products']);
        $this->assertIsArray($data['ingredients']);
        $this->assertIsArray($data['product_availability']);
        $this->assertIsArray($data['ingredient_availability']);
    }

    public function test_get_single_branch_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('GET', ['id' => 99999]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Branch not found', $data['error']);
    }

    /* =====================================================================
     *  UPDATE BRANCH TESTS
     * ===================================================================*/

    public function test_update_branch_success(): void
    {
        $branch_id = $this->createTestBranch([
            'name' => 'Original Name',
            'phone' => '555-0000'
        ]);

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $branch_id,
            'name' => 'Updated Name',
            'phone' => '555-9999'
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals('555-9999', $data['phone']);
    }

    public function test_update_branch_activity_times(): void
    {
        $branch_id = $this->createTestBranch();

        $new_activity_times = [
            'SUNDAY' => ['10:00-15:00'],
            'MONDAY' => ['08:00-20:00'],
            'FRIDAY' => ['08:00-14:00']
        ];

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $branch_id,
            'activity_times' => $new_activity_times
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($new_activity_times, $data['activity_times']);
    }

    public function test_update_branch_accessibility_list(): void
    {
        $branch_id = $this->createTestBranch();

        $new_accessibility = ['wheelchair_accessible', 'braille_menu', 'elevator'];

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $branch_id,
            'accessibility_list' => $new_accessibility
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($new_accessibility, $data['accessibility_list']);
    }

    public function test_update_branch_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => 99999,
            'name' => 'Updated Name'
        ]);

        $response = $this->controller->update_item($request);
        $this->assertEquals(404, $response->get_status());
    }

    public function test_update_branch_invalid_activity_times(): void
    {
        $branch_id = $this->createTestBranch();

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $branch_id,
            'activity_times' => [
                'INVALIDDAY' => ['09:00-17:00']
            ]
        ]);

        $response = $this->controller->update_item($request);
        $this->assertEquals(400, $response->get_status());
    }

    /* =====================================================================
     *  DELETE BRANCH TESTS
     * ===================================================================*/

    public function test_delete_branch_success(): void
    {
        $branch_id = $this->createTestBranch(['name' => 'To Be Deleted']);

        $request = $this->createAuthenticatedRequest('DELETE', ['id' => $branch_id]);
        $response = $this->controller->delete_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertEquals('Store branch deleted successfully', $data['message']);

        // Verify branch is actually deleted
        $deleted_branch = $this->repository->get($branch_id);
        $this->assertNull($deleted_branch);

        // Remove from cleanup array since it's already deleted
        $this->test_branch_ids = array_filter(
            $this->test_branch_ids,
            fn($id) => $id !== $branch_id
        );
    }

    public function test_delete_branch_with_force(): void
    {
        $branch_id = $this->createTestBranch(['name' => 'Force Delete']);

        $request = $this->createAuthenticatedRequest('DELETE', [
            'id' => $branch_id,
            'force' => true
        ]);

        $response = $this->controller->delete_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);

        // Remove from cleanup array
        $this->test_branch_ids = array_filter(
            $this->test_branch_ids,
            fn($id) => $id !== $branch_id
        );
    }

    public function test_delete_branch_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('DELETE', ['id' => 99999]);
        $response = $this->controller->delete_item($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Store branch not found', $data['error']);
    }

    /* =====================================================================
     *  AVAILABILITY ENDPOINT TESTS
     * ===================================================================*/

    public function test_get_availability_success(): void
    {
        $branch_id = $this->createTestBranch();

        // Set some availability data
        $product_availability = [
            $this->test_product_ids[0] => true,
            $this->test_product_ids[1] => false
        ];
        $ingredient_availability = [
            $this->test_ingredient_ids[0] => true,
            $this->test_ingredient_ids[1] => false
        ];

        $this->repository->update($branch_id, [
            'product_availability' => $product_availability,
            'ingredient_availability' => $ingredient_availability
        ]);

        $request = $this->createAuthenticatedRequest('GET', ['id' => $branch_id]);
        $response = $this->controller->get_availability($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($branch_id, $data['branch_id']);
        $this->assertEquals($product_availability, $data['product_availability']);
        $this->assertEquals($ingredient_availability, $data['ingredient_availability']);
    }

    public function test_update_availability_success(): void
    {
        $branch_id = $this->createTestBranch();

        $new_product_availability = [
            $this->test_product_ids[0] => false,
            $this->test_product_ids[1] => true
        ];
        $new_ingredient_availability = [
            $this->test_ingredient_ids[0] => false,
            $this->test_ingredient_ids[1] => true
        ];

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $branch_id,
            'product_availability' => $new_product_availability,
            'ingredient_availability' => $new_ingredient_availability
        ]);

        $response = $this->controller->update_availability($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($branch_id, $data['branch_id']);
        $this->assertEquals($new_product_availability, $data['product_availability']);
        $this->assertEquals($new_ingredient_availability, $data['ingredient_availability']);
    }

    public function test_get_availability_branch_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('GET', ['id' => 99999]);
        $response = $this->controller->get_availability($request);

        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  PRODUCT MANAGEMENT ENDPOINT TESTS
     * ===================================================================*/

    public function test_add_product_to_branch_success(): void
    {
        $branch_id = $this->createTestBranch();
        $product_id = $this->test_product_ids[0];

        $request = $this->createAuthenticatedRequest('POST', [
            'id' => $branch_id,
            'product_id' => $product_id,
            'is_active' => true
        ]);

        $response = $this->controller->add_product($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertEquals('Product added to branch successfully', $data['message']);
        $this->assertEquals($branch_id, $data['branch_id']);
        $this->assertEquals($product_id, $data['product_id']);
        $this->assertTrue($data['is_active']);
    }

    public function test_add_product_to_branch_inactive(): void
    {
        $branch_id = $this->createTestBranch();
        $product_id = $this->test_product_ids[0];

        $request = $this->createAuthenticatedRequest('POST', [
            'id' => $branch_id,
            'product_id' => $product_id,
            'is_active' => false
        ]);

        $response = $this->controller->add_product($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertFalse($data['is_active']);
    }

    public function test_remove_product_from_branch_success(): void
    {
        $branch_id = $this->createTestBranch();
        $product_id = $this->test_product_ids[0];

        // First add the product
        $this->repository->addProduct($branch_id, $product_id);

        // Then remove it
        $request = $this->createAuthenticatedRequest('DELETE', [
            'id' => $branch_id,
            'product_id' => $product_id
        ]);

        $response = $this->controller->remove_product($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertEquals('Product removed from branch successfully', $data['message']);
        $this->assertEquals($branch_id, $data['branch_id']);
        $this->assertEquals($product_id, $data['product_id']);
    }

    /* =====================================================================
     *  INGREDIENT MANAGEMENT ENDPOINT TESTS
     * ===================================================================*/

    public function test_add_ingredient_to_branch_success(): void
    {
        $branch_id = $this->createTestBranch();
        $ingredient_id = $this->test_ingredient_ids[0];

        $request = $this->createAuthenticatedRequest('POST', [
            'id' => $branch_id,
            'ingredient_id' => $ingredient_id,
            'is_active' => true
        ]);

        $response = $this->controller->add_ingredient($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertEquals('Ingredient added to branch successfully', $data['message']);
        $this->assertEquals($branch_id, $data['branch_id']);
        $this->assertEquals($ingredient_id, $data['ingredient_id']);
        $this->assertTrue($data['is_active']);
    }

    public function test_remove_ingredient_from_branch_success(): void
    {
        $branch_id = $this->createTestBranch();
        $ingredient_id = $this->test_ingredient_ids[0];

        // First add the ingredient
        $this->repository->addIngredient($branch_id, $ingredient_id);

        // Then remove it
        $request = $this->createAuthenticatedRequest('DELETE', [
            'id' => $branch_id,
            'ingredient_id' => $ingredient_id
        ]);

        $response = $this->controller->remove_ingredient($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertEquals('Ingredient removed from branch successfully', $data['message']);
        $this->assertEquals($branch_id, $data['branch_id']);
        $this->assertEquals($ingredient_id, $data['ingredient_id']);
    }

    /* =====================================================================
     *  SCHEMA AND VALIDATION TESTS
     * ===================================================================*/

    public function test_collection_params_structure(): void
    {
        $params = $this->controller->get_collection_params();

        $this->assertArrayHasKey('city', $params);
        $this->assertArrayHasKey('city_like', $params);
        $this->assertArrayHasKey('is_open', $params);
        $this->assertArrayHasKey('kosher_type', $params);
        $this->assertArrayHasKey('has_accessibility', $params);
        $this->assertArrayHasKey('has_product', $params);
        $this->assertArrayHasKey('has_ingredient', $params);
        $this->assertArrayHasKey('search', $params);

        // Test enum values for kosher_type
        $this->assertEquals(
            ['Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'],
            $params['kosher_type']['enum']
        );

        // Test enum values for accessibility
        $this->assertEquals(
            ['wheelchair_accessible', 'braille_menu', 'hearing_loop', 'elevator'],
            $params['has_accessibility']['enum']
        );
    }

    public function test_item_schema_structure(): void
    {
        $create_args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);
        $update_args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE);
        $delete_args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::DELETABLE);

        // Test create schema
        $this->assertArrayHasKey('name', $create_args);
        $this->assertArrayHasKey('phone', $create_args);
        $this->assertArrayHasKey('city', $create_args);
        $this->assertArrayHasKey('address', $create_args);
        $this->assertArrayHasKey('is_open', $create_args);
        $this->assertArrayHasKey('activity_times', $create_args);
        $this->assertArrayHasKey('kosher_type', $create_args);
        $this->assertArrayHasKey('accessibility_list', $create_args);
        $this->assertArrayHasKey('products', $create_args);
        $this->assertArrayHasKey('ingredients', $create_args);
        $this->assertArrayHasKey('product_availability', $create_args);
        $this->assertArrayHasKey('ingredient_availability', $create_args);

        // Test required fields for creation
        $this->assertTrue($create_args['name']['required']);
        $this->assertFalse($create_args['phone']['required']);
        $this->assertFalse($create_args['city']['required']);

        // Test update schema (name should not be required)
        $this->assertFalse($update_args['name']['required']);

        // Test delete schema
        $this->assertArrayHasKey('force', $delete_args);
        $this->assertFalse($delete_args['force']['default']);
    }

    /* =====================================================================
     *  PREPARE RESPONSE TESTS
     * ===================================================================*/

    public function test_prepare_item_for_response_structure(): void
    {
        $branch_id = $this->createTestBranch([
            'name' => 'Response Test Branch',
            'phone' => '555-2222',
            'city' => 'Response City',
            'address' => '222 Response Street',
            'is_open' => true,
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => ['wheelchair_accessible', 'braille_menu']
        ]);

        $branch = $this->repository->get($branch_id);
        $request = new WP_REST_Request();

        $response = $this->controller->prepare_item_for_response($branch, $request);
        $data = $response->get_data();

        // Check all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('city', $data);
        $this->assertArrayHasKey('address', $data);
        $this->assertArrayHasKey('is_open', $data);
        $this->assertArrayHasKey('activity_times', $data);
        $this->assertArrayHasKey('kosher_type', $data);
        $this->assertArrayHasKey('accessibility_list', $data);
        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('ingredients', $data);
        $this->assertArrayHasKey('product_availability', $data);
        $this->assertArrayHasKey('ingredient_availability', $data);

        // Check data types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsString($data['phone']);
        $this->assertIsString($data['city']);
        $this->assertIsString($data['address']);
        $this->assertIsBool($data['is_open']);
        $this->assertIsArray($data['activity_times']);
        $this->assertIsString($data['kosher_type']);
        $this->assertIsArray($data['accessibility_list']);
        $this->assertIsArray($data['products']);
        $this->assertIsArray($data['ingredients']);
        $this->assertIsArray($data['product_availability']);
        $this->assertIsArray($data['ingredient_availability']);

        // Check specific values
        $this->assertEquals('Response Test Branch', $data['name']);
        $this->assertEquals('555-2222', $data['phone']);
        $this->assertEquals('Response City', $data['city']);
        $this->assertEquals('222 Response Street', $data['address']);
        $this->assertTrue($data['is_open']);
        $this->assertEquals('Kosher Dairy', $data['kosher_type']);
        $this->assertEquals(['wheelchair_accessible', 'braille_menu'], $data['accessibility_list']);
    }

    public function test_prepare_item_for_response_with_products_and_ingredients(): void
    {
        $branch_id = $this->createTestBranch();

        // Add products and ingredients to branch
        $this->repository->addProduct($branch_id, $this->test_product_ids[0], true);
        $this->repository->addProduct($branch_id, $this->test_product_ids[1], false);
        $this->repository->addIngredient($branch_id, $this->test_ingredient_ids[0], true);
        $this->repository->addIngredient($branch_id, $this->test_ingredient_ids[1], false);

        $branch = $this->repository->get($branch_id);
        $request = new WP_REST_Request();

        $response = $this->controller->prepare_item_for_response($branch, $request);
        $data = $response->get_data();

        // Check products array structure
        $this->assertIsArray($data['products']);
        foreach ($data['products'] as $product) {
            $this->assertArrayHasKey('id', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('price', $product);
            $this->assertArrayHasKey('available', $product);
            $this->assertIsInt($product['id']);
            $this->assertIsString($product['name']);
            $this->assertIsFloat($product['price']);
            $this->assertIsBool($product['available']);
        }

        // Check ingredients array structure
        $this->assertIsArray($data['ingredients']);
        foreach ($data['ingredients'] as $ingredient) {
            $this->assertArrayHasKey('id', $ingredient);
            $this->assertArrayHasKey('name', $ingredient);
            $this->assertArrayHasKey('price', $ingredient);
            $this->assertArrayHasKey('available', $ingredient);
            $this->assertIsInt($ingredient['id']);
            $this->assertIsString($ingredient['name']);
            $this->assertIsFloat($ingredient['price']);
            $this->assertIsBool($ingredient['available']);
        }

        // Check availability mappings
        $this->assertArrayHasKey($this->test_product_ids[0], $data['product_availability']);
        $this->assertArrayHasKey($this->test_product_ids[1], $data['product_availability']);
        $this->assertTrue($data['product_availability'][$this->test_product_ids[0]]);
        $this->assertFalse($data['product_availability'][$this->test_product_ids[1]]);

        $this->assertArrayHasKey($this->test_ingredient_ids[0], $data['ingredient_availability']);
        $this->assertArrayHasKey($this->test_ingredient_ids[1], $data['ingredient_availability']);
        $this->assertTrue($data['ingredient_availability'][$this->test_ingredient_ids[0]]);
        $this->assertFalse($data['ingredient_availability'][$this->test_ingredient_ids[1]]);
    }

    /* =====================================================================
     *  ERROR HANDLING TESTS
     * ===================================================================*/

    public function test_handles_repository_exceptions(): void
    {
        // Test creating branch with empty name (should trigger repository validation)
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => '',
            'phone' => '555-0000'
        ]);

        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function test_error_response_consistency(): void
    {
        // Test various error conditions return consistent structure
        $error_requests = [
            // Create with missing required data
            $this->createAuthenticatedRequest('POST', []),
            // Update non-existent branch
            $this->createAuthenticatedRequest('PUT', ['id' => 99999, 'name' => 'Test']),
            // Delete non-existent branch
            $this->createAuthenticatedRequest('DELETE', ['id' => 99999]),
            // Get non-existent branch
            $this->createAuthenticatedRequest('GET', ['id' => 99999])
        ];

        foreach ($error_requests as $request) {
            $response = match ($request->get_method()) {
                'POST' => $this->controller->create_item($request),
                'PUT' => $this->controller->update_item($request),
                'DELETE' => $this->controller->delete_item($request),
                'GET' => $this->controller->get_item($request),
                default => null
            };

            if ($response) {
                $this->assertGreaterThanOrEqual(400, $response->get_status());
                $data = $response->get_data();
                $this->assertArrayHasKey('error', $data);
                $this->assertIsString($data['error']);
            }
        }
    }
}