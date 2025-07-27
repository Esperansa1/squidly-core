<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use GroupItem;
use ItemType;
use Ingredient;
use Product;
use PHPUnit\Framework\TestCase;

/** @covers \GroupItem */
class GroupItemTest extends TestCase
{
    /* -------------------------------------------------
     *  Helpers
     * ------------------------------------------------*/
    private function mockProductRepo(Product $p): object
    {
        $repo = $this->createStub(\ProductRepository::class);
        $repo->method('get')->with($p->id)->willReturn($p);
        return $repo;
    }

    private function mockIngredientRepo(Ingredient $i): object
    {
        $repo = $this->createStub(\IngredientRepository::class);
        $repo->method('get')->with($i->id)->willReturn($i);
        return $repo;
    }

    /* -------------------------------------------------
     *  Tests
     * ------------------------------------------------*/

    public function test_getItem_product_with_override_keeps_sale(): void
    {
        $prod = new Product([
            'id'=>7,'name'=>'Burger','description'=>'','price'=>10.0,
            'discounted_price'=>7.0,'category'=>null,'tags'=>[],'product_group_ids'=>[]
        ]);

        $gi = new GroupItem([
            'item_id'=>7,
            'item_type'=>ItemType::PRODUCT,
            'override_price'=>8.5
        ]);

        $resolved = $gi->getItem($this->mockProductRepo($prod));

        $this->assertSame(8.5, $resolved->price);
        $this->assertSame(7.0, $resolved->discounted_price);
        $this->assertNotSame(10.0, $resolved->price);
    }

    public function test_getItem_product_without_override_keeps_original_price(): void
    {
        $prod = new Product([
            'id'=>5,'name'=>'Water','description'=>'','price'=>3.0,
            'discounted_price'=>null,'category'=>null,'tags'=>[],'product_group_ids'=>[]
        ]);

        $gi = new GroupItem([
            'item_id'=>5,
            'item_type'=>ItemType::PRODUCT
        ]);

        $resolved = $gi->getItem($this->mockProductRepo($prod));

        $this->assertSame(3.0, $resolved->price);
        $this->assertNull($resolved->discounted_price);
    }

    public function test_getItem_ingredient_with_and_without_override(): void
    {
        $ing = new Ingredient(['id'=>4,'name'=>'Salt','price'=>1.0]);

        $giOverride = new GroupItem([
            'item_id'=>4,
            'item_type'=>ItemType::INGREDIENT,
            'override_price'=>1.5
        ]);
        $giNoOverride = new GroupItem([
            'item_id'=>4,
            'item_type'=>ItemType::INGREDIENT
        ]);

        $with  = $giOverride->getItem(null, $this->mockIngredientRepo($ing));
        $plain = $giNoOverride->getItem(null, $this->mockIngredientRepo($ing));

        $this->assertSame(1.5, $with->price);   // override branch
        $this->assertSame(1.0, $plain->price);  // no-override branch
        $this->assertNotSame($plain->price, $with->price);
    }

    public function test_getItem_returns_null_when_item_missing(): void
    {
        $stubRepo = $this->createStub(\ProductRepository::class);
        $stubRepo->method('get')->willReturn(null);

        $gi = new GroupItem([
            'item_id'=>999,
            'item_type'=>ItemType::PRODUCT
        ]);

        $this->assertNull($gi->getItem($stubRepo));
    }
}
