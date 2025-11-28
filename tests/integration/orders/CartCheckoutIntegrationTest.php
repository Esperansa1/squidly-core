<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration Tests for Cart-to-Order Checkout Flow
 *
 * Tests the complete checkout flow from cart to order creation
 */
class CartCheckoutIntegrationTest extends WP_UnitTestCase
{
    private \CartService $cartService;
    private \OrderRepository $orderRepo;
    private \CustomerRepository $customerRepo;
    private \ProductRepository $productRepo;
    private \StoreBranchRepository $branchRepo;
    private int $branchId;
    private int $customerId;
    private int $productId;

    public function setUp(): void
    {
        parent::setUp();

        $this->cartService = new \CartService();
        $this->orderRepo = new \OrderRepository();
        $this->customerRepo = new \CustomerRepository();
        $this->productRepo = new \ProductRepository();
        $this->branchRepo = new \StoreBranchRepository();

        // Create test branch
        $this->branchId = $this->branchRepo->create([
            'name' => 'Test Branch',
            'address' => '123 Test St',
            'city' => 'Tel Aviv',
            'phone' => '+972501234567',
            'is_open' => true,
            'activity_times' => ['MONDAY' => ['09:00-22:00'], 'SUNDAY' => ['09:00-22:00']],
            'kosher_type' => 'None',
            'accessibility_list' => [],
        ]);

        // Create test customer
        $this->customerId = $this->customerRepo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+972501234567',
            'auth_provider' => 'phone',
        ]);

        // Create test product
        $this->productId = $this->productRepo->create([
            'name' => 'Test Product',
            'price' => 25.0,
            'category' => 'test',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Basic Checkout                                                    */
    /* ------------------------------------------------------------------ */

    public function test_checkout_creates_order_from_cart(): void
    {
        // Create cart with items
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 2);

        $this->assertCount(1, $cart->items);

        // Checkout
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('tracking_token', $data);
        $this->assertArrayHasKey('total_price', $data);
        $this->assertStringStartsWith('tk_', $data['tracking_token']);

        // Verify order was created
        $order = $this->orderRepo->get($data['order_id']);
        $this->assertNotNull($order);
        $this->assertEquals($this->customerId, $order->customer_id);
        $this->assertEquals($this->branchId, $order->branch_id);
        $this->assertCount(1, $order->order_items);
        $this->assertEquals(2, $order->order_items[0]->quantity);
    }

    public function test_checkout_clears_cart_after_order_creation(): void
    {
        // Create cart with items
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        // Checkout
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(201, $response->get_status());

        // Verify cart is empty
        $cart = $this->cartService->getCart($cart->token);
        $this->assertNotNull($cart);
        $this->assertCount(0, $cart->items);
    }

    public function test_checkout_calculates_tax_correctly(): void
    {
        // Set tax rate
        update_option('squidly_tax_rate', 0.17);

        // Create cart with items (subtotal: 50.0)
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 2); // 25 × 2 = 50

        // Checkout
        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertEquals(50.0, $data['subtotal']);
        $this->assertEquals(8.5, $data['tax_amount']); // 50 × 0.17
        $this->assertEquals(58.5, $data['total_price']); // 50 + 8.5
    }

    public function test_checkout_includes_delivery_fee_for_delivery_orders(): void
    {
        update_option('squidly_tax_rate', 0.17);

        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 2); // Subtotal: 50

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'delivery',
            'delivery_address' => '456 Test Ave',
            'delivery_fee' => 15.0,
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertEquals(50.0, $data['subtotal']);
        $this->assertEquals(8.5, $data['tax_amount']);
        $this->assertEquals(15.0, $data['delivery_fee']);
        $this->assertEquals(73.5, $data['total_price']); // 50 + 8.5 + 15
    }

    /* ------------------------------------------------------------------ */
    /*  Validation Tests                                                  */
    /* ------------------------------------------------------------------ */

    public function test_checkout_requires_customer_id(): void
    {
        $cart = $this->cartService->createCart($this->branchId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $error_message = $data['error'] ?? $data['message'] ?? json_encode($data);
        $this->assertStringContainsString('customer_id', $error_message);
    }

    public function test_checkout_fails_with_invalid_customer(): void
    {
        $cart = $this->cartService->createCart($this->branchId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => 99999, // Non-existent customer
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $error_message = $data['error'] ?? $data['message'] ?? json_encode($data);
        $this->assertStringContainsString('Customer not found', $error_message);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('empty cart', $response->get_data()['message']);
    }

    public function test_checkout_fails_with_expired_cart(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        // Delete the cart to simulate expiration
        $this->cartService->deleteCart($cart->token);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('Cart not found or expired', $response->get_data()['message']);
    }

    public function test_checkout_requires_delivery_address_for_delivery_orders(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'delivery',
            // Missing delivery_address
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $error_message = $data['error'] ?? $data['message'] ?? json_encode($data);
        $this->assertStringContainsString('delivery_address', $error_message);
    }

    public function test_checkout_validates_delivery_type(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'invalid_type',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $error_message = $data['error'] ?? $data['message'] ?? json_encode($data);
        $this->assertStringContainsString('delivery_type', $error_message);
    }

    public function test_checkout_validates_payment_method(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'bitcoin', // Invalid
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $error_message = $data['error'] ?? $data['message'] ?? json_encode($data);
        $this->assertStringContainsString('payment_method', $error_message);
    }

    /* ------------------------------------------------------------------ */
    /*  Order Data Integrity                                              */
    /* ------------------------------------------------------------------ */

    public function test_checkout_preserves_product_customizations(): void
    {
        // Create product with groups (assuming we have test data)
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart(
            $cart->token,
            $this->productId,
            1,
            [], // Customizations would go here
            'Extra sauce please'
        );

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $order = $this->orderRepo->get($data['order_id']);
        $this->assertEquals('Extra sauce please', $order->order_items[0]->notes);
    }

    public function test_checkout_creates_order_with_correct_status(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertEquals(\Order::STATUS_PENDING, $data['status']);
        $this->assertEquals(\Order::PAYMENT_PENDING, $data['payment_status']);
    }

    public function test_checkout_includes_order_notes(): void
    {
        $cart = $this->cartService->createCart($this->branchId, $this->customerId);
        $cart = $this->cartService->addItemToCart($cart->token, $this->productId, 1);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/cart/' . $cart->token . '/checkout');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'notes' => 'Please call when ready',
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $order = $this->orderRepo->get($data['order_id']);
        $this->assertEquals('Please call when ready', $order->notes);
    }

    /* ------------------------------------------------------------------ */
    /*  Rate Limiting                                                     */
    /* ------------------------------------------------------------------ */

    public function test_checkout_respects_rate_limiting(): void
    {
        // Skip rate limiting test for now - requires proper cleanup between requests
        $this->markTestSkipped('Rate limiting test requires IP-based cleanup between test runs');
    }
}
