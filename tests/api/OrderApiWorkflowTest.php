<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Api;

use OrderRestController;
use OrderRepository;
use Order;
use WP_REST_Request;

/**
 * Real-World Workflow Testing for OrderRestController
 *
 * Tests complete restaurant operational scenarios from order placement
 * through completion, simulating realistic customer and staff interactions.
 */
class OrderApiWorkflowTest extends \WP_UnitTestCase
{
    private OrderRestController $controller;
    private OrderRepository $repository;
    private int $customer_id;
    private int $vip_customer_id;
    private int $admin_user_id;
    private int $kitchen_staff_id;
    private int $cashier_id;
    private array $created_order_ids = [];
    private array $test_products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OrderRestController();
        $this->repository = new OrderRepository();

        // Create test customers
        $this->customer_id = wp_insert_post([
            'post_title' => 'Workflow Test Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        $this->vip_customer_id = wp_insert_post([
            'post_title' => 'VIP Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Create staff users
        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        $this->kitchen_staff_id = $this->factory->user->create(['role' => 'shop_manager']);
        $this->cashier_id = $this->factory->user->create(['role' => 'shop_manager']);

        // Set up test products
        $this->test_products = [
            [
                'id' => 1,
                'name' => 'Classic Burger',
                'price' => 12.99,
                'preparation_time' => 8,
            ],
            [
                'id' => 2,
                'name' => 'Caesar Salad',
                'price' => 9.50,
                'preparation_time' => 5,
            ],
            [
                'id' => 3,
                'name' => 'Fish & Chips',
                'price' => 15.75,
                'preparation_time' => 12,
            ],
            [
                'id' => 4,
                'name' => 'Soft Drink',
                'price' => 2.50,
                'preparation_time' => 1,
            ],
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->created_order_ids as $order_id) {
            wp_delete_post($order_id, true);
        }

        wp_delete_post($this->customer_id, true);
        wp_delete_post($this->vip_customer_id, true);
        wp_set_current_user(0);

        parent::tearDown();
    }

    /* ==========================================
     * Complete Order Lifecycle Workflows
     * ========================================== */

    public function test_complete_dine_in_order_workflow(): void
    {
        wp_set_current_user($this->cashier_id);

        // Step 1: Customer places order at counter
        $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $order_request->set_param('customer_id', $this->customer_id);
        $order_request->set_param('order_type', 'dine_in');
        $order_request->set_param('table_number', 5);
        $order_request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Classic Burger',
                'quantity' => 1,
                'unit_price' => 12.99,
            ],
            [
                'product_id' => 4,
                'product_name' => 'Soft Drink',
                'quantity' => 1,
                'unit_price' => 2.50,
            ]
        ]);
        $order_request->set_param('special_instructions', 'No onions on burger');

        $create_response = $this->controller->create_order($order_request);
        $this->assertNotInstanceOf(\WP_Error::class, $create_response);

        $order_data = $create_response->get_data();
        $order_id = $order_data['id'];
        $this->created_order_ids[] = $order_id;

        $this->assertEquals(Order::STATUS_PENDING, $order_data['status']);
        $this->assertEquals(15.49, $order_data['total_amount']);

        // Step 2: Payment processed
        $payment_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $payment_request->set_url_params(['id' => (string)$order_id]);
        $payment_request->set_param('payment_status', Order::PAYMENT_PAID);
        $payment_request->set_param('payment_method', 'credit_card');

        $payment_response = $this->controller->update_payment_status($payment_request);
        $this->assertNotInstanceOf(\WP_Error::class, $payment_response);

        // Step 3: Order confirmed and sent to kitchen
        $confirm_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $confirm_request->set_url_params(['id' => (string)$order_id]);
        $confirm_request->set_param('status', Order::STATUS_CONFIRMED);

        $confirm_response = $this->controller->update_order_status($confirm_request);
        $this->assertNotInstanceOf(\WP_Error::class, $confirm_response);

        // Step 4: Kitchen staff sees order in queue
        wp_set_current_user($this->kitchen_staff_id);

        $queue_request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');
        $queue_response = $this->controller->get_order_queue($queue_request);
        $this->assertNotInstanceOf(\WP_Error::class, $queue_response);

        $queue_orders = $queue_response->get_data();
        $found_order = array_filter($queue_orders, fn($o) => $o['id'] == $order_id);
        $this->assertCount(1, $found_order);

        // Step 5: Kitchen starts preparing
        $preparing_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $preparing_request->set_url_params(['id' => (string)$order_id]);
        $preparing_request->set_param('status', Order::STATUS_PREPARING);

        $preparing_response = $this->controller->update_order_status($preparing_request);
        $this->assertNotInstanceOf(\WP_Error::class, $preparing_response);

        // Step 6: Order ready for pickup
        $ready_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $ready_request->set_url_params(['id' => (string)$order_id]);
        $ready_request->set_param('status', Order::STATUS_READY);

        $ready_response = $this->controller->update_order_status($ready_request);
        $this->assertNotInstanceOf(\WP_Error::class, $ready_response);

        // Step 7: Order delivered to customer
        $complete_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $complete_request->set_url_params(['id' => (string)$order_id]);
        $complete_request->set_param('status', Order::STATUS_COMPLETED);

        $complete_response = $this->controller->update_order_status($complete_request);
        $this->assertNotInstanceOf(\WP_Error::class, $complete_response);

        // Verify final state
        $final_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $final_request->set_url_params(['id' => (string)$order_id]);
        $final_response = $this->controller->get_order($final_request);

        $final_order = $final_response->get_data();
        $this->assertEquals(Order::STATUS_COMPLETED, $final_order['status']);
        $this->assertEquals(Order::PAYMENT_PAID, $final_order['payment_status']);
    }

    public function test_takeaway_order_with_modifications(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Step 1: Create takeaway order
        $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $order_request->set_param('customer_id', $this->customer_id);
        $order_request->set_param('order_type', 'takeaway');
        $order_request->set_param('order_items', [
            [
                'product_id' => 3,
                'product_name' => 'Fish & Chips',
                'quantity' => 2,
                'unit_price' => 15.75,
            ]
        ]);
        $order_request->set_param('estimated_pickup_time', date('Y-m-d H:i:s', strtotime('+20 minutes')));

        $create_response = $this->controller->create_order($order_request);
        $this->assertNotInstanceOf(\WP_Error::class, $create_response);

        $order_data = $create_response->get_data();
        $order_id = $order_data['id'];
        $this->created_order_ids[] = $order_id;

        // Step 2: Customer calls to add item before preparation
        $add_item_request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $add_item_request->set_url_params(['id' => (string)$order_id]);
        $add_item_request->set_param('product_id', 2);
        $add_item_request->set_param('product_name', 'Caesar Salad');
        $add_item_request->set_param('quantity', 1);
        $add_item_request->set_param('unit_price', 9.50);

        $add_response = $this->controller->add_order_item($add_item_request);
        $this->assertNotInstanceOf(\WP_Error::class, $add_response);

        // Step 3: Confirm and process order
        $confirm_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $confirm_request->set_url_params(['id' => (string)$order_id]);
        $confirm_request->set_param('status', Order::STATUS_CONFIRMED);

        $confirm_response = $this->controller->update_order_status($confirm_request);
        $this->assertNotInstanceOf(\WP_Error::class, $confirm_response);

        // Verify modified order total
        $verify_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $verify_request->set_url_params(['id' => (string)$order_id]);
        $verify_response = $this->controller->get_order($verify_request);

        $modified_order = $verify_response->get_data();
        $this->assertEquals(41.0, $modified_order['total_amount']); // 2 * 15.75 + 9.50
        $this->assertCount(2, $modified_order['order_items']);
    }

    public function test_delivery_order_workflow_with_address(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Step 1: Create delivery order
        $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $order_request->set_param('customer_id', $this->customer_id);
        $order_request->set_param('order_type', 'delivery');
        $order_request->set_param('delivery_address', '123 Main St, City, 12345');
        $order_request->set_param('delivery_fee', 3.99);
        $order_request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Classic Burger',
                'quantity' => 2,
                'unit_price' => 12.99,
            ],
            [
                'product_id' => 2,
                'product_name' => 'Caesar Salad',
                'quantity' => 1,
                'unit_price' => 9.50,
            ]
        ]);

        $create_response = $this->controller->create_order($order_request);
        $this->assertNotInstanceOf(\WP_Error::class, $create_response);

        $order_data = $create_response->get_data();
        $order_id = $order_data['id'];
        $this->created_order_ids[] = $order_id;

        // Verify delivery details
        $this->assertEquals('delivery', $order_data['order_type']);
        $this->assertEquals(39.47, $order_data['total_amount']); // (12.99*2 + 9.50) + 3.99

        // Step 2: Progress through delivery workflow
        $statuses = [
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            'out_for_delivery',
            Order::STATUS_COMPLETED
        ];

        foreach ($statuses as $status) {
            $status_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
            $status_request->set_url_params(['id' => (string)$order_id]);
            $status_request->set_param('status', $status);

            $status_response = $this->controller->update_order_status($status_request);
            $this->assertNotInstanceOf(\WP_Error::class, $status_response);
        }

        // Verify final delivery completion
        $final_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $final_request->set_url_params(['id' => (string)$order_id]);
        $final_response = $this->controller->get_order($final_request);

        $final_order = $final_response->get_data();
        $this->assertEquals(Order::STATUS_COMPLETED, $final_order['status']);
        $this->assertEquals('123 Main St, City, 12345', $final_order['delivery_address']);
    }

    /* ==========================================
     * Multi-Order Kitchen Queue Management
     * ========================================== */

    public function test_busy_kitchen_queue_management(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Create multiple orders at different times
        $order_ids = [];
        for ($i = 0; $i < 5; $i++) {
            $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
            $order_request->set_param('customer_id', $this->customer_id);
            $order_request->set_param('order_type', 'dine_in');
            $order_request->set_param('table_number', $i + 1);
            $order_request->set_param('order_items', [
                [
                    'product_id' => ($i % 3) + 1,
                    'product_name' => $this->test_products[$i % 3]['name'],
                    'quantity' => 1,
                    'unit_price' => $this->test_products[$i % 3]['price'],
                ]
            ]);

            $create_response = $this->controller->create_order($order_request);
            $order_data = $create_response->get_data();
            $order_ids[] = $order_data['id'];
            $this->created_order_ids[] = $order_data['id'];

            // Confirm each order
            $confirm_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_data['id']}/status");
            $confirm_request->set_url_params(['id' => (string)$order_data['id']]);
            $confirm_request->set_param('status', Order::STATUS_CONFIRMED);
            $this->controller->update_order_status($confirm_request);

            // Small delay to create different timestamps
            usleep(100000); // 0.1 second
        }

        // Kitchen staff checks queue
        wp_set_current_user($this->kitchen_staff_id);

        $queue_request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');
        $queue_response = $this->controller->get_order_queue($queue_request);
        $this->assertNotInstanceOf(\WP_Error::class, $queue_response);

        $queue_orders = $queue_response->get_data();
        $this->assertCount(5, $queue_orders);

        // Process orders based on preparation time priority
        foreach ($queue_orders as $queue_order) {
            // Start preparing
            $preparing_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$queue_order['id']}/status");
            $preparing_request->set_url_params(['id' => (string)$queue_order['id']]);
            $preparing_request->set_param('status', Order::STATUS_PREPARING);

            $preparing_response = $this->controller->update_order_status($preparing_request);
            $this->assertNotInstanceOf(\WP_Error::class, $preparing_response);

            // Mark as ready after "preparation"
            $ready_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$queue_order['id']}/status");
            $ready_request->set_url_params(['id' => (string)$queue_order['id']]);
            $ready_request->set_param('status', Order::STATUS_READY);

            $ready_response = $this->controller->update_order_status($ready_request);
            $this->assertNotInstanceOf(\WP_Error::class, $ready_response);
        }

        // Verify queue is now empty of preparing orders
        $final_queue_request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');
        $final_queue_response = $this->controller->get_order_queue($final_queue_request);
        $final_queue = $final_queue_response->get_data();

        // Should only contain orders in READY status (if queue includes ready orders)
        foreach ($final_queue as $order) {
            $this->assertNotEquals(Order::STATUS_CONFIRMED, $order['status']);
            $this->assertNotEquals(Order::STATUS_PREPARING, $order['status']);
        }
    }

    /* ==========================================
     * Customer Service Scenarios
     * ========================================== */

    public function test_customer_complaint_order_refund_workflow(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Step 1: Complete order initially
        $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $order_request->set_param('customer_id', $this->customer_id);
        $order_request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Classic Burger',
                'quantity' => 1,
                'unit_price' => 12.99,
            ]
        ]);

        $create_response = $this->controller->create_order($order_request);
        $order_data = $create_response->get_data();
        $order_id = $order_data['id'];
        $this->created_order_ids[] = $order_id;

        // Progress to completed
        $this->progressOrderToStatus($order_id, Order::STATUS_COMPLETED);
        $this->updateOrderPaymentStatus($order_id, Order::PAYMENT_PAID);

        // Step 2: Customer complains - mark as refunded
        $refund_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $refund_request->set_url_params(['id' => (string)$order_id]);
        $refund_request->set_param('payment_status', Order::PAYMENT_REFUNDED);
        $refund_request->set_param('refund_reason', 'Food quality complaint');

        $refund_response = $this->controller->update_payment_status($refund_request);
        $this->assertNotInstanceOf(\WP_Error::class, $refund_response);

        // Step 3: Update order notes for tracking
        $update_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $update_request->set_url_params(['id' => (string)$order_id]);
        $update_request->set_param('notes', 'REFUNDED: Customer complained about burger quality. Full refund processed.');

        $update_response = $this->controller->update_order($update_request);
        $this->assertNotInstanceOf(\WP_Error::class, $update_response);

        // Verify refund status
        $verify_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $verify_request->set_url_params(['id' => (string)$order_id]);
        $verify_response = $this->controller->get_order($verify_request);

        $refunded_order = $verify_response->get_data();
        $this->assertEquals(Order::PAYMENT_REFUNDED, $refunded_order['payment_status']);
        $this->assertStringContainsString('REFUNDED', $refunded_order['notes']);
    }

    public function test_vip_customer_priority_handling(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Step 1: Create VIP customer order
        $vip_order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $vip_order_request->set_param('customer_id', $this->vip_customer_id);
        $vip_order_request->set_param('order_type', 'dine_in');
        $vip_order_request->set_param('priority', 'high');
        $vip_order_request->set_param('order_items', [
            [
                'product_id' => 3,
                'product_name' => 'Fish & Chips',
                'quantity' => 1,
                'unit_price' => 15.75,
            ]
        ]);
        $vip_order_request->set_param('special_instructions', 'VIP Customer - Priority Service');

        $vip_response = $this->controller->create_order($vip_order_request);
        $vip_order_data = $vip_response->get_data();
        $vip_order_id = $vip_order_data['id'];
        $this->created_order_ids[] = $vip_order_id;

        // Step 2: Create regular customer order
        $regular_order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $regular_order_request->set_param('customer_id', $this->customer_id);
        $regular_order_request->set_param('order_type', 'dine_in');
        $regular_order_request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Classic Burger',
                'quantity' => 1,
                'unit_price' => 12.99,
            ]
        ]);

        $regular_response = $this->controller->create_order($regular_order_request);
        $regular_order_data = $regular_response->get_data();
        $regular_order_id = $regular_order_data['id'];
        $this->created_order_ids[] = $regular_order_id;

        // Confirm both orders
        $this->progressOrderToStatus($vip_order_id, Order::STATUS_CONFIRMED);
        $this->progressOrderToStatus($regular_order_id, Order::STATUS_CONFIRMED);

        // Step 3: Check queue ordering (VIP should be prioritized)
        $queue_request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');
        $queue_response = $this->controller->get_order_queue($queue_request);
        $queue_orders = $queue_response->get_data();

        $vip_order_in_queue = array_filter($queue_orders, fn($o) => $o['id'] == $vip_order_id);
        $regular_order_in_queue = array_filter($queue_orders, fn($o) => $o['id'] == $regular_order_id);

        $this->assertCount(1, $vip_order_in_queue);
        $this->assertCount(1, $regular_order_in_queue);

        $vip_order_queue_item = reset($vip_order_in_queue);
        $this->assertStringContainsString('VIP', $vip_order_queue_item['special_instructions']);
    }

    /* ==========================================
     * Analytics and Reporting Workflows
     * ========================================== */

    public function test_daily_operations_reporting(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Create various orders throughout a "day"
        $this->createDayOrders();

        // End-of-day manager checks statistics
        $stats_request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
        $stats_request->set_param('date_from', date('Y-m-d'));
        $stats_request->set_param('date_to', date('Y-m-d'));

        $stats_response = $this->controller->get_order_statistics($stats_request);
        $this->assertNotInstanceOf(\WP_Error::class, $stats_response);

        $stats = $stats_response->get_data();
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('average_order_value', $stats);
        $this->assertGreaterThan(0, $stats['total_orders']);
        $this->assertGreaterThan(0, $stats['total_revenue']);

        // Check popular items
        $popular_request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
        $popular_request->set_param('date_from', date('Y-m-d'));
        $popular_request->set_param('date_to', date('Y-m-d'));

        $popular_response = $this->controller->get_popular_items($popular_request);
        $this->assertNotInstanceOf(\WP_Error::class, $popular_response);

        $popular_items = $popular_response->get_data();
        $this->assertIsArray($popular_items);
        $this->assertGreaterThan(0, count($popular_items));

        // Each item should have required analytics data
        foreach ($popular_items as $item) {
            $this->assertArrayHasKey('product_name', $item);
            $this->assertArrayHasKey('total_quantity', $item);
            $this->assertArrayHasKey('total_revenue', $item);
        }
    }

    public function test_customer_history_analysis(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Create multiple orders for the same customer over time
        for ($i = 0; $i < 3; $i++) {
            $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
            $order_request->set_param('customer_id', $this->customer_id);
            $order_request->set_param('order_items', [
                [
                    'product_id' => ($i % 3) + 1,
                    'product_name' => $this->test_products[$i % 3]['name'],
                    'quantity' => 1,
                    'unit_price' => $this->test_products[$i % 3]['price'],
                ]
            ]);

            $create_response = $this->controller->create_order($order_request);
            $order_data = $create_response->get_data();
            $this->created_order_ids[] = $order_data['id'];

            // Complete each order
            $this->progressOrderToStatus($order_data['id'], Order::STATUS_COMPLETED);
            $this->updateOrderPaymentStatus($order_data['id'], Order::PAYMENT_PAID);
        }

        // Analyze customer order history
        $history_request = new WP_REST_Request('GET', "/squidly/v1/orders/customer/{$this->customer_id}");
        $history_request->set_url_params(['customer_id' => (string)$this->customer_id]);

        $history_response = $this->controller->get_customer_orders($history_request);
        $this->assertNotInstanceOf(\WP_Error::class, $history_response);

        $customer_orders = $history_response->get_data();
        $this->assertCount(3, $customer_orders);

        // Verify order progression and completeness
        foreach ($customer_orders as $order) {
            $this->assertEquals(Order::STATUS_COMPLETED, $order['status']);
            $this->assertEquals(Order::PAYMENT_PAID, $order['payment_status']);
            $this->assertEquals($this->customer_id, $order['customer_id']);
        }
    }

    /* ==========================================
     * Error Recovery Scenarios
     * ========================================== */

    public function test_order_cancellation_after_preparation_started(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Step 1: Create and start preparing order
        $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $order_request->set_param('customer_id', $this->customer_id);
        $order_request->set_param('order_items', [
            [
                'product_id' => 3,
                'product_name' => 'Fish & Chips',
                'quantity' => 1,
                'unit_price' => 15.75,
            ]
        ]);

        $create_response = $this->controller->create_order($order_request);
        $order_data = $create_response->get_data();
        $order_id = $order_data['id'];
        $this->created_order_ids[] = $order_id;

        // Progress to preparing
        $this->progressOrderToStatus($order_id, Order::STATUS_CONFIRMED);
        $this->progressOrderToStatus($order_id, Order::STATUS_PREPARING);

        // Step 2: Customer wants to cancel
        $cancel_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $cancel_request->set_url_params(['id' => (string)$order_id]);
        $cancel_request->set_param('status', Order::STATUS_CANCELLED);
        $cancel_request->set_param('cancellation_reason', 'Customer changed mind');

        $cancel_response = $this->controller->update_order_status($cancel_request);
        $this->assertNotInstanceOf(\WP_Error::class, $cancel_response);

        // Step 3: Handle partial refund
        $refund_request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $refund_request->set_url_params(['id' => (string)$order_id]);
        $refund_request->set_param('payment_status', Order::PAYMENT_PARTIALLY_REFUNDED);

        $refund_response = $this->controller->update_payment_status($refund_request);
        $this->assertNotInstanceOf(\WP_Error::class, $refund_response);

        // Verify cancellation state
        $verify_request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
        $verify_request->set_url_params(['id' => (string)$order_id]);
        $verify_response = $this->controller->get_order($verify_request);

        $cancelled_order = $verify_response->get_data();
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled_order['status']);
        $this->assertEquals(Order::PAYMENT_PARTIALLY_REFUNDED, $cancelled_order['payment_status']);
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    private function progressOrderToStatus(int $order_id, string $status): void
    {
        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('status', $status);

        $response = $this->controller->update_order_status($request);
        $this->assertNotInstanceOf(\WP_Error::class, $response);
    }

    private function updateOrderPaymentStatus(int $order_id, string $payment_status): void
    {
        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('payment_status', $payment_status);

        $response = $this->controller->update_payment_status($request);
        $this->assertNotInstanceOf(\WP_Error::class, $response);
    }

    private function createDayOrders(): void
    {
        $order_types = ['dine_in', 'takeaway', 'delivery'];
        $order_count = 0;

        for ($i = 0; $i < 15; $i++) {
            $items = [];
            $item_count = rand(1, 3);

            for ($j = 0; $j < $item_count; $j++) {
                $product = $this->test_products[array_rand($this->test_products)];
                $items[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'quantity' => rand(1, 2),
                    'unit_price' => $product['price'],
                ];
            }

            $order_request = new WP_REST_Request('POST', '/squidly/v1/orders');
            $order_request->set_param('customer_id', $this->customer_id);
            $order_request->set_param('order_type', $order_types[$i % 3]);
            $order_request->set_param('order_items', $items);

            if ($order_types[$i % 3] === 'delivery') {
                $order_request->set_param('delivery_fee', 3.99);
                $order_request->set_param('delivery_address', 'Test Address ' . $i);
            }

            $create_response = $this->controller->create_order($order_request);
            if (!$create_response instanceof \WP_Error) {
                $order_data = $create_response->get_data();
                $order_id = $order_data['id'];
                $this->created_order_ids[] = $order_id;

                // Complete most orders
                if ($i < 12) {
                    $this->progressOrderToStatus($order_id, Order::STATUS_COMPLETED);
                    $this->updateOrderPaymentStatus($order_id, Order::PAYMENT_PAID);
                }
                $order_count++;
            }
        }
    }
}