<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicCartRestController;
use CartService;
use ProductRepository;
use CustomerRepository;
use StoreBranchRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for PublicCartRestController
 *
 * Tests the public cart API including:
 * - Cart creation and item addition
 * - Cart retrieval
 * - Item updates (quantity, notes)
 * - Item removal
 * - Cart clearing
 * - Token-based access
 * - Price calculations
 *
 * @covers \PublicCartRestController
 */
class PublicCartRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicCartRestController $controller;
    private CartService $cartService;
    private ProductRepository $productRepo;
    private CustomerRepository $customerRepo;
    private StoreBranchRepository $branchRepo;

    private int $branch_id;
    private int $customer_id;
    private int $product1_id;
    private int $product2_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicCartRestController();
        $this->cartService = new CartService();
        $this->productRepo = new ProductRepository();
        $this->customerRepo = new CustomerRepository();
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
            'delivery_enabled' => true,
            'delivery_base_fee' => 15.0,
        ]);

        // Create test customer
        $this->customer_id = $this->customerRepo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+972501234567',
            'auth_provider' => 'phone',
        ]);

        // Create test products
        $this->product1_id = $this->productRepo->create([
            'name' => 'Test Product 1',
            'description' => 'Description 1',
            'price' => 50.0,
            'product_group_ids' => [],
        ]);

        $this->product2_id = $this->productRepo->create([
            'name' => 'Test Product 2',
            'description' => 'Description 2',
            'price' => 35.0,
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

        if (isset($this->customer_id)) {
            try {
                wp_delete_post($this->customer_id, true);
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

        parent::tearDown();
    }

    // ========================================================================
    // CART CREATION TESTS
    // ========================================================================

    public function test_create_cart_with_first_item(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branch_id,
            'product_id' => $this->product1_id,
            'quantity' => 2,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('cart', $data);
        $this->assertArrayHasKey('message', $data);

        $cart = $data['cart'];
        $this->assertArrayHasKey('token', $cart);
        $this->assertArrayHasKey('items', $cart);
        $this->assertCount(1, $cart['items']);
        $this->assertEquals($this->branch_id, $cart['branch_id']);
        $this->assertEquals(2, $cart['items'][0]['quantity']);
    }

    public function test_create_cart_with_customer_id(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branch_id,
            'product_id' => $this->product1_id,
            'quantity' => 1,
            'customer_id' => $this->customer_id,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        $cart = $data['cart'];
        $this->assertEquals($this->customer_id, $cart['customer_id']);
    }

    public function test_create_cart_with_notes(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branch_id,
            'product_id' => $this->product1_id,
            'quantity' => 1,
            'notes' => 'No onions please',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        $cart = $data['cart'];
        $this->assertEquals('No onions please', $cart['items'][0]['notes']);
    }

    public function test_create_cart_validates_branch_id(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'product_id' => $this->product1_id,
            'quantity' => 1,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_create_cart_validates_product_id(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branch_id,
            'quantity' => 1,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    // ========================================================================
    // ADD ITEM TO EXISTING CART TESTS
    // ========================================================================

    public function test_add_item_to_existing_cart(): void
    {
        // Create cart with first item
        $cart = $this->cartService->createCart($this->branch_id, $this->customer_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 1);

        $this->assertCount(1, $cart->items);

        // Add second item via API
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'token' => $cart->token,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product2_id,
            'quantity' => 2,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        $this->assertCount(2, $updated_cart['items']);
    }

    public function test_add_same_product_creates_separate_item(): void
    {
        // Create cart with item
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 2);

        // Add same product again (creates separate line item)
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'token' => $cart->token,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product1_id,
            'quantity' => 3,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        // Two separate items for same product (allows different customizations)
        $this->assertCount(2, $updated_cart['items']);
        $this->assertEquals($this->product1_id, $updated_cart['items'][0]['product_id']);
        $this->assertEquals($this->product1_id, $updated_cart['items'][1]['product_id']);
    }

    // ========================================================================
    // GET CART TESTS
    // ========================================================================

    public function test_get_cart_by_token(): void
    {
        // Create cart
        $cart = $this->cartService->createCart($this->branch_id, $this->customer_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 2);

        // Get cart via API
        $request = new WP_REST_Request('GET', "/squidly/v1/public/cart/{$cart->token}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('cart', $data);
        $retrieved_cart = $data['cart'];
        $this->assertEquals($cart->token, $retrieved_cart['token']);
        $this->assertEquals($this->branch_id, $retrieved_cart['branch_id']);
        $this->assertEquals($this->customer_id, $retrieved_cart['customer_id']);
        $this->assertCount(1, $retrieved_cart['items']);
    }

    public function test_get_cart_returns_404_for_invalid_token(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/cart/invalid_token_123');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================================================
    // UPDATE CART ITEM TESTS
    // ========================================================================

    public function test_update_cart_item_quantity(): void
    {
        // Create cart with item
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 2);

        $item_id = $cart->items[0]->id;

        // Update quantity via API
        $request = new WP_REST_Request('PUT', "/squidly/v1/public/cart/{$cart->token}/item/{$item_id}");
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'quantity' => 5,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        $this->assertEquals(5, $updated_cart['items'][0]['quantity']);
    }

    public function test_update_cart_item_notes(): void
    {
        // Create cart with item
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 1);

        $item_id = $cart->items[0]->id;

        // Update notes via API
        $request = new WP_REST_Request('PUT', "/squidly/v1/public/cart/{$cart->token}/item/{$item_id}");
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'notes' => 'Extra spicy',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        $this->assertEquals('Extra spicy', $updated_cart['items'][0]['notes']);
    }

    public function test_update_cart_item_both_quantity_and_notes(): void
    {
        // Create cart with item
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 1);

        $item_id = $cart->items[0]->id;

        // Update both via API
        $request = new WP_REST_Request('PUT', "/squidly/v1/public/cart/{$cart->token}/item/{$item_id}");
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'quantity' => 3,
            'notes' => 'Well done',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        $this->assertEquals(3, $updated_cart['items'][0]['quantity']);
        $this->assertEquals('Well done', $updated_cart['items'][0]['notes']);
    }

    public function test_update_cart_item_returns_404_for_invalid_cart(): void
    {
        $request = new WP_REST_Request('PUT', '/squidly/v1/public/cart/invalid_token/item/item123');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'quantity' => 5,
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ========================================================================
    // REMOVE CART ITEM TESTS
    // ========================================================================

    public function test_remove_cart_item(): void
    {
        // Create cart with 2 items
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 1);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product2_id, 1);

        $this->assertCount(2, $cart->items);
        $item_id = $cart->items[0]->id;

        // Remove first item via API
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/cart/{$cart->token}/item/{$item_id}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        $this->assertCount(1, $updated_cart['items']);
    }

    public function test_remove_last_cart_item_leaves_empty_cart(): void
    {
        // Create cart with 1 item
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 1);

        $item_id = $cart->items[0]->id;

        // Remove item via API
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/cart/{$cart->token}/item/{$item_id}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $updated_cart = $data['cart'];
        $this->assertCount(0, $updated_cart['items']);
    }

    public function test_remove_cart_item_returns_404_for_invalid_cart(): void
    {
        $request = new WP_REST_Request('DELETE', '/squidly/v1/public/cart/invalid_token/item/item123');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ========================================================================
    // CLEAR CART TESTS
    // ========================================================================

    public function test_clear_cart_removes_all_items(): void
    {
        // Create cart with multiple items
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 2);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product2_id, 3);

        $this->assertCount(2, $cart->items);

        // Clear cart via API
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/cart/{$cart->token}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $cleared_cart = $data['cart'];
        $this->assertCount(0, $cleared_cart['items']);
        $this->assertEquals($cart->token, $cleared_cart['token']); // Token still valid
    }

    public function test_clear_cart_returns_404_for_invalid_cart(): void
    {
        $request = new WP_REST_Request('DELETE', '/squidly/v1/public/cart/invalid_token');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ========================================================================
    // PRICE CALCULATION TESTS
    // ========================================================================

    public function test_cart_calculates_subtotal_correctly(): void
    {
        // Create cart
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 2); // 50 × 2 = 100
        $cart = $this->cartService->addItemToCart($cart->token, $this->product2_id, 1); // 35 × 1 = 35

        // Get cart via API
        $request = new WP_REST_Request('GET', "/squidly/v1/public/cart/{$cart->token}");
        $response = rest_get_server()->dispatch($request);

        $data = $response->get_data();
        $retrieved_cart = $data['cart'];

        $this->assertEquals(135.0, $retrieved_cart['subtotal']); // 100 + 35
    }

    public function test_cart_item_has_correct_total_price(): void
    {
        // Create cart with item
        $cart = $this->cartService->createCart($this->branch_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 3);

        // Get cart via API
        $request = new WP_REST_Request('GET', "/squidly/v1/public/cart/{$cart->token}");
        $response = rest_get_server()->dispatch($request);

        $data = $response->get_data();
        $retrieved_cart = $data['cart'];

        $this->assertEquals(50.0, $retrieved_cart['items'][0]['unit_price']);
        $this->assertEquals(150.0, $retrieved_cart['items'][0]['total_price']); // 50 × 3
    }

    // ========================================================================
    // RESPONSE STRUCTURE TESTS
    // ========================================================================

    public function test_cart_response_contains_required_fields(): void
    {
        // Create cart
        $cart = $this->cartService->createCart($this->branch_id, $this->customer_id);
        $cart = $this->cartService->addItemToCart($cart->token, $this->product1_id, 1);

        // Get cart via API
        $request = new WP_REST_Request('GET', "/squidly/v1/public/cart/{$cart->token}");
        $response = rest_get_server()->dispatch($request);

        $data = $response->get_data();
        $retrieved_cart = $data['cart'];

        // Verify cart fields
        $this->assertArrayHasKey('token', $retrieved_cart);
        $this->assertArrayHasKey('branch_id', $retrieved_cart);
        $this->assertArrayHasKey('customer_id', $retrieved_cart);
        $this->assertArrayHasKey('items', $retrieved_cart);
        $this->assertArrayHasKey('subtotal', $retrieved_cart);
        $this->assertArrayHasKey('created_at', $retrieved_cart);

        // Verify item fields
        $item = $retrieved_cart['items'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('product_id', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('unit_price', $item);
        $this->assertArrayHasKey('total_price', $item);
        $this->assertArrayHasKey('customizations', $item);
        $this->assertArrayHasKey('notes', $item);
    }

    // ========================================================================
    // TOKEN VALIDATION TESTS
    // ========================================================================

    public function test_cart_token_format(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branch_id,
            'product_id' => $this->product1_id,
            'quantity' => 1,
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $cart = $data['cart'];
        $this->assertStringStartsWith('cart_', $cart['token']);
        $this->assertGreaterThan(10, strlen($cart['token']));
    }
}
