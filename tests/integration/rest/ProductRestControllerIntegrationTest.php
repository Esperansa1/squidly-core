<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductRestController;
use ProductRepository;
use StoreBranchRepository;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for ProductRestController.
 * Tests the REST API endpoints for product management.
 *
 * @covers ProductRestController
 */
class ProductRestControllerIntegrationTest extends WP_UnitTestCase
{
    private ProductRestController $controller;
    private ProductRepository $repository;
    private StoreBranchRepository $branchRepository;
    private int $admin_user_id;
    private int $regular_user_id;

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new ProductRestController();
        $this->repository = new ProductRepository();
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
     *  CREATE PRODUCT TESTS
     * ===================================================================*/

    public function test_create_product_success(): void
    {
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Test Burger',
            'price' => 25.99,
            'description' => 'A delicious test burger',
            'category' => 'burgers'
        ]);

        $response = $this->controller->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Test Burger', $data['name']);
        $this->assertEquals(25.99, $data['price']);
        $this->assertEquals('A delicious test burger', $data['description']);
        $this->assertEquals('burgers', $data['category']);
        $this->assertIsArray($data['availability']);
    }

    public function test_create_product_with_availability(): void
    {
        $branches = $this->branchRepository->getAll();
        $availability = [];
        foreach ($branches as $branch) {
            $availability[$branch->id] = true;
        }

        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Available Everywhere',
            'price' => 15.50,
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

    public function test_create_product_validation_failures(): void
    {
        // Test missing name
        $request = $this->createAuthenticatedRequest('POST', [
            'price' => 10.00
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());

        // Test negative price
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => 'Test Product',
            'price' => -5.00
        ]);
        $response = $this->controller->create_item($request);
        $this->assertEquals(400, $response->get_status());
    }

    /* =====================================================================
     *  GET PRODUCTS TESTS
     * ===================================================================*/

    public function test_get_products_empty_list(): void
    {
        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($data['data']);
        $this->assertEmpty($data['data']);
    }

    public function test_get_products_with_data(): void
    {
        // Create test products
        $product1_id = $this->repository->create([
            'name' => 'Product 1',
            'price' => 10.00
        ]);
        $product2_id = $this->repository->create([
            'name' => 'Product 2',
            'price' => 15.00
        ]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        $names = array_column($data['data'], 'name');
        $this->assertContains('Product 1', $names);
        $this->assertContains('Product 2', $names);
    }

    public function test_get_products_with_search_filter(): void
    {
        // Create test products
        $this->repository->create(['name' => 'Burger Deluxe', 'price' => 20.00]);
        $this->repository->create(['name' => 'Pizza Special', 'price' => 18.00]);
        $this->repository->create(['name' => 'Burger Classic', 'price' => 15.00]);

        $request = $this->createAuthenticatedRequest('GET', [
            'search' => 'Burger'
        ]);
        $response = $this->controller->get_items($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(2, $data['data']);

        foreach ($data['data'] as $product) {
            $this->assertStringContainsString('Burger', $product['name']);
        }
    }

    /* =====================================================================
     *  GET SINGLE PRODUCT TESTS
     * ===================================================================*/

    public function test_get_single_product_success(): void
    {
        $product_id = $this->repository->create([
            'name' => 'Single Product',
            'price' => 12.50,
            'description' => 'Test description'
        ]);

        $request = $this->createAuthenticatedRequest('GET', ['id' => $product_id]);
        $response = $this->controller->get_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Single Product', $data['name']);
        $this->assertEquals(12.50, $data['price']);
        $this->assertEquals('Test description', $data['description']);
    }

    public function test_get_single_product_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('GET', ['id' => 99999]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  UPDATE PRODUCT TESTS
     * ===================================================================*/

    public function test_update_product_success(): void
    {
        $product_id = $this->repository->create([
            'name' => 'Original Name',
            'price' => 10.00
        ]);

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $product_id,
            'name' => 'Updated Name',
            'price' => 15.00
        ]);

        $response = $this->controller->update_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals(15.00, $data['price']);
    }

    public function test_update_product_availability(): void
    {
        $product_id = $this->repository->create([
            'name' => 'Test Product',
            'price' => 10.00
        ]);

        $branches = $this->branchRepository->getAll();
        $availability = [];
        foreach ($branches as $i => $branch) {
            // Make first branch available, second unavailable
            $availability[$branch->id] = $i === 0;
        }

        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => $product_id,
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

    public function test_update_product_not_found(): void
    {
        $request = $this->createAuthenticatedRequest('PUT', [
            'id' => 99999,
            'name' => 'Updated Name'
        ]);

        $response = $this->controller->update_item($request);
        $this->assertEquals(404, $response->get_status());
    }

    /* =====================================================================
     *  DELETE PRODUCT TESTS
     * ===================================================================*/

    public function test_delete_product_success(): void
    {
        $product_id = $this->repository->create([
            'name' => 'To Be Deleted',
            'price' => 5.00
        ]);

        $request = $this->createAuthenticatedRequest('DELETE', ['id' => $product_id]);
        $response = $this->controller->delete_item($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($data['success']);

        // Verify product is actually deleted
        $deleted_product = $this->repository->get($product_id);
        $this->assertNull($deleted_product);
    }

    public function test_delete_product_not_found(): void
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
        $this->assertArrayHasKey('category', $params);
    }

    public function test_item_schema_structure(): void
    {
        $args = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);

        $this->assertArrayHasKey('name', $args);
        $this->assertArrayHasKey('price', $args);
        $this->assertArrayHasKey('description', $args);
        $this->assertArrayHasKey('category', $args);
        $this->assertArrayHasKey('availability', $args);

        // Check required fields for creation
        $this->assertTrue($args['name']['required']);
        $this->assertTrue($args['price']['required']);
        $this->assertFalse($args['description']['required']);
    }

    /* =====================================================================
     *  ERROR HANDLING TESTS
     * ===================================================================*/

    public function test_handles_repository_exceptions(): void
    {
        // Test creating product with invalid data that causes repository exception
        $request = $this->createAuthenticatedRequest('POST', [
            'name' => '', // Empty name should trigger repository validation
            'price' => 10.00
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
        $product_id = $this->repository->create([
            'name' => 'Test Product',
            'price' => 20.00,
            'description' => 'Test description',
            'category' => 'test-category'
        ]);

        $product = $this->repository->get($product_id);
        $request = new WP_REST_Request();

        $response = $this->controller->prepare_item_for_response($product, $request);
        $data = $response->get_data();

        // Check all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('discounted_price', $data);
        $this->assertArrayHasKey('category', $data);
        $this->assertArrayHasKey('tags', $data);
        $this->assertArrayHasKey('product_group_ids', $data);
        $this->assertArrayHasKey('availability', $data);

        // Check data types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsFloat($data['price']);
        $this->assertIsArray($data['availability']);
        $this->assertIsArray($data['tags']);
        $this->assertIsArray($data['product_group_ids']);
    }
}