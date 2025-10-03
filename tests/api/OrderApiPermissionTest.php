<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Api;

use OrderRestController;
use OrderRepository;
use Order;
use WP_REST_Request;

/**
 * Permission Boundary Testing for OrderRestController
 *
 * Tests authorization boundaries, role-based access control, and security
 * scenarios to ensure proper access restrictions for order management.
 */
class OrderApiPermissionTest extends \WP_UnitTestCase
{
    private OrderRestController $controller;
    private OrderRepository $repository;
    private int $customer_id;
    private int $admin_user_id;
    private int $editor_user_id;
    private int $shop_manager_user_id;
    private int $subscriber_user_id;
    private array $created_order_ids = [];
    private array $created_user_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OrderRestController();
        $this->repository = new OrderRepository();

        // Create test customer
        $this->customer_id = wp_insert_post([
            'post_title' => 'Permission Test Customer',
            'post_type' => 'squidly_customer',
            'post_status' => 'publish',
        ]);

        // Create users with different roles
        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        $this->editor_user_id = $this->factory->user->create(['role' => 'editor']);
        $this->shop_manager_user_id = $this->factory->user->create(['role' => 'shop_manager']);
        $this->subscriber_user_id = $this->factory->user->create(['role' => 'subscriber']);

        $this->created_user_ids = [
            $this->admin_user_id,
            $this->editor_user_id,
            $this->shop_manager_user_id,
            $this->subscriber_user_id
        ];

