<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use OrderRestController;
use OrderRepository;
use Order;
use OrderItem;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for OrderRestController
 *
 * Tests the REST controller with real WordPress database operations
 * and repository interactions.
 */
class OrderRestControllerIntegrationTest extends \WP_UnitTestCase
{
    private OrderRestController $controller;
    private OrderRepository $repository;
    private int $customer_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OrderRestController();
        $this->repository = new OrderRepository();

        // Create a test customer
        $this->customer_id = wp_insert_post([
            'post_title' => 'Test Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Initialize WordPress REST server
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init');

        // Register REST routes during rest_api_init action
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');

        // Set up admin user for permission tests
        wp_set_current_user(1);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $orders = $this->repository->getAll();
        foreach ($orders as $order) {
            wp_delete_post($order->id, true);
        }

        wp_delete_post($this->customer_id, true);

        parent::tearDown();
    }

    /* ==========================================
     * Integration Tests - Full CRUD Flow
     * ========================================== */

    public function test_full_order_lifecycle(): void
    {
        // 1. Create Order
        $create_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 101,
                    'product_name' => 'Margherita Pizza',
                    'quantity' => 2,
                    'unit_price' => 28.50,
                    'modifications' => ['Extra cheese', 'Thin crust'],
                    'notes' => 'Well done',
                ],
                [
                    'product_id' => 102,
                    'product_name' => 'Caesar Salad',
                    'quantity' => 1,
                    'unit_price' => 18.00,
                ],
            ],
            'delivery_fee' => 5.00,
            'notes' => 'Please ring doorbell',
            'delivery_address' => '123 Test Street, Test City',
            'special_instructions' => 'Leave at door if no answer',
        ];

        foreach ($order_data as $key => $value) {
            $create_request->set_param($key, $value);
        }

        $create_response = $this->controller->create_order($create_request);
        $this->assertNotInstanceOf(\WP_Error::class, $create_response);
        $this->assertEquals(201, $create_response->get_status());

        $created_order = $create_response->get_data();
        $order_id = $created_order['id'];

        // Verify created order structure
        $this->assertEquals($this->customer_id, $created_order['customer_id']);
        $this->assertEquals(Order::STATUS_PENDING, $created_order['status']);
        $this->assertCount(2, $created_order['order_items']);
        $this->assertEquals(75.0, $created_order['subtotal']); // 2*28.5 + 18 = 75
        $this->assertEquals(12.75, $created_order['tax_amount']); // 75 * 0.17 = 12.75
        $this->assertEquals(5.0, $created_order['delivery_fee']);
        $this->assertEquals(92.75, $created_order['total_amount']); // 75 + 12.75 + 5 = 92.75

        // 2. Get Order
        $get_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $get_request->set_url_params(['id' => (string)$order_id]);

        $get_response = $this->controller->get_order($get_request);
        $this->assertNotInstanceOf(\WP_Error::class, $get_response);

        $retrieved_order = $get_response->get_data();
        $this->assertEquals($order_id, $retrieved_order['id']);
        $this->assertEquals('Margherita Pizza', $retrieved_order['order_items'][0]['product_name']);

        // 3. Update Order Status
        $status_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $status_request->set_url_params(['id' => (string)$order_id]);
        $status_request->set_param('status', Order::STATUS_CONFIRMED);

        $status_response = $this->controller->update_order_status($status_request);
        $this->assertNotInstanceOf(\WP_Error::class, $status_response);

        $updated_order = $status_response->get_data();
        $this->assertEquals(Order::STATUS_CONFIRMED, $updated_order['status']);

        // 4. Update Payment Status
        $payment_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $payment_request->set_url_params(['id' => (string)$order_id]);
        $payment_request->set_param('payment_status', Order::PAYMENT_PAID);

        $payment_response = $this->controller->update_payment_status($payment_request);
        $this->assertNotInstanceOf(\WP_Error::class, $payment_response);

        $paid_order = $payment_response->get_data();
        $this->assertEquals(Order::PAYMENT_PAID, $paid_order['payment_status']);

        // 5. Add Order Item
        $item_request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $item_request->set_url_params(['id' => (string)$order_id]);
        $item_request->set_param('product_id', 103);
        $item_request->set_param('product_name', 'Garlic Bread');
        $item_request->set_param('quantity', 1);
        $item_request->set_param('unit_price', 8.50);

        $item_response = $this->controller->add_order_item($item_request);
        $this->assertNotInstanceOf(\WP_Error::class, $item_response);

        $updated_order_with_item = $item_response->get_data();
        $this->assertCount(3, $updated_order_with_item['order_items']);

        // 6. Get Orders Collection with Filters
        $list_request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $list_request->set_param('customer_id', $this->customer_id);
        $list_request->set_param('status', Order::STATUS_CONFIRMED);

        $list_response = $this->controller->get_orders($list_request);
        $this->assertNotInstanceOf(\WP_Error::class, $list_response);

        $orders_list = $list_response->get_data();
        $this->assertCount(1, $orders_list);
        $this->assertEquals($order_id, $orders_list[0]['id']);

        // 7. Delete Order (should fail due to paid status)
        $delete_request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
        $delete_request->set_url_params(['id' => (string)$order_id]);

        $delete_response = $this->controller->delete_order($delete_request);
        $this->assertInstanceOf(\WP_Error::class, $delete_response);
        $this->assertEquals('order_delete_restricted', $delete_response->get_error_code());

        // 8. Force Delete Order
        $force_delete_request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
        $force_delete_request->set_url_params(['id' => (string)$order_id]);
        $force_delete_request->set_param('force', true);

        $force_delete_response = $this->controller->delete_order($force_delete_request);
        $this->assertNotInstanceOf(\WP_Error::class, $force_delete_response);

        $delete_result = $force_delete_response->get_data();
        $this->assertTrue($delete_result['deleted']);
    }

    /* ==========================================
     * Analytics Integration Tests
     * ========================================== */

    public function test_order_statistics_integration(): void
    {
        // Create multiple test orders
        $orders_data = [
            [
                'customer_id' => $this->customer_id,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_PAID,
                'total_amount' => 50.0,
            ],
            [
                'customer_id' => $this->customer_id,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_PAID,
                'total_amount' => 75.0,
            ],
            [
                'customer_id' => $this->customer_id,
                'status' => Order::STATUS_CANCELLED,
                'payment_status' => Order::PAYMENT_REFUNDED,
                'total_amount' => 30.0,
            ],
        ];

        $order_ids = [];
        foreach ($orders_data as $data) {
            $data['order_items'] = [
                [
                    'product_id' => 101,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => $data['total_amount'],
                ]
            ];

            $order_id = $this->repository->create($data);
            $order_ids[] = $order_id;

            // Update status and payment status
            $this->repository->updateStatus($order_id, $data['status']);
            $this->repository->updatePaymentStatus($order_id, $data['payment_status']);
        }

        // Test statistics endpoint
        $stats_request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
        $stats_request->set_param('customer_id', $this->customer_id);

        $stats_response = $this->controller->get_order_statistics($stats_request);
        $this->assertNotInstanceOf(\WP_Error::class, $stats_response);

        $stats = $stats_response->get_data();
        $this->assertEquals(3, $stats['total_orders']);
        $this->assertEquals(155.0, $stats['total_revenue']); // 50 + 75 + 30
        $this->assertEquals(51.67, round($stats['average_order_value'], 2)); // 155/3

        $this->assertArrayHasKey('status_breakdown', $stats);
        $this->assertEquals(2, $stats['status_breakdown'][Order::STATUS_COMPLETED]);
        $this->assertEquals(1, $stats['status_breakdown'][Order::STATUS_CANCELLED]);

        // Clean up
        foreach ($order_ids as $order_id) {
            $this->repository->delete($order_id, true);
        }
    }

    public function test_revenue_analytics_integration(): void
    {
        // Create orders with different dates
        $test_orders = [
            ['date' => '2023-01-01', 'total' => 100.0],
            ['date' => '2023-01-01', 'total' => 150.0],
            ['date' => '2023-01-02', 'total' => 200.0],
            ['date' => '2023-01-03', 'total' => 120.0],
        ];

        $order_ids = [];
        foreach ($test_orders as $order_data) {
            $data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 101,
                        'product_name' => 'Test Product',
                        'quantity' => 1,
                        'unit_price' => $order_data['total'],
                    ]
                ],
                'total_amount' => $order_data['total'],
            ];

            $order_id = $this->repository->create($data);
            $order_ids[] = $order_id;

            // Update the order date
            wp_update_post([
                'ID' => $order_id,
                'post_date' => $order_data['date'] . ' 12:00:00',
                'post_date_gmt' => get_gmt_from_date($order_data['date'] . ' 12:00:00'),
            ]);
        }

        // Test revenue analytics endpoint
        $revenue_request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
        $revenue_request->set_param('period', 'daily');
        $revenue_request->set_param('date_from', '2023-01-01');
        $revenue_request->set_param('date_to', '2023-01-03');

        $revenue_response = $this->controller->get_revenue_analytics($revenue_request);
        $this->assertNotInstanceOf(\WP_Error::class, $revenue_response);

        $revenue_data = $revenue_response->get_data();
        $this->assertIsArray($revenue_data);
        $this->assertNotEmpty($revenue_data);

        // Verify daily grouping
        $by_date = [];
        foreach ($revenue_data as $day_data) {
            $by_date[$day_data['period']] = $day_data;
        }

        $this->assertArrayHasKey('2023-01-01', $by_date);
        $this->assertEquals(250.0, $by_date['2023-01-01']['revenue']); // 100 + 150
        $this->assertEquals(2, $by_date['2023-01-01']['orders']);

        $this->assertArrayHasKey('2023-01-02', $by_date);
        $this->assertEquals(200.0, $by_date['2023-01-02']['revenue']);
        $this->assertEquals(1, $by_date['2023-01-02']['orders']);

        // Clean up
        foreach ($order_ids as $order_id) {
            $this->repository->delete($order_id, true);
        }
    }

    /* ==========================================
     * Customer Orders Integration Test
     * ========================================== */

    public function test_customer_orders_integration(): void
    {
        // Create another customer for comparison
        $customer2_id = wp_insert_post([
            'post_title' => 'Test Customer 2',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Create orders for both customers
        $order_ids = [];

        // Orders for customer 1
        for ($i = 0; $i < 3; $i++) {
            $data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 101,
                        'product_name' => 'Test Product',
                        'quantity' => 1,
                        'unit_price' => 50.0,
                    ]
                ],
                'total_amount' => 50.0,
            ];

            $order_ids[] = $this->repository->create($data);
        }

        // Orders for customer 2
        for ($i = 0; $i < 2; $i++) {
            $data = [
                'customer_id' => $customer2_id,
                'order_items' => [
                    [
                        'product_id' => 102,
                        'product_name' => 'Test Product 2',
                        'quantity' => 1,
                        'unit_price' => 30.0,
                    ]
                ],
                'total_amount' => 30.0,
            ];

            $order_ids[] = $this->repository->create($data);
        }

        // Test customer orders endpoint
        $customer_orders_request = new WP_REST_Request('GET', "/squidly/v1/orders/customer/{$this->customer_id}");
        $customer_orders_request->set_url_params(['customer_id' => (string)$this->customer_id]);

        $customer_orders_response = $this->controller->get_customer_orders($customer_orders_request);
        $this->assertNotInstanceOf(\WP_Error::class, $customer_orders_response);

        $customer_orders = $customer_orders_response->get_data();
        $this->assertCount(3, $customer_orders);

        // Verify all orders belong to the correct customer
        foreach ($customer_orders as $order) {
            $this->assertEquals($this->customer_id, $order['customer_id']);
        }

        // Clean up
        foreach ($order_ids as $order_id) {
            $this->repository->delete($order_id, true);
        }
        wp_delete_post($customer2_id, true);
    }

    /* ==========================================
     * Popular Items Integration Test
     * ========================================== */

    public function test_popular_items_integration(): void
    {
        // Create orders with different items
        $orders_with_items = [
            [
                ['product_id' => 101, 'name' => 'Pizza', 'quantity' => 3],
                ['product_id' => 102, 'name' => 'Burger', 'quantity' => 1],
            ],
            [
                ['product_id' => 101, 'name' => 'Pizza', 'quantity' => 2],
                ['product_id' => 103, 'name' => 'Salad', 'quantity' => 1],
            ],
            [
                ['product_id' => 102, 'name' => 'Burger', 'quantity' => 2],
                ['product_id' => 103, 'name' => 'Salad', 'quantity' => 2],
            ],
        ];

        $order_ids = [];
        foreach ($orders_with_items as $order_items) {
            $items_data = [];
            foreach ($order_items as $item) {
                $items_data[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => 25.0,
                ];
            }

            $data = [
                'customer_id' => $this->customer_id,
                'order_items' => $items_data,
                'total_amount' => count($items_data) * 25.0,
            ];

            $order_ids[] = $this->repository->create($data);
        }

        // Test popular items endpoint
        $popular_request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
        $popular_request->set_param('limit', 3);
        // Ensure we capture the test orders with explicit date range
        $popular_request->set_param('date_from', date('Y-m-d', strtotime('-1 day')));
        $popular_request->set_param('date_to', date('Y-m-d', strtotime('+1 day')));

        $popular_response = $this->controller->get_popular_items($popular_request);
        $this->assertNotInstanceOf(\WP_Error::class, $popular_response);

        $popular_items = $popular_response->get_data();
        $this->assertCount(3, $popular_items);

        // Sort by quantity to verify ordering
        usort($popular_items, fn($a, $b) => $b['total_quantity'] - $a['total_quantity']);

        // Pizza should be most popular (3 + 2 = 5 total)
        $this->assertEquals(101, $popular_items[0]['product_id']);
        $this->assertEquals('Pizza', $popular_items[0]['product_name']);
        $this->assertEquals(5, $popular_items[0]['total_quantity']);

        // Burger and Salad should tie (1 + 2 = 3 each)
        $this->assertEquals(3, $popular_items[1]['total_quantity']);
        $this->assertEquals(3, $popular_items[2]['total_quantity']);

        // Clean up
        foreach ($order_ids as $order_id) {
            $this->repository->delete($order_id, true);
        }
    }

    /* ==========================================
     * Order Queue Integration Test
     * ========================================== */

    public function test_order_queue_integration(): void
    {
        // Create orders with different statuses
        $order_statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            Order::STATUS_COMPLETED,
        ];

        $order_ids = [];
        foreach ($order_statuses as $status) {
            $data = [
                'customer_id' => $this->customer_id,
                'status' => $status,
                'order_items' => [
                    [
                        'product_id' => 101,
                        'product_name' => 'Test Product',
                        'quantity' => 1,
                        'unit_price' => 25.0,
                    ]
                ],
                'total_amount' => 25.0,
            ];

            $order_id = $this->repository->create($data);
            $this->repository->updateStatus($order_id, $status);
            $order_ids[] = $order_id;
        }

        // Test queue endpoint (should only return confirmed and preparing)
        $queue_request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');

        $queue_response = $this->controller->get_order_queue($queue_request);
        $this->assertNotInstanceOf(\WP_Error::class, $queue_response);

        $queue_orders = $queue_response->get_data();
        $this->assertCount(2, $queue_orders); // Only confirmed and preparing

        $statuses_in_queue = array_column($queue_orders, 'status');
        $this->assertContains(Order::STATUS_CONFIRMED, $statuses_in_queue);
        $this->assertContains(Order::STATUS_PREPARING, $statuses_in_queue);

        // Verify queue order structure
        foreach ($queue_orders as $queue_order) {
            $this->assertArrayHasKey('estimated_ready_time', $queue_order);
            $this->assertArrayHasKey('special_instructions', $queue_order);
            $this->assertArrayHasKey('order_items', $queue_order);
        }

        // Clean up
        foreach ($order_ids as $order_id) {
            $this->repository->delete($order_id, true);
        }
    }

    /* ==========================================
     * Validation Integration Tests
     * ========================================== */

    public function test_create_order_validation_integration(): void
    {
        // Test missing customer_id
        $request1 = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request1->set_param('order_items', [
            [
                'product_id' => 101,
                'product_name' => 'Test',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response1 = $this->controller->create_order($request1);
        $this->assertInstanceOf(\WP_Error::class, $response1);
        $this->assertEquals('order_validation_error', $response1->get_error_code());

        // Test empty order items
        $request2 = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request2->set_param('customer_id', $this->customer_id);
        $request2->set_param('order_items', []);

        $response2 = $this->controller->create_order($request2);
        $this->assertInstanceOf(\WP_Error::class, $response2);
        $this->assertEquals('order_validation_error', $response2->get_error_code());

        // Test invalid quantity
        $request3 = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request3->set_param('customer_id', $this->customer_id);
        $request3->set_param('order_items', [
            [
                'product_id' => 101,
                'product_name' => 'Test',
                'quantity' => 0, // Invalid
                'unit_price' => 25.0,
            ]
        ]);

        $response3 = $this->controller->create_order($request3);
        $this->assertInstanceOf(\WP_Error::class, $response3);
        $this->assertEquals('order_validation_error', $response3->get_error_code());
    }

    /* ==========================================
     * Permission Integration Tests
     * ========================================== */

    public function test_permission_checks_integration(): void
    {
        // Test without authentication
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $can_read = $this->controller->get_orders_permissions_check($request);
        $this->assertFalse($can_read);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $can_create = $this->controller->create_order_permissions_check($request);
        $this->assertFalse($can_create);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/orders/123');
        $can_delete = $this->controller->delete_order_permissions_check($request);
        $this->assertFalse($can_delete);

        // Test with admin user
        wp_set_current_user(1);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $can_read = $this->controller->get_orders_permissions_check($request);
        $this->assertTrue($can_read);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $can_create = $this->controller->create_order_permissions_check($request);
        $this->assertTrue($can_create);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/orders/123');
        $can_delete = $this->controller->delete_order_permissions_check($request);
        $this->assertTrue($can_delete);
    }
}