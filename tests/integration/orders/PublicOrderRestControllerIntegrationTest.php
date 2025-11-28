<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use PublicOrderRestController;
use OrderRepository;
use CustomerRepository;
use StoreBranchRepository;
use ProductRepository;
use ProductGroupRepository;
use GroupItemRepository;
use IngredientRepository;
use ItemType;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for PublicOrderRestController
 *
 * Tests the public order creation endpoint that customers use to place orders.
 * Validates security, price calculation, customization validation, and order creation.
 *
 * Run with: vendor/bin/phpunit --testsuite integration --filter PublicOrderRestController
 */
class PublicOrderRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicOrderRestController $controller;
    private OrderRepository $orderRepo;
    private CustomerRepository $customerRepo;
    private StoreBranchRepository $branchRepo;
    private ProductRepository $productRepo;
    private ProductGroupRepository $groupRepo;
    private GroupItemRepository $groupItemRepo;
    private IngredientRepository $ingredientRepo;

    // Test data IDs
    private int $customerId;
    private int $branchId;
    private int $productId;
    private int $productWithGroupId;
    private int $groupId;
    private int $ingredient1Id;
    private int $ingredient2Id;

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new PublicOrderRestController();
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
        $this->branchRepo = new StoreBranchRepository();
        $this->productRepo = new ProductRepository();
        $this->groupRepo = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        $this->ingredientRepo = new IngredientRepository();

        // Register controller routes during rest_api_init action
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');

        // Create test data
        $this->createTestData();
    }

    /**
     * Create complete test data: customer, branch, products, groups
     */
    private function createTestData(): void
    {
        // Create customer
        $this->customerId = $this->customerRepo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
        ]);

        // Create branch
        $this->branchId = $this->branchRepo->create([
            'name' => 'Test Branch',
            'address' => '123 Test St',
            'city' => 'Tel Aviv',
            'phone' => '0987654321',
            'is_open' => true,
            'activity_times' => ['MONDAY' => ['09:00-22:00'], 'SUNDAY' => ['09:00-22:00']],
            'kosher_type' => 'None',
            'accessibility_list' => [],
            'delivery_enabled' => true,
            'delivery_base_fee' => 10.0,
        ]);

        // Create simple product (no customizations)
        $this->productId = $this->productRepo->create([
            'name' => 'Simple Burger',
            'price' => 25.0,
            'description' => 'A simple burger',
        ]);

        // Create ingredients for customization
        $this->ingredient1Id = $this->ingredientRepo->create([
            'name' => 'Lettuce',
            'price' => 0.0,
            'unit' => 'piece'
        ]);

        $this->ingredient2Id = $this->ingredientRepo->create([
            'name' => 'Cheese',
            'price' => 5.0,
            'unit' => 'piece'
        ]);

        // Create group items
        $groupItem1Id = $this->groupItemRepo->create([
            'item_id' => $this->ingredient1Id,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => null
        ]);

        $groupItem2Id = $this->groupItemRepo->create([
            'item_id' => $this->ingredient2Id,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => null
        ]);

        // Create product group (min=0, max=2)
        $this->groupId = $this->groupRepo->create([
            'name' => 'Toppings',
            'description' => 'Choose your toppings',
            'type' => 'ingredient',
            'group_item_ids' => [$groupItem1Id, $groupItem2Id],
            'min_selections' => 0,
            'max_selections' => 2,
        ]);

        // Create product with customizations
        $this->productWithGroupId = $this->productRepo->create([
            'name' => 'Custom Burger',
            'price' => 20.0,
            'description' => 'Customizable burger',
            'product_group_ids' => [$this->groupId]
        ]);

        // Make products available at branch
        $this->branchRepo->addProduct($this->branchId, $this->productId, true);
        $this->branchRepo->addProduct($this->branchId, $this->productWithGroupId, true);
    }

    /* ------------------------------------------------------------------ */
    /*  Valid Order Creation                                              */
    /* ------------------------------------------------------------------ */

    public function test_create_simple_order_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 2,
                ]
            ],
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
        $this->assertEquals('pickup', $order->delivery_type);
        $this->assertCount(1, $order->order_items);
    }

    public function test_create_order_with_customizations_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                [
                    'product_id' => $this->productWithGroupId,
                    'quantity' => 1,
                    'customizations' => [
                        $this->groupId => [
                            ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0],
                            ['id' => $this->ingredient2Id, 'name' => 'Cheese', 'price' => 5.0]
                        ]
                    ]
                ]
            ],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();

        // Base price (20.0) + Cheese (5.0) = 25.0
        // With tax (17%): 25.0 * 1.17 = 29.25
        $this->assertEquals(29.25, $data['total_price']);
        $this->assertEquals(25.0, $data['subtotal']);

        // Verify customizations are stored
        $order = $this->orderRepo->get($data['order_id']);
        $this->assertNotEmpty($order->order_items[0]->modifications);
    }

    public function test_create_order_with_multiple_items_succeeds(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $this->productWithGroupId,
                    'quantity' => 1,
                    'customizations' => [
                        $this->groupId => [
                            ['id' => $this->ingredient2Id, 'name' => 'Cheese', 'price' => 5.0]
                        ]
                    ]
                ]
            ],
            'delivery_type' => 'delivery',
            'delivery_address' => '456 Delivery St',
            'payment_method' => 'card',
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();

        // Item 1: 25.0 * 2 = 50.0
        // Item 2: 20.0 + 5.0 = 25.0
        // Subtotal: 75.0
        // Delivery fee: 10.0 (delivery order)
        // Tax: 75.0 * 0.17 = 12.75
        // Total: 75.0 + 10.0 + 12.75 = 97.75
        $this->assertEquals(97.75, $data['total_price']);

        $order = $this->orderRepo->get($data['order_id']);
        $this->assertEquals('delivery', $order->delivery_type);
        $this->assertEquals('456 Delivery St', $order->delivery_address);
        $this->assertCount(2, $order->order_items);
    }

    /* ------------------------------------------------------------------ */
    /*  Validation Failures                                               */
    /* ------------------------------------------------------------------ */

    public function test_missing_customer_id_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'branch_id' => $this->branchId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('customer_id', strtolower($response->get_data()['message']));
    }

    public function test_invalid_customer_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => 99999,
            'branch_id' => $this->branchId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('Customer not found', $response->get_data()['error']);
    }

    public function test_invalid_branch_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => 99999,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('Branch not found', $response->get_data()['error']);
    }

    public function test_invalid_product_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                ['product_id' => 99999, 'quantity' => 1]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('Product not found', $response->get_data()['message']);
    }

    public function test_empty_items_array_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('at least one item', strtolower($response->get_data()['message']));
    }

    public function test_invalid_quantity_returns_400(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 0]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    /* ------------------------------------------------------------------ */
    /*  Customization Validation                                          */
    /* ------------------------------------------------------------------ */

    public function test_invalid_customizations_rejected(): void
    {
        // Try to exceed max_selections (max is 2, sending 3)
        $extraIngredientId = $this->ingredientRepo->create([
            'name' => 'Tomato',
            'price' => 2.0,
            'unit' => 'piece'
        ]);

        $groupItemId = $this->groupItemRepo->create([
            'item_id' => $extraIngredientId,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => null
        ]);

        // Add to group
        $group = $this->groupRepo->get($this->groupId);
        $this->groupRepo->update($this->groupId, [
            'group_item_ids' => array_merge($group->group_item_ids, [$groupItemId])
        ]);

        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                [
                    'product_id' => $this->productWithGroupId,
                    'quantity' => 1,
                    'customizations' => [
                        $this->groupId => [
                            ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0],
                            ['id' => $this->ingredient2Id, 'name' => 'Cheese', 'price' => 5.0],
                            ['id' => $extraIngredientId, 'name' => 'Tomato', 'price' => 2.0] // Too many!
                        ]
                    ]
                ]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('maximum', strtolower($response->get_data()['message']));
    }

    public function test_price_manipulation_rejected(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                [
                    'product_id' => $this->productWithGroupId,
                    'quantity' => 1,
                    'customizations' => [
                        $this->groupId => [
                            // Try to change cheese price from 5.0 to 0.0
                            ['id' => $this->ingredient2Id, 'name' => 'Cheese', 'price' => 0.0]
                        ]
                    ]
                ]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('manipulation', strtolower($response->get_data()['message']));
    }

    /* ------------------------------------------------------------------ */
    /*  Price Calculation                                                 */
    /* ------------------------------------------------------------------ */

    public function test_price_calculated_server_side(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                [
                    'product_id' => $this->productWithGroupId,
                    'quantity' => 2,
                    'customizations' => [
                        $this->groupId => [
                            ['id' => $this->ingredient2Id, 'name' => 'Cheese', 'price' => 5.0]
                        ]
                    ]
                ]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();

        // Server-calculated price:
        // Unit: 20.0 (base) + 5.0 (cheese) = 25.0
        // Quantity: 25.0 * 2 = 50.0
        // Tax: 50.0 * 0.17 = 8.5
        // Total: 58.5
        $this->assertEquals(50.0, $data['subtotal']);
        $this->assertEquals(8.5, $data['tax_amount']);
        $this->assertEquals(58.5, $data['total_price']);
    }

    /* ------------------------------------------------------------------ */
    /*  Tracking Token                                                    */
    /* ------------------------------------------------------------------ */

    public function test_tracking_token_generated(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 1]
            ],
        ]));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Verify token format
        $this->assertStringStartsWith('tk_', $data['tracking_token']);
        $this->assertGreaterThan(10, strlen($data['tracking_token']));

        // Verify token is stored in order
        $order = $this->orderRepo->get($data['order_id']);
        $this->assertEquals($data['tracking_token'], $order->tracking_token);
    }

    public function test_tracking_token_unique_per_order(): void
    {
        // Create two orders
        $request1 = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request1->set_header('Content-Type', 'application/json');
        $request1->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ]));

        $request2 = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request2->set_header('Content-Type', 'application/json');
        $request2->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ]));

        $response1 = rest_get_server()->dispatch($request1);
        $response2 = rest_get_server()->dispatch($request2);

        $token1 = $response1->get_data()['tracking_token'];
        $token2 = $response2->get_data()['tracking_token'];

        $this->assertNotEquals($token1, $token2);
    }

    /* ------------------------------------------------------------------ */
    /*  Order Status Endpoint                                             */
    /* ------------------------------------------------------------------ */

    public function test_get_order_status_with_valid_token_succeeds(): void
    {
        // Create an order first
        $createRequest = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $createRequest->set_header('Content-Type', 'application/json');
        $createRequest->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ]));

        $createResponse = rest_get_server()->dispatch($createRequest);
        $orderData = $createResponse->get_data();

        // Get status with token
        $statusRequest = new WP_REST_Request('GET', '/squidly/v1/public/orders/' . $orderData['order_id'] . '/status');
        $statusRequest->set_param('token', $orderData['tracking_token']);

        $statusResponse = rest_get_server()->dispatch($statusRequest);

        $this->assertEquals(200, $statusResponse->get_status());

        $data = $statusResponse->get_data();
        $this->assertEquals($orderData['order_id'], $data['order_id']);
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals('pending', $data['payment_status']);
    }

    public function test_get_order_status_with_invalid_token_fails(): void
    {
        // Create an order
        $createRequest = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $createRequest->set_header('Content-Type', 'application/json');
        $createRequest->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ]));

        $createResponse = rest_get_server()->dispatch($createRequest);
        $orderData = $createResponse->get_data();

        // Try to get status with wrong token
        $statusRequest = new WP_REST_Request('GET', '/squidly/v1/public/orders/' . $orderData['order_id'] . '/status');
        $statusRequest->set_param('token', 'tk_invalid_token');

        $statusResponse = rest_get_server()->dispatch($statusRequest);

        $this->assertEquals(403, $statusResponse->get_status());
        $this->assertStringContainsString('Invalid tracking token', $statusResponse->get_data()['error']);
    }

    public function test_get_order_status_nonexistent_order_fails(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/orders/99999/status');
        $request->set_param('token', 'tk_anytoken');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertStringContainsString('Order not found', $response->get_data()['error']);
    }

    /* ------------------------------------------------------------------ */
    /*  Delivery Types                                                    */
    /* ------------------------------------------------------------------ */

    public function test_pickup_order_created(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'delivery_time' => '2025-11-22 18:00:00',
        ]));

        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(201, $response->get_status());

        $order = $this->orderRepo->get($response->get_data()['order_id']);
        $this->assertEquals('pickup', $order->delivery_type);
        $this->assertNull($order->delivery_address);
        $this->assertEquals('2025-11-22 18:00:00', $order->pickup_time);
    }

    public function test_delivery_order_created(): void
    {
        $request = new WP_REST_Request('POST', '/squidly/v1/public/orders');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
            'delivery_type' => 'delivery',
            'delivery_address' => '789 Test Ave, City',
        ]));

        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(201, $response->get_status());

        $order = $this->orderRepo->get($response->get_data()['order_id']);
        $this->assertEquals('delivery', $order->delivery_type);
        $this->assertEquals('789 Test Ave, City', $order->delivery_address);
    }
}