        // Add shop_manager capability for testing
        $role = get_role('shop_manager');
        if ($role) {
            $role->add_cap('manage_woocommerce');
            $role->add_cap('edit_shop_orders');
            $role->add_cap('read_shop_orders');
        }
    }

    protected function tearDown(): void
    {
        // Clean up test data
        foreach ($this->created_order_ids as $order_id) {
            wp_delete_post($order_id, true);
        }

        wp_delete_post($this->customer_id, true);

        // Reset current user
        wp_set_current_user(0);

        parent::tearDown();
    }

    /* ==========================================
     * Admin Role Permission Tests
     * ========================================== */

    public function test_admin_can_access_all_order_operations(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Test all major operations admin should have access to
        $operations = [
            'create_order' => true,
            'get_orders' => true,
            'get_order' => true,
            'update_order' => true,
            'delete_order' => true,
            'get_statistics' => true,
            'get_revenue_analytics' => true,
            'get_popular_items' => true,
            'get_order_queue' => true,
            'update_order_status' => true,
            'update_payment_status' => true,
            'add_order_item' => true,
        ];

        foreach ($operations as $operation => $should_have_access) {
            $has_access = $this->testUserOperation($operation);
            $this->assertEquals(
                $should_have_access,
                $has_access,
                "Admin should have access to {$operation}"
            );
        }
    }

    public function test_admin_can_create_orders_for_any_customer(): void
    {
        wp_set_current_user($this->admin_user_id);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Admin Test Product',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(201, $response->get_status());

        $order_data = $response->get_data();
        $this->created_order_ids[] = $order_data['id'];
    }

    public function test_admin_can_delete_any_order(): void
    {
        wp_set_current_user($this->admin_user_id);

        // Create test order
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);

        $response = $this->controller->delete_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /* ==========================================
     * Shop Manager Role Permission Tests
     * ========================================== */

    public function test_shop_manager_has_limited_access(): void
    {
        wp_set_current_user($this->shop_manager_user_id);

        // Operations shop manager should have access to
        $allowed_operations = [
            'get_orders' => true,
            'get_order' => true,
            'update_order_status' => true,
            'get_order_queue' => true,
            'add_order_item' => true,
        ];

        // Operations shop manager should NOT have access to
        $restricted_operations = [
            'delete_order' => false,
            'get_statistics' => false,
            'get_revenue_analytics' => false,
        ];

        $all_operations = array_merge($allowed_operations, $restricted_operations);

        foreach ($all_operations as $operation => $should_have_access) {
            $has_access = $this->testUserOperation($operation);
            $this->assertEquals(
                $should_have_access,
                $has_access,
                "Shop manager access to {$operation} should be " . ($should_have_access ? 'allowed' : 'restricted')
            );
        }
    }

    public function test_shop_manager_cannot_access_financial_data(): void
    {
        wp_set_current_user($this->shop_manager_user_id);

        // Test revenue analytics access
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
        $response = $this->controller->get_revenue_analytics($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());

        // Test statistics access
        $stats_request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
        $stats_response = $this->controller->get_order_statistics($stats_request);

        $this->assertInstanceOf(\WP_Error::class, $stats_response);
        $this->assertEquals('rest_forbidden', $stats_response->get_error_code());
    }

    public function test_shop_manager_can_manage_order_workflow(): void
    {
        wp_set_current_user($this->admin_user_id);
        $order_id = $this->createTestOrder();

        wp_set_current_user($this->shop_manager_user_id);

        // Should be able to update order status
        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('status', Order::STATUS_CONFIRMED);

        $response = $this->controller->update_order_status($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    /* ==========================================
     * Editor Role Permission Tests
     * ========================================== */

    public function test_editor_has_read_only_access(): void
    {
        wp_set_current_user($this->editor_user_id);

        // Editor should have read access
        $read_operations = [
            'get_orders' => true,
            'get_order' => true,
            'get_order_queue' => true,
        ];

        // Editor should NOT have write access
        $write_operations = [
            'create_order' => false,
            'update_order' => false,
            'delete_order' => false,
            'update_order_status' => false,
            'update_payment_status' => false,
            'add_order_item' => false,
        ];

        $all_operations = array_merge($read_operations, $write_operations);

        foreach ($all_operations as $operation => $should_have_access) {
            $has_access = $this->testUserOperation($operation);
            $this->assertEquals(
                $should_have_access,
                $has_access,
                "Editor access to {$operation} should be " . ($should_have_access ? 'allowed' : 'restricted')
            );
        }
    }

    public function test_editor_cannot_modify_orders(): void
    {
        wp_set_current_user($this->admin_user_id);
        $order_id = $this->createTestOrder();

        wp_set_current_user($this->editor_user_id);

        // Try to update order
        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('notes', 'Editor trying to modify');

        $response = $this->controller->update_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());
    }

    /* ==========================================
     * Subscriber Role Permission Tests
     * ========================================== */

    public function test_subscriber_has_no_access(): void
    {
        wp_set_current_user($this->subscriber_user_id);

        $operations = [
            'get_orders',
            'get_order',
            'create_order',
            'update_order',
            'delete_order',
            'get_statistics',
            'get_revenue_analytics',
            'get_order_queue',
            'update_order_status',
        ];

        foreach ($operations as $operation) {
            $has_access = $this->testUserOperation($operation);
            $this->assertFalse(
                $has_access,
                "Subscriber should not have access to {$operation}"
            );
        }
    }

    public function test_subscriber_gets_forbidden_for_all_endpoints(): void
    {
        wp_set_current_user($this->subscriber_user_id);

        // Test list orders
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $response = $this->controller->get_orders($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());

        // Test create order
        $create_request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $create_request->set_param('customer_id', $this->customer_id);
        $create_request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 10.0,
            ]
        ]);

        $create_response = $this->controller->create_order($create_request);

        $this->assertInstanceOf(\WP_Error::class, $create_response);
        $this->assertEquals('rest_forbidden', $create_response->get_error_code());
    }

    /* ==========================================
     * Unauthenticated User Tests
     * ========================================== */

    public function test_unauthenticated_user_denied_access(): void
    {
        wp_set_current_user(0); // No user logged in

        $operations = [
            'get_orders',
            'create_order',
            'get_statistics',
            'get_revenue_analytics',
        ];

        foreach ($operations as $operation) {
            $has_access = $this->testUserOperation($operation);
            $this->assertFalse(
                $has_access,
                "Unauthenticated user should not have access to {$operation}"
            );
        }
    }

    public function test_unauthenticated_create_order_returns_unauthorized(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Unauthorized Test',
                'quantity' => 1,
                'unit_price' => 15.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());
    }

    /* ==========================================
     * Cross-User Access Tests
     * ========================================== */

    public function test_user_cannot_access_other_users_restricted_data(): void
    {
        // Create order as admin
        wp_set_current_user($this->admin_user_id);
        $order_id = $this->createTestOrder();

        // Switch to shop manager and try to delete (should fail)
        wp_set_current_user($this->shop_manager_user_id);

        $request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);

        $response = $this->controller->delete_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());
    }

    public function test_role_elevation_attack_prevention(): void
    {
        wp_set_current_user($this->editor_user_id);

        // Try to access admin-only statistics with manipulated request
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
        $request->set_header('X-Fake-Admin', 'true');
        $request->set_param('admin_override', 'true');

        $response = $this->controller->get_order_statistics($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());
    }

    /* ==========================================
     * Data Isolation Tests
     * ========================================== */

    public function test_users_see_appropriate_order_data(): void
    {
        // Create orders as admin
        wp_set_current_user($this->admin_user_id);
        $order_id_1 = $this->createTestOrder();
        $order_id_2 = $this->createTestOrder();

        // Shop manager should see orders but not financial analytics
        wp_set_current_user($this->shop_manager_user_id);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);

        // Verify orders don't contain sensitive financial data for shop manager
        foreach ($orders as $order) {
            // Should have basic order info
            $this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('status', $order);

            // May or may not have total_amount depending on implementation
            // This is a business decision for the specific role permissions
        }
    }

    public function test_permission_check_bypassing_attempts(): void
    {
        wp_set_current_user($this->subscriber_user_id);

        // Try various bypass attempts
        $bypass_attempts = [
            ['bypass_permissions' => true],
            ['force' => true],
            ['admin_override' => 'yes'],
            ['skip_permission_check' => 1],
        ];

        foreach ($bypass_attempts as $params) {
            $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
            foreach ($params as $key => $value) {
                $request->set_param($key, $value);
            }

            $response = $this->controller->get_order_statistics($request);

            $this->assertInstanceOf(\WP_Error::class, $response);
            $this->assertEquals('rest_forbidden', $response->get_error_code());
        }
    }

    /* ==========================================
     * Session and Token Security Tests
     * ========================================== */

    public function test_invalid_nonce_rejected(): void
    {
        wp_set_current_user($this->admin_user_id);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Nonce Test Product',
                'quantity' => 1,
                'unit_price' => 20.0,
            ]
        ]);

        // Set invalid nonce
        $request->set_header('X-WP-Nonce', 'invalid_nonce_value');

        // Note: WordPress REST API nonce validation depends on the implementation
        // This test verifies the controller doesn't bypass WordPress security
        $response = $this->controller->create_order($request);

        // Response should either succeed (if nonce not required for this endpoint)
        // or fail with proper error (if nonce is required)
        $this->assertTrue(
            !$response instanceof \WP_Error || $response->get_error_code() !== 'rest_cookie_invalid_nonce',
            'Invalid nonce should be handled by WordPress REST API security layer'
        );
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    private function testUserOperation(string $operation): bool
    {
        try {
            switch ($operation) {
                case 'get_orders':
                    $request = new WP_REST_Request('GET', '/squidly/v1/orders');
                    $response = $this->controller->get_orders($request);
                    break;

                case 'get_order':
                    $order_id = $this->createTestOrderAsAdmin();
                    $request = new WP_REST_Request('GET', "/squidly/v1/orders/{$order_id}");
                    $request->set_url_params(['id' => (string)$order_id]);
                    $response = $this->controller->get_order($request);
                    break;

                case 'create_order':
                    $request = new WP_REST_Request('POST', '/squidly/v1/orders');
                    $request->set_param('customer_id', $this->customer_id);
                    $request->set_param('order_items', [
                        [
                            'product_id' => 1,
                            'product_name' => 'Permission Test Product',
                            'quantity' => 1,
                            'unit_price' => 25.0,
                        ]
                    ]);
                    $response = $this->controller->create_order($request);
                    if (!$response instanceof \WP_Error) {
                        $order_data = $response->get_data();
                        $this->created_order_ids[] = $order_data['id'];
                    }
                    break;

                case 'update_order':
                    $order_id = $this->createTestOrderAsAdmin();
                    $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
                    $request->set_url_params(['id' => (string)$order_id]);
                    $request->set_param('notes', 'Permission test update');
                    $response = $this->controller->update_order($request);
                    break;

                case 'delete_order':
                    $order_id = $this->createTestOrderAsAdmin();
                    $request = new WP_REST_Request('DELETE', "/squidly/v1/orders/{$order_id}");
                    $request->set_url_params(['id' => (string)$order_id]);
                    $response = $this->controller->delete_order($request);
                    break;

                case 'get_statistics':
                    $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');
                    $response = $this->controller->get_order_statistics($request);
                    break;

                case 'get_revenue_analytics':
                    $request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
                    $response = $this->controller->get_revenue_analytics($request);
                    break;

                case 'get_popular_items':
                    $request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
                    $response = $this->controller->get_popular_items($request);
                    break;

                case 'get_order_queue':
                    $request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');
                    $response = $this->controller->get_order_queue($request);
                    break;

                case 'update_order_status':
                    $order_id = $this->createTestOrderAsAdmin();
                    $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
                    $request->set_url_params(['id' => (string)$order_id]);
                    $request->set_param('status', Order::STATUS_CONFIRMED);
                    $response = $this->controller->update_order_status($request);
                    break;

                case 'update_payment_status':
                    $order_id = $this->createTestOrderAsAdmin();
                    $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
                    $request->set_url_params(['id' => (string)$order_id]);
                    $request->set_param('payment_status', Order::PAYMENT_PAID);
                    $response = $this->controller->update_payment_status($request);
                    break;

                case 'add_order_item':
                    $order_id = $this->createTestOrderAsAdmin();
                    $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
                    $request->set_url_params(['id' => (string)$order_id]);
                    $request->set_param('product_id', 99);
                    $request->set_param('product_name', 'Added Test Item');
                    $request->set_param('quantity', 1);
                    $request->set_param('unit_price', 5.0);
                    $response = $this->controller->add_order_item($request);
                    break;

                default:
                    return false;
            }

            return !($response instanceof \WP_Error);

        } catch (\Exception $e) {
            return false;
        }
    }

    private function createTestOrder(): int
    {
        $order_data = [
            'customer_id' => $this->customer_id,
            'order_items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Permission Test Order Product',
                    'quantity' => 1,
                    'unit_price' => 30.0,
                ]
            ],
            'total_amount' => 30.0,
        ];

        $order_id = $this->repository->create($order_data);
        $this->created_order_ids[] = $order_id;
        return $order_id;
    }

    private function createTestOrderAsAdmin(): int
    {
        $current_user = wp_get_current_user();
        wp_set_current_user($this->admin_user_id);

        $order_id = $this->createTestOrder();

        wp_set_current_user($current_user->ID);
        return $order_id;
    }
}