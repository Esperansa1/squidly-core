<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductGroupRepository;
use GroupItemRepository;
use ProductRepository;
use IngredientRepository;
use ItemType;
use WP_UnitTestCase;
use ResourceInUseException;
use InvalidArgumentException;

/**
 * WordPress-backed integration tests for ProductGroupRepository.
 *
 * Run with:  WP_INTEGRATION=1 vendor/bin/phpunit --testsuite integration
 */
class ProductGroupRepositoryIntegrationTest extends WP_UnitTestCase
{
    private ProductGroupRepository $repo;
    private GroupItemRepository $groupItemRepo;
    private ProductRepository $productRepo;
    private IngredientRepository $ingredientRepo;

    public function set_up(): void
    {
        parent::set_up();

        $this->repo = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        $this->productRepo = new ProductRepository();
        $this->ingredientRepo = new IngredientRepository();
    }

    /* ------------------------------------------------------------------ */
    public function test_full_create_get_update_delete_cycle(): void
    {
        /* create a product and group item */
        $product_id = $this->productRepo->create([
            'name' => 'Test Burger',
            'price' => 25.0,
        ]);

        $group_item_id = $this->groupItemRepo->create([
            'item_id' => $product_id,
            'item_type' => ItemType::PRODUCT,
        ]);

        /* create product group */
        $id = $this->repo->create([
            'name' => 'Main Dishes',
            'description' => 'Integration test group',
            'type' => 'product',
            'group_item_ids' => [$group_item_id],
        ]);
        $this->assertSame('product_group', get_post_type($id));

        /* get */
        $pg = $this->repo->get($id);
        $this->assertSame('Main Dishes', $pg->name);
        $this->assertSame('Integration test group', $pg->description);
        $this->assertEquals(ItemType::PRODUCT, $pg->type->value);
        $this->assertCount(1, $pg->group_item_ids);

        /* update */
        $this->repo->update($id, ['name' => 'Updated Dishes', 'description' => 'Updated description']);
        $pg2 = $this->repo->get($id);
        $this->assertSame('Updated Dishes', $pg2->name);
        $this->assertSame('Updated description', $pg2->description);

        /* delete  no dependants */
        $this->assertTrue($this->repo->delete($id, true));
        $this->assertFalse(get_post_status($id));
    }

    public function test_getAll_returns_all_product_groups(): void
    {
        // create 2 product groups
        $gi1 = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);
        $gi2 = $this->groupItemRepo->create([
            'item_id' => 2,
            'item_type' => ItemType::INGREDIENT,
        ]);

        $a = $this->repo->create(['name' => 'Group A', 'type' => 'product', 'group_item_ids' => [$gi1]]);
        $b = $this->repo->create(['name' => 'Group B', 'type' => 'ingredient', 'group_item_ids' => [$gi2]]);

        $all = $this->repo->getAll();
        $names = array_map(fn($pg) => $pg->name, $all);
        sort($names);

