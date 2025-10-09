<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use ProductGroupRestController;
use ProductGroupRepository;
use GroupItemRepository;
use ProductRepository;
use IngredientRepository;
use ItemType;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for ProductGroupRestController
 *
 * Tests the REST controller with real WordPress database operations
 * and repository interactions.
 */
class ProductGroupRestControllerIntegrationTest extends \WP_UnitTestCase
{
    private ProductGroupRestController $controller;
    private ProductGroupRepository $repository;
    private GroupItemRepository $groupItemRepo;
    private ProductRepository $productRepo;
    private IngredientRepository $ingredientRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ProductGroupRestController();
        $this->repository = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        $this->productRepo = new ProductRepository();
        $this->ingredientRepo = new IngredientRepository();

        // Initialize WordPress REST server
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init');

        // Register REST routes
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');

        // Set up admin user for permission tests
        wp_set_current_user(1);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $groups = $this->repository->getAll();
        foreach ($groups as $group) {
            try {
                wp_delete_post($group->id, true);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    /* ==========================================
     * Integration Tests - Full CRUD Flow
     * ========================================== */

    public function test_full_product_group_lifecycle(): void
    {
        // Create products for the group
        $product1_id = $this->productRepo->create([
            'name' => 'Burger',
            'price' => 25.0,
        ]);
        $product2_id = $this->productRepo->create([
            'name' => 'Pizza',
            'price' => 30.0,
        ]);

        // 1. Create Product Group
        $create_request = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $group_data = [
            'name' => 'Main Dishes',
            'description' => 'Popular main course items',
            'type' => 'product',
            'item_ids' => [$product1_id, $product2_id],
        ];

        foreach ($group_data as $key => $value) {
            $create_request->set_param($key, $value);
        }

        $create_response = $this->controller->create_item($create_request);
        $this->assertNotInstanceOf(\WP_Error::class, $create_response);
        $this->assertEquals(200, $create_response->get_status());

        $created_group = $create_response->get_data();
        $group_id = $created_group['id'];

        // Verify created group structure
        $this->assertEquals('Main Dishes', $created_group['name']);
        $this->assertEquals('Popular main course items', $created_group['description']);
        $this->assertEquals('product', $created_group['type']);
        $this->assertCount(2, $created_group['group_item_ids']);

        // 2. Get Product Group
        $get_request = new WP_REST_Request('GET', "/squidly/v1/product-groups/{$group_id}");
        $get_request->set_url_params(['id' => (string)$group_id]);

        $get_response = $this->controller->get_item($get_request);
        $this->assertNotInstanceOf(\WP_Error::class, $get_response);

        $retrieved_group = $get_response->get_data();
        $this->assertEquals($group_id, $retrieved_group['id']);
        $this->assertEquals('Main Dishes', $retrieved_group['name']);

        // 3. Update Product Group
        $update_request = new WP_REST_Request('PUT', "/squidly/v1/product-groups/{$group_id}");
        $update_request->set_url_params(['id' => (string)$group_id]);
        $update_request->set_param('name', 'Premium Main Dishes');
        $update_request->set_param('description', 'Updated description');

        $update_response = $this->controller->update_item($update_request);
        $this->assertNotInstanceOf(\WP_Error::class, $update_response);

        $updated_group = $update_response->get_data();
        $this->assertEquals('Premium Main Dishes', $updated_group['name']);
        $this->assertEquals('Updated description', $updated_group['description']);

        // 4. Get Product Groups Collection
        $list_request = new WP_REST_Request('GET', '/squidly/v1/product-groups');
        $list_request->set_param('item_type', 'product');

        $list_response = $this->controller->get_items($list_request);
        $this->assertNotInstanceOf(\WP_Error::class, $list_response);

        $groups_list = $list_response->get_data();
        $this->assertGreaterThanOrEqual(1, count($groups_list));

        // 5. Delete Product Group
        $delete_request = new WP_REST_Request('DELETE', "/squidly/v1/product-groups/{$group_id}");
        $delete_request->set_url_params(['id' => (string)$group_id]);

        $delete_response = $this->controller->delete_item($delete_request);
        $this->assertNotInstanceOf(\WP_Error::class, $delete_response);

        $delete_result = $delete_response->get_data();
        $this->assertTrue($delete_result['success']);
    }

    /* ==========================================
     * Item Type Filtering Tests
     * ========================================== */

    public function test_filter_by_item_type(): void
    {
        // Create product group
        $product_id = $this->productRepo->create(['name' => 'Test Product', 'price' => 10.0]);
        $product_group_id = $this->repository->create([
            'name' => 'Product Group',
            'type' => 'product',
            'group_item_ids' => [
                $this->groupItemRepo->create([
                    'item_id' => $product_id,
                    'item_type' => ItemType::PRODUCT,
                ])
            ],
        ]);

        // Create ingredient group
        $ingredient_id = $this->ingredientRepo->create(['name' => 'Test Ingredient', 'price' => 1.0]);
        $ingredient_group_id = $this->repository->create([
            'name' => 'Ingredient Group',
            'type' => 'ingredient',
            'group_item_ids' => [
                $this->groupItemRepo->create([
                    'item_id' => $ingredient_id,
                    'item_type' => ItemType::INGREDIENT,
                ])
            ],
        ]);

        // Filter by product type
        $request1 = new WP_REST_Request('GET', '/squidly/v1/product-groups');
        $request1->set_param('item_type', 'product');

        $response1 = $this->controller->get_items($request1);
        $data1 = $response1->get_data();
        $this->assertGreaterThanOrEqual(1, count($data1));
        foreach ($data1 as $group) {
            $this->assertEquals('product', $group['type']);
        }

        // Filter by ingredient type
        $request2 = new WP_REST_Request('GET', '/squidly/v1/product-groups');
        $request2->set_param('item_type', 'ingredient');

        $response2 = $this->controller->get_items($request2);
        $data2 = $response2->get_data();
        $this->assertGreaterThanOrEqual(1, count($data2));
        foreach ($data2 as $group) {
            $this->assertEquals('ingredient', $group['type']);
        }

        // Clean up
        $this->repository->delete($product_group_id, true);
        $this->repository->delete($ingredient_group_id, true);
    }

    /* ==========================================
     * Availability Tests
     * ========================================== */

    public function test_availability_calculation(): void
    {
        // Create a product
        $product_id = $this->productRepo->create([
            'name' => 'Available Product',
            'price' => 20.0,
        ]);

        // Create product group
        $create_request = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $create_request->set_param('name', 'Availability Test Group');
        $create_request->set_param('type', 'product');
        $create_request->set_param('item_ids', [$product_id]);
        $create_request->set_param('availability', [1 => true, 2 => false]);

        $create_response = $this->controller->create_item($create_request);
        $created_group = $create_response->get_data();

        // Verify availability is set
        $this->assertArrayHasKey('availability', $created_group);
        $this->assertArrayHasKey('final_availability', $created_group);
        $this->assertArrayHasKey('calculated_availability', $created_group);

        // Clean up
        $this->repository->delete($created_group['id'], true);
    }

    /* ==========================================
     * Validation Tests
     * ========================================== */

    public function test_create_product_group_validation(): void
    {
        // Test missing name
        $request1 = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $request1->set_param('type', 'product');
        $request1->set_param('item_ids', []);

        $response1 = $this->controller->create_item($request1);
        $this->assertEquals(400, $response1->get_status());
        $data1 = $response1->get_data();
        $this->assertArrayHasKey('error', $data1);

        // Test invalid type
        $request2 = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $request2->set_param('name', 'Test Group');
        $request2->set_param('type', 'invalid_type');
        $request2->set_param('item_ids', []);

        $response2 = $this->controller->create_item($request2);
        $this->assertEquals(400, $response2->get_status());
    }

    /* ==========================================
     * Mixed Items Validation Test
     * ========================================== */

    public function test_prevent_mixed_item_types(): void
    {
        // Create a product and an ingredient
        $product_id = $this->productRepo->create(['name' => 'Test Product', 'price' => 10.0]);
        $ingredient_id = $this->ingredientRepo->create(['name' => 'Test Ingredient', 'price' => 1.0]);

        // Create group items
        $product_gi = $this->groupItemRepo->create([
            'item_id' => $product_id,
            'item_type' => ItemType::PRODUCT,
        ]);
        $ingredient_gi = $this->groupItemRepo->create([
            'item_id' => $ingredient_id,
            'item_type' => ItemType::INGREDIENT,
        ]);

        // Try to create a product group with mixed items (using raw repository for this test)
        try {
            $this->repository->create([
                'name' => 'Mixed Group',
                'type' => 'product',
                'group_item_ids' => [$product_gi, $ingredient_gi],
            ]);
            $this->fail('Expected InvalidArgumentException for mixed item types');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot mix', $e->getMessage());
        }
    }

    /* ==========================================
     * Deletion with Dependencies Test
     * ========================================== */

    public function test_delete_with_product_dependency(): void
    {
        // Create product
        $product_id = $this->productRepo->create([
            'name' => 'Dependent Product',
            'price' => 15.0,
        ]);

        // Create group item
        $gi = $this->groupItemRepo->create([
            'item_id' => $product_id,
            'item_type' => ItemType::PRODUCT,
        ]);

        // Create product group
        $group_id = $this->repository->create([
            'name' => 'Test Group',
            'type' => 'product',
            'group_item_ids' => [$gi],
        ]);

        // Add this group to the product (create dependency)
        $this->productRepo->update($product_id, [
            'product_group_ids' => [$group_id],
        ]);

        // Try to delete the group
        $delete_request = new WP_REST_Request('DELETE', "/squidly/v1/product-groups/{$group_id}");
        $delete_request->set_url_params(['id' => (string)$group_id]);

        $delete_response = $this->controller->delete_item($delete_request);
        $this->assertEquals(409, $delete_response->get_status()); // 409 Conflict

        $error_data = $delete_response->get_data();
        $this->assertArrayHasKey('error', $error_data);
        $this->assertArrayHasKey('dependants', $error_data);
    }

    /* ==========================================
     * Permission Tests
     * ========================================== */

    public function test_permission_checks(): void
    {
        // Test without authentication
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/squidly/v1/product-groups');
        $can_read = $this->controller->get_items_permissions_check($request);
        $this->assertFalse($can_read);

        $request = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $can_create = $this->controller->create_item_permissions_check($request);
        $this->assertFalse($can_create);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/product-groups/123');
        $can_delete = $this->controller->delete_item_permissions_check($request);
        $this->assertFalse($can_delete);

        // Test with admin user
        wp_set_current_user(1);

        $request = new WP_REST_Request('GET', '/squidly/v1/product-groups');
        $can_read = $this->controller->get_items_permissions_check($request);
        $this->assertTrue($can_read);

        $request = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $can_create = $this->controller->create_item_permissions_check($request);
        $this->assertTrue($can_create);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/product-groups/123');
        $can_delete = $this->controller->delete_item_permissions_check($request);
        $this->assertTrue($can_delete);
    }

    /* ==========================================
     * Response Structure Tests
     * ========================================== */

    public function test_response_structure(): void
    {
        // Create a simple product group
        $product_id = $this->productRepo->create(['name' => 'Test Product', 'price' => 10.0]);

        $create_request = new WP_REST_Request('POST', '/squidly/v1/product-groups');
        $create_request->set_param('name', 'Structure Test');
        $create_request->set_param('description', 'Test description');
        $create_request->set_param('type', 'product');
        $create_request->set_param('item_ids', [$product_id]);

        $response = $this->controller->create_item($create_request);
        $data = $response->get_data();

        // Check all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('group_item_ids', $data);
        $this->assertArrayHasKey('resolved_items', $data);
        $this->assertArrayHasKey('availability', $data);
        $this->assertArrayHasKey('calculated_availability', $data);
        $this->assertArrayHasKey('final_availability', $data);
        $this->assertArrayHasKey('items_count', $data);

        // Check data types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsString($data['description']);
        $this->assertIsString($data['type']);
        $this->assertIsArray($data['group_item_ids']);
        $this->assertIsArray($data['resolved_items']);

        // Check resolved items structure
        if (!empty($data['resolved_items'])) {
            $resolved_item = $data['resolved_items'][0];
            $this->assertArrayHasKey('id', $resolved_item);
            $this->assertArrayHasKey('type', $resolved_item);
            $this->assertArrayHasKey('group_item_id', $resolved_item);
        }

        // Clean up
        $this->repository->delete($data['id'], true);
    }

    /* ==========================================
     * Empty Results Test
     * ========================================== */

    public function test_get_product_groups_empty(): void
    {
        // Delete all groups first
        $all_groups = $this->repository->getAll();
        foreach ($all_groups as $group) {
            try {
                $this->repository->delete($group->id, true);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $request = new WP_REST_Request('GET', '/squidly/v1/product-groups');
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    /* ==========================================
     * Not Found Test
     * ========================================== */

    public function test_get_nonexistent_product_group(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/product-groups/99999');
        $request->set_url_params(['id' => '99999']);

        $response = $this->controller->get_item($request);
        $this->assertEquals(404, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    /* ==========================================
     * Update Not Found Test
     * ========================================== */

    public function test_update_nonexistent_product_group(): void
    {
        $request = new WP_REST_Request('PUT', '/squidly/v1/product-groups/99999');
        $request->set_url_params(['id' => '99999']);
        $request->set_param('name', 'Updated Name');

        $response = $this->controller->update_item($request);
        $this->assertEquals(404, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }
}
