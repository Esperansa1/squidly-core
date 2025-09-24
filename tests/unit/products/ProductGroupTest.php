<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use ItemType;
use ProductGroup;
use PHPUnit\Framework\TestCase;

/** @covers \ProductGroup */
class ProductGroupTest extends TestCase
{
    public function test_constructor_and_toArray(): void
    {
        $data = [
            'id'             => 3,
            'name'           => 'Combo',
            'description'    => 'Test combo description',
            'type'           => ItemType::PRODUCT,
            'group_item_ids' => [11, 12],
            'availability'   => [1 => true, 2 => false],
        ];

        $pg = new ProductGroup($data);

        /* positive */
        $this->assertSame($data['id'],   $pg->id);
        $this->assertSame($data['name'], $pg->name);
        $this->assertSame($data['description'], $pg->description);
        $this->assertSame($data['type'], $pg->type->value);
        $this->assertSame($data['group_item_ids'], $pg->group_item_ids);
        $this->assertSame($data['availability'], $pg->availability);

        /* round-trip */
        $this->assertSame($data, $pg->toArray());

        /* negative */
        $this->assertNotSame('ingredient', $pg->type->value);
    }

    public function test_constructor_with_defaults(): void
    {
        $data = [
            'id'             => 3,
            'name'           => 'Combo',
            'type'           => ItemType::PRODUCT,
            'group_item_ids' => [11, 12],
        ];

        $pg = new ProductGroup($data);

        // Test default values
        $this->assertSame('', $pg->description);
        $this->assertSame([], $pg->availability);

        // Test toArray includes defaults
        $expected = [
            'id'             => 3,
            'name'           => 'Combo',
            'description'    => '',
            'type'           => ItemType::PRODUCT,
            'group_item_ids' => [11, 12],
            'availability'   => [],
        ];
        $this->assertSame($expected, $pg->toArray());
    }
}
