<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Api;

use OrderRestController;
use OrderRepository;
use Order;
use WP_REST_Request;

/**
 * Edge Case Tests for OrderRestController
 *
 * Tests unusual scenarios, boundary conditions, and error edge cases
 * to ensure the API handles unexpected situations gracefully.
 */
class OrderApiEdgeCaseTest extends \WP_UnitTestCase
{
    private OrderRestController $controller;
    private OrderRepository $repository;
    private int $customer_id;
    private int $admin_user_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OrderRestController();
        $this->repository = new OrderRepository();

        // Create test customer
        $this->customer_id = wp_insert_post([
            'post_title' => 'Edge Case Test Customer',
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
        // Clean up test data
        $orders = $this->repository->getAll();
        foreach ($orders as $order) {
            wp_delete_post($order->id, true);
        }

        wp_delete_post($this->customer_id, true);

        parent::tearDown();
    }

    /* ==========================================
     * Boundary Value Tests
     * ========================================== */

    public function test_create_order_with_zero_price_item(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Free Sample',
                'quantity' => 1,
                'unit_price' => 0.0, // Zero price - should be allowed
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();
        $this->assertEquals(0.0, $created_order['order_items'][0]['unit_price']);
        $this->assertEquals(0.0, $created_order['subtotal']);
    }

    public function test_create_order_with_maximum_quantity(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Bulk Item',
                'quantity' => 999999, // Very large quantity
                'unit_price' => 0.01, // Small price to avoid overflow
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();
        $this->assertEquals(999999, $created_order['order_items'][0]['quantity']);
        $this->assertEquals(9999.99, $created_order['subtotal']); // 999999 * 0.01
    }

    public function test_create_order_with_very_small_price(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Cheap Item',
                'quantity' => 1,
                'unit_price' => 0.01, // One cent
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();
        $this->assertEquals(0.01, $created_order['order_items'][0]['unit_price']);
        $this->assertEquals(0.01, $created_order['subtotal']);
    }

    public function test_create_order_with_maximum_items(): void
    {
        $order_items = [];

        // Create 100 different items in one order
        for ($i = 1; $i <= 100; $i++) {
            $order_items[] = [
                'product_id' => $i,
                'product_name' => "Item {$i}",
                'quantity' => 1,
                'unit_price' => 1.0,
            ];
        }

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', $order_items);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();
        $this->assertCount(100, $created_order['order_items']);
        $this->assertEquals(100.0, $created_order['subtotal']);
    }

    /* ==========================================
     * Data Consistency Edge Cases
     * ========================================== */

    public function test_get_nonexistent_order(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/999999');
        $request->set_url_params(['id' => '999999']);

        $response = $this->controller->get_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    public function test_update_nonexistent_order(): void
    {
        $request = new WP_REST_Request('PUT', '/squidly/v1/orders/999999');
        $request->set_url_params(['id' => '999999']);
        $request->set_param('notes', 'Updated notes');

        $response = $this->controller->update_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    public function test_delete_nonexistent_order(): void
    {
        $request = new WP_REST_Request('DELETE', '/squidly/v1/orders/999999');
        $request->set_url_params(['id' => '999999']);

        $response = $this->controller->delete_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    public function test_add_item_to_nonexistent_order(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders/999999/items');
        $request->set_url_params(['id' => '999999']);
        $request->set_param('product_id', 123);
        $request->set_param('product_name', 'Test Item');
        $request->set_param('quantity', 1);
        $request->set_param('unit_price', 15.0);

        $response = $this->controller->add_order_item($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    /* ==========================================
     * State Transition Edge Cases
     * ========================================== */

    public function test_delete_completed_paid_order_without_force(): void
    {
        // Create order and set it as completed and paid
        $order_id = $this->createTestOrder();
        $this->repository->updateStatus($order_id, Order::STATUS_COMPLETED);
        $this->repository->updatePaymentStatus($order_id, Order::PAYMENT_PAID);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);

        $response = $this->controller->delete_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_delete_restricted', $response->get_error_code());
        $this->assertEquals(409, $response->get_error_data()['status']);
    }

    public function test_delete_completed_paid_order_with_force(): void
    {
        // Create order and set it as completed and paid
        $order_id = $this->createTestOrder();
        $this->repository->updateStatus($order_id, Order::STATUS_COMPLETED);
        $this->repository->updatePaymentStatus($order_id, Order::PAYMENT_PAID);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('force', true);

        $response = $this->controller->delete_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $delete_result = $response->get_data();
        $this->assertTrue($delete_result['deleted']);
    }

    public function test_rapid_status_changes(): void
    {
        $order_id = $this->createTestOrder();

        // Rapid succession of status changes
        $statuses = [
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            Order::STATUS_COMPLETED,
        ];

        foreach ($statuses as $status) {
            $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
            $request->set_url_params(['id' => (string)$order_id]);
            $request->set_param('status', $status);

            $response = $this->controller->update_order_status($request);
            $this->assertNotInstanceOf(\WP_Error::class, $response);

            $updated_order = $response->get_data();
            $this->assertEquals($status, $updated_order['status']);
        }
    }

    public function test_concurrent_item_additions(): void
    {
        $order_id = $this->createTestOrder();

        // Simulate concurrent item additions
        $items_to_add = [
            ['product_id' => 101, 'product_name' => 'Extra Cheese', 'quantity' => 1, 'unit_price' => 5.0],
            ['product_id' => 102, 'product_name' => 'Pepperoni', 'quantity' => 2, 'unit_price' => 3.0],
            ['product_id' => 103, 'product_name' => 'Mushrooms', 'quantity' => 1, 'unit_price' => 2.5],
        ];

        $final_order = null;
        foreach ($items_to_add as $item) {
            $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
            $request->set_url_params(['id' => (string)$order_id]);
            foreach ($item as $key => $value) {
                $request->set_param($key, $value);
            }

            $response = $this->controller->add_order_item($request);
            $this->assertNotInstanceOf(\WP_Error::class, $response);
            $final_order = $response->get_data();
        }

        // Should have original item + 3 added items = 4 total
        $this->assertCount(4, $final_order['order_items']);

        // Total should be recalculated: 25.0 (original) + 5.0 + 6.0 + 2.5 = 38.5 + tax + delivery
        $expected_subtotal = 25.0 + 5.0 + 6.0 + 2.5;
        $this->assertEquals($expected_subtotal, $final_order['subtotal']);
    }

    /* ==========================================
     * Malformed Request Edge Cases
     * ========================================== */

    public function test_create_order_with_null_values(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('notes', null);
        $request->set_param('delivery_address', null);
        $request->set_param('special_instructions', null);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
                'modifications' => null,
                'notes' => null,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();

        // Null values should be handled gracefully
        $this->assertIsString($created_order['notes']);
        $this->assertNull($created_order['delivery_address']);
        $this->assertNull($created_order['special_instructions']);
        $this->assertIsArray($created_order['order_items'][0]['modifications']);
        $this->assertNull($created_order['order_items'][0]['notes']);
    }

    public function test_update_order_with_empty_string_values(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('notes', '');
        $request->set_param('delivery_address', '');
        $request->set_param('special_instructions', '');

        $response = $this->controller->update_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $updated_order = $response->get_data();

        // Empty strings should be preserved
        $this->assertEquals('', $updated_order['notes']);
        $this->assertEquals('', $updated_order['delivery_address']);
        $this->assertEquals('', $updated_order['special_instructions']);
    }

    /* ==========================================
     * Performance Edge Cases
     * ========================================== */

    public function test_get_orders_with_extreme_pagination(): void
    {
        // Create some test orders
        for ($i = 0; $i < 10; $i++) {
            $this->createTestOrder();
        }

        // Test with extremely large per_page value
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', 999999);

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);
        // Should be limited by actual number of orders
        $this->assertLessThanOrEqual(10, count($orders));
    }

    public function test_get_orders_with_zero_per_page(): void
    {
        $this->createTestOrder();

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', 0);

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);
        // Should handle zero gracefully (probably return no results or use default)
    }

    public function test_get_orders_with_negative_per_page(): void
    {
        $this->createTestOrder();

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('per_page', -5);

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);
        // Should handle negative values gracefully
    }

    /* ==========================================
     * Date Range Edge Cases
     * ========================================== */

    public function test_get_orders_with_future_date_range(): void
    {
        $this->createTestOrder();

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('date_from', date('Y-m-d', strtotime('+1 year')));
        $request->set_param('date_to', date('Y-m-d', strtotime('+2 years')));

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);
        $this->assertEmpty($orders); // Should return no orders for future dates
    }

    public function test_get_orders_with_reversed_date_range(): void
    {
        $this->createTestOrder();

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('date_from', '2024-12-31');
        $request->set_param('date_to', '2024-01-01'); // From after To

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);
        // Should handle reversed range gracefully (probably return no results)
    }

