<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use OrderItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OrderItem model
 * @covers \OrderItem
 */
class OrderItemTest extends TestCase
{
    public function test_constructor_creates_valid_order_item(): void
    {
        $item = new OrderItem(
            101,
            'Margherita Pizza',
            2,
            28.50,
            ['Extra cheese', 'Thin crust'],
            'Well done'
        );

        $this->assertEquals(101, $item->product_id);
        $this->assertEquals('Margherita Pizza', $item->product_name);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(28.50, $item->unit_price);
        $this->assertEquals(57.0, $item->total_price); // Automatically calculated
        $this->assertEquals(['Extra cheese', 'Thin crust'], $item->modifications);
        $this->assertEquals('Well done', $item->notes);
    }

    public function test_constructor_with_empty_modifications_and_notes(): void
    {
        $item = new OrderItem(102, 'Simple Product', 1, 10.0);

        $this->assertEquals(102, $item->product_id);
        $this->assertEquals('Simple Product', $item->product_name);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(10.0, $item->unit_price);
        $this->assertEquals(10.0, $item->total_price);
        $this->assertEquals([], $item->modifications);
        $this->assertNull($item->notes);
    }

    public function test_calculateTotal_computes_correctly(): void
    {
        $item = new OrderItem(101, 'Product', 5, 12.50, [], null);

        $this->assertEquals(62.50, $item->total_price); // 5 * 12.50
    }

    public function test_calculateTotal_with_single_quantity(): void
    {
        $item = new OrderItem(101, 'Product', 1, 25.99, [], null);

        $this->assertEquals(25.99, $item->total_price);
    }

    public function test_fromArray_creates_order_item(): void
    {
        $data = [
            'product_id' => 103,
            'product_name' => 'Caesar Salad',
            'quantity' => 3,
            'unit_price' => 18.00,
            'modifications' => ['No croutons', 'Extra dressing'],
            'notes' => 'Allergy warning',
        ];

        $item = OrderItem::fromArray($data);

        $this->assertEquals(103, $item->product_id);
        $this->assertEquals('Caesar Salad', $item->product_name);
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(18.00, $item->unit_price);
        $this->assertEquals(54.0, $item->total_price);
        $this->assertEquals(['No croutons', 'Extra dressing'], $item->modifications);
        $this->assertEquals('Allergy warning', $item->notes);
    }

    public function test_fromArray_with_minimal_data(): void
    {
        $data = [
            'product_id' => 104,
            'product_name' => 'Minimal Product',
            'quantity' => 1,
            'unit_price' => 5.0,
        ];

        $item = OrderItem::fromArray($data);

        $this->assertEquals(104, $item->product_id);
        $this->assertEquals('Minimal Product', $item->product_name);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(5.0, $item->unit_price);
        $this->assertEquals([], $item->modifications);
        $this->assertNull($item->notes);
    }

    public function test_toArray_includes_all_fields(): void
    {
        $item = new OrderItem(
            105,
            'Combo Meal',
            4,
            35.00,
            ['Upgrade to large', 'Add fries'],
            'For delivery'
        );

        $array = $item->toArray();

        $this->assertEquals(105, $array['product_id']);
        $this->assertEquals('Combo Meal', $array['product_name']);
        $this->assertEquals(4, $array['quantity']);
        $this->assertEquals(35.00, $array['unit_price']);
        $this->assertEquals(140.0, $array['total_price']);
        $this->assertEquals(['Upgrade to large', 'Add fries'], $array['modifications']);
        $this->assertEquals('For delivery', $array['notes']);
    }

    public function test_getDisplayString_with_modifications(): void
    {
        $item = new OrderItem(
            101,
            'Pizza',
            2,
            25.0,
            ['Extra cheese', 'Thin crust'],
            null
        );

        $display = $item->getDisplayString();

        $this->assertStringContainsString('2x Pizza', $display);
        $this->assertStringContainsString('Extra cheese', $display);
        $this->assertStringContainsString('Thin crust', $display);
    }

