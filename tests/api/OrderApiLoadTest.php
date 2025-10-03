<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Api;

use OrderRestController;
use OrderRepository;
use Order;
use WP_REST_Request;

/**
 * Load Testing for OrderRestController
 *
 * Tests API performance under heavy load to ensure it can handle
 * high-traffic scenarios typical in busy restaurant environments.
 */
class OrderApiLoadTest extends \WP_UnitTestCase
{
    private OrderRestController $controller;
    private OrderRepository $repository;
    private int $customer_id;
    private int $admin_user_id;
    private array $created_order_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OrderRestController();
        $this->repository = new OrderRepository();

        // Create test customer
        $this->customer_id = wp_insert_post([
            'post_title' => 'Load Test Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Create admin user
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);

        wp_set_current_user($this->admin_user_id);
    }

    protected function tearDown(): void
    {
        // Clean up test orders
        foreach ($this->created_order_ids as $order_id) {
            wp_delete_post($order_id, true);
        }

        wp_delete_post($this->customer_id, true);

        parent::tearDown();
    }

    /* ==========================================
     * High Volume Order Creation Tests
     * ========================================== */

    public function test_create_100_orders_sequentially(): void
    {
        $start_time = microtime(true);
        $successful_orders = 0;

        for ($i = 1; $i <= 100; $i++) {
            $request = new WP_REST_Request('POST', '/squidly/v1/orders');
            $request->set_param('customer_id', $this->customer_id);
            $request->set_param('order_items', [
                [
                    'product_id' => $i % 10 + 1, // Cycle through 10 products
                    'product_name' => "Load Test Product " . ($i % 10 + 1),
                    'quantity' => rand(1, 3),
                    'unit_price' => rand(1000, 5000) / 100, // $10-$50
                ]
            ]);
            $request->set_param('notes', "Load test order #{$i}");

            $response = $this->controller->create_order($request);

            if (!$response instanceof \WP_Error) {
                $successful_orders++;
                $order_data = $response->get_data();
                $this->created_order_ids[] = $order_data['id'];
            }
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds

        $this->assertEquals(100, $successful_orders);
        $this->assertLessThan(30000, $execution_time, 'Creating 100 orders should take less than 30 seconds');

        $average_time_per_order = $execution_time / 100;
        $this->assertLessThan(300, $average_time_per_order, 'Average order creation should be under 300ms');

        echo "\n📊 Sequential Order Creation: {$execution_time}ms for 100 orders ({$average_time_per_order}ms avg)\n";
    }

    public function test_concurrent_order_status_updates(): void
    {
        // Create 50 test orders
        $order_ids = [];
        for ($i = 0; $i < 50; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => $i + 1,
                        'product_name' => "Concurrent Test Product {$i}",
                        'quantity' => 1,
                        'unit_price' => 25.0,
                    ]
                ],
                'total_amount' => 25.0,
            ];

