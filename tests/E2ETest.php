<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\E2E;

use WP_UnitTestCase;
use StoreBranchRepository;
use ProductRepository;
use IngredientRepository;
use ProductGroupRepository;
use GroupItemRepository;
use CustomerRepository;
use StoreBranch;
use Product;
use Ingredient;
use ProductGroup;
use GroupItem;
use Customer;
use ItemType;

/**
 * End-to-End tests for the complete Squidly restaurant system
 * Tests real-world scenarios and complex component interactions
 */
class CompleteSystemE2ETest extends WP_UnitTestCase
{
    private StoreBranchRepository $branchRepo;
    private ProductRepository $productRepo;
    private IngredientRepository $ingredientRepo;
    private ProductGroupRepository $productGroupRepo;
    private GroupItemRepository $groupItemRepo;
    private CustomerRepository $customerRepo;

    public function setUp(): void
    {
        parent::setUp();
        
        // Initialize all repositories
        $this->branchRepo = new StoreBranchRepository();
        $this->productRepo = new ProductRepository();
        $this->ingredientRepo = new IngredientRepository();
        $this->productGroupRepo = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        $this->customerRepo = new CustomerRepository();
    }

    /**
     * Test complete restaurant setup scenario
     * This simulates setting up a new restaurant branch with full menu
     */
    public function test_complete_restaurant_setup_scenario(): void
    {
        // Step 1: Create ingredients
        $lettuce_id = $this->ingredientRepo->create(['name' => 'Lettuce', 'price' => 0.50]);
        $tomato_id = $this->ingredientRepo->create(['name' => 'Tomato', 'price' => 0.75]);
        $cheese_id = $this->ingredientRepo->create(['name' => 'Cheese', 'price' => 1.50]);
        $bacon_id = $this->ingredientRepo->create(['name' => 'Bacon', 'price' => 2.00]);
        $onion_id = $this->ingredientRepo->create(['name' => 'Onion', 'price' => 0.50]);
        $pickle_id = $this->ingredientRepo->create(['name' => 'Pickle', 'price' => 0.50]);
        
        // Step 2: Create group items for ingredients with price overrides
        $gi_lettuce = $this->groupItemRepo->create([
            'item_id' => $lettuce_id,
            'item_type' => 'ingredient',
            'override_price' => null, // Free with burger
        ]);
        
        $gi_tomato = $this->groupItemRepo->create([
            'item_id' => $tomato_id,
            'item_type' => 'ingredient',
            'override_price' => null, // Free with burger
        ]);
        
        $gi_cheese = $this->groupItemRepo->create([
            'item_id' => $cheese_id,
            'item_type' => 'ingredient',
            'override_price' => 1.00, // Discounted when with burger
        ]);
        
        $gi_bacon = $this->groupItemRepo->create([
            'item_id' => $bacon_id,
            'item_type' => 'ingredient',
            'override_price' => 1.50, // Discounted when with burger
        ]);

        // Step 3: Create product group for burger toppings
        $burger_toppings_group_id = $this->productGroupRepo->create([
            'name' => 'Burger Toppings',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_lettuce, $gi_tomato, $gi_cheese, $gi_bacon],
        ]);

        // Step 4: Create side products
        $fries_id = $this->productRepo->create([
            'name' => 'French Fries',
            'price' => 12.00,
            'description' => 'Crispy golden fries',
            'category' => 'Sides',
        ]);
        
        $salad_id = $this->productRepo->create([
            'name' => 'Garden Salad',
            'price' => 15.00,
            'description' => 'Fresh garden salad',
            'category' => 'Sides',
        ]);

        // Step 5: Create group items for side products
        $gi_fries = $this->groupItemRepo->create([
            'item_id' => $fries_id,
            'item_type' => 'product',
            'override_price' => 8.00, // Discounted as combo side
        ]);
        
        $gi_salad = $this->groupItemRepo->create([
            'item_id' => $salad_id,
            'item_type' => 'product',
            'override_price' => 10.00, // Discounted as combo side
        ]);

        // Step 6: Create product group for combo sides
        $combo_sides_group_id = $this->productGroupRepo->create([
            'name' => 'Combo Sides',
            'type' => 'product',
            'group_item_ids' => [$gi_fries, $gi_salad],
        ]);

        // Step 7: Create main product (burger) with groups
        $burger_id = $this->productRepo->create([
            'name' => 'Classic Burger',
            'price' => 45.00,
            'discounted_price' => 39.99,
            'description' => 'Our signature beef burger',
            'category' => 'Main Dishes',
            'tags' => ['beef', 'signature', 'bestseller'],
            'product_group_ids' => [$burger_toppings_group_id, $combo_sides_group_id],
        ]);

