<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use Product;
use PHPUnit\Framework\TestCase;

/** @covers \Product */
class ProductTest extends TestCase
{
    public function test_constructor_and_toArray_full_payload(): void
    {
        $data = [
            'id'               => 10,
            'name'             => 'Burger',
            'description'      => 'Tasty',
            'price'            => 25.9,
            'discounted_price' => 19.9,
            'category'         => 'Food',
            'tags'             => ['beef','lunch'],
            'product_group_ids'=> [1,2],
        ];

        $p = new Product($data);

        // Positive checks
        $this->assertSame($data, $p->toArray());

        // Negative counterpart
        $this->assertNotEquals(0, $p->price, 'Price should not be zero');
    }

    public function test_constructor_handles_missing_optionals(): void
    {
        $data = ['id'=>4,'name'=>'Water','price'=>3.0];

        $p = new Product($data);

        $this->assertNull($p->discounted_price);
        $this->assertNull($p->category);
        $this->assertSame([], $p->tags);
        $this->assertSame([], $p->product_group_ids);
    }
}