    public function test_revenue_analytics_with_no_orders_in_range(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
        $request->set_param('period', 'daily');
        $request->set_param('date_from', '2020-01-01');
        $request->set_param('date_to', '2020-01-02');

        $response = $this->controller->get_revenue_analytics($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $revenue_data = $response->get_data();
        $this->assertIsArray($revenue_data);
        // Should return empty array or zero-filled data
    }

    /* ==========================================
     * Statistics Edge Cases
     * ========================================== */

    public function test_get_statistics_with_no_orders(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');

        $response = $this->controller->get_order_statistics($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $stats = $response->get_data();

        $this->assertEquals(0, $stats['total_orders']);
        $this->assertEquals(0.0, $stats['total_revenue']);
        $this->assertEquals(0, $stats['average_order_value']);
        $this->assertIsArray($stats['status_breakdown']);
        $this->assertIsArray($stats['payment_breakdown']);
    }

    public function test_popular_items_with_no_orders(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
        $request->set_param('limit', 10);

        $response = $this->controller->get_popular_items($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $popular_items = $response->get_data();
        $this->assertIsArray($popular_items);
        $this->assertEmpty($popular_items);
    }

    public function test_queue_with_no_active_orders(): void
    {
        // Create completed order that shouldn't appear in queue
        $order_id = $this->createTestOrder();
        $this->repository->updateStatus($order_id, Order::STATUS_COMPLETED);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');

        $response = $this->controller->get_order_queue($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $queue_orders = $response->get_data();
        $this->assertIsArray($queue_orders);
        $this->assertEmpty($queue_orders);
    }

    /* ==========================================
     * Float Precision Edge Cases
     * ========================================== */

    public function test_create_order_with_floating_point_precision(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Precision Test Item',
                'quantity' => 3,
                'unit_price' => 10.333333333333334, // Repeating decimal
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();

        // Check that floating point calculations are handled correctly
        $expected_item_total = 3 * 10.333333333333334;
        $this->assertEqualsWithDelta($expected_item_total, $created_order['order_items'][0]['total_price'], 0.01);
        $this->assertEqualsWithDelta($expected_item_total, $created_order['subtotal'], 0.01);
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
                    'product_name' => 'Edge Case Test Pizza',
                    'quantity' => 1,
                    'unit_price' => 25.0,
                ]
            ],
            'total_amount' => 25.0,
        ];

        return $this->repository->create($order_data);
    }
}