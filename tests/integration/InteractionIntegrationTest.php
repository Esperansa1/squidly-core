<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use WP_UnitTestCase;
use ProductRepository;
use ProductGroupRepository;
use GroupItemRepository;
use IngredientRepository;
use StoreBranchRepository;
use CustomerRepository;
use ItemType;
use InvalidArgumentException;
use RuntimeException;
use ResourceInUseException;

/**
 * Additional integration tests for complex component interactions
 * These tests ensure that edge cases and error conditions are properly handled
 */
class AdditionalIntegrationTest extends WP_UnitTestCase
{
    private ProductRepository $productRepo;
    private ProductGroupRepository $productGroupRepo;
    private GroupItemRepository $groupItemRepo;
    private IngredientRepository $ingredientRepo;
    private StoreBranchRepository $branchRepo;
    private CustomerRepository $customerRepo;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->productRepo = new ProductRepository();
        $this->productGroupRepo = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        $this->ingredientRepo = new IngredientRepository();
        $this->branchRepo = new StoreBranchRepository();
        $this->customerRepo = new CustomerRepository();
    }

    /**
     * Test that repository findBy methods handle pagination correctly
     */
    public function test_repository_pagination(): void
    {
        // Create 10 products
        $product_ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $product_ids[] = $this->productRepo->create([
                'name' => "Product $i",
                'price' => 10.00 * $i,
                'category' => 'Test Category',
            ]);
        }

        // Test limit
        $first_page = $this->productRepo->findBy(['category' => 'Test Category'], 3, 0);
        $this->assertCount(3, $first_page);

        // Test offset
        $second_page = $this->productRepo->findBy(['category' => 'Test Category'], 3, 3);
        $this->assertCount(3, $second_page);

        // Ensure different products
        $first_ids = array_map(fn($p) => $p->id, $first_page);
        $second_ids = array_map(fn($p) => $p->id, $second_page);
        $this->assertEmpty(array_intersect($first_ids, $second_ids));

        // Test with no limit
        $all_products = $this->productRepo->findBy(['category' => 'Test Category'], null, 0);
        $this->assertCount(10, $all_products);
    }

    /**
     * Test that circular dependencies are handled properly
     */
    public function test_circular_dependency_prevention(): void
    {
        // Create two products that reference each other through groups
        $product1_id = $this->productRepo->create([
            'name' => 'Product 1',
            'price' => 20.00,
        ]);

        $product2_id = $this->productRepo->create([
            'name' => 'Product 2',
            'price' => 30.00,
        ]);

        // Create group items for both products
        $gi1 = $this->groupItemRepo->create([
            'item_id' => $product1_id,
            'item_type' => 'product',
        ]);

        $gi2 = $this->groupItemRepo->create([
            'item_id' => $product2_id,
            'item_type' => 'product',
        ]);

        // Create groups
        $group1 = $this->productGroupRepo->create([
            'name' => 'Group 1',
            'type' => 'product',
            'group_item_ids' => [$gi2], // Product 1 includes Product 2
        ]);

        $group2 = $this->productGroupRepo->create([
            'name' => 'Group 2',
            'type' => 'product',
            'group_item_ids' => [$gi1], // Product 2 includes Product 1
        ]);

        // Update products with groups
        $this->productRepo->update($product1_id, [
            'product_group_ids' => [$group1],
        ]);

        $this->productRepo->update($product2_id, [
            'product_group_ids' => [$group2],
        ]);

        // Test that we can still retrieve products without infinite recursion
        $product1 = $this->productRepo->get($product1_id);
        $this->assertNotNull($product1);

        $product2 = $this->productRepo->get($product2_id);
        $this->assertNotNull($product2);

        // Test building product doesn't cause infinite loop
        $built = $product1->buildProduct(
            $this->productGroupRepo,
            $this->groupItemRepo,
            $this->productRepo,
            $this->ingredientRepo
        );
        
        $this->assertArrayHasKey('groups_product_data', $built);
    }

    /**
     * Test concurrent modifications to shared resources
     */
    public function test_concurrent_modifications(): void
    {
        // Create shared ingredient
        $ingredient_id = $this->ingredientRepo->create([
            'name' => 'Shared Ingredient',
            'price' => 5.00,
        ]);

        // Create multiple group items referencing the same ingredient
        $gi1 = $this->groupItemRepo->create([
            'item_id' => $ingredient_id,
            'item_type' => 'ingredient',
            'override_price' => 3.00,
        ]);

        $gi2 = $this->groupItemRepo->create([
            'item_id' => $ingredient_id,
            'item_type' => 'ingredient',
            'override_price' => 4.00,
        ]);

        // Update the base ingredient
        $this->ingredientRepo->update($ingredient_id, ['price' => 6.00]);

        // Verify group items maintain their override prices
        $group_item1 = $this->groupItemRepo->get($gi1);
        $resolved1 = $group_item1->getItem(null, $this->ingredientRepo);
        $this->assertEquals(3.00, $resolved1->price);

        $group_item2 = $this->groupItemRepo->get($gi2);
        $resolved2 = $group_item2->getItem(null, $this->ingredientRepo);
        $this->assertEquals(4.00, $resolved2->price);

        // Remove override from one group item
        $this->groupItemRepo->update($gi1, ['override_price' => null]);

        // Verify it now uses the updated base price
        $group_item1_updated = $this->groupItemRepo->get($gi1);
        $resolved1_updated = $group_item1_updated->getItem(null, $this->ingredientRepo);
        $this->assertEquals(6.00, $resolved1_updated->price);
    }

    /**
     * Test branch product cascading with complex product structures
     */
    public function test_branch_product_cascading(): void
    {
        // Create ingredients
        $cheese_id = $this->ingredientRepo->create(['name' => 'Cheese', 'price' => 2.00]);
        $sauce_id = $this->ingredientRepo->create(['name' => 'Sauce', 'price' => 1.00]);

        // Create sub-product (sauce mix)
        $sauce_mix_id = $this->productRepo->create([
            'name' => 'Special Sauce Mix',
            'price' => 5.00,
        ]);

        // Create group items
        $gi_cheese = $this->groupItemRepo->create([
            'item_id' => $cheese_id,
            'item_type' => 'ingredient',
        ]);

        $gi_sauce_mix = $this->groupItemRepo->create([
            'item_id' => $sauce_mix_id,
            'item_type' => 'product',
        ]);

        // Create groups
        $toppings_group = $this->productGroupRepo->create([
            'name' => 'Pizza Toppings',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_cheese],
        ]);

        $extras_group = $this->productGroupRepo->create([
            'name' => 'Pizza Extras',
            'type' => 'product',
            'group_item_ids' => [$gi_sauce_mix],
        ]);

        // Create main product with both groups
        $pizza_id = $this->productRepo->create([
            'name' => 'Super Pizza',
            'price' => 45.00,
            'product_group_ids' => [$toppings_group, $extras_group],
        ]);

        // Create branch
        $branch_id = $this->branchRepo->create([
            'name' => 'Test Branch',
            'phone' => '0501234567',
            'city' => 'Test City',
            'address' => '123 Test St',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => [],
        ]);

        // Add main product to branch (should cascade)
        $this->branchRepo->addProduct($branch_id, $pizza_id, true);

        // Verify all components were added
        $branch = $this->branchRepo->get($branch_id);
        
        // Should have main product and sub-product
        $product_ids = array_map(fn($p) => $p->id, $branch->products);
        $this->assertContains($pizza_id, $product_ids);
        $this->assertContains($sauce_mix_id, $product_ids);

        // Should have ingredient
        $ingredient_ids = array_map(fn($i) => $i->id, $branch->ingredients);
        $this->assertContains($cheese_id, $ingredient_ids);
    }

    /**
     * Test error recovery and transaction-like behavior
     */
    public function test_error_recovery_in_create_operations(): void
    {
        // Test that a failed product creation doesn't leave orphaned data
        try {
            $this->productRepo->create([
                'name' => 'Test Product',
                'price' => -10.00, // Invalid price
            ]);
            $this->fail('Should have thrown exception for negative price');
        } catch (InvalidArgumentException $e) {
            // Expected
            $this->assertStringContainsString('non-negative', $e->getMessage());
        }

        // Verify no partial data was saved
        $all_products = $this->productRepo->getAll();
        $test_products = array_filter($all_products, fn($p) => $p->name === 'Test Product');
        $this->assertEmpty($test_products);
    }

    /**
     * Test that exists() method works correctly across repositories
     */
    public function test_exists_method_across_repositories(): void
    {
        $product_id = $this->productRepo->create([
            'name' => 'Exists Test',
            'price' => 10.00,
        ]);

        $ingredient_id = $this->ingredientRepo->create([
            'name' => 'Exists Test',
            'price' => 5.00,
        ]);

        $customer_id = $this->customerRepo->create([
            'first_name' => 'Exists',
            'last_name' => 'Test',
            'phone' => '0501234567',
            'auth_provider' => 'phone',
        ]);

        // Test exists returns true for valid IDs
        $this->assertTrue($this->productRepo->exists($product_id));
        $this->assertTrue($this->ingredientRepo->exists($ingredient_id));
        $this->assertTrue($this->customerRepo->exists($customer_id));

        // Test exists returns false for invalid IDs
        $this->assertFalse($this->productRepo->exists(99999));
        $this->assertFalse($this->ingredientRepo->exists(99999));
        $this->assertFalse($this->customerRepo->exists(99999));

        // Test exists returns false for negative/zero IDs
        $this->assertFalse($this->productRepo->exists(0));
        $this->assertFalse($this->productRepo->exists(-1));
    }

    /**
     * Test countBy methods work correctly
     */
    public function test_countBy_methods(): void
    {
        // Create test data
        for ($i = 1; $i <= 5; $i++) {
            $this->productRepo->create([
                'name' => "Product $i",
                'price' => 20.00,
                'category' => 'Category A',
            ]);
        }

        for ($i = 6; $i <= 8; $i++) {
            $this->productRepo->create([
                'name' => "Product $i",
                'price' => 30.00,
                'category' => 'Category B',
            ]);
        }

        // Test counting
        $count_a = $this->productRepo->countBy(['category' => 'Category A']);
        $this->assertEquals(5, $count_a);

        $count_b = $this->productRepo->countBy(['category' => 'Category B']);
        $this->assertEquals(3, $count_b);

        $count_all = $this->productRepo->countBy([]);
        $this->assertGreaterThanOrEqual(8, $count_all);
    }

    /**
     * Test that deletion order matters for dependent entities
     */
    public function test_deletion_order_with_dependencies(): void
    {
        // Create a chain: Ingredient -> GroupItem -> ProductGroup -> Product
        $ingredient_id = $this->ingredientRepo->create([
            'name' => 'Chain Ingredient',
            'price' => 1.00,
        ]);

        $gi_id = $this->groupItemRepo->create([
            'item_id' => $ingredient_id,
            'item_type' => 'ingredient',
        ]);

        $pg_id = $this->productGroupRepo->create([
            'name' => 'Chain Group',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_id],
        ]);

        $product_id = $this->productRepo->create([
            'name' => 'Chain Product',
            'price' => 10.00,
            'product_group_ids' => [$pg_id],
        ]);

        // Try to delete in wrong order - should fail
        try {
            $this->ingredientRepo->delete($ingredient_id, true);
            $this->fail('Should not be able to delete ingredient with dependencies');
        } catch (ResourceInUseException $e) {
            $this->assertNotEmpty($e->dependants);
        }

        try {
            $this->groupItemRepo->delete($gi_id, true);
            $this->fail('Should not be able to delete group item with dependencies');
        } catch (ResourceInUseException $e) {
            $this->assertNotEmpty($e->dependants);
        }

        try {
            $this->productGroupRepo->delete($pg_id, true);
            $this->fail('Should not be able to delete product group with dependencies');
        } catch (ResourceInUseException $e) {
            $this->assertNotEmpty($e->dependants);
        }

        // Delete in correct order - should succeed
        $this->assertTrue($this->productRepo->delete($product_id, true));
        $this->assertTrue($this->productGroupRepo->delete($pg_id, true));
        $this->assertTrue($this->groupItemRepo->delete($gi_id, true));
        $this->assertTrue($this->ingredientRepo->delete($ingredient_id, true));
    }

    /**
     * Test that update operations preserve data integrity
     */
    public function test_update_preserves_unmodified_fields(): void
    {
        // Create product with full data
        $product_id = $this->productRepo->create([
            'name' => 'Original Product',
            'price' => 50.00,
            'discounted_price' => 40.00,
            'description' => 'Original description',
            'category' => 'Original Category',
            'tags' => ['tag1', 'tag2'],
        ]);

        // Update only price
        $this->productRepo->update($product_id, ['price' => 60.00]);

        // Verify other fields unchanged
        $product = $this->productRepo->get($product_id);
        $this->assertEquals('Original Product', $product->name);
        $this->assertEquals(60.00, $product->price); // Changed
        $this->assertEquals(40.00, $product->discounted_price); // Unchanged
        $this->assertEquals('Original description', $product->description);
        $this->assertEquals('Original Category', $product->category);
        $this->assertEquals(['tag1', 'tag2'], $product->tags);
    }

    /**
     * Test that branch availability settings work correctly
     */
    public function test_branch_availability_toggles(): void
    {
        $branch_id = $this->branchRepo->create([
            'name' => 'Availability Test Branch',
            'phone' => '0501234567',
            'city' => 'Test City',
            'address' => '123 Test St',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => [],
        ]);

        $product_id = $this->productRepo->create([
            'name' => 'Test Product',
            'price' => 25.00,
        ]);

        $ingredient_id = $this->ingredientRepo->create([
            'name' => 'Test Ingredient',
            'price' => 3.00,
        ]);

        // Add items to branch
        $this->branchRepo->addProduct($branch_id, $product_id, true);
        $this->branchRepo->addIngredient($branch_id, $ingredient_id, true);

        $branch = $this->branchRepo->get($branch_id);
        $this->assertTrue($branch->isProductAvailable($product_id));
        $this->assertTrue($branch->isIngredientAvailable($ingredient_id));

        // Toggle availability
        $this->branchRepo->setProductAvailability($branch_id, $product_id, false);
        $this->branchRepo->setIngredientAvailability($branch_id, $ingredient_id, false);

        $branch = $this->branchRepo->get($branch_id);
        $this->assertFalse($branch->isProductAvailable($product_id));
        $this->assertFalse($branch->isIngredientAvailable($ingredient_id));

        // Remove and re-add should reset availability
        $this->branchRepo->removeProduct($branch_id, $product_id);
        $this->branchRepo->addProduct($branch_id, $product_id, true);

        $branch = $this->branchRepo->get($branch_id);
        $this->assertTrue($branch->isProductAvailable($product_id));
    }
}