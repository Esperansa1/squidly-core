<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Performance;

use OrderRestController;
use OrderRepository;
use WP_REST_Request;

/**
 * Performance tests for OrderRestController
 *
 * Tests API performance under various load conditions to ensure
 * efficient database operations and response times.
 */
class OrderRestControllerPerformanceTest extends \WP_UnitTestCase
{
    private OrderRestController $controller;
    private OrderRepository $repository;
    private int $customer_id;
    private array $created_order_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OrderRestController();
        $this->repository = new OrderRepository();

        // Create test customer
        $this->customer_id = wp_insert_post([
            'post_title' => 'Performance Test Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Set up admin user
        wp_set_current_user(1);
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
     * Performance Benchmarks
     * ========================================== */

    public function test_large_order_collection_performance(): void
    {
        // Create 1000 test orders
        $this->createTestOrders(1000);

        // Benchmark: Get all orders
        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', 100); // Paginate for performance

        $response = $this->controller->get_orders($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertLessThan(2000, $execution_time, 'Getting 100 orders from 1000 should take less than 2 seconds');

        $data = $response->get_data();
        $this->assertCount(100, $data);

        echo "\n📊 Large Collection Performance: {$execution_time}ms for 100 orders from 1000 total\n";
    }

    public function test_complex_filter_performance(): void
    {
        // Create orders with various statuses and payment states
        $this->createTestOrdersWithVariedData(500);

        // Benchmark: Complex filtering
        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('status', 'completed');
        $request->set_param('payment_status', 'paid');
        $request->set_param('date_from', date('Y-m-d', strtotime('-30 days')));
        $request->set_param('date_to', date('Y-m-d'));
        $request->set_param('total_min', 50.0);
        $request->set_param('total_max', 200.0);

        $response = $this->controller->get_orders($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertLessThan(1500, $execution_time, 'Complex filtering should take less than 1.5 seconds');

        echo "\n🔍 Complex Filter Performance: {$execution_time}ms for multi-criteria filtering\n";
    }

    public function test_statistics_calculation_performance(): void
    {
        // Create varied dataset
        $this->createTestOrdersWithVariedData(800);

        // Benchmark: Statistics calculation
        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
        $request->set_param('date_from', date('Y-m-d', strtotime('-90 days')));
        $request->set_param('date_to', date('Y-m-d'));

        $response = $this->controller->get_order_statistics($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertLessThan(3000, $execution_time, 'Statistics calculation should take less than 3 seconds');

        $stats = $response->get_data();
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('status_breakdown', $stats);

        echo "\n📈 Statistics Performance: {$execution_time}ms for 800 orders analysis\n";
    }

    public function test_revenue_analytics_performance(): void
    {
        // Create orders across different dates
        $this->createTestOrdersAcrossDateRange(600, 90); // 600 orders across 90 days

        // Benchmark: Revenue analytics
        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
        $request->set_param('period', 'daily');
        $request->set_param('date_from', date('Y-m-d', strtotime('-90 days')));
        $request->set_param('date_to', date('Y-m-d'));

        $response = $this->controller->get_revenue_analytics($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertLessThan(2500, $execution_time, 'Revenue analytics should take less than 2.5 seconds');

        $revenue_data = $response->get_data();
        $this->assertIsArray($revenue_data);
        $this->assertNotEmpty($revenue_data);

        echo "\n💰 Revenue Analytics Performance: {$execution_time}ms for 90-day daily breakdown\n";
    }

    public function test_popular_items_calculation_performance(): void
    {
        // Create orders with many different items
        $this->createTestOrdersWithManyItems(400, 50); // 400 orders with 50 different products

        // Benchmark: Popular items calculation
        $start_time = microtime(true);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
        $request->set_param('date_from', date('Y-m-d', strtotime('-30 days')));
        $request->set_param('date_to', date('Y-m-d'));
        $request->set_param('limit', 20);

        $response = $this->controller->get_popular_items($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertLessThan(2000, $execution_time, 'Popular items calculation should take less than 2 seconds');

        $popular_items = $response->get_data();
        $this->assertIsArray($popular_items);
        $this->assertLessThanOrEqual(20, count($popular_items));

        echo "\n🏆 Popular Items Performance: {$execution_time}ms for analyzing 400 orders with 50 products\n";
    }

    public function test_large_order_creation_performance(): void
    {
        // Benchmark: Creating order with many items
        $large_order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => []
        ];

        // Add 20 items to the order
        for ($i = 1; $i <= 20; $i++) {
            $large_order_data['order_items'][] = [
                'product_id' => 1000 + $i,
                'product_name' => "Performance Test Product {$i}",
                'quantity' => rand(1, 3),
                'unit_price' => rand(1000, 5000) / 100, // $10-$50
                'modifications' => $i % 3 === 0 ? ['Extra sauce', 'No onions'] : [],
                'notes' => $i % 5 === 0 ? "Special instructions for item {$i}" : null,
            ];
        }

        $start_time = microtime(true);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        foreach ($large_order_data as $key => $value) {
            $request->set_param($key, $value);
        }

        $response = $this->controller->create_order($request);

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(201, $response->get_status());
        $this->assertLessThan(1000, $execution_time, 'Creating order with 20 items should take less than 1 second');

        $created_order = $response->get_data();
        $this->assertCount(20, $created_order['order_items']);
        $this->created_order_ids[] = $created_order['id'];

        echo "\n🛒 Large Order Creation Performance: {$execution_time}ms for order with 20 items\n";
    }

    public function test_concurrent_order_updates_performance(): void
    {
        // Create test orders
        $order_ids = [];
        for ($i = 0; $i < 50; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 2000 + $i,
                        'product_name' => "Concurrent Test Product {$i}",
                        'quantity' => 1,
                        'unit_price' => 25.0,
                    ]
                ],
            ];

            $order_id = $this->repository->create($order_data);
            $order_ids[] = $order_id;
            $this->created_order_ids[] = $order_id;
        }

        // Benchmark: Multiple status updates
        $start_time = microtime(true);

        foreach ($order_ids as $order_id) {
            $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
            $request->set_url_params(['id' => (string)$order_id]);
            $request->set_param('status', 'confirmed');

            $response = $this->controller->update_order_status($request);
            $this->assertNotInstanceOf(\WP_Error::class, $response);
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        $this->assertLessThan(5000, $execution_time, 'Updating 50 order statuses should take less than 5 seconds');

        echo "\n⚡ Concurrent Updates Performance: {$execution_time}ms for 50 status updates\n";
    }

    public function test_memory_usage_with_large_dataset(): void
    {
        $initial_memory = memory_get_usage();

        // Create dataset and measure memory usage
        $this->createTestOrders(500);

        $after_creation_memory = memory_get_usage();
        $creation_memory_usage = ($after_creation_memory - $initial_memory) / 1024 / 1024; // MB

        // Load all orders and measure
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', 500);

        $response = $this->controller->get_orders($request);
        $this->assertNotInstanceOf(\WP_Error::class, $response);

        $after_loading_memory = memory_get_usage();
        $loading_memory_usage = ($after_loading_memory - $after_creation_memory) / 1024 / 1024; // MB

        $this->assertLessThan(100, $creation_memory_usage, 'Creating 500 orders should use less than 100MB');
        $this->assertLessThan(50, $loading_memory_usage, 'Loading 500 orders should use less than 50MB');

        echo "\n🧠 Memory Usage: Creation={$creation_memory_usage}MB, Loading={$loading_memory_usage}MB\n";
    }

    /* ==========================================
     * Helper Methods for Test Data Creation
     * ========================================== */

    private function createTestOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 100 + ($i % 20), // Vary products
                        'product_name' => 'Performance Test Product ' . ($i % 20),
                        'quantity' => rand(1, 3),
                        'unit_price' => rand(1000, 5000) / 100, // $10-$50
                    ]
                ],
                'delivery_fee' => ($i % 3 === 0) ? 5.0 : 0.0,
                'notes' => ($i % 10 === 0) ? "Performance test order {$i}" : '',
            ];

            $order_id = $this->repository->create($order_data);
            $this->created_order_ids[] = $order_id;
        }
    }

    private function createTestOrdersWithVariedData(int $count): void
    {
        $statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
        $payment_statuses = ['pending', 'paid', 'failed', 'refunded'];
        $payment_methods = ['cash', 'card', 'online'];

        for ($i = 0; $i < $count; $i++) {
            $total_amount = rand(2000, 25000) / 100; // $20-$250

            $order_data = [
                'customer_id' => $this->customer_id,
                'status' => $statuses[$i % count($statuses)],
                'payment_status' => $payment_statuses[$i % count($payment_statuses)],
                'payment_method' => $payment_methods[$i % count($payment_methods)],
                'total_amount' => $total_amount,
                'order_items' => [
                    [
                        'product_id' => 200 + ($i % 30),
                        'product_name' => 'Varied Test Product ' . ($i % 30),
                        'quantity' => rand(1, 4),
                        'unit_price' => $total_amount / rand(1, 3), // Divide total among items
                    ]
                ],
                'delivery_fee' => ($i % 4 === 0) ? rand(300, 1000) / 100 : 0.0, // $3-$10
            ];

            $order_id = $this->repository->create($order_data);
            $this->created_order_ids[] = $order_id;

            // Update status and payment status
            if ($order_data['status'] !== 'pending') {
                $this->repository->updateStatus($order_id, $order_data['status']);
            }
            if ($order_data['payment_status'] !== 'pending') {
                $this->repository->updatePaymentStatus($order_id, $order_data['payment_status']);
            }
        }
    }

    private function createTestOrdersAcrossDateRange(int $count, int $days): void
    {
        for ($i = 0; $i < $count; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 300 + ($i % 25),
                        'product_name' => 'Date Range Product ' . ($i % 25),
                        'quantity' => rand(1, 2),
                        'unit_price' => rand(1500, 8000) / 100, // $15-$80
                    ]
                ],
                'total_amount' => rand(2000, 10000) / 100, // $20-$100
                'status' => 'completed',
                'payment_status' => 'paid',
            ];

            $order_id = $this->repository->create($order_data);
            $this->created_order_ids[] = $order_id;

            // Set random date within range
            $days_ago = rand(0, $days);
            $order_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));

            wp_update_post([
                'ID' => $order_id,
                'post_date' => $order_date,
                'post_date_gmt' => get_gmt_from_date($order_date),
            ]);

            // Update status
            $this->repository->updateStatus($order_id, 'completed');
            $this->repository->updatePaymentStatus($order_id, 'paid');
        }
    }

    private function createTestOrdersWithManyItems(int $order_count, int $product_variety): void
    {
        for ($i = 0; $i < $order_count; $i++) {
            // Each order has 1-5 items
            $item_count = rand(1, 5);
            $order_items = [];

            for ($j = 0; $j < $item_count; $j++) {
                $product_id = 400 + ($i * $item_count + $j) % $product_variety;
                $order_items[] = [
                    'product_id' => $product_id,
                    'product_name' => 'Multi-Item Product ' . ($product_id % $product_variety),
                    'quantity' => rand(1, 3),
                    'unit_price' => rand(800, 4000) / 100, // $8-$40
                ];
            }

            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => $order_items,
                'status' => 'completed',
                'payment_status' => 'paid',
            ];

            $order_id = $this->repository->create($order_data);
            $this->created_order_ids[] = $order_id;

            // Update status
            $this->repository->updateStatus($order_id, 'completed');
            $this->repository->updatePaymentStatus($order_id, 'paid');
        }
    }
}