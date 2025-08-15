<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use Ingredient;
use PHPUnit\Framework\TestCase;

/** @covers \Ingredient */
class IngredientTest extends TestCase {

    public function test_constructor_and_toArray(): void {
        $data = ['id' => 7, 'name' => 'Sugar', 'price' => 2.5];
        $obj  = new Ingredient($data);

        $this->assertSame(7,       $obj->id);
        $this->assertSame('Sugar', $obj->name);
        $this->assertSame(2.5,     $obj->price);
        $this->assertSame($data,   $obj->toArray());
    }
}