            $order_id = $this->repository->create($order_data);
            $order_ids[] = $order_id;
            $this->created_order_ids[] = $order_id;
        }

        $start_time = microtime(true);
        $successful_updates = 0;

        // Update all orders to confirmed status
        foreach ($order_ids as $order_id) {
            $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
            $request->set_url_params(['id' => (string)$order_id]);
            $request->set_param('status', Order::STATUS_CONFIRMED);

            $response = $this->controller->update_order_status($request);

            if (!$response instanceof \WP_Error) {
                $successful_updates++;
            }
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertEquals(50, $successful_updates);
        $this->assertLessThan(10000, $execution_time, 'Updating 50 order statuses should take less than 10 seconds');

        echo "\n⚡ Concurrent Status Updates: {$execution_time}ms for 50 updates\n";
    }

    public function test_bulk_item_additions(): void
    {
        // Create base order
        $order_id = $this->createTestOrder();

        $start_time = microtime(true);
        $successful_additions = 0;

        // Add 20 items to the order
        for ($i = 1; $i <= 20; $i++) {
            $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
            $request->set_url_params(['id' => (string)$order_id]);
            $request->set_param('product_id', 100 + $i);
            $request->set_param('product_name', "Bulk Addition Item {$i}");
            $request->set_param('quantity', 1);
            $request->set_param('unit_price', 5.0);

            $response = $this->controller->add_order_item($request);

            if (!$response instanceof \WP_Error) {
                $successful_additions++;
            }
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertEquals(20, $successful_additions);
        $this->assertLessThan(5000, $execution_time, 'Adding 20 items should take less than 5 seconds');

        // Verify final order has all items
        $get_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $get_request->set_url_params(['id' => (string)$order_id]);
        $get_response = $this->controller->get_order($get_request);

        $this->assertNotInstanceOf(\WP_Error::class, $get_response);
        $final_order = $get_response->get_data();
        $this->assertCount(21, $final_order['order_items']); // Original + 20 added

        echo "\n🛒 Bulk Item Additions: {$execution_time}ms for 20 items\n";
    }

    /* ==========================================
     * High Volume Data Retrieval Tests
     * ========================================== */

    public function test_retrieve_large_order_list_performance(): void
    {
        // Create 500 orders with varied data
        $this->createVariedTestOrders(500);

        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', 100); // Paginated request

        $response = $this->controller->get_orders($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertCount(100, $orders);

        $this->assertLessThan(3000, $execution_time, 'Retrieving 100 orders from 500 should take less than 3 seconds');

        echo "\n📋 Large List Retrieval: {$execution_time}ms for 100/500 orders\n";
    }

    public function test_complex_filtering_performance_under_load(): void
    {
        // Create 200 orders with specific patterns
        $this->createOrdersWithPatterns(200);

        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('status', Order::STATUS_COMPLETED);
        $request->set_param('payment_status', Order::PAYMENT_PAID);
        $request->set_param('date_from', date('Y-m-d', strtotime('-7 days')));
        $request->set_param('date_to', date('Y-m-d'));
        $request->set_param('total_min', 25.0);
        $request->set_param('total_max', 100.0);

        $response = $this->controller->get_orders($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $filtered_orders = $response->get_data();

        $this->assertLessThan(2000, $execution_time, 'Complex filtering should take less than 2 seconds');

        echo "\n🔍 Complex Filtering Load: {$execution_time}ms for filtering 200 orders\n";
    }

    /* ==========================================
     * Analytics Performance Under Load
     * ========================================== */

    public function test_statistics_calculation_with_large_dataset(): void
    {
        // Create 1000 orders across different time periods
        $this->createTimeSpreadOrders(1000);

        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
        $request->set_param('date_from', date('Y-m-d', strtotime('-90 days')));
        $request->set_param('date_to', date('Y-m-d'));

        $response = $this->controller->get_order_statistics($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $stats = $response->get_data();

        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('average_order_value', $stats);

        $this->assertLessThan(5000, $execution_time, 'Statistics calculation should take less than 5 seconds');

        echo "\n📈 Large Dataset Statistics: {$execution_time}ms for 1000 orders\n";
    }

    public function test_revenue_analytics_performance(): void
    {
        // Create 300 orders spread across 30 days
        $this->createDailySpreadOrders(300, 30);

        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
        $request->set_param('period', 'daily');
        $request->set_param('date_from', date('Y-m-d', strtotime('-30 days')));
        $request->set_param('date_to', date('Y-m-d'));

        $response = $this->controller->get_revenue_analytics($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $revenue_data = $response->get_data();
        $this->assertIsArray($revenue_data);

        $this->assertLessThan(3000, $execution_time, 'Revenue analytics should take less than 3 seconds');

        echo "\n💰 Revenue Analytics Load: {$execution_time}ms for 300 orders across 30 days\n";
    }

    public function test_popular_items_calculation_performance(): void
    {
        // Create 400 orders with 100 different products
        $this->createProductVarietyOrders(400, 100);

        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
        $request->set_param('limit', 25);
        $request->set_param('date_from', date('Y-m-d', strtotime('-30 days')));
        $request->set_param('date_to', date('Y-m-d'));

        $response = $this->controller->get_popular_items($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $popular_items = $response->get_data();
        $this->assertLessThanOrEqual(25, count($popular_items));

        $this->assertLessThan(2500, $execution_time, 'Popular items calculation should take less than 2.5 seconds');

        echo "\n🏆 Popular Items Load: {$execution_time}ms for 400 orders with 100 products\n";
    }

    /* ==========================================
     * Queue Performance Tests
     * ========================================== */

    public function test_kitchen_queue_performance_with_many_active_orders(): void
    {
        // Create 100 orders in various active states
        $active_statuses = [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING];

        for ($i = 0; $i < 100; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'status' => $active_statuses[$i % 2],
                'order_items' => [
                    [
                        'product_id' => $i + 1,
                        'product_name' => "Queue Test Product {$i}",
                        'quantity' => rand(1, 3),
                        'unit_price' => rand(1500, 4000) / 100,
                    ]
                ],
                'special_instructions' => "Queue test instructions {$i}",
            ];

            $order_id = $this->repository->create($order_data);
            $this->repository->updateStatus($order_id, $active_statuses[$i % 2]);
            $this->created_order_ids[] = $order_id;
        }

        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');

        $response = $this->controller->get_order_queue($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $queue_orders = $response->get_data();
        $this->assertCount(100, $queue_orders);

        $this->assertLessThan(2000, $execution_time, 'Queue retrieval should take less than 2 seconds');

        // Verify queue orders have required fields for kitchen display
        foreach ($queue_orders as $queue_order) {
            $this->assertArrayHasKey('estimated_ready_time', $queue_order);
            $this->assertArrayHasKey('special_instructions', $queue_order);
            $this->assertArrayHasKey('order_items', $queue_order);
        }

        echo "\n🍳 Kitchen Queue Load: {$execution_time}ms for 100 active orders\n";
    }

    /* ==========================================
     * Memory Usage Tests
     * ========================================== */

    public function test_memory_usage_under_load(): void
    {
        $initial_memory = memory_get_usage();

        // Create 200 orders with multiple items each
        for ($i = 0; $i < 200; $i++) {
            $items = [];
            $item_count = rand(2, 5);

            for ($j = 0; $j < $item_count; $j++) {
                $items[] = [
                    'product_id' => ($i * 10) + $j,
                    'product_name' => "Memory Test Product {$i}-{$j}",
                    'quantity' => rand(1, 3),
                    'unit_price' => rand(1000, 5000) / 100,
                ];
            }

            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => $items,
                'notes' => "Memory test order with {$item_count} items",
            ];

            $order_id = $this->repository->create($order_data);
            $this->created_order_ids[] = $order_id;
        }

        $after_creation_memory = memory_get_usage();
        $creation_memory_usage = ($after_creation_memory - $initial_memory) / 1024 / 1024; // MB

        // Retrieve all orders
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', 200);

        $response = $this->controller->get_orders($request);
        $this->assertNotInstanceOf(\WP_Error::class, $response);

        $after_retrieval_memory = memory_get_usage();
        $retrieval_memory_usage = ($after_retrieval_memory - $after_creation_memory) / 1024 / 1024; // MB

        $this->assertLessThan(150, $creation_memory_usage, 'Creating 200 multi-item orders should use less than 150MB');
        $this->assertLessThan(100, $retrieval_memory_usage, 'Retrieving 200 orders should use less than 100MB');

        echo "\n🧠 Memory Usage: Creation={$creation_memory_usage}MB, Retrieval={$retrieval_memory_usage}MB\n";
    }

    /* ==========================================
     * Stress Test Scenarios
     * ========================================== */

    public function test_mixed_operations_stress_test(): void
    {
        $start_time = microtime(true);
        $operations = 0;
        $errors = 0;

        // Perform mixed operations: create, read, update, add items
        for ($i = 0; $i < 50; $i++) {
            // Create order
            $create_response = $this->createOrderViaAPI();
            if ($create_response instanceof \WP_Error) {
                $errors++;
            } else {
                $order_data = $create_response->get_data();
                $order_id = $order_data['id'];
                $this->created_order_ids[] = $order_id;
                $operations++;

                // Update status
                $status_response = $this->updateOrderStatusViaAPI($order_id, Order::STATUS_CONFIRMED);
                if (!$status_response instanceof \WP_Error) {
                    $operations++;
                } else {
                    $errors++;
                }

                // Add item
                $item_response = $this->addItemViaAPI($order_id);
                if (!$item_response instanceof \WP_Error) {
                    $operations++;
                } else {
                    $errors++;
                }

                // Read order
                $read_response = $this->getOrderViaAPI($order_id);
                if (!$read_response instanceof \WP_Error) {
                    $operations++;
                } else {
                    $errors++;
                }
            }
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $total_operations = $operations + $errors;
        $success_rate = ($operations / $total_operations) * 100;
        $average_time_per_op = $execution_time / $total_operations;

        $this->assertGreaterThan(95, $success_rate, 'Success rate should be above 95%');
        $this->assertLessThan(100, $average_time_per_op, 'Average operation time should be under 100ms');

        echo "\n⚡ Mixed Operations Stress: {$total_operations} ops in {$execution_time}ms ";
        echo "({$success_rate}% success, {$average_time_per_op}ms avg)\n";
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    private function createTestOrder(): int
    {
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Load Test Base Order',
                    'quantity' => 1,
                    'unit_price' => 25.0,
                ]
            ],
            'total_amount' => 25.0,
        ];

        $order_id = $this->repository->create($order_data);
        $this->created_order_ids[] = $order_id;
        return $order_id;
    }

    private function createVariedTestOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => $i % 50 + 1,
                        'product_name' => "Varied Product " . ($i % 50 + 1),
                        'quantity' => rand(1, 4),
                        'unit_price' => rand(1000, 8000) / 100,
                    ]
                ],
                'delivery_fee' => ($i % 3 === 0) ? 5.0 : 0.0,
            ];

            $order_id = $this->repository->create($order_data);
            $this->created_order_ids[] = $order_id;
        }
    }

    private function createOrdersWithPatterns(int $count): void
    {
        $statuses = [Order::STATUS_COMPLETED, Order::STATUS_PENDING, Order::STATUS_CONFIRMED];
        $payment_statuses = [Order::PAYMENT_PAID, Order::PAYMENT_PENDING];

        for ($i = 0; $i < $count; $i++) {
            $total = rand(2500, 15000) / 100; // $25-$150

            $order_data = [
                'customer_id' => $this->customer_id,
                'status' => $statuses[$i % 3],
                'payment_status' => $payment_statuses[$i % 2],
                'total_amount' => $total,
                'order_items' => [
                    [
                        'product_id' => $i % 20 + 1,
                        'product_name' => "Pattern Product " . ($i % 20 + 1),
                        'quantity' => 1,
                        'unit_price' => $total,
                    ]
                ],
            ];

            $order_id = $this->repository->create($order_data);
            $this->repository->updateStatus($order_id, $order_data['status']);
            $this->repository->updatePaymentStatus($order_id, $order_data['payment_status']);
            $this->created_order_ids[] = $order_id;
        }
    }

    private function createTimeSpreadOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_PAID,
                'total_amount' => rand(2000, 10000) / 100,
                'order_items' => [
                    [
                        'product_id' => $i % 30 + 1,
                        'product_name' => "Time Spread Product " . ($i % 30 + 1),
                        'quantity' => 1,
                        'unit_price' => rand(2000, 10000) / 100,
                    ]
                ],
            ];

            $order_id = $this->repository->create($order_data);

            // Set random date within last 90 days
            $days_ago = rand(0, 90);
            $order_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            wp_update_post([
                'ID' => $order_id,
                'post_date' => $order_date,
                'post_date_gmt' => get_gmt_from_date($order_date),
            ]);

            $this->repository->updateStatus($order_id, Order::STATUS_COMPLETED);
            $this->repository->updatePaymentStatus($order_id, Order::PAYMENT_PAID);
            $this->created_order_ids[] = $order_id;
        }
    }

    private function createDailySpreadOrders(int $count, int $days): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_PAID,
                'total_amount' => rand(2000, 8000) / 100,
                'order_items' => [
                    [
                        'product_id' => $i % 20 + 1,
                        'product_name' => "Daily Product " . ($i % 20 + 1),
                        'quantity' => 1,
                        'unit_price' => rand(2000, 8000) / 100,
                    ]
                ],
            ];

            $order_id = $this->repository->create($order_data);

            // Spread evenly across the days
            $days_ago = ($i * $days) / $count;
            $order_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            wp_update_post([
                'ID' => $order_id,
                'post_date' => $order_date,
                'post_date_gmt' => get_gmt_from_date($order_date),
            ]);

            $this->repository->updateStatus($order_id, Order::STATUS_COMPLETED);
            $this->repository->updatePaymentStatus($order_id, Order::PAYMENT_PAID);
            $this->created_order_ids[] = $order_id;
        }
    }

    private function createProductVarietyOrders(int $order_count, int $product_variety): void
    {
        for ($i = 0; $i < $order_count; $i++) {
            $item_count = rand(1, 4);
            $items = [];

            for ($j = 0; $j < $item_count; $j++) {
                $product_id = rand(1, $product_variety);
                $items[] = [
                    'product_id' => $product_id,
                    'product_name' => "Variety Product {$product_id}",
                    'quantity' => rand(1, 3),
                    'unit_price' => rand(800, 4000) / 100,
                ];
            }

            $order_data = [
                'customer_id' => $this->customer_id,
                'status' => Order::STATUS_COMPLETED,
                'payment_status' => Order::PAYMENT_PAID,
                'order_items' => $items,
            ];

            $order_id = $this->repository->create($order_data);
            $this->repository->updateStatus($order_id, Order::STATUS_COMPLETED);
            $this->repository->updatePaymentStatus($order_id, Order::PAYMENT_PAID);
            $this->created_order_ids[] = $order_id;
        }
    }

    private function createOrderViaAPI()
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => rand(1, 100),
                'product_name' => 'Stress Test Product',
                'quantity' => rand(1, 3),
                'unit_price' => rand(1000, 5000) / 100,
            ]
        ]);

        return $this->controller->create_order($request);
    }

    private function updateOrderStatusViaAPI(int $order_id, string $status)
    {
        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('status', $status);

        return $this->controller->update_order_status($request);
    }

    private function addItemViaAPI(int $order_id)
    {
        $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('product_id', rand(200, 300));
        $request->set_param('product_name', 'Added Item');
        $request->set_param('quantity', 1);
        $request->set_param('unit_price', 10.0);

        return $this->controller->add_order_item($request);
    }

    private function getOrderViaAPI(int $order_id)
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);

        return $this->controller->get_order($request);
    }
}