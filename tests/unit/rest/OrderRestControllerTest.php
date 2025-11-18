<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Rest;

use OrderRestController;
use OrderRepository;
use Order;
use OrderItem;
use WP_REST_Request;
use WP_Error;
use InvalidArgumentException;
use ResourceInUseException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for OrderRestController
 *
 * Tests all REST API endpoints, validation, permission checks, and error handling
 * without database operations using mocked dependencies.
 */
class OrderRestControllerTest extends TestCase
{
    private OrderRestController $controller;
    private OrderRepository|MockObject $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(OrderRepository::class);
        $this->controller = new OrderRestController();

        // Use reflection to inject the mock repository
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->mockRepository);
    }

    /* ==========================================
     * GET /orders Tests
     * ========================================== */

    public function test_get_orders_returns_successful_response(): void
    {
        // Arrange
        $orders = [$this->createMockOrder(1), $this->createMockOrder(2)];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], null, 0)
            ->willReturn($orders);

        $this->mockRepository
            ->expects($this->once())
            ->method('countBy')
            ->with([])
            ->willReturn(2);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');

        // Act
        $response = $this->controller->get_orders($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertCount(2, $data);
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals(2, $data[1]['id']);
    }

    public function test_get_orders_with_filters(): void
    {
        // Arrange
        $request = new WP_REST_Request('GET', '/squidly/v1/orders');
        $request->set_param('customer_id', 123);
        $request->set_param('status', Order::STATUS_CONFIRMED);
        $request->set_param('per_page', 5);

        $expected_filters = [
            'customer_id' => 123,
            'status' => Order::STATUS_CONFIRMED,
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($expected_filters, 5, 0)
            ->willReturn([]);

        $this->mockRepository
            ->expects($this->once())
            ->method('countBy')
            ->with($expected_filters)
            ->willReturn(0);

        // Act
        $response = $this->controller->get_orders($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
    }

    public function test_get_orders_handles_repository_exception(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->willThrowException(new \Exception('Database error'));

        $request = new WP_REST_Request('GET', '/squidly/v1/orders');

        // Act
        $response = $this->controller->get_orders($request);

        // Assert
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_fetch_error', $response->get_error_code());
        $this->assertEquals(500, $response->get_error_data()['status']);
    }

    /* ==========================================
     * GET /orders/{id} Tests
     * ========================================== */

    public function test_get_order_returns_order_when_found(): void
    {
        // Arrange
        $order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($order);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/123');
        $request->set_url_params(['id' => '123']);

        // Act
        $response = $this->controller->get_order($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    public function test_get_order_returns_404_when_not_found(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn(null);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/999');
        $request->set_url_params(['id' => '999']);

        // Act
        $response = $this->controller->get_order($request);

        // Assert
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    /* ==========================================
     * POST /orders Tests
     * ========================================== */

    public function test_create_order_returns_created_order(): void
    {
        // Arrange
        $order_data = [
            'customer_id' => 123,
            'order_items' => [
                [
                    'product_id' => 456,
                    'product_name' => 'Test Pizza',
                    'quantity' => 2,
                    'unit_price' => 25.0,
                ]
            ],
        ];

        $created_order = $this->createMockOrder(999);

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn(999);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn($created_order);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        foreach ($order_data as $key => $value) {
            $request->set_param($key, $value);
        }

        // Act
        $response = $this->controller->create_order($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals(999, $data['id']);
    }

    public function test_create_order_handles_validation_error(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new InvalidArgumentException('Customer ID is required'));

        $request = new WP_REST_Request('POST', '/squidly/v1/orders');
        $request->set_param('order_items', []);

        // Act
        $response = $this->controller->create_order($request);

        // Assert
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_validation_error', $response->get_error_code());
        $this->assertEquals(400, $response->get_error_data()['status']);
    }

    /* ==========================================
     * PUT /orders/{id} Tests
     * ========================================== */

    public function test_update_order_returns_updated_order(): void
    {
        // Arrange
        $update_data = ['notes' => 'Updated notes'];
        $updated_order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->with(123, ['notes' => 'Updated notes'])
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updated_order);

        $request = new WP_REST_Request('PUT', '/squidly/v1/orders/123');
        $request->set_url_params(['id' => '123']);
        $request->set_param('notes', 'Updated notes');

        // Act
        $response = $this->controller->update_order($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    public function test_update_order_returns_404_when_not_found(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->with(999, [])
            ->willReturn(false);

        $request = new WP_REST_Request('PUT', '/squidly/v1/orders/999');
        $request->set_url_params(['id' => '999']);

        // Act
        $response = $this->controller->update_order($request);

        // Assert
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_not_found', $response->get_error_code());
        $this->assertEquals(404, $response->get_error_data()['status']);
    }

    /* ==========================================
     * DELETE /orders/{id} Tests
     * ========================================== */

    public function test_delete_order_returns_success(): void
    {
        // Arrange
        $order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($order);

        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with(123, false)
            ->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/orders/123');
        $request->set_url_params(['id' => '123']);

        // Act
        $response = $this->controller->delete_order($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['deleted']);
    }

    public function test_delete_order_with_force(): void
    {
        // Arrange
        $order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($order);

        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with(123, true)
            ->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/orders/123');
        $request->set_url_params(['id' => '123']);
        $request->set_param('force', true);

        // Act
        $response = $this->controller->delete_order($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
    }

    public function test_delete_order_handles_resource_in_use_exception(): void
    {
        // Arrange
        $order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($order);

        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new ResourceInUseException(['Cannot delete completed paid orders']));

        $request = new WP_REST_Request('DELETE', '/squidly/v1/orders/123');
        $request->set_url_params(['id' => '123']);

        // Act
        $response = $this->controller->delete_order($request);

        // Assert
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('order_delete_restricted', $response->get_error_code());
        $this->assertEquals(409, $response->get_error_data()['status']);
    }

    /* ==========================================
     * Statistics Tests
     * ========================================== */

    public function test_get_order_statistics_returns_stats(): void
    {
        // Arrange
        $stats = [
            'total_orders' => 100,
            'total_revenue' => 5000.0,
            'average_order_value' => 50.0,
            'status_breakdown' => [
                Order::STATUS_COMPLETED => 80,
                Order::STATUS_PENDING => 20,
            ],
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getStatistics')
            ->willReturn($stats);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/statistics');

        // Act
        $response = $this->controller->get_order_statistics($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertEquals(100, $data['total_orders']);
        $this->assertEquals(5000.0, $data['total_revenue']);
    }

    public function test_get_revenue_analytics_returns_revenue_data(): void
    {
        // Arrange
        $request = new WP_REST_Request('GET', '/squidly/v1/orders/revenue');
        $request->set_param('period', 'daily');
        $request->set_param('date_from', '2023-01-01');
        $request->set_param('date_to', '2023-01-07');

        // Mock orders for revenue calculation
        $orders = [
            $this->createMockOrderWithDate(1, '2023-01-01', 100.0),
            $this->createMockOrderWithDate(2, '2023-01-01', 150.0),
            $this->createMockOrderWithDate(3, '2023-01-02', 200.0),
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'date_from' => '2023-01-01',
                'date_to' => '2023-01-07'
            ])
            ->willReturn($orders);

        // Act
        $response = $this->controller->get_revenue_analytics($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    /* ==========================================
     * Customer Orders Tests
     * ========================================== */

    public function test_get_customer_orders_returns_orders(): void
    {
        // Arrange
        $customer_id = 123;
        $orders = [$this->createMockOrder(1), $this->createMockOrder(2)];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['customer_id' => 123], 10)
            ->willReturn($orders);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/customer/123');
        $request->set_url_params(['customer_id' => '123']);

        // Act
        $response = $this->controller->get_customer_orders($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertCount(2, $data);
    }

    /* ==========================================
     * Status Management Tests
     * ========================================== */

    public function test_update_order_status_returns_updated_order(): void
    {
        // Arrange
        $updated_order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with(123, Order::STATUS_CONFIRMED)
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updated_order);

        $request = new WP_REST_Request('PUT', '/squidly/v1/orders/123/status');
        $request->set_url_params(['id' => '123']);
        $request->set_param('status', Order::STATUS_CONFIRMED);

        // Act
        $response = $this->controller->update_order_status($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    public function test_update_order_status_handles_invalid_status(): void
    {
        // Arrange
        $this->mockRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->willThrowException(new InvalidArgumentException('Invalid order status'));

        $request = new WP_REST_Request('PUT', '/squidly/v1/orders/123/status');
        $request->set_url_params(['id' => '123']);
        $request->set_param('status', 'invalid_status');

        // Act
        $response = $this->controller->update_order_status($request);

        // Assert
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('invalid_status', $response->get_error_code());
        $this->assertEquals(400, $response->get_error_data()['status']);
    }

    public function test_update_payment_status_returns_updated_order(): void
    {
        // Arrange
        $updated_order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('updatePaymentStatus')
            ->with(123, Order::PAYMENT_PAID)
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updated_order);

        $request = new WP_REST_Request('PUT', '/squidly/v1/orders/123/payment');
        $request->set_url_params(['id' => '123']);
        $request->set_param('payment_status', Order::PAYMENT_PAID);

        // Act
        $response = $this->controller->update_payment_status($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    /* ==========================================
     * Order Items Tests
     * ========================================== */

    public function test_add_order_item_returns_updated_order(): void
    {
        // Arrange
        $updated_order = $this->createMockOrder(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('addItem')
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updated_order);

        $request = new WP_REST_Request('POST', '/squidly/v1/orders/123/items');
        $request->set_url_params(['id' => '123']);
        $request->set_param('product_id', 456);
        $request->set_param('product_name', 'Test Pizza');
        $request->set_param('quantity', 2);
        $request->set_param('unit_price', 25.0);

        // Act
        $response = $this->controller->add_order_item($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    /* ==========================================
     * Queue Tests
     * ========================================== */

    public function test_get_order_queue_returns_queue_orders(): void
    {
        // Arrange
        $confirmed_orders = [$this->createMockOrder(1)];
        $preparing_orders = [$this->createMockOrder(2)];

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('getByStatus')
            ->withConsecutive([Order::STATUS_CONFIRMED], [Order::STATUS_PREPARING])
            ->willReturnOnConsecutiveCalls($confirmed_orders, $preparing_orders);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/queue');

        // Act
        $response = $this->controller->get_order_queue($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertCount(2, $data);
    }

    /* ==========================================
     * Popular Items Tests
     * ========================================== */

    public function test_get_popular_items_returns_item_stats(): void
    {
        // Arrange
        $orders = [
            $this->createMockOrderWithItems(1, [
                $this->createMockOrderItem(101, 'Pizza', 2, 25.0),
                $this->createMockOrderItem(102, 'Burger', 1, 15.0),
            ]),
            $this->createMockOrderWithItems(2, [
                $this->createMockOrderItem(101, 'Pizza', 1, 25.0),
            ]),
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn($orders);

        $request = new WP_REST_Request('GET', '/squidly/v1/orders/popular-items');
        $request->set_param('limit', 5);

        // Act
        $response = $this->controller->get_popular_items($request);

        // Assert
        $this->assertNotInstanceOf(\WP_Error::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    private function createMockOrder(int $id): Order
    {
        $order = $this->createMock(Order::class);
        $order->id = $id;
        $order->customer_id = 123;
        $order->status = Order::STATUS_PENDING;
        $order->order_date = '2023-01-01 12:00:00';
        $order->subtotal = 100.0;
        $order->tax_amount = 17.0;
        $order->delivery_fee = 5.0;
        $order->total_amount = 122.0;
        $order->payment_status = Order::PAYMENT_PENDING;
        $order->payment_method = Order::PAYMENT_ONLINE;
        $order->notes = '';
        $order->delivery_address = null;
        $order->pickup_time = null;
        $order->special_instructions = null;
        $order->order_items = [];

        $order->method('canBeCancelled')->willReturn(true);
        $order->method('isCompleted')->willReturn(false);
        $order->method('getDisplayName')->willReturn("Order #{$id}");

        return $order;
    }

    private function createMockOrderWithDate(int $id, string $date, float $total): Order
    {
        $order = $this->createMockOrder($id);
        $order->order_date = $date . ' 12:00:00';
        $order->total_amount = $total;
        return $order;
    }

    private function createMockOrderWithItems(int $id, array $items): Order
    {
        $order = $this->createMockOrder($id);
        $order->order_items = $items;
        return $order;
    }

    private function createMockOrderItem(int $product_id, string $name, int $quantity, float $price): OrderItem
    {
        $item = $this->createMock(OrderItem::class);
        $item->product_id = $product_id;
        $item->product_name = $name;
        $item->quantity = $quantity;
        $item->unit_price = $price;
        $item->total_price = $quantity * $price;
        $item->modifications = [];
        $item->notes = null;

        $item->method('getDisplayString')->willReturn("{$quantity}x {$name}");

        return $item;
    }
}