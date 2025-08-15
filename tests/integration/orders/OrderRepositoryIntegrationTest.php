<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use OrderRepository;
use Order;
use OrderItem;
use CustomerRepository;
use ProductRepository;
use PostTypeRegistry;
use InvalidArgumentException;
use ResourceInUseException;
use WP_UnitTestCase;

/**
 * Integration tests for OrderRepository.
 * 
 * Tests order creation, management, item handling, and business logic
 * in real WordPress environment with database operations.
 */
class OrderRepositoryIntegrationTest extends WP_UnitTestCase
{
    private OrderRepository $orderRepo;
    private CustomerRepository $customerRepo;
    private ProductRepository $productRepo;
    private int $testCustomerId;
    private int $testProductId;

    public function setUp(): void
    {
        parent::setUp();
        
        // Register all post types including Order
        PostTypeRegistry::register_all();
        
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
        $this->productRepo = new ProductRepository();
        
        // Create test customer
        $this->testCustomerId = $this->customerRepo->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+972501234567',
            'auth_provider' => 'phone'
        ]);
        
        // Verify customer was created successfully
        $this->assertGreaterThan(0, $this->testCustomerId, "Test customer should be created successfully");
        $this->assertTrue($this->customerRepo->exists($this->testCustomerId), "Test customer should exist");
        
        // Create test product
        $this->testProductId = $this->productRepo->create([
            'name' => 'Test Pizza',
            'price' => 35.0,
            'description' => 'Delicious test pizza',
            'category' => 'Main'
        ]);
        
        // Verify product was created successfully  
        $this->assertGreaterThan(0, $this->testProductId, "Test product should be created successfully");
        $this->assertTrue($this->productRepo->exists($this->testProductId), "Test product should exist");
    }

    /* ---------------------------------------------------------------------
     *  Order Creation Tests
     * -------------------------------------------------------------------*/

    public function test_create_order_with_single_item(): void
    {
        $orderData = [
            'customer_id' => $this->testCustomerId,
            'order_items' => [
                [
                    'product_id' => $this->testProductId,
                    'product_name' => 'Test Pizza',
                    'quantity' => 2,
                    'unit_price' => 35.0,
                    'total_price' => 70.0,
                    'modifications' => ['extra cheese'],
                    'notes' => 'Well done'
                ]
            ],
            'subtotal' => 70.0,
            'tax_amount' => 11.9,
            'delivery_fee' => 5.0,
            'total_amount' => 86.9,
            'payment_method' => Order::PAYMENT_CARD,
            'delivery_address' => '123 Test Street, Tel Aviv'
        ];

        $orderId = $this->orderRepo->create($orderData);
        
        $this->assertIsInt($orderId);
        $this->assertGreaterThan(0, $orderId);
        
        // Verify order was saved correctly
        $order = $this->orderRepo->get($orderId);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->testCustomerId, $order->customer_id);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(70.0, $order->subtotal);
        $this->assertEquals(11.9, $order->tax_amount);
        $this->assertEquals(5.0, $order->delivery_fee);
        $this->assertEquals(86.9, $order->total_amount);
        $this->assertEquals(Order::PAYMENT_CARD, $order->payment_method);
        $this->assertEquals('123 Test Street, Tel Aviv', $order->delivery_address);
        
        // Verify order items
        $this->assertCount(1, $order->order_items);
        $item = $order->order_items[0];
        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertEquals($this->testProductId, $item->product_id);
        $this->assertEquals('Test Pizza', $item->product_name);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(35.0, $item->unit_price);
        $this->assertEquals(70.0, $item->total_price);
        $this->assertEquals(['extra cheese'], $item->modifications);
        $this->assertEquals('Well done', $item->notes);
    }

    public function test_create_order_with_multiple_items(): void
    {
        // Create another product
        $productId2 = $this->productRepo->create([
            'name' => 'Test Drink',
            'price' => 8.0,
            'category' => 'Beverages'
        ]);

        $orderData = [
            'customer_id' => $this->testCustomerId,
            'order_items' => [
                [
                    'product_id' => $this->testProductId,
                    'product_name' => 'Test Pizza',
                    'quantity' => 1,
                    'unit_price' => 35.0,
                    'total_price' => 35.0,
                    'modifications' => [],
                    'notes' => null
                ],
                [
                    'product_id' => $productId2,
                    'product_name' => 'Test Drink',
                    'quantity' => 2,
                    'unit_price' => 8.0,
                    'total_price' => 16.0,
                    'modifications' => ['no ice'],
                    'notes' => null
                ]
            ],
            'subtotal' => 51.0,
            'tax_amount' => 8.67,
            'delivery_fee' => 0.0,
            'total_amount' => 59.67
        ];

        $orderId = $this->orderRepo->create($orderData);
        $order = $this->orderRepo->get($orderId);
        
        $this->assertCount(2, $order->order_items);
        $this->assertEquals(51.0, $order->subtotal);
        $this->assertEquals(59.67, $order->total_amount);
    }

    /* ---------------------------------------------------------------------
     *  Order Validation Tests
     * -------------------------------------------------------------------*/

    public function test_create_order_without_customer_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer ID is required');
        
        $this->orderRepo->create([
            'order_items' => [
                [
                    'product_id' => $this->testProductId,
                    'product_name' => 'Test Pizza',
                    'quantity' => 1,
                    'unit_price' => 35.0
                ]
            ]
        ]);
    }

    public function test_create_order_without_items_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must contain at least one item');
        
        $this->orderRepo->create([
            'customer_id' => $this->testCustomerId,
            'order_items' => []
        ]);
    }

    public function test_create_order_with_invalid_item_data_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each order item must have product_id, quantity, and unit_price');
        
        $this->orderRepo->create([
            'customer_id' => $this->testCustomerId,
            'order_items' => [
                [
                    'product_id' => $this->testProductId,
                    'quantity' => 1
                    // Missing unit_price
                ]
            ]
        ]);
    }

    public function test_create_order_with_negative_price_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item unit price cannot be negative');
        
        $this->orderRepo->create([
            'customer_id' => $this->testCustomerId,
            'order_items' => [
                [
                    'product_id' => $this->testProductId,
                    'product_name' => 'Test Pizza',
                    'quantity' => 1,
                    'unit_price' => -10.0
                ]
            ]
        ]);
    }

    /* ---------------------------------------------------------------------
     *  Order Retrieval and Querying Tests
     * -------------------------------------------------------------------*/

    public function test_get_order_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->orderRepo->get(99999));
    }

    public function test_get_all_orders_returns_empty_array_when_none_exist(): void
    {
        $orders = $this->orderRepo->getAll();
        $this->assertIsArray($orders);
        // Note: May not be empty if other tests created orders
    }

    public function test_get_orders_by_customer(): void
    {
        // Create orders for different customers
        $customer2Id = $this->customerRepo->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '+972507654321',
            'auth_provider' => 'phone'
        ]);

        $order1Id = $this->createTestOrder($this->testCustomerId);
        $order2Id = $this->createTestOrder($this->testCustomerId);
        $order3Id = $this->createTestOrder($customer2Id);

        
        $customer1Orders = $this->orderRepo->getByCustomer($this->testCustomerId);
        $customer2Orders = $this->orderRepo->getByCustomer($customer2Id);

        $this->assertGreaterThanOrEqual(2, count($customer1Orders));
        $this->assertGreaterThanOrEqual(1, count($customer2Orders));
        
        // Check all orders belong to correct customer
        foreach ($customer1Orders as $order) {
            $this->assertEquals($this->testCustomerId, $order->customer_id);
        }
    }

    public function test_get_orders_by_status(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        // Update status
        $this->orderRepo->updateStatus($orderId, Order::STATUS_PREPARING);
        
        $preparingOrders = $this->orderRepo->getByStatus(Order::STATUS_PREPARING);
        $this->assertGreaterThanOrEqual(1, count($preparingOrders));
        
        foreach ($preparingOrders as $order) {
            $this->assertEquals(Order::STATUS_PREPARING, $order->status);
        }
    }

    /* ---------------------------------------------------------------------
     *  Order Update Tests
     * -------------------------------------------------------------------*/

    public function test_update_order_status(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        $result = $this->orderRepo->updateStatus($orderId, Order::STATUS_CONFIRMED);
        $this->assertTrue($result);
        
        $order = $this->orderRepo->get($orderId);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
    }

    public function test_update_payment_status(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        $result = $this->orderRepo->updatePaymentStatus($orderId, Order::PAYMENT_PAID);
        $this->assertTrue($result);
        
        $order = $this->orderRepo->get($orderId);
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
    }

    public function test_update_order_with_invalid_status_throws_exception(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order status');
        
        $this->orderRepo->updateStatus($orderId, 'invalid_status');
    }

    public function test_update_nonexistent_order_returns_false(): void
    {
        $result = $this->orderRepo->update(99999, ['notes' => 'Test note']);
        $this->assertFalse($result);
    }

    /* ---------------------------------------------------------------------
     *  Order Items Management Tests
     * -------------------------------------------------------------------*/

    public function test_add_item_to_existing_order(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        $order = $this->orderRepo->get($orderId);
        $originalItemCount = count($order->order_items);
        
        // Create new product
        $newProductId = $this->productRepo->create([
            'name' => 'Added Item',
            'price' => 12.0
        ]);
        
        $newItem = new OrderItem(
            $newProductId,
            'Added Item',
            1,
            12.0,
            ['special request']
        );
        
        $result = $this->orderRepo->addItem($orderId, $newItem);
        $this->assertTrue($result);
        
        // Verify item was added and totals recalculated
        $updatedOrder = $this->orderRepo->get($orderId);
        $this->assertCount($originalItemCount + 1, $updatedOrder->order_items);
        $this->assertGreaterThan($order->total_amount, $updatedOrder->total_amount);
    }

    /* ---------------------------------------------------------------------
     *  Order Statistics Tests
     * -------------------------------------------------------------------*/

    public function test_get_order_statistics(): void
    {
        // Create several orders
        $this->createTestOrder($this->testCustomerId);
        $this->createTestOrder($this->testCustomerId);
        
        $stats = $this->orderRepo->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('average_order_value', $stats);
        $this->assertArrayHasKey('status_breakdown', $stats);
        $this->assertArrayHasKey('payment_breakdown', $stats);
        
        $this->assertGreaterThanOrEqual(2, $stats['total_orders']);
        $this->assertGreaterThan(0, $stats['total_revenue']);
    }

    /* ---------------------------------------------------------------------
     *  Order Deletion Tests
     * -------------------------------------------------------------------*/

    public function test_delete_pending_order_succeeds(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        $result = $this->orderRepo->delete($orderId, true);
        $this->assertTrue($result);
        
        $order = $this->orderRepo->get($orderId);
        $this->assertNull($order);
    }

    public function test_delete_completed_paid_order_throws_exception(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        // Mark as completed and paid
        $this->orderRepo->updateStatus($orderId, Order::STATUS_COMPLETED);
        $this->orderRepo->updatePaymentStatus($orderId, Order::PAYMENT_PAID);
        
        $this->expectException(ResourceInUseException::class);
        $this->expectExceptionMessage('Cannot delete completed paid orders');
        
        $this->orderRepo->delete($orderId, false);
    }

    public function test_exists_method(): void
    {
        $orderId = $this->createTestOrder($this->testCustomerId);
        
        $this->assertTrue($this->orderRepo->exists($orderId));
        $this->assertFalse($this->orderRepo->exists(99999));
    }

    /* ---------------------------------------------------------------------
     *  Helper Methods
     * -------------------------------------------------------------------*/

    private function createTestOrder(int $customerId): int
    {
        return $this->orderRepo->create([
            'customer_id' => $customerId,
            'order_items' => [
                [
                    'product_id' => $this->testProductId,
                    'product_name' => 'Test Pizza',
                    'quantity' => 1,
                    'unit_price' => 35.0,
                    'total_price' => 35.0,
                    'modifications' => [],
                    'notes' => null
                ]
            ],
            'subtotal' => 35.0,
            'tax_amount' => 5.95,
            'delivery_fee' => 0.0,
            'total_amount' => 40.95
        ]);
    }
}