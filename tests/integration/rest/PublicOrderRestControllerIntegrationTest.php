<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicOrderRestController;
use OrderRepository;
use CustomerRepository;
use StoreBranchRepository;
use ProductRepository;
use Order;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for PublicOrderRestController
 *
 * Tests the complete order creation flow including:
 * - Delivery fee calculation
 * - Minimum order validation
 * - Product customization validation
 * - Price calculation
 * - Order persistence
 *
 * @covers \PublicOrderRestController
 */
class PublicOrderRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicOrderRestController $controller;
    private CustomerRepository $customerRepo;
    private StoreBranchRepository $branchRepo;
    private ProductRepository $productRepo;
    private OrderRepository $orderRepo;

    private int $customer_id;
    private int $branch_id;
    private int $product_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicOrderRestController();
        $this->customerRepo = new CustomerRepository();
        $this->branchRepo = new StoreBranchRepository();
        $this->productRepo = new ProductRepository();
        $this->orderRepo = new OrderRepository();

        // Set up REST API
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
        $this->controller->register_routes();

        // Create test customer
        $this->customer_id = $this->customerRepo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '0501234567',
            'email' => 'test@example.com',
            'auth_provider' => 'phone',
        ]);

        // Create test branch with delivery configuration
        $this->branch_id = $this->branchRepo->create([
            'name' => 'Test Branch',
            'phone' => '0501234567',
            'city' => 'Tel Aviv',
            'address' => '123 Test St',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['open' => '10:00', 'close' => '22:00'],
                'MONDAY' => ['open' => '10:00', 'close' => '22:00'],
            ],
            'kosher_type' => 'regular',
            'accessibility_list' => ['wheelchair', 'parking'],
            'delivery_enabled' => true,
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 100.0,
            'delivery_max_distance' => 5.0,
            'min_order_amount' => 50.0,
        ]);

        // Create test product
        $this->product_id = $this->productRepo->create([
            'name' => 'Test Pizza',
            'description' => 'Test Description',
            'price' => 45.0,
            'product_group_ids' => [],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data - delete all orders for this customer first
        if (isset($this->customer_id)) {
            // Find all orders for this customer
            $all_orders = $this->orderRepo->getAll();
            foreach ($all_orders as $order) {
                if ($order->customer_id === $this->customer_id) {
                    try {
                        wp_delete_post($order->id, true); // Force delete
                    } catch (Exception $e) {
                        // Ignore errors during cleanup
                    }
                }
            }

            // Now delete customer
            try {
                wp_delete_post($this->customer_id, true); // Force delete
            } catch (Exception $e) {
                // Ignore errors during cleanup
            }
        }

        if (isset($this->branch_id)) {
            try {
                wp_delete_post($this->branch_id, true); // Force delete
            } catch (Exception $e) {
                // Ignore errors during cleanup
            }
        }

        if (isset($this->product_id)) {
            try {
                wp_delete_post($this->product_id, true); // Force delete
            } catch (Exception $e) {
                // Ignore errors during cleanup
            }
        }

        parent::tearDown();
    }

    // ========================================================================
    // PICKUP ORDER TESTS
    // ========================================================================

    public function test_create_pickup_order_success(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product_id,
                    'quantity' => 2,
                    'customizations' => [],
                ],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        // Verify response structure
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('tracking_token', $data);
        $this->assertArrayHasKey('total_price', $data);
        $this->assertArrayHasKey('subtotal', $data);
        $this->assertArrayHasKey('delivery_fee', $data);

        // Verify calculations
        $this->assertEquals(90.0, $data['subtotal']); // 2 × 45.0
        $this->assertEquals(0.0, $data['delivery_fee']); // Pickup = no delivery fee
        $this->assertEquals(Order::STATUS_PENDING, $data['status']);
        $this->assertEquals(Order::PAYMENT_PENDING, $data['payment_status']);

        // Verify order persisted in database
        $order = $this->orderRepo->get($data['order_id']);
        $this->assertNotNull($order);
        $this->assertEquals($this->customer_id, $order->customer_id);
        $this->assertEquals($this->branch_id, $order->branch_id);
        $this->assertEquals('pickup', $order->delivery_type);
    }

    // ========================================================================
    // DELIVERY ORDER TESTS
    // ========================================================================

    public function test_create_delivery_order_with_fee(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'delivery',
            'delivery_address' => '456 Customer St, Tel Aviv',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product_id,
                    'quantity' => 2, // 90.0 subtotal (above minimum of 50.0)
                    'customizations' => [],
                ],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Debug output if not 201
        if ($response->get_status() !== 201) {
            error_log('Response status: ' . $response->get_status());
            error_log('Response data: ' . print_r($data, true));
        }

        $this->assertEquals(201, $response->get_status(), 'Order creation failed: ' . json_encode($data));

        // Verify delivery fee applied (below free threshold of 100.0)
        $this->assertEquals(90.0, $data['subtotal']);
        $this->assertEquals(15.0, $data['delivery_fee']); // Branch base fee
        $this->assertGreaterThan($data['subtotal'], $data['total_price']); // Total includes fee + tax
    }

    public function test_create_delivery_order_free_delivery_threshold(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'delivery',
            'delivery_address' => '456 Customer St, Tel Aviv',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product_id,
                    'quantity' => 3, // 135.0 subtotal (above 100.0 threshold)
                    'customizations' => [],
                ],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        // Verify free delivery (above threshold)
        $this->assertEquals(135.0, $data['subtotal']);
        $this->assertEquals(0.0, $data['delivery_fee']); // Free delivery
    }

    // ========================================================================
    // MINIMUM ORDER VALIDATION
    // ========================================================================

    public function test_create_order_fails_below_minimum_amount(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product_id,
                    'quantity' => 1, // 45.0 subtotal (below 50.0 minimum)
                    'customizations' => [],
                ],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Minimum order amount not met', $data['error']);
        $this->assertArrayHasKey('min_order_amount', $data);
        $this->assertArrayHasKey('current_subtotal', $data);
        $this->assertEquals(50.0, $data['min_order_amount']);
        $this->assertEquals(45.0, $data['current_subtotal']);
    }

    public function test_create_order_succeeds_at_minimum_amount(): void
    {
        // Update product price to exactly meet minimum
        $this->productRepo->update($this->product_id, ['price' => 50.0]);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product_id,
                    'quantity' => 1, // 50.0 subtotal (exactly at minimum)
                    'customizations' => [],
                ],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(50.0, $data['subtotal']);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_create_order_fails_with_missing_customer_id(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branch_id,
            'items' => [
                ['product_id' => $this->product_id, 'quantity' => 2],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // WP REST API validation returns either 400 with 'error' or 'code' key
        $this->assertEquals(400, $response->get_status());
        $this->assertTrue(
            isset($data['error']) || isset($data['code']),
            'Response should contain error or code key: ' . json_encode($data)
        );
    }

    public function test_create_order_fails_with_invalid_branch(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => 999999, // Non-existent branch
            'items' => [
                ['product_id' => $this->product_id, 'quantity' => 1],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Branch not found', $data['error']);
    }

    public function test_create_order_fails_with_invalid_product(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'items' => [
                ['product_id' => 999999, 'quantity' => 1], // Non-existent product
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    public function test_create_order_fails_with_empty_items(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'items' => [], // Empty items array
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================================================
    // DELIVERY VALIDATION
    // ========================================================================

    public function test_create_delivery_order_when_delivery_disabled(): void
    {
        // Update branch to disable delivery
        $this->branchRepo->update($this->branch_id, ['delivery_enabled' => false]);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'delivery',
            'delivery_address' => '456 Customer St',
            'items' => [
                ['product_id' => $this->product_id, 'quantity' => 2],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Delivery fee calculation failed', $data['error']);
    }

    // ========================================================================
    // ORDER STATUS TRACKING
    // ========================================================================

    public function test_get_order_status_with_valid_token(): void
    {
        // Create order first
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 90.0,
            'tax_amount' => 15.3,
            'delivery_fee' => 0.0,
            'total_amount' => 105.3,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash',
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test123',
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Pizza',
                    'quantity' => 2,
                    'unit_price' => 45.0,
                    'total_price' => 90.0,
                ],
            ],
        ]);

        $request = new WP_REST_Request('GET', "/squidly/v1/public/orders/{$order_id}/status");
        $request->set_param('token', 'tk_test123');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertEquals($order_id, $data['order_id']);
        $this->assertEquals(Order::STATUS_PENDING, $data['status']);
        $this->assertEquals(Order::PAYMENT_PENDING, $data['payment_status']);
        $this->assertEquals(105.3, $data['total_amount']);
    }

    public function test_get_order_status_with_invalid_token(): void
    {
        // Create order first
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 90.0,
            'tax_amount' => 15.3,
            'delivery_fee' => 0.0,
            'total_amount' => 105.3,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash',
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_correct_token',
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Pizza',
                    'quantity' => 2,
                    'unit_price' => 45.0,
                    'total_price' => 90.0,
                ],
            ],
        ]);

        $request = new WP_REST_Request('GET', "/squidly/v1/public/orders/{$order_id}/status");
        $request->set_param('token', 'tk_wrong_token');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid tracking token', $data['error']);
    }

    public function test_get_order_status_with_nonexistent_order(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/orders/999999/status');
        $request->set_param('token', 'tk_any_token');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Order not found', $data['error']);
    }

    // ========================================================================
    // PRICE CALCULATION TESTS
    // ========================================================================

    public function test_server_side_price_calculation_prevents_manipulation(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product_id,
                    'quantity' => 2,
                    'customizations' => [],
                    // Client sends fake prices (should be ignored)
                    'unit_price' => 1.0,
                    'total_price' => 2.0,
                ],
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();

        // Verify server calculated correct prices (not client-supplied)
        $this->assertEquals(90.0, $data['subtotal']); // 2 × 45.0 (from database)
        $this->assertNotEquals(2.0, $data['subtotal']); // Client's fake price ignored
    }
}
