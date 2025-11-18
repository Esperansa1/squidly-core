<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use StoreBranch;
use Product;
use Ingredient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StoreBranch model
 * @covers \StoreBranch
 */
class StoreBranchTest extends TestCase
{
    public function test_constructor_with_complete_data(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Main Branch',
            'phone' => '03-1234567',
            'city' => 'Tel Aviv',
            'address' => '123 Dizengoff St',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['09:00', '21:00'],
                'MONDAY' => ['09:00', '21:00'],
            ],
            'kosher_type' => 'mehadrin',
            'accessibility_list' => ['wheelchair_accessible', 'hearing_impaired_friendly'],
            'products' => [
                ['id' => 10, 'name' => 'Pizza', 'price' => 30.0, 'category' => null, 'tags' => [], 'product_group_ids' => []],
            ],
            'ingredients' => [
                ['id' => 20, 'name' => 'Cheese', 'price' => 5.0],
            ],
            'product_availability' => [10 => true],
            'ingredient_availability' => [20 => true],
        ];

        $branch = new StoreBranch($data);

        $this->assertEquals(1, $branch->id);
        $this->assertEquals('Main Branch', $branch->name);
        $this->assertEquals('03-1234567', $branch->phone);
        $this->assertEquals('Tel Aviv', $branch->city);
        $this->assertEquals('123 Dizengoff St', $branch->address);
        $this->assertTrue($branch->is_open);
        $this->assertCount(2, $branch->activity_times);
        $this->assertEquals('mehadrin', $branch->kosher_type);
        $this->assertCount(2, $branch->accessibility_list);
        $this->assertCount(1, $branch->products);
        $this->assertCount(1, $branch->ingredients);
        $this->assertInstanceOf(Product::class, $branch->products[0]);
        $this->assertInstanceOf(Ingredient::class, $branch->ingredients[0]);
    }

    public function test_constructor_with_minimal_data(): void
    {
        $data = [
            'id' => 2,
            'name' => 'Minimal Branch',
            'phone' => '04-5678901',
            'city' => 'Haifa',
            'address' => '456 Main St',
            'is_open' => false,
        ];

        $branch = new StoreBranch($data);

        $this->assertEquals(2, $branch->id);
        $this->assertEquals('Minimal Branch', $branch->name);
        $this->assertEquals('04-5678901', $branch->phone);
        $this->assertEquals('Haifa', $branch->city);
        $this->assertEquals('456 Main St', $branch->address);
        $this->assertFalse($branch->is_open);
        $this->assertEquals([], $branch->activity_times);
        $this->assertEquals('', $branch->kosher_type);
        $this->assertEquals([], $branch->accessibility_list);
        $this->assertEquals([], $branch->products);
        $this->assertEquals([], $branch->ingredients);
        $this->assertEquals([], $branch->product_availability);
        $this->assertEquals([], $branch->ingredient_availability);
    }

    public function test_isProductAvailable_returns_true_when_available(): void
    {
        $branch = new StoreBranch([
            'id' => 1,
            'name' => 'Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'product_availability' => [101 => true, 102 => false],
        ]);

        $this->assertTrue($branch->isProductAvailable(101));
        $this->assertFalse($branch->isProductAvailable(102));
    }

    public function test_isProductAvailable_returns_false_when_not_in_list(): void
    {
        $branch = new StoreBranch([
            'id' => 1,
            'name' => 'Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'product_availability' => [101 => true],
        ]);

        $this->assertFalse($branch->isProductAvailable(999));
    }

    public function test_isIngredientAvailable_returns_true_when_available(): void
    {
        $branch = new StoreBranch([
            'id' => 1,
            'name' => 'Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'ingredient_availability' => [201 => true, 202 => false],
        ]);

        $this->assertTrue($branch->isIngredientAvailable(201));
        $this->assertFalse($branch->isIngredientAvailable(202));
    }

    public function test_isIngredientAvailable_returns_false_when_not_in_list(): void
    {
        $branch = new StoreBranch([
            'id' => 1,
            'name' => 'Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'ingredient_availability' => [201 => true],
        ]);

        $this->assertFalse($branch->isIngredientAvailable(999));
    }

    public function test_toArray_includes_all_fields(): void
    {
        $branch = new StoreBranch([
            'id' => 3,
            'name' => 'Full Branch',
            'phone' => '02-9876543',
            'city' => 'Jerusalem',
            'address' => '789 King George St',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['10:00', '22:00'],
            ],
            'kosher_type' => 'regular',
            'accessibility_list' => ['wheelchair_accessible'],
            'products' => [
                ['id' => 30, 'name' => 'Burger', 'price' => 25.0, 'category' => null, 'tags' => [], 'product_group_ids' => []],
            ],
            'ingredients' => [
                ['id' => 40, 'name' => 'Lettuce', 'price' => 2.0],
            ],
            'product_availability' => [30 => true],
            'ingredient_availability' => [40 => false],
        ]);

        $array = $branch->toArray();

        $this->assertEquals(3, $array['id']);
        $this->assertEquals('Full Branch', $array['name']);
        $this->assertEquals('02-9876543', $array['phone']);
        $this->assertEquals('Jerusalem', $array['city']);
        $this->assertEquals('789 King George St', $array['address']);
        $this->assertTrue($array['is_open']);
        $this->assertArrayHasKey('SUNDAY', $array['activity_times']);
        $this->assertEquals('regular', $array['kosher_type']);
        $this->assertContains('wheelchair_accessible', $array['accessibility_list']);
        $this->assertCount(1, $array['products']);
        $this->assertCount(1, $array['ingredients']);
        $this->assertIsArray($array['products'][0]); // Product converted to array
        $this->assertIsArray($array['ingredients'][0]); // Ingredient converted to array
        $this->assertEquals([30 => true], $array['product_availability']);
        $this->assertEquals([40 => false], $array['ingredient_availability']);
    }

    public function test_activity_times_structure(): void
    {
        $activity_times = [
            'SUNDAY' => ['09:00', '17:00'],
            'MONDAY' => ['09:00', '17:00'],
            'TUESDAY' => ['09:00', '17:00'],
            'WEDNESDAY' => ['09:00', '17:00'],
            'THURSDAY' => ['09:00', '17:00'],
            'FRIDAY' => ['09:00', '14:00'],
            'SATURDAY' => [], // Closed
        ];

        $branch = new StoreBranch([
            'id' => 4,
            'name' => 'Weekly Branch',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'activity_times' => $activity_times,
        ]);

        $this->assertCount(7, $branch->activity_times);
        $this->assertEquals(['09:00', '17:00'], $branch->activity_times['SUNDAY']);
        $this->assertEquals(['09:00', '14:00'], $branch->activity_times['FRIDAY']);
        $this->assertEquals([], $branch->activity_times['SATURDAY']);
    }

    public function test_accessibility_list_types(): void
    {
        $accessibility = [
            'wheelchair_accessible',
            'hearing_impaired_friendly',
            'service_dog_friendly',
            'braille_menu',
        ];

        $branch = new StoreBranch([
            'id' => 5,
            'name' => 'Accessible Branch',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'accessibility_list' => $accessibility,
        ]);

        $this->assertCount(4, $branch->accessibility_list);
        $this->assertContains('wheelchair_accessible', $branch->accessibility_list);
        $this->assertContains('hearing_impaired_friendly', $branch->accessibility_list);
        $this->assertContains('service_dog_friendly', $branch->accessibility_list);
        $this->assertContains('braille_menu', $branch->accessibility_list);
    }

    public function test_kosher_type_values(): void
    {
        $kosher_types = ['mehadrin', 'regular', 'not_kosher', ''];

        foreach ($kosher_types as $type) {
            $branch = new StoreBranch([
                'id' => 6,
                'name' => 'Test Branch',
                'phone' => '1234',
                'city' => 'City',
                'address' => 'Address',
                'is_open' => true,
                'kosher_type' => $type,
            ]);

            $this->assertEquals($type, $branch->kosher_type);
        }
    }

    public function test_products_are_converted_to_product_objects(): void
    {
        $branch = new StoreBranch([
            'id' => 7,
            'name' => 'Product Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'products' => [
                ['id' => 50, 'name' => 'Product A', 'price' => 10.0, 'category' => null, 'tags' => [], 'product_group_ids' => []],
                ['id' => 51, 'name' => 'Product B', 'price' => 15.0, 'category' => null, 'tags' => [], 'product_group_ids' => []],
            ],
        ]);

        $this->assertCount(2, $branch->products);
        $this->assertInstanceOf(Product::class, $branch->products[0]);
        $this->assertInstanceOf(Product::class, $branch->products[1]);
        $this->assertEquals('Product A', $branch->products[0]->name);
        $this->assertEquals('Product B', $branch->products[1]->name);
    }

    public function test_products_already_as_objects_are_preserved(): void
    {
        $product = new Product([
            'id' => 60,
            'name' => 'Existing Product',
            'price' => 20.0,
            'category' => null,
            'tags' => [],
            'product_group_ids' => [],
        ]);

        $branch = new StoreBranch([
            'id' => 8,
            'name' => 'Object Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'products' => [$product],
        ]);

        $this->assertCount(1, $branch->products);
        $this->assertInstanceOf(Product::class, $branch->products[0]);
        $this->assertEquals('Existing Product', $branch->products[0]->name);
    }

    public function test_ingredients_are_converted_to_ingredient_objects(): void
    {
        $branch = new StoreBranch([
            'id' => 9,
            'name' => 'Ingredient Test',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'ingredients' => [
                ['id' => 70, 'name' => 'Ingredient A', 'price' => 1.0],
                ['id' => 71, 'name' => 'Ingredient B', 'price' => 2.0],
            ],
        ]);

        $this->assertCount(2, $branch->ingredients);
        $this->assertInstanceOf(Ingredient::class, $branch->ingredients[0]);
        $this->assertInstanceOf(Ingredient::class, $branch->ingredients[1]);
        $this->assertEquals('Ingredient A', $branch->ingredients[0]->name);
        $this->assertEquals('Ingredient B', $branch->ingredients[1]->name);
    }

    public function test_branch_is_closed(): void
    {
        $branch = new StoreBranch([
            'id' => 10,
            'name' => 'Closed Branch',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => false,
        ]);

        $this->assertFalse($branch->is_open);
    }

    public function test_branch_is_open(): void
    {
        $branch = new StoreBranch([
            'id' => 11,
            'name' => 'Open Branch',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
        ]);

        $this->assertTrue($branch->is_open);
    }

    public function test_availability_mixed_boolean_values(): void
    {
        $branch = new StoreBranch([
            'id' => 12,
            'name' => 'Mixed Availability',
            'phone' => '1234',
            'city' => 'City',
            'address' => 'Address',
            'is_open' => true,
            'product_availability' => [
                100 => true,
                101 => false,
                102 => true,
                103 => false,
            ],
            'ingredient_availability' => [
                200 => true,
                201 => true,
                202 => false,
            ],
        ]);

        // Products
        $this->assertTrue($branch->isProductAvailable(100));
        $this->assertFalse($branch->isProductAvailable(101));
        $this->assertTrue($branch->isProductAvailable(102));
        $this->assertFalse($branch->isProductAvailable(103));

        // Ingredients
        $this->assertTrue($branch->isIngredientAvailable(200));
        $this->assertTrue($branch->isIngredientAvailable(201));
        $this->assertFalse($branch->isIngredientAvailable(202));
    }

    public function test_toArray_preserves_data_structure(): void
    {
        $original_data = [
            'id' => 13,
            'name' => 'Preservation Test',
            'phone' => '12345678',
            'city' => 'Test City',
            'address' => 'Test Address 123',
            'is_open' => true,
            'activity_times' => ['MONDAY' => ['08:00', '20:00']],
            'kosher_type' => 'mehadrin',
            'accessibility_list' => ['wheelchair_accessible'],
            'products' => [
                ['id' => 80, 'name' => 'Test Product', 'price' => 30.0, 'category' => null, 'tags' => [], 'product_group_ids' => []],
            ],
            'ingredients' => [
                ['id' => 90, 'name' => 'Test Ingredient', 'price' => 3.0],
            ],
            'product_availability' => [80 => true],
            'ingredient_availability' => [90 => false],
        ];

        $branch = new StoreBranch($original_data);
        $array = $branch->toArray();

        $this->assertEquals($original_data['id'], $array['id']);
        $this->assertEquals($original_data['name'], $array['name']);
        $this->assertEquals($original_data['phone'], $array['phone']);
        $this->assertEquals($original_data['city'], $array['city']);
        $this->assertEquals($original_data['address'], $array['address']);
        $this->assertEquals($original_data['is_open'], $array['is_open']);
        $this->assertEquals($original_data['activity_times'], $array['activity_times']);
        $this->assertEquals($original_data['kosher_type'], $array['kosher_type']);
        $this->assertEquals($original_data['accessibility_list'], $array['accessibility_list']);
        $this->assertEquals($original_data['product_availability'], $array['product_availability']);
        $this->assertEquals($original_data['ingredient_availability'], $array['ingredient_availability']);
    }
}
