<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use Ingredient;
use Product;
use StoreBranch;
use PHPUnit\Framework\TestCase;

/** @covers \StoreBranch */
class StoreBranchTest extends TestCase
{
    /* -------------------------------------------------
     * Helpers
     * ------------------------------------------------*/
    private function makeProduct(int $id, string $name, float $price): Product
    {
        return new Product([
            'id'               => $id,
            'name'             => $name,
            'description'      => '',
            'price'            => $price,
            'discounted_price' => null,
            'category'         => null,
            'tags'             => [],
            'product_group_ids'=> [],
        ]);
    }

    private function makeIngredient(int $id, string $name, float $price): Ingredient
    {
        return new Ingredient([
            'id'    => $id,
            'name'  => $name,
            'price' => $price,
        ]);
    }

    /* -------------------------------------------------
     * Tests
     * ------------------------------------------------*/

    public function test_constructor_hydrates_all_fields(): void
    {
        $p1 = $this->makeProduct(10, 'Burger', 30);
        $i1 = $this->makeIngredient(20, 'Salt', 0);

        $branch = new StoreBranch([
            'id'        => 1,
            'name'      => 'Downtown',
            'phone'     => '123-456',
            'city'      => 'Gotham',
            'address'   => '42 Main St',
            'is_open'   => true,
            'activity_times' => [
                'Sunday' => ['08:00-14:00','16:00-22:00'],
                'Monday' => ['08:00-22:00'],
            ],
            'kosher_type'        => 'Kosher Mehadrin',
            'accessibility_list' => ['Wheelchair','Braille menu'],
            'products'           => [$p1],
            'ingredients'        => [$i1],
            'product_availability'    => [10 => true],
            'ingredient_availability' => [20 => false],
        ]);

        /* scalar assertions */
        $this->assertSame(1,           $branch->id);
        $this->assertSame('Downtown',  $branch->name);
        $this->assertTrue($branch->is_open);
        $this->assertSame('Kosher Mehadrin', $branch->kosher_type);
        $this->assertSame(['Wheelchair','Braille menu'], $branch->accessibility_list);

        /* nested objects */
        $this->assertCount(1, $branch->products);
        $this->assertSame('Burger', $branch->products[0]->name);

        $this->assertCount(1, $branch->ingredients);
        $this->assertSame('Salt', $branch->ingredients[0]->name);

        /* availability helpers */
        $this->assertTrue($branch->isProductAvailable(10));
        $this->assertFalse($branch->isIngredientAvailable(20));

        /* activity times preserved */
        $this->assertArrayHasKey('Sunday', $branch->activity_times);
        $this->assertSame(['08:00-14:00','16:00-22:00'], $branch->activity_times['Sunday']);
    }

    public function test_toArray_round_trip(): void
    {
        $p = $this->makeProduct(1,'Water',5);
        $i = $this->makeIngredient(2,'Sugar',0.5);

        $original = [
            'id'=>3,'name'=>'Branch','phone'=>'000',
            'city'=>'Metropolis','address'=>'1 Ave','is_open'=>false,
            'activity_times'=>[],'kosher_type'=>'None','accessibility_list'=>[],
            'products'=>[$p],'ingredients'=>[$i],
            'product_availability'=>[1=>true],'ingredient_availability'=>[2=>true],
        ];

        $branch = new StoreBranch($original);
        $asArray = $branch->toArray();

        /* Product & Ingredient objects are flattened to arrays */
        $this->assertSame('Water', $asArray['products'][0]['name']);
        $this->assertSame('Sugar', $asArray['ingredients'][0]['name']);

        /* All scalar keys match */
        foreach (['id','name','phone','city','address','is_open',
                  'activity_times','kosher_type','accessibility_list'] as $k) {
            $this->assertSame($original[$k], $asArray[$k]);
        }
    }

    public function test_default_values_when_optional_fields_missing(): void
    {
        $branch = new StoreBranch([
            'id'=>99,'name'=>'Minimal','phone'=>'','city'=>'','address'=>'','is_open'=>false,
            'activity_times'=>[],'kosher_type'=>'','accessibility_list'=>[]
        ]);

        $this->assertEmpty($branch->products);
        $this->assertEmpty($branch->ingredients);
        $this->assertFalse($branch->isProductAvailable(123));
        $this->assertFalse($branch->isIngredientAvailable(456));
    }

    /* -------------------------------------------------
    *  New fields & edge cases
    * ------------------------------------------------*/

    public function test_phone_city_address_and_open_flag(): void
    {
        $branch = new StoreBranch([
            'id'=>7,
            'name'=>'Midtown',
            'phone'=>'02-333-7777',
            'city'=>'Jerusalem',
            'address'=>'99 King David St.',
            'is_open'=>false,
            'activity_times'=>[],
            'kosher_type'=>'',
            'accessibility_list'=>[],
        ]);

        $this->assertSame('02-333-7777',   $branch->phone);
        $this->assertSame('Jerusalem',     $branch->city);
        $this->assertSame('99 King David St.', $branch->address);
        $this->assertFalse($branch->is_open);
    }

    public function test_activity_times_accept_multiple_slots_per_day(): void
    {
        $times = [
            'Sunday' => ['08:00-13:00','16:00-21:00'],
            'Monday' => ['09:00-22:00'],
        ];
        $branch = new StoreBranch([
            'id'=>8,'name'=>'Activity','phone'=>'','city'=>'','address'=>'','is_open'=>true,
            'activity_times'=>$times,'kosher_type'=>'','accessibility_list'=>[]
        ]);

        $this->assertSame($times, $branch->activity_times);
        $this->assertCount(2,     $branch->activity_times['Sunday']);
    }

    public function test_kosher_and_accessibility_lists_round_trip(): void
    {
        $branch = new StoreBranch([
            'id'=>9,'name'=>'Kosher','phone'=>'','city'=>'','address'=>'','is_open'=>true,
            'activity_times'=>[],
            'kosher_type'=>'Kosher Mehadrin',
            'accessibility_list'=>['Wheelchair','Elevator'],
        ]);

        $array = $branch->toArray();

        $this->assertSame('Kosher Mehadrin', $array['kosher_type']);
        $this->assertEqualsCanonicalizing(['Wheelchair','Elevator'], $array['accessibility_list']);
    }
}
