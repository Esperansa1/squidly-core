<?php

declare(strict_types=1);

// Load WordPress
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/squidly-core.php';

echo '<pre>';

// Initialize Repositories
$ingredientRepo = new IngredientRepository();
$groupItemRepo = new GroupItemRepository();
$productGroupRepo = new ProductGroupRepository();
$productRepo = new ProductRepository();

// 🟣 Step 1: Create Ingredients
$lettuce_id = $ingredientRepo->create(['name' => 'Lettuce', 'price' => 0.0]);
$pickles_id = $ingredientRepo->create(['name' => 'Pickles', 'price' => 0.0]);

// 🟣 Step 2: Create GroupItems (reference ingredients)
$topping1_id = $groupItemRepo->create(['item_id' => $lettuce_id, 'item_type' => 'ingredient', 'override_price' => null]);
$topping2_id = $groupItemRepo->create(['item_id' => $pickles_id, 'item_type' => 'ingredient', 'override_price' => 0.2]);

// 🟣 Step 3: Create ProductGroups (reference group_item_ids)
$toppings_group_id = $productGroupRepo->create([
    'name' => 'Toppings',
    'type' => ProductGroupType::INGREDIENT->value,
    'group_item_ids' => [$topping1_id, $topping2_id],
]);

// 🟣 Step 4: Create Product
$burger_id = $productRepo->create([
    'name' => 'Classic Burger',
    'description' => 'Beef patty with toppings',
    'price' => 28.0,
    'discounted_price' => 24.5,
    'category' => 'Main',
    'tags' => ['beef', 'customizable'],
    'product_group_ids' => [$toppings_group_id],
]);

// 🟣 Step 5: Retrieve everything
$lettuce = $ingredientRepo->get($lettuce_id);
$topping1 = $groupItemRepo->get($topping1_id);
$toppings_group = $productGroupRepo->get($toppings_group_id);
$burger = $productRepo->get($burger_id);

// 🟣 Step 6: Validation Output
echo "✅ Ingredient Lettuce: ", $lettuce ? 'OK' : '❌ MISSING', PHP_EOL;
echo "✅ GroupItem Lettuce ID: ", $topping1 ? 'OK' : '❌ MISSING', PHP_EOL;
echo "✅ ProductGroup Toppings: ", $toppings_group ? 'OK' : '❌ MISSING', PHP_EOL;
echo "✅ Product Burger: ", $burger ? 'OK' : '❌ MISSING', PHP_EOL;

echo PHP_EOL, '📦 Burger Full Data:', PHP_EOL;
print_r($burger ? $burger->toArray() : '❌ Burger Not Found');

echo '</pre>';
