<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\E2E;

use OrderRestController;

/**
 * End-to-End tests for OrderRestController
 *
 * Tests the complete REST API through HTTP requests to ensure
 * real-world functionality works as expected.
 */
class OrderRestControllerE2ETest extends \WP_UnitTestCase
{
    private string $rest_url;
    private int $admin_user_id;
    private int $customer_id;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up REST URL
        $this->rest_url = rest_url('squidly/v1/orders');

        // Create admin user
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);

        // Create test customer
        $this->customer_id = wp_insert_post([
            'post_title' => 'E2E Test Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Register REST controller
        $controller = new OrderRestController();
        $controller->register_routes();

        // Set current user for permissions
        wp_set_current_user($this->admin_user_id);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE post_type = 'squidly_order' AND post_title LIKE %s",
            '%E2E Test%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})"
        ));

        wp_delete_post($this->customer_id, true);

        parent::tearDown();
    }

    /* ==========================================
     * E2E Test Scenarios
     * ========================================== */

    public function test_complete_order_management_workflow(): void
    {
        // Scenario: Restaurant receives online order, processes it through completion

        // 1. Create order (simulating customer placing order)
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Margherita Pizza Large',
                    'quantity' => 2,
                    'unit_price' => 32.90,
                    'modifications' => ['Extra cheese', 'Thin crust'],
                    'notes' => 'Please make it crispy'
                ],
                [
                    'product_id' => 5,
                    'product_name' => 'Greek Salad',
                    'quantity' => 1,
                    'unit_price' => 18.50,
                    'modifications' => ['No olives'],
                ]
            ],
            'delivery_fee' => 8.90,
            'delivery_address' => '456 Oak Street, Downtown, City 12345',
            'special_instructions' => 'Apartment 3B, ring buzzer twice',
            'payment_method' => 'online',
            'notes' => 'Customer prefers contactless delivery'
        ];

        $create_response = $this->make_rest_request('POST', '', $order_data);
        $this->assertEquals(201, wp_remote_retrieve_response_code($create_response));

        $created_order = json_decode(wp_remote_retrieve_body($create_response), true);
        $order_id = $created_order['id'];

        // Verify order calculations
        $this->assertEquals(84.30, $created_order['subtotal']); // (32.90 * 2) + 18.50
        $this->assertEquals(14.33, $created_order['tax_amount']); // 84.30 * 0.17
        $this->assertEquals(8.90, $created_order['delivery_fee']);
        $this->assertEquals(107.53, $created_order['total_amount']); // 84.30 + 14.33 + 8.90
        $this->assertEquals('pending', $created_order['status']);
        $this->assertEquals('pending', $created_order['payment_status']);

        // 2. Payment processing (simulating successful payment)
        $payment_response = $this->make_rest_request('PUT', "/{$order_id}/payment", [
            'payment_status' => 'paid'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($payment_response));

        // 3. Restaurant confirms order
        $confirm_response = $this->make_rest_request('PUT', "/{$order_id}/status", [
            'status' => 'confirmed'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($confirm_response));

        // 4. Kitchen starts preparing
        $preparing_response = $this->make_rest_request('PUT', "/{$order_id}/status", [
            'status' => 'preparing'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($preparing_response));

        // 5. Check order appears in kitchen queue
        $queue_response = $this->make_rest_request('GET', '/queue');
        $this->assertEquals(200, wp_remote_retrieve_response_code($queue_response));

        $queue_orders = json_decode(wp_remote_retrieve_body($queue_response), true);
        $this->assertNotEmpty($queue_orders);

        $found_in_queue = false;
        foreach ($queue_orders as $queue_order) {
            if ($queue_order['id'] == $order_id) {
                $found_in_queue = true;
                $this->assertEquals('preparing', $queue_order['status']);
                $this->assertArrayHasKey('estimated_ready_time', $queue_order);
                break;
            }
        }
        $this->assertTrue($found_in_queue, 'Order should appear in kitchen queue');

        // 6. Order ready for pickup/delivery
        $ready_response = $this->make_rest_request('PUT', "/{$order_id}/status", [
            'status' => 'ready'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($ready_response));

        // 7. Order completed
        $complete_response = $this->make_rest_request('PUT', "/{$order_id}/status", [
            'status' => 'completed'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($complete_response));

        $completed_order = json_decode(wp_remote_retrieve_body($complete_response), true);
        $this->assertTrue($completed_order['is_completed']);
        $this->assertFalse($completed_order['can_be_cancelled']);

        // 8. Verify order no longer in queue
        $final_queue_response = $this->make_rest_request('GET', '/queue');
        $final_queue_orders = json_decode(wp_remote_retrieve_body($final_queue_response), true);

        $still_in_queue = false;
        foreach ($final_queue_orders as $queue_order) {
            if ($queue_order['id'] == $order_id) {
                $still_in_queue = true;
                break;
            }
        }
        $this->assertFalse($still_in_queue, 'Completed order should not appear in queue');
    }

    public function test_order_modification_scenario(): void
    {
        // Scenario: Customer places order, then adds items before kitchen starts

        // 1. Create initial order
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 10,
                    'product_name' => 'Cheeseburger',
                    'quantity' => 1,
                    'unit_price' => 24.90,
                ]
            ],
            'payment_method' => 'card',
        ];

        $create_response = $this->make_rest_request('POST', '', $order_data);
        $created_order = json_decode(wp_remote_retrieve_body($create_response), true);
        $order_id = $created_order['id'];

        // 2. Customer adds another item
        $add_item_response = $this->make_rest_request('POST', "/{$order_id}/items", [
            'product_id' => 11,
            'product_name' => 'French Fries',
            'quantity' => 1,
            'unit_price' => 12.50,
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($add_item_response));

        $updated_order = json_decode(wp_remote_retrieve_body($add_item_response), true);
        $this->assertCount(2, $updated_order['order_items']);
        $this->assertEquals(37.40, $updated_order['subtotal']); // 24.90 + 12.50

        // 3. Customer adds special instructions
        $update_response = $this->make_rest_request('PUT', "/{$order_id}", [
            'special_instructions' => 'Please make fries extra crispy'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($update_response));

        $final_order = json_decode(wp_remote_retrieve_body($update_response), true);
        $this->assertEquals('Please make fries extra crispy', $final_order['special_instructions']);
    }

    public function test_order_cancellation_scenario(): void
    {
        // Scenario: Customer needs to cancel order

        // 1. Create order
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 20,
                    'product_name' => 'Pasta Carbonara',
                    'quantity' => 1,
                    'unit_price' => 28.90,
                ]
            ],
        ];

        $create_response = $this->make_rest_request('POST', '', $order_data);
        $created_order = json_decode(wp_remote_retrieve_body($create_response), true);
        $order_id = $created_order['id'];

        // 2. Cancel order while still pending (should work)
        $cancel_response = $this->make_rest_request('PUT', "/{$order_id}/status", [
            'status' => 'cancelled'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($cancel_response));

        $cancelled_order = json_decode(wp_remote_retrieve_body($cancel_response), true);
        $this->assertEquals('cancelled', $cancelled_order['status']);

        // 3. Try to delete cancelled order (should work since not paid)
        $delete_response = $this->make_rest_request('DELETE', "/{$order_id}");
        $this->assertEquals(200, wp_remote_retrieve_response_code($delete_response));

        $delete_result = json_decode(wp_remote_retrieve_body($delete_response), true);
        $this->assertTrue($delete_result['deleted']);
    }

    public function test_analytics_and_reporting_scenario(): void
    {
        // Scenario: Manager wants to analyze sales performance

        // Create multiple orders with different characteristics
        $test_orders = [
            ['total' => 45.50, 'status' => 'completed', 'payment' => 'paid', 'date' => '2023-01-01'],
            ['total' => 67.80, 'status' => 'completed', 'payment' => 'paid', 'date' => '2023-01-01'],
            ['total' => 32.20, 'status' => 'completed', 'payment' => 'paid', 'date' => '2023-01-02'],
            ['total' => 89.90, 'status' => 'cancelled', 'payment' => 'refunded', 'date' => '2023-01-02'],
            ['total' => 56.70, 'status' => 'completed', 'payment' => 'paid', 'date' => '2023-01-03'],
        ];

        $created_order_ids = [];
        foreach ($test_orders as $order_info) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 100,
                        'product_name' => 'Test Product',
                        'quantity' => 1,
                        'unit_price' => $order_info['total'],
                    ]
                ],
                'total_amount' => $order_info['total'],
                'status' => $order_info['status'],
                'payment_status' => $order_info['payment'],
            ];

            $create_response = $this->make_rest_request('POST', '', $order_data);
            $created_order = json_decode(wp_remote_retrieve_body($create_response), true);
            $created_order_ids[] = $created_order['id'];

            // Update date and status
            wp_update_post([
                'ID' => $created_order['id'],
                'post_date' => $order_info['date'] . ' 12:00:00',
            ]);

            if ($order_info['status'] !== 'pending') {
                $this->make_rest_request('PUT', "/{$created_order['id']}/status", [
                    'status' => $order_info['status']
                ]);
            }

            if ($order_info['payment'] !== 'pending') {
                $this->make_rest_request('PUT', "/{$created_order['id']}/payment", [
                    'payment_status' => $order_info['payment']
                ]);
            }
        }

        // 1. Get overall statistics
        $stats_response = $this->make_rest_request('GET', '/statistics', [
            'date_from' => '2023-01-01',
            'date_to' => '2023-01-03'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($stats_response));

        $stats = json_decode(wp_remote_retrieve_body($stats_response), true);
        $this->assertEquals(5, $stats['total_orders']);
        $this->assertEquals(292.10, $stats['total_revenue']); // Sum of all orders
        $this->assertEquals(4, $stats['status_breakdown']['completed']);
        $this->assertEquals(1, $stats['status_breakdown']['cancelled']);

        // 2. Get revenue analytics by day
        $revenue_response = $this->make_rest_request('GET', '/revenue', [
            'period' => 'daily',
            'date_from' => '2023-01-01',
            'date_to' => '2023-01-03'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($revenue_response));

        $revenue_data = json_decode(wp_remote_retrieve_body($revenue_response), true);
        $this->assertIsArray($revenue_data);
        $this->assertNotEmpty($revenue_data);

        // 3. Get popular items
        $popular_response = $this->make_rest_request('GET', '/popular-items', [
            'date_from' => '2023-01-01',
            'date_to' => '2023-01-03',
            'limit' => 5
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($popular_response));

        $popular_items = json_decode(wp_remote_retrieve_body($popular_response), true);
        $this->assertIsArray($popular_items);

        // Clean up test orders
        foreach ($created_order_ids as $order_id) {
            $this->make_rest_request('DELETE', "/{$order_id}", ['force' => true]);
        }
    }

    public function test_customer_order_history_scenario(): void
    {
        // Scenario: Customer wants to view their order history

        // Create multiple orders for the customer
        $customer_orders = [];
        for ($i = 1; $i <= 4; $i++) {
            $order_data = [
                'customer_id' => $this->customer_id,
                'order_items' => [
                    [
                        'product_id' => 200 + $i,
                        'product_name' => "Customer Order Product {$i}",
                        'quantity' => 1,
                        'unit_price' => 25.00 * $i,
                    ]
                ],
            ];

            $create_response = $this->make_rest_request('POST', '', $order_data);
            $customer_orders[] = json_decode(wp_remote_retrieve_body($create_response), true);
        }

        // Get customer order history
        $history_response = $this->make_rest_request('GET', "/customer/{$this->customer_id}");
        $this->assertEquals(200, wp_remote_retrieve_response_code($history_response));

        $order_history = json_decode(wp_remote_retrieve_body($history_response), true);
        $this->assertCount(4, $order_history);

        // Verify all orders belong to the customer
        foreach ($order_history as $order) {
            $this->assertEquals($this->customer_id, $order['customer_id']);
        }

        // Test filtering by status
        $pending_response = $this->make_rest_request('GET', "/customer/{$this->customer_id}", [
            'status' => 'pending'
        ]);
        $this->assertEquals(200, wp_remote_retrieve_response_code($pending_response));

        $pending_orders = json_decode(wp_remote_retrieve_body($pending_response), true);
        $this->assertCount(4, $pending_orders); // All should be pending

        // Clean up
        foreach ($customer_orders as $order) {
            $this->make_rest_request('DELETE', "/{$order['id']}", ['force' => true]);
        }
    }

    public function test_error_handling_scenarios(): void
    {
        // Scenario: Test various error conditions

        // 1. Try to get non-existent order
        $not_found_response = $this->make_rest_request('GET', '/99999');
        $this->assertEquals(404, wp_remote_retrieve_response_code($not_found_response));

        $error = json_decode(wp_remote_retrieve_body($not_found_response), true);
        $this->assertEquals('order_not_found', $error['code']);

        // 2. Try to create order with invalid data
        $invalid_response = $this->make_rest_request('POST', '', [
            'customer_id' => 'invalid',
            'order_items' => []
        ]);
        $this->assertEquals(400, wp_remote_retrieve_response_code($invalid_response));

        // 3. Try to update non-existent order
        $update_not_found = $this->make_rest_request('PUT', '/99999', [
            'notes' => 'This should fail'
        ]);
        $this->assertEquals(404, wp_remote_retrieve_response_code($update_not_found));

        // 4. Try to delete non-existent order
        $delete_not_found = $this->make_rest_request('DELETE', '/99999');
        $this->assertEquals(404, wp_remote_retrieve_response_code($delete_not_found));

        // 5. Try to use invalid status
        // First create a valid order
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 999,
                    'product_name' => 'Error Test Product',
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ],
        ];

        $create_response = $this->make_rest_request('POST', '', $order_data);
        $created_order = json_decode(wp_remote_retrieve_body($create_response), true);
        $order_id = $created_order['id'];

        // Try invalid status
        $invalid_status_response = $this->make_rest_request('PUT', "/{$order_id}/status", [
            'status' => 'invalid_status'
        ]);
        $this->assertEquals(400, wp_remote_retrieve_response_code($invalid_status_response));

        // Clean up
        $this->make_rest_request('DELETE', "/{$order_id}", ['force' => true]);
    }

    public function test_authentication_and_permissions(): void
    {
        // Test without authentication
        wp_set_current_user(0);

        $no_auth_response = $this->make_rest_request('GET', '');
        $this->assertEquals(401, wp_remote_retrieve_response_code($no_auth_response));

        // Test with insufficient permissions
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $subscriber_response = $this->make_rest_request('GET', '');
        $this->assertEquals(403, wp_remote_retrieve_response_code($subscriber_response));

        // Restore admin access
        wp_set_current_user($this->admin_user_id);

        $admin_response = $this->make_rest_request('GET', '');
        $this->assertEquals(200, wp_remote_retrieve_response_code($admin_response));
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    private function make_rest_request(string $method, string $endpoint = '', array $data = []): array
    {
        $url = $this->rest_url . $endpoint;

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        if ($data) {
            $args['body'] = json_encode($data);
        }

        // Add authentication
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $args['headers']['Authorization'] = 'Bearer ' . wp_create_nonce('wp_rest');
        }

        return wp_remote_request($url, $args);
    }
}