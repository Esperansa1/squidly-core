<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use Order;
use OrderItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Order model
 * @covers \Order
 */
class OrderTest extends TestCase
{
    public function test_order_status_constants(): void
    {
        $this->assertEquals('pending', Order::STATUS_PENDING);
        $this->assertEquals('confirmed', Order::STATUS_CONFIRMED);
        $this->assertEquals('preparing', Order::STATUS_PREPARING);
        $this->assertEquals('ready', Order::STATUS_READY);
        $this->assertEquals('completed', Order::STATUS_COMPLETED);
        $this->assertEquals('cancelled', Order::STATUS_CANCELLED);
    }

    public function test_payment_status_constants(): void
    {
        $this->assertEquals('pending', Order::PAYMENT_PENDING);
        $this->assertEquals('paid', Order::PAYMENT_PAID);
        $this->assertEquals('failed', Order::PAYMENT_FAILED);
        $this->assertEquals('refunded', Order::PAYMENT_REFUNDED);
        $this->assertEquals('partially_refunded', Order::PAYMENT_PARTIALLY_REFUNDED);
    }

    public function test_payment_method_constants(): void
    {
        $this->assertEquals('cash', Order::PAYMENT_CASH);
        $this->assertEquals('card', Order::PAYMENT_CARD);
        $this->assertEquals('online', Order::PAYMENT_ONLINE);
    }

    public function test_getValidStatuses_returns_all_statuses(): void
    {
        $statuses = Order::getValidStatuses();

        $this->assertCount(6, $statuses);
        $this->assertContains(Order::STATUS_PENDING, $statuses);
        $this->assertContains(Order::STATUS_CONFIRMED, $statuses);
        $this->assertContains(Order::STATUS_PREPARING, $statuses);
        $this->assertContains(Order::STATUS_READY, $statuses);
        $this->assertContains(Order::STATUS_COMPLETED, $statuses);
        $this->assertContains(Order::STATUS_CANCELLED, $statuses);
    }

    public function test_getValidPaymentStatuses_returns_all_payment_statuses(): void
    {
        $statuses = Order::getValidPaymentStatuses();

        $this->assertCount(5, $statuses);
        $this->assertContains(Order::PAYMENT_PENDING, $statuses);
        $this->assertContains(Order::PAYMENT_PAID, $statuses);
        $this->assertContains(Order::PAYMENT_FAILED, $statuses);
        $this->assertContains(Order::PAYMENT_REFUNDED, $statuses);
        $this->assertContains(Order::PAYMENT_PARTIALLY_REFUNDED, $statuses);
    }

    public function test_getValidPaymentMethods_returns_all_payment_methods(): void
    {
        $methods = Order::getValidPaymentMethods();

        $this->assertCount(3, $methods);
        $this->assertContains(Order::PAYMENT_CASH, $methods);
        $this->assertContains(Order::PAYMENT_CARD, $methods);
        $this->assertContains(Order::PAYMENT_ONLINE, $methods);
    }

    public function test_calculateTotals_with_single_item(): void
    {
        $order = new Order();
        $order->order_items = [
            new OrderItem(101, 'Pizza', 2, 28.50, [], null)
        ];
        $order->delivery_fee = 5.0;

        $order->calculateTotals();

        $this->assertEquals(57.0, $order->subtotal); // 2 * 28.5
        $this->assertEquals(9.69, round($order->tax_amount, 2)); // 57 * 0.17
        $this->assertEquals(71.69, round($order->total_amount, 2)); // 57 + 9.69 + 5
    }

    public function test_calculateTotals_with_multiple_items(): void
    {
        $order = new Order();
        $order->order_items = [
            new OrderItem(101, 'Pizza', 1, 30.0, [], null),
            new OrderItem(102, 'Burger', 2, 25.0, [], null),
            new OrderItem(103, 'Salad', 1, 15.0, [], null),
        ];
        $order->delivery_fee = 10.0;

        $order->calculateTotals();

        $this->assertEquals(95.0, $order->subtotal); // 30 + 50 + 15
        $this->assertEquals(16.15, round($order->tax_amount, 2)); // 95 * 0.17
        $this->assertEquals(121.15, round($order->total_amount, 2)); // 95 + 16.15 + 10
    }

    public function test_calculateTotals_with_zero_delivery_fee(): void
    {
        $order = new Order();
        $order->order_items = [
            new OrderItem(101, 'Pizza', 1, 50.0, [], null)
        ];
        $order->delivery_fee = 0.0;

        $order->calculateTotals();

        $this->assertEquals(50.0, $order->subtotal);
        $this->assertEquals(8.5, $order->tax_amount); // 50 * 0.17
        $this->assertEquals(58.5, $order->total_amount); // 50 + 8.5 + 0
    }