    public function test_getDisplayString_without_modifications(): void
    {
        $item = new OrderItem(101, 'Simple Burger', 1, 20.0, [], null);

        $display = $item->getDisplayString();

        $this->assertEquals('1x Simple Burger', $display);
        $this->assertStringNotContainsString('(', $display);
    }

    public function test_updateQuantity_recalculates_total(): void
    {
        $item = new OrderItem(101, 'Product', 2, 15.0, [], null);
        $this->assertEquals(30.0, $item->total_price);

        $item->updateQuantity(5);

        $this->assertEquals(5, $item->quantity);
        $this->assertEquals(75.0, $item->total_price);
    }

    public function test_updateQuantity_throws_exception_for_zero(): void
    {
        $item = new OrderItem(101, 'Product', 2, 15.0, [], null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        $item->updateQuantity(0);
    }

    public function test_updateQuantity_throws_exception_for_negative(): void
    {
        $item = new OrderItem(101, 'Product', 2, 15.0, [], null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        $item->updateQuantity(-5);
    }

    public function test_updateUnitPrice_recalculates_total(): void
    {
        $item = new OrderItem(101, 'Product', 3, 10.0, [], null);
        $this->assertEquals(30.0, $item->total_price);

        $item->updateUnitPrice(12.50);

        $this->assertEquals(12.50, $item->unit_price);
        $this->assertEquals(37.50, $item->total_price); // 3 * 12.50
    }

    public function test_updateUnitPrice_throws_exception_for_negative(): void
    {
        $item = new OrderItem(101, 'Product', 2, 15.0, [], null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit price cannot be negative');

        $item->updateUnitPrice(-10.0);
    }

    public function test_updateUnitPrice_accepts_zero(): void
    {
        $item = new OrderItem(101, 'Free Product', 1, 10.0, [], null);

        $item->updateUnitPrice(0.0);

        $this->assertEquals(0.0, $item->unit_price);
        $this->assertEquals(0.0, $item->total_price);
    }

    public function test_calculateTotal_with_large_quantity(): void
    {
        $item = new OrderItem(101, 'Bulk Product', 100, 2.50, [], null);

        $this->assertEquals(250.0, $item->total_price); // 100 * 2.50
    }

    public function test_calculateTotal_with_decimal_price(): void
    {
        $item = new OrderItem(101, 'Decimal Product', 7, 3.33, [], null);

        $this->assertEquals(23.31, round($item->total_price, 2)); // 7 * 3.33
    }

    public function test_modifications_array_is_preserved(): void
    {
        $modifications = ['Mod 1', 'Mod 2', 'Mod 3'];
        $item = new OrderItem(101, 'Product', 1, 10.0, $modifications, null);

        $this->assertSame($modifications, $item->modifications);
        $this->assertCount(3, $item->modifications);
    }

    public function test_notes_can_be_empty_string(): void
    {
        $item = new OrderItem(101, 'Product', 1, 10.0, [], '');

        $this->assertEquals('', $item->notes);
    }

    public function test_round_trip_from_array_to_array(): void
    {
        $original_data = [
            'product_id' => 999,
            'product_name' => 'Round Trip Product',
            'quantity' => 5,
            'unit_price' => 12.75,
            'modifications' => ['Custom mod 1', 'Custom mod 2'],
            'notes' => 'Round trip test notes',
        ];

        $item = OrderItem::fromArray($original_data);
        $result_data = $item->toArray();

        $this->assertEquals(999, $result_data['product_id']);
        $this->assertEquals('Round Trip Product', $result_data['product_name']);
        $this->assertEquals(5, $result_data['quantity']);
        $this->assertEquals(12.75, $result_data['unit_price']);
        $this->assertEquals(63.75, $result_data['total_price']); // Calculated
        $this->assertEquals(['Custom mod 1', 'Custom mod 2'], $result_data['modifications']);
        $this->assertEquals('Round trip test notes', $result_data['notes']);
    }
}
