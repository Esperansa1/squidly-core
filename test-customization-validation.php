<?php
/**
 * Test ProductCustomizationValidator
 * Access via: squidly.local/wp-content/plugins/squidly-core/test-customization-validation.php
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Load plugin classes
require_once(__DIR__ . '/includes/shared/models/enums/ItemType.php');
require_once(__DIR__ . '/includes/shared/interfaces/RepositoryInterface.php');
require_once(__DIR__ . '/includes/domains/products/models/Product.php');
require_once(__DIR__ . '/includes/domains/products/models/ProductGroup.php');
require_once(__DIR__ . '/includes/domains/products/models/GroupItem.php');
require_once(__DIR__ . '/includes/domains/products/repositories/ProductRepository.php');
require_once(__DIR__ . '/includes/domains/products/repositories/ProductGroupRepository.php');
require_once(__DIR__ . '/includes/domains/products/repositories/GroupItemRepository.php');
require_once(__DIR__ . '/includes/domains/products/repositories/IngredientRepository.php');
require_once(__DIR__ . '/includes/domains/products/models/Ingredient.php');
require_once(__DIR__ . '/includes/domains/products/services/ProductCustomizationValidator.php');

echo "<h1>Testing ProductCustomizationValidator</h1>";
echo "<pre>";

$validator = new ProductCustomizationValidator();
$productRepo = new ProductRepository();
$groupRepo = new ProductGroupRepository();
$ingredientRepo = new IngredientRepository();
$groupItemRepo = new GroupItemRepository();

echo "\n=== Test Setup ===\n";
echo "Creating test data...\n";

// Create test ingredients
$ingredient1Id = $ingredientRepo->create([
    'name' => 'Test Lettuce',
    'price' => 0.0,
    'unit' => 'piece'
]);
$ingredient2Id = $ingredientRepo->create([
    'name' => 'Test Tomato',
    'price' => 2.0,
    'unit' => 'piece'
]);
$ingredient3Id = $ingredientRepo->create([
    'name' => 'Test Cheese',
    'price' => 5.0,
    'unit' => 'piece'
]);

echo "✅ Created 3 test ingredients\n";

// Create ProductGroup with constraints: min=1, max=2
$groupId = $groupRepo->create([
    'name' => 'Test Toppings Group',
    'description' => 'Choose your toppings',
    'type' => 'ingredient',
    'group_item_ids' => [],
    'min_selections' => 1,
    'max_selections' => 2,
]);

// Add ingredients to group via GroupItems
$groupItem1Id = $groupItemRepo->create([
    'item_id' => $ingredient1Id,
    'item_type' => 'ingredient',
    'override_price' => null
]);
$groupItem2Id = $groupItemRepo->create([
    'item_id' => $ingredient2Id,
    'item_type' => 'ingredient',
    'override_price' => null
]);
$groupItem3Id = $groupItemRepo->create([
    'item_id' => $ingredient3Id,
    'item_type' => 'ingredient',
    'override_price' => null
]);

// Update group with group items
$groupRepo->update($groupId, [
    'group_item_ids' => [$groupItem1Id, $groupItem2Id, $groupItem3Id]
]);

echo "✅ Created ProductGroup (min=1, max=2) with 3 ingredients\n";

// Create test product
$productId = $productRepo->create([
    'name' => 'Test Burger',
    'description' => 'A test burger',
    'price' => 20.0,
    'product_group_ids' => [$groupId]
]);

echo "✅ Created test product with group\n";
echo "   Product ID: {$productId}\n";
echo "   Group ID: {$groupId}\n";

// Run tests
$passedTests = 0;
$failedTests = 0;

// Test 1: Valid customization (1 selection, within min=1 max=2)
echo "\n=== Test 1: Valid Customization (1 selection) ===\n";
try {
    $customizations = [
        $groupId => [
            ['id' => $ingredient1Id, 'name' => 'Test Lettuce', 'price' => 0.0]
        ]
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "✅ PASS: Valid customization accepted\n";
    $passedTests++;
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $failedTests++;
}

// Test 2: Valid customization (2 selections, at max)
echo "\n=== Test 2: Valid Customization (2 selections) ===\n";
try {
    $customizations = [
        $groupId => [
            ['id' => $ingredient1Id, 'name' => 'Test Lettuce', 'price' => 0.0],
            ['id' => $ingredient2Id, 'name' => 'Test Tomato', 'price' => 2.0]
        ]
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "✅ PASS: Valid customization with 2 items accepted\n";
    $passedTests++;
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $failedTests++;
}

// Test 3: Too few selections (0 selections, min=1)
echo "\n=== Test 3: Too Few Selections (should FAIL) ===\n";
try {
    $customizations = [
        $groupId => []
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "❌ FAIL: Should have rejected 0 selections (min=1)\n";
    $failedTests++;
} catch (InvalidArgumentException $e) {
    echo "✅ PASS: Correctly rejected - " . $e->getMessage() . "\n";
    $passedTests++;
}

// Test 4: Too many selections (3 selections, max=2)
echo "\n=== Test 4: Too Many Selections (should FAIL) ===\n";
try {
    $customizations = [
        $groupId => [
            ['id' => $ingredient1Id, 'name' => 'Test Lettuce', 'price' => 0.0],
            ['id' => $ingredient2Id, 'name' => 'Test Tomato', 'price' => 2.0],
            ['id' => $ingredient3Id, 'name' => 'Test Cheese', 'price' => 5.0]
        ]
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "❌ FAIL: Should have rejected 3 selections (max=2)\n";
    $failedTests++;
} catch (InvalidArgumentException $e) {
    echo "✅ PASS: Correctly rejected - " . $e->getMessage() . "\n";
    $passedTests++;
}

// Test 5: Price manipulation (customer changed price)
echo "\n=== Test 5: Price Manipulation (should FAIL) ===\n";
try {
    $customizations = [
        $groupId => [
            ['id' => $ingredient2Id, 'name' => 'Test Tomato', 'price' => 0.0] // Should be 2.0
        ]
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "❌ FAIL: Should have detected price manipulation\n";
    $failedTests++;
} catch (InvalidArgumentException $e) {
    echo "✅ PASS: Correctly detected manipulation - " . $e->getMessage() . "\n";
    $passedTests++;
}

// Test 6: Invalid item (not in group)
echo "\n=== Test 6: Invalid Item (should FAIL) ===\n";
try {
    $customizations = [
        $groupId => [
            ['id' => 99999, 'name' => 'Fake Item', 'price' => 0.0]
        ]
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "❌ FAIL: Should have rejected fake item\n";
    $failedTests++;
} catch (InvalidArgumentException $e) {
    echo "✅ PASS: Correctly rejected - " . $e->getMessage() . "\n";
    $passedTests++;
}

// Test 7: Invalid group (not part of product)
echo "\n=== Test 7: Invalid Group (should FAIL) ===\n";
try {
    $customizations = [
        99999 => [
            ['id' => $ingredient1Id, 'name' => 'Test Lettuce', 'price' => 0.0]
        ]
    ];

    $result = $validator->validateProductCustomizations($productId, $customizations);
    echo "❌ FAIL: Should have rejected fake group\n";
    $failedTests++;
} catch (InvalidArgumentException $e) {
    echo "✅ PASS: Correctly rejected - " . $e->getMessage() . "\n";
    $passedTests++;
}

// Test 8: Calculate total price
echo "\n=== Test 8: Calculate Total Price ===\n";
try {
    $customizations = [
        $groupId => [
            ['id' => $ingredient2Id, 'name' => 'Test Tomato', 'price' => 2.0],
            ['id' => $ingredient3Id, 'name' => 'Test Cheese', 'price' => 5.0]
        ]
    ];

    $totalPrice = $validator->calculateTotalPrice($productId, $customizations);
    $expectedPrice = 20.0 + 2.0 + 5.0; // Base + toppings

    if (abs($totalPrice - $expectedPrice) < 0.01) {
        echo "✅ PASS: Correct total price: ₪{$totalPrice} (base ₪20 + toppings ₪7)\n";
        $passedTests++;
    } else {
        echo "❌ FAIL: Wrong price. Expected ₪{$expectedPrice}, got ₪{$totalPrice}\n";
        $failedTests++;
    }
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $failedTests++;
}

// Clean up
echo "\n=== Cleanup ===\n";
try {
    $productRepo->delete($productId, true);
    $groupRepo->delete($groupId, true);
    $groupItemRepo->delete($groupItem1Id, true);
    $groupItemRepo->delete($groupItem2Id, true);
    $groupItemRepo->delete($groupItem3Id, true);
    $ingredientRepo->delete($ingredient1Id, true);
    $ingredientRepo->delete($ingredient2Id, true);
    $ingredientRepo->delete($ingredient3Id, true);
    echo "✅ Test data cleaned up\n";
} catch (Exception $e) {
    echo "⚠️  Cleanup warning: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "✅ Passed: {$passedTests}\n";
echo "❌ Failed: {$failedTests}\n";
echo "\n";

if ($failedTests === 0) {
    echo "🎉 ALL TESTS PASSED! Validation is working correctly.\n";
} else {
    echo "⚠️  SOME TESTS FAILED. Review implementation.\n";
}

echo "</pre>";