    public function test_calculateTotals_with_custom_tax_rate(): void
    {
        $order = new Order();
        $order->order_items = [
            new OrderItem(101, 'Product', 1, 100.0, [], null)
        ];
        $order->delivery_fee = 0.0;

        $order->calculateTotals(0.20); // 20% tax

        $this->assertEquals(100.0, $order->subtotal);
        $this->assertEquals(20.0, $order->tax_amount); // 100 * 0.20
        $this->assertEquals(120.0, $order->total_amount);
    }

    public function test_canBeCancelled_returns_true_for_pending(): void
    {
        $order = new Order();
        $order->status = Order::STATUS_PENDING;

        $this->assertTrue($order->canBeCancelled());
    }

    public function test_canBeCancelled_returns_true_for_confirmed(): void
    {
        $order = new Order();
        $order->status = Order::STATUS_CONFIRMED;

        $this->assertTrue($order->canBeCancelled());
    }

    public function test_canBeCancelled_returns_false_for_preparing(): void
    {
        $order = new Order();
        $order->status = Order::STATUS_PREPARING;

        $this->assertFalse($order->canBeCancelled());
    }

    public function test_canBeCancelled_returns_false_for_completed(): void
    {
        $order = new Order();
        $order->status = Order::STATUS_COMPLETED;

        $this->assertFalse($order->canBeCancelled());
    }

    public function test_isCompleted_returns_true_for_completed_status(): void
    {
        $order = new Order();
        $order->status = Order::STATUS_COMPLETED;

        $this->assertTrue($order->isCompleted());
    }

    public function test_isCompleted_returns_false_for_other_statuses(): void
    {
        $statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
            Order::STATUS_CANCELLED,
        ];

        foreach ($statuses as $status) {
            $order = new Order();
            $order->status = $status;

            $this->assertFalse($order->isCompleted(), "Failed for status: {$status}");
        }
    }

    public function test_getDisplayName_formats_correctly(): void
    {
        $order = new Order();
        $order->id = 123;
        $order->order_date = '2024-01-15 10:30:00';

        $displayName = $order->getDisplayName();

        $this->assertStringContainsString('Order #123', $displayName);
        $this->assertStringContainsString('Jan', $displayName);
        $this->assertStringContainsString('15', $displayName);
        $this->assertStringContainsString('2024', $displayName);
    }

    public function test_toArray_includes_all_fields(): void
    {
        $order = new Order();
        $order->customer_id = 456;
        $order->status = Order::STATUS_PENDING;
        $order->order_date = '2024-01-15 10:30:00';
        $order->total_amount = 100.0;
        $order->subtotal = 85.0;
        $order->tax_amount = 15.0;
        $order->delivery_fee = 5.0;
        $order->payment_status = Order::PAYMENT_PENDING;
        $order->payment_method = Order::PAYMENT_CASH;
        $order->notes = 'Test notes';
        $order->order_items = [
            new OrderItem(101, 'Test Product', 1, 85.0, [], null)
        ];
        $order->delivery_address = '123 Test St';
        $order->pickup_time = '2024-01-15 12:00:00';
        $order->special_instructions = 'Ring doorbell';
        $order->gateway_transaction_id = 'txn_123';

        $array = $order->toArray();

        $this->assertEquals(456, $array['customer_id']);
        $this->assertEquals(Order::STATUS_PENDING, $array['status']);
        $this->assertEquals('2024-01-15 10:30:00', $array['order_date']);
        $this->assertEquals(100.0, $array['total_amount']);
        $this->assertEquals(85.0, $array['subtotal']);
        $this->assertEquals(15.0, $array['tax_amount']);
        $this->assertEquals(5.0, $array['delivery_fee']);
        $this->assertEquals(Order::PAYMENT_PENDING, $array['payment_status']);
        $this->assertEquals(Order::PAYMENT_CASH, $array['payment_method']);
        $this->assertEquals('Test notes', $array['notes']);
        $this->assertCount(1, $array['order_items']);
        $this->assertEquals('123 Test St', $array['delivery_address']);
        $this->assertEquals('2024-01-15 12:00:00', $array['pickup_time']);
        $this->assertEquals('Ring doorbell', $array['special_instructions']);
        $this->assertEquals('txn_123', $array['gateway_transaction_id']);
    }

    public function test_calculateTotals_with_empty_order_items(): void
    {
        $order = new Order();
        $order->order_items = [];
        $order->delivery_fee = 5.0;

        $order->calculateTotals();

        $this->assertEquals(0.0, $order->subtotal);
        $this->assertEquals(0.0, $order->tax_amount);
        $this->assertEquals(5.0, $order->total_amount); // Only delivery fee
    }

    public function test_calculateTotals_rounds_correctly(): void
    {
        $order = new Order();
        $order->order_items = [
            new OrderItem(101, 'Product', 3, 10.33, [], null) // 30.99
        ];
        $order->delivery_fee = 0.0;

        $order->calculateTotals();

        $this->assertEquals(30.99, round($order->subtotal, 2));
        $this->assertEqualsWithDelta(5.27, $order->tax_amount, 0.01); // 30.99 * 0.17 ≈ 5.27
        $this->assertEqualsWithDelta(36.26, $order->total_amount, 0.01);
    }
}
