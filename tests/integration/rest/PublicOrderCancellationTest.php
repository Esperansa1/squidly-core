<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicOrderRestController;
use OrderRepository;
use CustomerRepository;
use ProductRepository;
use StoreBranchRepository;
use Order;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for Public Order Cancellation
 *
 * Tests the order cancellation endpoint including:
 * - Token-based authentication
 * - Status validation (only pending/confirmed can be cancelled)
 * - Cancellation success flow
 * - Error cases (invalid token, wrong status, not found)
 *
 * @covers \PublicOrderRestController::cancel_order
 */
class PublicOrderCancellationTest extends WP_UnitTestCase
{
    private PublicOrderRestController $controller;
    private OrderRepository $orderRepo;
    private CustomerRepository $customerRepo;
    private ProductRepository $productRepo;
    private StoreBranchRepository $branchRepo;

    private int $branch_id;
    private int $customer_id;
    private int $product_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicOrderRestController();
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
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

        // Create test customer
        $this->customer_id = $this->customerRepo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+972501234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        // Create test product
        $this->product_id = $this->productRepo->create([
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 50.0,
            'product_group_ids' => [],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        foreach ([$this->branch_id, $this->customer_id, $this->product_id] as $id) {
            if (isset($id)) {
                try {
                    wp_delete_post($id, true);
                } catch (\Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }

        parent::tearDown();
    }

    // ========================================================================
    // SUCCESSFUL CANCELLATION TESTS
    // ========================================================================

    public function test_cancel_pending_order_with_valid_token(): void
    {
        // Create a pending order
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        $order = $this->orderRepo->get($order_id);
        $tracking_token = $order->tracking_token;

        // Cancel order via API
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', $tracking_token);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals($order_id, $data['order_id']);
        $this->assertEquals(Order::STATUS_CANCELLED, $data['status']);

        // Verify order status was updated
        $updated_order = $this->orderRepo->get($order_id);
        $this->assertEquals(Order::STATUS_CANCELLED, $updated_order->status);
    }

    public function test_cancel_confirmed_order_with_valid_token(): void
    {
        // Create a confirmed order
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PAID,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        $order = $this->orderRepo->get($order_id);
        $tracking_token = $order->tracking_token;

        // Cancel order via API
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', $tracking_token);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        // Verify cancellation
        $updated_order = $this->orderRepo->get($order_id);
        $this->assertEquals(Order::STATUS_CANCELLED, $updated_order->status);
    }

    // ========================================================================
    // AUTHENTICATION TESTS
    // ========================================================================

    public function test_cancel_order_fails_with_invalid_token(): void
    {
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        // Try to cancel with wrong token
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', 'invalid_token_123');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Invalid tracking token', $data['error']);

        // Verify order was NOT cancelled
        $order = $this->orderRepo->get($order_id);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
    }

    public function test_cancel_order_fails_with_missing_token(): void
    {
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        // Try to cancel without token
        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    // ========================================================================
    // STATUS VALIDATION TESTS
    // ========================================================================

    public function test_cannot_cancel_preparing_order(): void
    {
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_PREPARING,
            'payment_status' => Order::PAYMENT_PAID,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        $order = $this->orderRepo->get($order_id);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', $order->tracking_token);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('cannot be cancelled', $data['error']);

        // Verify status unchanged
        $updated_order = $this->orderRepo->get($order_id);
        $this->assertEquals(Order::STATUS_PREPARING, $updated_order->status);
    }

    public function test_cannot_cancel_ready_order(): void
    {
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_READY,
            'payment_status' => Order::PAYMENT_PAID,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        $order = $this->orderRepo->get($order_id);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', $order->tracking_token);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_cannot_cancel_completed_order(): void
    {
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_PAID,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        $order = $this->orderRepo->get($order_id);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', $order->tracking_token);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_cannot_cancel_already_cancelled_order(): void
    {
        $order_id = $this->orderRepo->create([
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_PENDING,
            'order_date' => current_time('mysql'),
            'subtotal' => 50.0,
            'tax_amount' => 8.5,
            'delivery_fee' => 0.0,
            'total_amount' => 58.5,
            'delivery_type' => 'pickup',
            'tracking_token' => 'tk_test_' . bin2hex(random_bytes(8)),
            'order_items' => [
                [
                    'product_id' => $this->product_id,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.0,
                    'customizations' => [],
                    'special_instructions' => null,
                ]
            ],
        ]);

        $order = $this->orderRepo->get($order_id);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/public/orders/{$order_id}");
        $request->set_param('token', $order->tracking_token);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    // ========================================================================
    // NOT FOUND TESTS
    // ========================================================================

    public function test_cancel_nonexistent_order_returns_404(): void
    {
        $request = new WP_REST_Request('DELETE', '/squidly/v1/public/orders/99999');
        $request->set_param('token', 'tk_some_token');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('not found', $data['error']);
    }
}
