<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use OrderRepository;
use Order;
use OrderItem;
use InvalidArgumentException;
use ResourceInUseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level tests for OrderRepository.
 *
 * Tests order business logic, validation, and calculations
 * using stub WordPress functions without database operations.
 */
class OrderRepositoryTest extends TestCase
{
    private OrderRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new OrderRepository();
    }

    /* ---------------------------------------------------------------------
     *  Order Creation Validation Tests
     * -------------------------------------------------------------------*/

    public function test_create_valid_order_returns_id(): void
    {
        $orderData = [
            'customer_id' => 123,
            'order_items' => [
                [
                    'product_id' => 456,
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
        ];

        $id = $this->repo->create($orderData);
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /** @dataProvider provideInvalidOrderData */
    public function test_create_invalid_order_throws_exception(array $data, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        
        $this->repo->create($data);
    }

    public function provideInvalidOrderData(): array
    {
        return [
            'missing customer_id' => [
                [
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 10.0]
                    ]
                ],
                'Customer ID is required'
            ],
            'invalid customer_id' => [
                [
                    'customer_id' => 'invalid',
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 10.0]
                    ]
                ],
                'Customer ID is required'
            ],
            'empty order_items' => [
                [
                    'customer_id' => 123,
                    'order_items' => []
                ],
                'Order must contain at least one item'
            ],
            'missing order_items' => [
                [
                    'customer_id' => 123
                ],
                'Order must contain at least one item'
            ],
            'invalid item data' => [
                [
                    'customer_id' => 123,
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1] // Missing unit_price
                    ]
                ],
                'Each order item must have product_id, quantity, and unit_price'
            ],
            'zero quantity' => [
                [
                    'customer_id' => 123,
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 0, 'unit_price' => 10.0]
                    ]
                ],
                'Item quantity must be at least 1'
            ],
            'negative price' => [
                [
                    'customer_id' => 123,
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1, 'unit_price' => -5.0]
                    ]
                ],
                'Item unit price cannot be negative'
            ],
            'invalid status' => [
                [
                    'customer_id' => 123,
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 10.0]
                    ],
                    'status' => 'invalid_status'
                ],
                'Invalid order status'
            ],
            'invalid payment_status' => [
                [
                    'customer_id' => 123,
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 10.0]
                    ],
                    'payment_status' => 'invalid_payment'
                ],
                'Invalid payment status'
            ],
            'invalid payment_method' => [
                [
                    'customer_id' => 123,
                    'order_items' => [
                        ['product_id' => 1, 'quantity' => 1, 'unit_price' => 10.0]
                    ],
                    'payment_method' => 'invalid_method'
                ],
                'Invalid payment method'
            ]
        ];
    }

    /* ---------------------------------------------------------------------
     *  Order Status Validation Tests
     * -------------------------------------------------------------------*/

    public function test_update_status_with_valid_status_succeeds(): void
    {
        $orderId = $this->createTestOrder();
        
        $result = $this->repo->updateStatus($orderId, Order::STATUS_CONFIRMED);
        $this->assertTrue($result);
    }

    public function test_update_status_with_invalid_status_throws_exception(): void
    {
        $orderId = $this->createTestOrder();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order status');
        
        $this->repo->updateStatus($orderId, 'invalid_status');
    }

    public function test_update_payment_status_with_valid_status_succeeds(): void
    {
        $orderId = $this->createTestOrder();
        
        $result = $this->repo->updatePaymentStatus($orderId, Order::PAYMENT_PAID);
        $this->assertTrue($result);
    }

    public function test_update_payment_status_with_invalid_status_throws_exception(): void
    {
        $orderId = $this->createTestOrder();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payment status');
        
        $this->repo->updatePaymentStatus($orderId, 'invalid_payment');
    }

    /* ---------------------------------------------------------------------
     *  Order Update Validation Tests
     * -------------------------------------------------------------------*/

    public function test_update_with_valid_data_succeeds(): void
    {
        $orderId = $this->createTestOrder();
        
        $result = $this->repo->update($orderId, [
            'notes' => 'Updated notes',
            'delivery_address' => 'New address'
        ]);
        
        $this->assertTrue($result);
    }

    public function test_update_with_invalid_status_throws_exception(): void
    {
        $orderId = $this->createTestOrder();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order status');
        
        $this->repo->update($orderId, ['status' => 'invalid_status']);
    }

    public function test_update_with_invalid_item_quantity_throws_exception(): void
    {
        $orderId = $this->createTestOrder();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item quantity must be at least 1');
        
        $this->repo->update($orderId, [
            'order_items' => [
                ['quantity' => 0, 'unit_price' => 10.0]
            ]
        ]);
    }

    public function test_update_with_negative_item_price_throws_exception(): void
    {
        $orderId = $this->createTestOrder();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item unit price cannot be negative');
        
        $this->repo->update($orderId, [
            'order_items' => [
                ['quantity' => 1, 'unit_price' => -5.0]
            ]
        ]);
    }

    /* ---------------------------------------------------------------------
     *  Order Existence and Retrieval Tests
     * -------------------------------------------------------------------*/

    public function test_exists_returns_true_for_valid_order(): void
    {
        $orderId = $this->createTestOrder();
        
        $this->assertTrue($this->repo->exists($orderId));
    }

    public function test_exists_returns_false_for_invalid_id(): void
    {
        $this->assertFalse($this->repo->exists(99999));
        $this->assertFalse($this->repo->exists(0));
        $this->assertFalse($this->repo->exists(-1));
    }

    public function test_get_returns_null_for_nonexistent_order(): void
    {
        $this->assertNull($this->repo->get(99999));
    }

    public function test_update_nonexistent_order_returns_false(): void
    {
        $result = $this->repo->update(99999, ['notes' => 'Test']);
        $this->assertFalse($result);
    }

    /* ---------------------------------------------------------------------
     *  OrderItem Tests
     * -------------------------------------------------------------------*/

    public function test_order_item_creation_and_calculation(): void
    {
        $item = new OrderItem(
            123,
            'Test Product',
            2,
            15.50,
            ['extra cheese'],
            'Special note'
        );

        $this->assertEquals(123, $item->product_id);
        $this->assertEquals('Test Product', $item->product_name);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(15.50, $item->unit_price);
        $this->assertEquals(31.00, $item->total_price);
        $this->assertEquals(['extra cheese'], $item->modifications);
        $this->assertEquals('Special note', $item->notes);
    }

    public function test_order_item_update_quantity(): void
    {
        $item = new OrderItem(123, 'Test Product', 1, 10.00);
        
        $item->updateQuantity(3);
        
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(30.00, $item->total_price);
    }

    public function test_order_item_update_quantity_with_invalid_value_throws_exception(): void
    {
        $item = new OrderItem(123, 'Test Product', 1, 10.00);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');
        
        $item->updateQuantity(0);
    }

    public function test_order_item_update_unit_price(): void
    {
        $item = new OrderItem(123, 'Test Product', 2, 10.00);
        
        $item->updateUnitPrice(15.00);
        
        $this->assertEquals(15.00, $item->unit_price);
        $this->assertEquals(30.00, $item->total_price);
    }

    public function test_order_item_update_unit_price_with_negative_value_throws_exception(): void
    {
        $item = new OrderItem(123, 'Test Product', 1, 10.00);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit price cannot be negative');
        
        $item->updateUnitPrice(-5.00);
    }

    public function test_order_item_display_string(): void
    {
        $item = new OrderItem(123, 'Margherita Pizza', 2, 25.00, ['extra cheese', 'thin crust']);
        
        $displayString = $item->getDisplayString();
        
        $this->assertEquals('2x Margherita Pizza (extra cheese, thin crust)', $displayString);
    }

    public function test_order_item_display_string_without_modifications(): void
    {
        $item = new OrderItem(123, 'Regular Pizza', 1, 20.00);
        
        $displayString = $item->getDisplayString();
        
        $this->assertEquals('1x Regular Pizza', $displayString);
    }

    public function test_order_item_to_array(): void
    {
        $item = new OrderItem(123, 'Test Product', 2, 15.00, ['mod1'], 'note');
        
        $array = $item->toArray();
        
        $expected = [
            'product_id' => 123,
            'product_name' => 'Test Product',
            'quantity' => 2,
            'unit_price' => 15.00,
            'total_price' => 30.00,
            'modifications' => ['mod1'],
            'notes' => 'note',
        ];
        
        $this->assertEquals($expected, $array);
    }

    public function test_order_item_from_array(): void
    {
        $data = [
            'product_id' => 456,
            'product_name' => 'Array Product',
            'quantity' => 3,
            'unit_price' => 12.50,
            'modifications' => ['spicy'],
            'notes' => 'array note'
        ];
        
        $item = OrderItem::fromArray($data);
        
        $this->assertEquals(456, $item->product_id);
        $this->assertEquals('Array Product', $item->product_name);
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(12.50, $item->unit_price);
        $this->assertEquals(37.50, $item->total_price);
        $this->assertEquals(['spicy'], $item->modifications);
        $this->assertEquals('array note', $item->notes);
    }

    /* ---------------------------------------------------------------------
     *  Order Constants and Validation Tests
     * -------------------------------------------------------------------*/

    public function test_order_valid_statuses(): void
    {
        $statuses = Order::getValidStatuses();
        
        $expected = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            Order::STATUS_COMPLETED,
            Order::STATUS_CANCELLED
        ];
        
        $this->assertEquals($expected, $statuses);
    }

    public function test_order_valid_payment_statuses(): void
    {
        $statuses = Order::getValidPaymentStatuses();
        
        $expected = [
            Order::PAYMENT_PENDING,
            Order::PAYMENT_PAID,
            Order::PAYMENT_FAILED,
            Order::PAYMENT_REFUNDED
        ];
        
        $this->assertEquals($expected, $statuses);
    }

    public function test_order_valid_payment_methods(): void
    {
        $methods = Order::getValidPaymentMethods();
        
        $expected = [
            Order::PAYMENT_CASH,
            Order::PAYMENT_CARD,
            Order::PAYMENT_ONLINE
        ];
        
        $this->assertEquals($expected, $methods);
    }

    /* ---------------------------------------------------------------------
     *  Helper Methods
     * -------------------------------------------------------------------*/

    private function createTestOrder(): int
    {
        return $this->repo->create([
            'customer_id' => 123,
            'order_items' => [
                [
                    'product_id' => 456,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 25.0,
                    'total_price' => 25.0,
                    'modifications' => [],
                    'notes' => null
                ]
            ],
            'subtotal' => 25.0,
            'tax_amount' => 4.25,
            'delivery_fee' => 0.0,
            'total_amount' => 29.25
        ]);
    }
}