<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicProductRestController;
use ProductRepository;
use StoreBranchRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for PublicProductRestController
 *
 * Tests the public product API including:
 * - Product listing with branch filtering
 * - Strict availability mode
 * - Product detail retrieval
 * - Search and filtering
 *
 * @covers \PublicProductRestController
 */
class PublicProductRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicProductRestController $controller;
    private ProductRepository $productRepo;
    private StoreBranchRepository $branchRepo;

    private int $branch_id;
    private int $product1_id;
    private int $product2_id;
    private int $product3_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicProductRestController();
        $this->productRepo = new ProductRepository();
        $this->branchRepo = new StoreBranchRepository();

        // Set up REST API
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
        $this->controller->register_routes();

        // Create test branch
        $this->branch_id = $this->branchRepo->create([
            'name' => 'Test Branch',
            'phone' => '0501234567',
            'city' => 'Tel Aviv',
            'address' => '123 Test St',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['open' => '10:00', 'close' => '22:00'],
            ],
            'kosher_type' => 'regular',
            'accessibility_list' => [],
        ]);

        // Create test products
        $this->product1_id = $this->productRepo->create([
            'name' => 'Margherita Pizza',
            'description' => 'Classic tomato and cheese',
            'price' => 45.0,
            'product_group_ids' => [],
        ]);

        $this->product2_id = $this->productRepo->create([
            'name' => 'Pepperoni Pizza',
            'description' => 'With spicy pepperoni',
            'price' => 52.0,
            'product_group_ids' => [],
        ]);

        $this->product3_id = $this->productRepo->create([
            'name' => 'Caesar Salad',
            'description' => 'Fresh romaine with caesar dressing',
            'price' => 38.0,
            'product_group_ids' => [],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (isset($this->branch_id)) {
            try {
                wp_delete_post($this->branch_id, true);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }

        if (isset($this->product1_id)) {
            try {
                wp_delete_post($this->product1_id, true);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }

        if (isset($this->product2_id)) {
            try {
                wp_delete_post($this->product2_id, true);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }

        if (isset($this->product3_id)) {
            try {
                wp_delete_post($this->product3_id, true);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }

        parent::tearDown();
    }

    // ========================================================================
    // PRODUCT LISTING TESTS
    // ========================================================================

    public function test_get_products_returns_all_products_without_branch_filter(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertCount(3, $data); // All 3 products returned
    }

    public function test_get_products_with_branch_filter_prototype_mode(): void
    {
        // Ensure strict mode is OFF (prototype mode)
        update_option('squidly_strict_availability_mode', false);

        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('branch_id', $this->branch_id);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // In prototype mode: if no products configured, show all
        $this->assertCount(3, $data);
    }

    public function test_get_products_strict_mode_with_no_availability_configured(): void
    {
        // Enable strict availability mode
        update_option('squidly_strict_availability_mode', true);

        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('branch_id', $this->branch_id);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // In strict mode: if no products configured, show none
        $this->assertCount(0, $data);

        // Clean up
        update_option('squidly_strict_availability_mode', false);
    }

    public function test_get_products_strict_mode_with_specific_availability(): void
    {
        // Enable strict availability mode
        update_option('squidly_strict_availability_mode', true);

        // Configure branch to only have product1 and product2 available
        $this->branchRepo->update($this->branch_id, [
            'product_availability' => [
                $this->product1_id => true,
                $this->product2_id => true,
                $this->product3_id => false, // Explicitly unavailable
            ],
        ]);

        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('branch_id', $this->branch_id);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // Only 2 products should be returned
        $this->assertCount(2, $data);

        $returned_ids = array_column($data, 'id');
        $this->assertContains($this->product1_id, $returned_ids);
        $this->assertContains($this->product2_id, $returned_ids);
        $this->assertNotContains($this->product3_id, $returned_ids);

        // Clean up
        update_option('squidly_strict_availability_mode', false);
    }

    public function test_get_products_prototype_mode_respects_explicit_unavailability(): void
    {
        // Ensure prototype mode
        update_option('squidly_strict_availability_mode', false);

        // Configure some products explicitly
        $this->branchRepo->update($this->branch_id, [
            'product_availability' => [
                $this->product1_id => true,
                $this->product2_id => false, // Explicitly unavailable
            ],
        ]);

        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('branch_id', $this->branch_id);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $returned_ids = array_column($data, 'id');

        // Product1 available, product2 explicitly unavailable
        $this->assertContains($this->product1_id, $returned_ids);
        $this->assertNotContains($this->product2_id, $returned_ids);

        // Product3 has no configuration in availability list
        // In prototype mode: if branch HAS any configured products, only configured ones show
        // So product3 should NOT appear (only configured products)
        $this->assertNotContains($this->product3_id, $returned_ids);

        // Only 1 product returned (product1)
        $this->assertCount(1, $data);
    }

    // ========================================================================
    // PRODUCT DETAIL TESTS
    // ========================================================================

    public function test_get_product_by_id_returns_correct_product(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/products/{$this->product1_id}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertEquals($this->product1_id, $data['id']);
        $this->assertEquals('Margherita Pizza', $data['name']);
        $this->assertEquals(45.0, $data['price']);
    }

    public function test_get_product_by_invalid_id_returns_404(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products/999999');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ========================================================================
    // SEARCH AND FILTERING TESTS
    // ========================================================================

    public function test_search_products_by_name(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('search', 'Pizza');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // Should return 2 pizza products
        $this->assertCount(2, $data);

        $names = array_column($data, 'name');
        $this->assertTrue(
            in_array('Margherita Pizza', $names) || in_array('Pepperoni Pizza', $names),
            'Search results should contain pizza products'
        );
    }

    public function test_search_products_case_insensitive(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('search', 'pizza'); // lowercase

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // Should still return 2 pizza products
        $this->assertCount(2, $data);
    }

    public function test_search_with_no_results(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('search', 'NonexistentProduct');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertCount(0, $data);
    }

    // ========================================================================
    // PAGINATION TESTS
    // ========================================================================

    public function test_pagination_limits_results(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('per_page', 2);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertCount(2, $data);

        // Check pagination headers
        $headers = $response->get_headers();
        $this->assertArrayHasKey('X-WP-Total', $headers);
        $this->assertEquals(3, $headers['X-WP-Total']); // Total of 3 products
    }

    public function test_pagination_offset_works(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('per_page', 2);
        $request->set_param('offset', 2);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // With offset 2, should get 1 remaining product
        $this->assertCount(1, $data);
    }

    // ========================================================================
    // RESPONSE STRUCTURE TESTS
    // ========================================================================

    public function test_product_response_contains_required_fields(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/products/{$this->product1_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Verify required fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('product_group_ids', $data);

        // Verify types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsFloat($data['price']);
        $this->assertIsArray($data['product_group_ids']);
    }

    // ========================================================================
    // COMBINED FILTERS TEST
    // ========================================================================

    public function test_combined_branch_and_search_filters(): void
    {
        // Enable strict mode
        update_option('squidly_strict_availability_mode', true);

        // Only product1 (Margherita Pizza) available at branch
        $this->branchRepo->update($this->branch_id, [
            'product_availability' => [
                $this->product1_id => true,
                $this->product2_id => false,
                $this->product3_id => false,
            ],
        ]);

        $request = new WP_REST_Request('GET', '/squidly/v1/public/products');
        $request->set_param('branch_id', $this->branch_id);
        $request->set_param('search', 'Pizza');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // Should return only Margherita Pizza (available + matches search)
        $this->assertCount(1, $data);
        $this->assertEquals('Margherita Pizza', $data[0]['name']);

        // Clean up
        update_option('squidly_strict_availability_mode', false);
    }
}