        // Step 8: Create restaurant branch
        $branch_id = $this->branchRepo->create([
            'name' => 'Downtown Branch',
            'phone' => '+972-3-1234567',
            'city' => 'Tel Aviv',
            'address' => '123 Dizengoff St, Tel Aviv',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['10:00-22:00'],
                'MONDAY' => ['10:00-22:00'],
                'TUESDAY' => ['10:00-22:00'],
                'WEDNESDAY' => ['10:00-22:00'],
                'THURSDAY' => ['10:00-23:00'],
                'FRIDAY' => ['10:00-15:00'],
                'SATURDAY' => ['20:00-23:00'],
            ],
            'kosher_type' => 'Kosher Meat',
            'accessibility_list' => ['wheelchair_accessible', 'braille_menu'],
        ]);

        // Step 9: Add products and ingredients to branch
        $this->branchRepo->addProduct($branch_id, $burger_id, true);
        $this->branchRepo->addProduct($branch_id, $fries_id, true);
        $this->branchRepo->addProduct($branch_id, $salad_id, true);
        
        foreach ([$lettuce_id, $tomato_id, $cheese_id, $bacon_id, $onion_id, $pickle_id] as $ingredient_id) {
            $this->branchRepo->addIngredient($branch_id, $ingredient_id, true);
        }

        // Step 10: Verify complete setup
        $branch = $this->branchRepo->get($branch_id);
        $this->assertInstanceOf(StoreBranch::class, $branch);
        $this->assertEquals('Downtown Branch', $branch->name);
        $this->assertTrue($branch->is_open);
        $this->assertEquals('Kosher Meat', $branch->kosher_type);
        
        // Verify products are available
        $this->assertTrue($branch->isProductAvailable($burger_id));
        $this->assertTrue($branch->isProductAvailable($fries_id));
        $this->assertTrue($branch->isProductAvailable($salad_id));
        
        // Verify ingredients are available
        $this->assertTrue($branch->isIngredientAvailable($lettuce_id));
        $this->assertTrue($branch->isIngredientAvailable($cheese_id));
        
        // Step 11: Test building complete product with all groups
        $burger = $this->productRepo->get($burger_id);
        $built_product = $burger->buildProduct(
            $this->productGroupRepo,
            $this->groupItemRepo,
            $this->productRepo,
            $this->ingredientRepo
        );
        
        $this->assertArrayHasKey('groups_product_data', $built_product);
        $this->assertCount(2, $built_product['groups_product_data']); // 2 groups
        
        // Verify toppings group
        $toppings_group = array_filter($built_product['groups_product_data'], 
            fn($g) => $g['group_name'] === 'Burger Toppings');
        $this->assertNotEmpty($toppings_group);
        
        // Verify sides group
        $sides_group = array_filter($built_product['groups_product_data'], 
            fn($g) => $g['group_name'] === 'Combo Sides');
        $this->assertNotEmpty($sides_group);
    }

    /**
     * Test complete customer order flow
     */
    public function test_complete_customer_order_flow(): void
    {
        // Setup: Create products and ingredients
        $burger_id = $this->productRepo->create([
            'name' => 'Cheeseburger',
            'price' => 42.00,
            'category' => 'Burgers',
        ]);
        
        $drink_id = $this->productRepo->create([
            'name' => 'Coca Cola',
            'price' => 8.00,
            'category' => 'Drinks',
        ]);
        
        // Create customer
        $customer_id = $this->customerRepo->create([
            'first_name' => 'David',
            'last_name' => 'Cohen',
            'email' => 'david.cohen@example.com',
            'phone' => '0541234567',
            'auth_provider' => 'google',
            'google_id' => 'google_david_123',
            'is_guest' => false,
        ]);
        
        // Simulate order placement
        $order_total = 50.00; // burger + drink
        $order_id = 2001;
        
        // Update customer order stats
        $this->customerRepo->updateOrderStats($customer_id, $order_id, $order_total);
        
        // Award loyalty points (2% of order)
        $loyalty_points = $order_total * 0.02;
        $this->customerRepo->addLoyaltyPoints($customer_id, $loyalty_points);
        
        // Verify customer state after order
        $customer = $this->customerRepo->get($customer_id);
        $this->assertEquals(1, $customer->total_orders);
        $this->assertEquals(50.00, $customer->total_spent);
        $this->assertEquals(1.00, $customer->loyalty_points_balance);
        $this->assertContains($order_id, $customer->order_ids);
        
        // Simulate second order with loyalty discount
        $second_order_total = 75.00;
        $second_order_id = 2002;
        
        // Use some loyalty points
        $points_to_use = 0.50;
        $this->customerRepo->useLoyaltyPoints($customer_id, $points_to_use);
        
        // Process second order
        $this->customerRepo->updateOrderStats($customer_id, $second_order_id, $second_order_total - $points_to_use);
        $loyalty_points_earned = ($second_order_total - $points_to_use) * 0.02;
        $this->customerRepo->addLoyaltyPoints($customer_id, $loyalty_points_earned);
        
        // Final verification
        $customer = $this->customerRepo->get($customer_id);
        $this->assertEquals(2, $customer->total_orders);
        $this->assertEquals(124.50, $customer->total_spent); // 50 + 74.50
        $this->assertEquals(1.99, $customer->loyalty_points_balance); // 1.00 - 0.50 + 1.49
    }

    /**
     * Test complex product configuration with nested groups
     */
    public function test_complex_product_with_nested_groups(): void
    {
        // Create base ingredients
        $bread_id = $this->ingredientRepo->create(['name' => 'Bread', 'price' => 2.00]);
        $meat_id = $this->ingredientRepo->create(['name' => 'Beef Patty', 'price' => 15.00]);
        $chicken_id = $this->ingredientRepo->create(['name' => 'Chicken Patty', 'price' => 12.00]);
        
        // Create sauces
        $ketchup_id = $this->ingredientRepo->create(['name' => 'Ketchup', 'price' => 0.50]);
        $mayo_id = $this->ingredientRepo->create(['name' => 'Mayo', 'price' => 0.50]);
        $bbq_id = $this->ingredientRepo->create(['name' => 'BBQ Sauce', 'price' => 1.00]);
        
        // Create group items for protein choices
        $gi_beef = $this->groupItemRepo->create([
            'item_id' => $meat_id,
            'item_type' => 'ingredient',
            'override_price' => null, // Use original price
        ]);
        
        $gi_chicken = $this->groupItemRepo->create([
            'item_id' => $chicken_id,
            'item_type' => 'ingredient',
            'override_price' => null,
        ]);
        
        // Create protein selection group
        $protein_group_id = $this->productGroupRepo->create([
            'name' => 'Choose Your Protein',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_beef, $gi_chicken],
        ]);
        
        // Create group items for sauces (free)
        $gi_ketchup = $this->groupItemRepo->create([
            'item_id' => $ketchup_id,
            'item_type' => 'ingredient',
            'override_price' => 0.00, // Free
        ]);
        
        $gi_mayo = $this->groupItemRepo->create([
            'item_id' => $mayo_id,
            'item_type' => 'ingredient',
            'override_price' => 0.00, // Free
        ]);
        
        $gi_bbq = $this->groupItemRepo->create([
            'item_id' => $bbq_id,
            'item_type' => 'ingredient',
            'override_price' => 0.50, // Discounted
        ]);
        
        // Create sauce selection group
        $sauce_group_id = $this->productGroupRepo->create([
            'name' => 'Choose Your Sauces',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_ketchup, $gi_mayo, $gi_bbq],
        ]);
        
        // Create customizable burger product
        $custom_burger_id = $this->productRepo->create([
            'name' => 'Build Your Own Burger',
            'price' => 35.00,
            'description' => 'Customize your perfect burger',
            'category' => 'Custom Burgers',
            'product_group_ids' => [$protein_group_id, $sauce_group_id],
        ]);
        
        // Test retrieving and building the complex product
        $custom_burger = $this->productRepo->get($custom_burger_id);
        $this->assertInstanceOf(Product::class, $custom_burger);
        
        // Get product groups
        $groups = [];
        foreach ($custom_burger->product_group_ids as $pg_id) {
            $group = $this->productGroupRepo->get($pg_id);
            $this->assertInstanceOf(ProductGroup::class, $group);
            $groups[] = $group;
        }
        
        $this->assertCount(2, $groups);
        
        // Test resolving items in protein group
        $protein_group = $this->productGroupRepo->get($protein_group_id);
        $resolved_proteins = $protein_group->getResolvedItems(
            $this->groupItemRepo,
            $this->productRepo,
            $this->ingredientRepo
        );
        
        $this->assertCount(2, $resolved_proteins);
        $this->assertContainsOnlyInstancesOf(Ingredient::class, $resolved_proteins);
        
        // Verify price overrides work correctly
        $sauce_group = $this->productGroupRepo->get($sauce_group_id);
        $resolved_sauces = $sauce_group->getResolvedItems(
            $this->groupItemRepo,
            $this->productRepo,
            $this->ingredientRepo
        );
        
        foreach ($resolved_sauces as $sauce) {
            if ($sauce->name === 'Ketchup' || $sauce->name === 'Mayo') {
                $this->assertEquals(0.00, $sauce->price, "Free sauces should have 0 price");
            } elseif ($sauce->name === 'BBQ Sauce') {
                $this->assertEquals(0.50, $sauce->price, "BBQ sauce should be discounted to 0.50");
            }
        }
    }

    /**
     * Test branch availability management
     */
    public function test_branch_availability_and_hours_management(): void
    {
        // Create branch
        $branch_id = $this->branchRepo->create([
            'name' => 'Test Branch',
            'phone' => '0501234567',
            'city' => 'Jerusalem',
            'address' => '456 Test St',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['09:00-14:00', '17:00-22:00'],
                'MONDAY' => ['09:00-22:00'],
            ],
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => ['wheelchair_accessible'],
        ]);
        
        // Create products
        $pizza_id = $this->productRepo->create([
            'name' => 'Margherita Pizza',
            'price' => 55.00,
            'category' => 'Pizza',
        ]);
        
        $pasta_id = $this->productRepo->create([
            'name' => 'Spaghetti Carbonara',
            'price' => 48.00,
            'category' => 'Pasta',
        ]);
        
        // Add products to branch
        $this->branchRepo->addProduct($branch_id, $pizza_id, true);
        $this->branchRepo->addProduct($branch_id, $pasta_id, true);
        
        // Test availability toggle
        $branch = $this->branchRepo->get($branch_id);
        $this->assertTrue($branch->isProductAvailable($pizza_id));
        
        // Make pizza unavailable
        $this->branchRepo->setProductAvailability($branch_id, $pizza_id, false);
        
        $branch = $this->branchRepo->get($branch_id);
        $this->assertFalse($branch->isProductAvailable($pizza_id));
        $this->assertTrue($branch->isProductAvailable($pasta_id));
        
        // Test branch closing
        $this->branchRepo->setIsOpen($branch_id, false);
        
        $branch = $this->branchRepo->get($branch_id);
        $this->assertFalse($branch->is_open);
        
        // Test adding new business hours
        $this->branchRepo->addActivityTime($branch_id, 'FRIDAY', '09:00-15:00');
        
        $branch = $this->branchRepo->get($branch_id);
        $this->assertArrayHasKey('FRIDAY', $branch->activity_times);
        $this->assertContains('09:00-15:00', $branch->activity_times['FRIDAY']);
    }

    /**
     * Test dependency chain and deletion restrictions
     */
    public function test_dependency_chain_prevents_deletion(): void
    {
        // Create ingredient
        $tomato_id = $this->ingredientRepo->create(['name' => 'Tomato', 'price' => 1.00]);
        
        // Create group item referencing ingredient
        $gi_tomato = $this->groupItemRepo->create([
            'item_id' => $tomato_id,
            'item_type' => 'ingredient',
            'override_price' => 0.50,
        ]);
        
        // Create product group containing group item
        $toppings_group = $this->productGroupRepo->create([
            'name' => 'Pizza Toppings',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_tomato],
        ]);
        
        // Create product using the product group
        $pizza_id = $this->productRepo->create([
            'name' => 'Custom Pizza',
            'price' => 50.00,
            'product_group_ids' => [$toppings_group],
        ]);
        
        // Test deletion restrictions
        
        // Cannot delete ingredient (used by group item)
        $this->expectException(\ResourceInUseException::class);
        $this->ingredientRepo->delete($tomato_id, true);
    }

    /**
     * Test deletion in reverse order works
     */
    public function test_deletion_in_correct_order_succeeds(): void
    {
        // Create the same setup as above
        $tomato_id = $this->ingredientRepo->create(['name' => 'Tomato', 'price' => 1.00]);
        
        $gi_tomato = $this->groupItemRepo->create([
            'item_id' => $tomato_id,
            'item_type' => 'ingredient',
            'override_price' => 0.50,
        ]);
        
        $toppings_group = $this->productGroupRepo->create([
            'name' => 'Pizza Toppings',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_tomato],
        ]);
        
        $pizza_id = $this->productRepo->create([
            'name' => 'Custom Pizza',
            'price' => 50.00,
            'product_group_ids' => [$toppings_group],
        ]);
        
        // Delete in correct order: Product -> ProductGroup -> GroupItem -> Ingredient
        $this->assertTrue($this->productRepo->delete($pizza_id, true));
        $this->assertTrue($this->productGroupRepo->delete($toppings_group, true));
        $this->assertTrue($this->groupItemRepo->delete($gi_tomato, true));
        $this->assertTrue($this->ingredientRepo->delete($tomato_id, true));
        
        // Verify all are deleted
        $this->assertNull($this->productRepo->get($pizza_id));
        $this->assertNull($this->productGroupRepo->get($toppings_group));
        $this->assertNull($this->groupItemRepo->get($gi_tomato));
        $this->assertNull($this->ingredientRepo->get($tomato_id));
    }

    /**
     * Test findBy methods across repositories
     */
    public function test_repository_search_and_filter_methods(): void
    {
        // Create test data
        $branch1 = $this->branchRepo->create([
            'name' => 'North Branch',
            'phone' => '0501111111',
            'city' => 'Haifa',
            'address' => '123 North St',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => 'Kosher Meat',
            'accessibility_list' => ['wheelchair_accessible'],
        ]);
        
        $branch2 = $this->branchRepo->create([
            'name' => 'South Branch',
            'phone' => '0502222222',
            'city' => 'Eilat',
            'address' => '456 South St',
            'is_open' => false,
            'activity_times' => [],
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => [],
        ]);
        
        // Test StoreBranch findBy methods
        $open_branches = $this->branchRepo->findOpen();
        $this->assertCount(1, $open_branches);
        $this->assertEquals($branch1, $open_branches[0]->id);
        
        $kosher_meat_branches = $this->branchRepo->findByKosherType('Kosher Meat');
        $this->assertCount(1, $kosher_meat_branches);
        
        $accessible_branches = $this->branchRepo->findWithAccessibility('wheelchair_accessible');
        $this->assertCount(1, $accessible_branches);
        
        // Create products for testing
        $product1 = $this->productRepo->create([
            'name' => 'Test Product 1',
            'price' => 20.00,
            'discounted_price' => 15.00,
            'category' => 'Test Category',
        ]);
        
        $product2 = $this->productRepo->create([
            'name' => 'Test Product 2',
            'price' => 30.00,
            'category' => 'Test Category',
        ]);
        
        // Test Product findBy methods
        $on_sale = $this->productRepo->findOnSale();
        $this->assertCount(1, $on_sale);
        $this->assertEquals($product1, $on_sale[0]->id);
        
        $by_category = $this->productRepo->findByCategory('Test Category');
        $this->assertCount(2, $by_category);
        
        $in_price_range = $this->productRepo->findInPriceRange(10.00, 25.00);
        $this->assertCount(1, $in_price_range);
        $this->assertEquals($product1, $in_price_range[0]->id);
    }

    /**
     * Test data integrity across updates
     */
    public function test_data_integrity_maintained_across_updates(): void
    {
        // Create complex product setup
        $ingredient_id = $this->ingredientRepo->create(['name' => 'Test Ingredient', 'price' => 5.00]);
        
        $gi_id = $this->groupItemRepo->create([
            'item_id' => $ingredient_id,
            'item_type' => 'ingredient',
            'override_price' => 3.00,
        ]);
        
        $pg_id = $this->productGroupRepo->create([
            'name' => 'Test Group',
            'type' => 'ingredient',
            'group_item_ids' => [$gi_id],
        ]);
        
        $product_id = $this->productRepo->create([
            'name' => 'Test Product',
            'price' => 50.00,
            'product_group_ids' => [$pg_id],
        ]);
        
        // Update ingredient price
        $this->ingredientRepo->update($ingredient_id, ['price' => 7.00]);
        
        // Verify group item still has override price
        $gi = $this->groupItemRepo->get($gi_id);
        $this->assertEquals(3.00, $gi->override_price);
        
        // Get resolved item should use override price
        $resolved_item = $gi->getItem(null, $this->ingredientRepo);
        $this->assertEquals(3.00, $resolved_item->price);
        
        // Update group item to remove override
        $this->groupItemRepo->update($gi_id, ['override_price' => null]);
        
        // Now resolved item should use ingredient's new price
        $gi_updated = $this->groupItemRepo->get($gi_id);
        $resolved_updated = $gi_updated->getItem(null, $this->ingredientRepo);
        $this->assertEquals(7.00, $resolved_updated->price);
        
        // Verify product still has correct group associations
        $product = $this->productRepo->get($product_id);
        $this->assertContains($pg_id, $product->product_group_ids);
    }
}