        $this->assertCount(2, $all);
        $this->assertSame(['Group A', 'Group B'], $names);
    }

    public function test_getAllByItemType_filters_by_type(): void
    {
        // Create product groups of different types
        $product_gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);
        $ingredient_gi = $this->groupItemRepo->create([
            'item_id' => 2,
            'item_type' => ItemType::INGREDIENT,
        ]);

        $this->repo->create(['name' => 'Product Group 1', 'type' => 'product', 'group_item_ids' => [$product_gi]]);
        $this->repo->create(['name' => 'Product Group 2', 'type' => 'product', 'group_item_ids' => [$product_gi]]);
        $this->repo->create(['name' => 'Ingredient Group', 'type' => 'ingredient', 'group_item_ids' => [$ingredient_gi]]);

        $product_groups = $this->repo->getProductGroups();
        $this->assertCount(2, $product_groups);
        foreach ($product_groups as $group) {
            $this->assertEquals(ItemType::PRODUCT, $group->type->value);
        }

        $ingredient_groups = $this->repo->getIngredientGroups();
        $this->assertCount(1, $ingredient_groups);
        foreach ($ingredient_groups as $group) {
            $this->assertEquals(ItemType::INGREDIENT, $group->type->value);
        }
    }

    public function test_delete_with_dependants_throws_exception(): void
    {
        // Create product group
        $product_id = $this->productRepo->create([
            'name' => 'Burger',
            'price' => 25.0,
        ]);

        $gi = $this->groupItemRepo->create([
            'item_id' => $product_id,
            'item_type' => ItemType::PRODUCT,
        ]);

        $pg_id = $this->repo->create([
            'name' => 'Test Group',
            'type' => 'product',
            'group_item_ids' => [$gi],
        ]);

        // Add this group to a product
        $this->productRepo->update($product_id, [
            'product_group_ids' => [$pg_id],
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->repo->delete($pg_id, true);
    }

    /** @dataProvider provideInvalidCreate */
    public function test_create_invalid_payload_throws(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function provideInvalidCreate(): array
    {
        return [
            'missing name' => [['type' => 'product', 'group_item_ids' => []]],
            'invalid type' => [['name' => 'Test', 'type' => 'invalid']],
        ];
    }

    public function test_validateGroupItems_prevents_mixed_types(): void
    {
        // Create product and ingredient group items
        $product_gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);
        $ingredient_gi = $this->groupItemRepo->create([
            'item_id' => 2,
            'item_type' => ItemType::INGREDIENT,
        ]);

        // Try to create product group with mixed items
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot mix.*in the same group/');

        $this->repo->create([
            'name' => 'Mixed Group',
            'type' => 'product',
            'group_item_ids' => [$product_gi, $ingredient_gi], // MIXED!
        ]);
    }

    public function test_availability_management(): void
    {
        // Create a simple product group
        $gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);

        $pg_id = $this->repo->create([
            'name' => 'Availability Test',
            'type' => 'product',
            'group_item_ids' => [$gi],
            'availability' => [1 => true, 2 => false],
        ]);

        // Get availability
        $availability = $this->repo->getAvailability($pg_id);
        $this->assertTrue($availability[1]);
        $this->assertFalse($availability[2]);

        // Update availability
        $this->repo->updateAvailability($pg_id, [1 => false, 3 => true]);
        $updated_availability = $this->repo->getAvailability($pg_id);
        $this->assertFalse($updated_availability[1]);
        $this->assertTrue($updated_availability[3]);
    }

    public function test_findBy_search(): void
    {
        $gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);

        $this->repo->create(['name' => 'Burgers', 'type' => 'product', 'group_item_ids' => [$gi]]);
        $this->repo->create(['name' => 'Burger Extras', 'type' => 'product', 'group_item_ids' => [$gi]]);
        $this->repo->create(['name' => 'Pizzas', 'type' => 'product', 'group_item_ids' => [$gi]]);

        $results = $this->repo->findBy(['search' => 'Burger']);
        $this->assertCount(2, $results);

        foreach ($results as $group) {
            $this->assertStringContainsString('Burger', $group->name);
        }
    }

    public function test_findBy_type_filter(): void
    {
        $product_gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);
        $ingredient_gi = $this->groupItemRepo->create([
            'item_id' => 2,
            'item_type' => ItemType::INGREDIENT,
        ]);

        $this->repo->create(['name' => 'Products', 'type' => 'product', 'group_item_ids' => [$product_gi]]);
        $this->repo->create(['name' => 'Ingredients', 'type' => 'ingredient', 'group_item_ids' => [$ingredient_gi]]);

        $product_results = $this->repo->findBy(['type' => 'product']);
        $this->assertCount(1, $product_results);
        $this->assertEquals(ItemType::PRODUCT, $product_results[0]->type->value);

        $ingredient_results = $this->repo->findBy(['type' => 'ingredient']);
        $this->assertCount(1, $ingredient_results);
        $this->assertEquals(ItemType::INGREDIENT, $ingredient_results[0]->type->value);
    }

    public function test_exists_returns_true_for_existing_group(): void
    {
        $gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);

        $id = $this->repo->create(['name' => 'Test', 'type' => 'product', 'group_item_ids' => [$gi]]);
        $this->assertTrue($this->repo->exists($id));
        $this->assertFalse($this->repo->exists(99999));
        $this->assertFalse($this->repo->exists(0));
        $this->assertFalse($this->repo->exists(-1));
    }

    public function test_countBy_returns_correct_count(): void
    {
        $gi = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);

        $this->repo->create(['name' => 'Group 1', 'type' => 'product', 'group_item_ids' => [$gi]]);
        $this->repo->create(['name' => 'Group 2', 'type' => 'product', 'group_item_ids' => [$gi]]);

        $count = $this->repo->countBy(['type' => 'product']);
        $this->assertEquals(2, $count);
    }

    public function test_findContainingGroupItem(): void
    {
        $gi1 = $this->groupItemRepo->create([
            'item_id' => 1,
            'item_type' => ItemType::PRODUCT,
        ]);
        $gi2 = $this->groupItemRepo->create([
            'item_id' => 2,
            'item_type' => ItemType::PRODUCT,
        ]);

        $this->repo->create(['name' => 'Group With GI1', 'type' => 'product', 'group_item_ids' => [$gi1]]);
        $this->repo->create(['name' => 'Group With GI2', 'type' => 'product', 'group_item_ids' => [$gi2]]);
        $this->repo->create(['name' => 'Group With Both', 'type' => 'product', 'group_item_ids' => [$gi1, $gi2]]);

        $groups_with_gi1 = $this->repo->findContainingGroupItem($gi1);
        $this->assertCount(2, $groups_with_gi1);
    }
}
