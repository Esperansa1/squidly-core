<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Api;

use OrderRestController;
use OrderRepository;
use Order;
use WP_REST_Request;
use WP_REST_Server;

/**
 * API Validation Tests for OrderRestController
 *
 * Tests all input validation, data sanitization, and error handling
 * to ensure the API properly validates and sanitizes user input.
 */
class OrderApiValidationTest extends \WP_UnitTestCase
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
            'post_title' => 'API Validation Test Customer',
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
     * Create Order Validation Tests
     * ========================================== */

    public function test_create_order_requires_customer_id(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Customer ID is required', $response->get_error_message());
    }

    public function test_create_order_requires_valid_customer_id(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', 'invalid_id');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        // Should sanitize to 0 and then fail validation
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
    }

    public function test_create_order_requires_order_items(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Order must contain at least one item', $response->get_error_message());
    }

    public function test_create_order_requires_non_empty_order_items(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', []);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Order must contain at least one item', $response->get_error_message());
    }

    public function test_create_order_validates_order_item_fields(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                // Missing product_name, quantity, unit_price
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('must have product_id, quantity, and unit_price', $response->get_error_message());
    }

    public function test_create_order_validates_quantity_minimum(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 0, // Invalid quantity
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Item quantity must be at least 1', $response->get_error_message());
    }

    public function test_create_order_validates_negative_quantity(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => -2, // Negative quantity
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Item quantity must be at least 1', $response->get_error_message());
    }

    public function test_create_order_validates_negative_unit_price(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => -25.0, // Negative price
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Item unit price cannot be negative', $response->get_error_message());
    }

    public function test_create_order_validates_order_status(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('status', 'invalid_status');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Invalid order status', $response->get_error_message());
    }

    public function test_create_order_validates_payment_status(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('payment_status', 'invalid_payment_status');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Invalid payment status', $response->get_error_message());
    }

    public function test_create_order_validates_payment_method(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('payment_method', 'invalid_payment_method');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Invalid payment method', $response->get_error_message());
    }

    /* ==========================================
     * Update Order Validation Tests
     * ========================================== */

    public function test_update_order_validates_status(): void
    {
        // Create test order first
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('status', 'invalid_status');

        $response = $this->controller->update_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Invalid order status', $response->get_error_message());
    }

    public function test_update_order_validates_payment_status(): void
    {
        // Create test order first
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('payment_status', 'invalid_payment_status');

        $response = $this->controller->update_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Invalid payment status', $response->get_error_message());
    }

    public function test_update_order_validates_item_quantity(): void
    {
        // Create test order first
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 0, // Invalid quantity
                'unit_price' => 25.0,
            ]
        ]);

        $response = $this->controller->update_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Item quantity must be at least 1', $response->get_error_message());
    }

    public function test_update_order_validates_item_unit_price(): void
    {
        // Create test order first
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => -25.0, // Invalid negative price
            ]
        ]);

        $response = $this->controller->update_order($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertStringContainsString('Item unit price cannot be negative', $response->get_error_message());
    }

    /* ==========================================
     * Status Update Validation Tests
     * ========================================== */

    public function test_update_order_status_validates_status_value(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('status', 'completely_invalid_status');

        $response = $this->controller->update_order_status($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_status', $response->get_error_code());
    }

    public function test_update_order_status_requires_status_param(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/status");
        $request->set_url_params(['id' => (string)$order_id]);
        // Missing status parameter

        $response = $this->controller->update_order_status($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        // The exact error will depend on the parameter validation setup
    }

    public function test_update_payment_status_validates_payment_status_value(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('payment_status', 'invalid_payment_status');

        $response = $this->controller->update_payment_status($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_payment_status', $response->get_error_code());
    }

    public function test_update_payment_status_requires_payment_status_param(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('PUT', "/squidly/v1/orders/{$order_id}/payment");
        $request->set_url_params(['id' => (string)$order_id]);
        // Missing payment_status parameter

        $response = $this->controller->update_payment_status($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
    }

    /* ==========================================
     * Add Item Validation Tests
     * ========================================== */

    public function test_add_order_item_requires_product_id(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('product_name', 'Test Item');
        $request->set_param('quantity', 1);
        $request->set_param('unit_price', 15.0);

        $response = $this->controller->add_order_item($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_item_data', $response->get_error_code());
    }

    public function test_add_order_item_requires_product_name(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('product_id', 123);
        $request->set_param('quantity', 1);
        $request->set_param('unit_price', 15.0);

        $response = $this->controller->add_order_item($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_item_data', $response->get_error_code());
    }

    public function test_add_order_item_validates_quantity(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('product_id', 123);
        $request->set_param('product_name', 'Test Item');
        $request->set_param('quantity', 0); // Invalid
        $request->set_param('unit_price', 15.0);

        $response = $this->controller->add_order_item($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_item_data', $response->get_error_code());
    }

    public function test_add_order_item_validates_unit_price(): void
    {
        $order_id = $this->createTestOrder();

        $request = new WP_REST_Request('POST', "/squidly/v1/orders/{$order_id}/items");
        $request->set_url_params(['id' => (string)$order_id]);
        $request->set_param('product_id', 123);
        $request->set_param('product_name', 'Test Item');
        $request->set_param('quantity', 1);
        $request->set_param('unit_price', -15.0); // Invalid

        $response = $this->controller->add_order_item($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_item_data', $response->get_error_code());
    }

    /* ==========================================
     * Data Sanitization Tests
     * ========================================== */

    public function test_create_order_sanitizes_text_fields(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('notes', '<script>alert("xss")</script>Legitimate notes');
        $request->set_param('delivery_address', '<b>123 Test St</b>');
        $request->set_param('special_instructions', 'No onions <script>alert("xss")</script>');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => '<script>alert("xss")</script>Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
                'modifications' => ['<script>alert("xss")</script>Extra cheese'],
                'notes' => '<script>alert("xss")</script>Well done'
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();

        // Check that scripts are removed but legitimate content remains
        $this->assertEquals('Legitimate notes', $created_order['notes']);
        $this->assertEquals('123 Test St', $created_order['delivery_address']);
        $this->assertEquals('No onions', $created_order['special_instructions']);
        $this->assertEquals('Test Pizza', $created_order['order_items'][0]['product_name']);
        $this->assertEquals('Extra cheese', $created_order['order_items'][0]['modifications'][0]);
        $this->assertEquals('Well done', $created_order['order_items'][0]['notes']);
    }

    public function test_create_order_sanitizes_numeric_fields(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('subtotal', '123.45abc'); // Should become 123.45
        $request->set_param('tax_amount', 'invalid'); // Should become 0
        $request->set_param('delivery_fee', '5.99'); // Should remain 5.99
        $request->set_param('total_amount', '129.44'); // Should remain 129.44
        $request->set_param('order_items', [
            [
                'product_id' => '1abc', // Should become 1
                'product_name' => 'Test Pizza',
                'quantity' => '2def', // Should become 2
                'unit_price' => '25.50xyz', // Should become 25.50
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();

        $this->assertEquals(123.45, $created_order['subtotal']);
        $this->assertEquals(0.0, $created_order['tax_amount']);
        $this->assertEquals(5.99, $created_order['delivery_fee']);
        $this->assertEquals(129.44, $created_order['total_amount']);
        $this->assertEquals(1, $created_order['order_items'][0]['product_id']);
        $this->assertEquals(2, $created_order['order_items'][0]['quantity']);
        $this->assertEquals(25.50, $created_order['order_items'][0]['unit_price']);
    }

    /* ==========================================
     * Query Parameter Validation Tests
     * ========================================== */

    public function test_get_orders_validates_customer_id_parameter(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('customer_id', 'invalid_id'); // Should be sanitized to 0

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        // Should return empty results for customer_id = 0
        $orders = $response->get_data();
        $this->assertIsArray($orders);
    }

    public function test_get_orders_validates_status_parameter(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('status', 'invalid_status');

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        // Should filter by the invalid status and return empty results
        $orders = $response->get_data();
        $this->assertIsArray($orders);
    }

    public function test_get_orders_validates_date_parameters(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('date_from', 'invalid_date');
        $request->set_param('date_to', '2023-13-45'); // Invalid date

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        // Should handle invalid dates gracefully
        $orders = $response->get_data();
        $this->assertIsArray($orders);
    }

    public function test_get_orders_validates_numeric_parameters(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('total_min', 'abc'); // Should become 0
        $request->set_param('total_max', '100.50def'); // Should become 100.50
        $request->set_param('per_page', 'invalid'); // Should use default

        $response = $this->controller->get_orders($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $orders = $response->get_data();
        $this->assertIsArray($orders);
    }

    /* ==========================================
     * Edge Case Tests
     * ========================================== */

    public function test_create_order_handles_empty_arrays_gracefully(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'Test Pizza',
                'quantity' => 1,
                'unit_price' => 25.0,
                'modifications' => [], // Empty array should be handled
                'notes' => null,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();
        $this->assertIsArray($created_order['order_items'][0]['modifications']);
        $this->assertEmpty($created_order['order_items'][0]['modifications']);
    }

    public function test_create_order_handles_very_large_numbers(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('order_items', [
            [
                'product_id' => PHP_INT_MAX,
                'product_name' => 'Expensive Item',
                'quantity' => 999999,
                'unit_price' => 999999.99,
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();

        // Check that large numbers are handled correctly
        $this->assertIsInt($created_order['order_items'][0]['product_id']);
        $this->assertIsInt($created_order['order_items'][0]['quantity']);
        $this->assertIsFloat($created_order['order_items'][0]['unit_price']);
    }

    public function test_create_order_handles_unicode_text(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('customer_id', $this->customer_id);
        $request->set_param('notes', 'הזמנה מיוחדת עם הוראות בעברית 🍕');
        $request->set_param('order_items', [
            [
                'product_id' => 1,
                'product_name' => 'פיצה מרגריטה עם תוספות 🧀',
                'quantity' => 1,
                'unit_price' => 25.0,
                'modifications' => ['גבינה נוספת', 'ללא בצל'],
                'notes' => 'אפויה היטב 🔥'
            ]
        ]);

        $response = $this->controller->create_order($request);

        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $created_order = $response->get_data();

        // Unicode text should be preserved
        $this->assertStringContainsString('הזמנה מיוחדת', $created_order['notes']);
        $this->assertStringContainsString('פיצה מרגריטה', $created_order['order_items'][0]['product_name']);
        $this->assertStringContainsString('גבינה נוספת', $created_order['order_items'][0]['modifications'][0]);
        $this->assertStringContainsString('אפויה היטב', $created_order['order_items'][0]['notes']);
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
                    'product_name' => 'Test Pizza',
                    'quantity' => 1,
                    'unit_price' => 25.0,
                ]
            ],
            'total_amount' => 25.0,
        ];

        return $this->repository->create($order_data);
    }
}