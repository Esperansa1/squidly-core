<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductCustomizationValidator;
use ProductRepository;
use ProductGroupRepository;
use GroupItemRepository;
use IngredientRepository;
use ItemType;
use WP_UnitTestCase;
use InvalidArgumentException;

/**
 * WordPress-backed integration tests for ProductCustomizationValidator.
 *
 * Tests security validation of product customizations against:
 * - Min/max selection constraints
 * - Price manipulation
 * - Invalid item injection
 * - Invalid group injection
 *
 * Run with: vendor/bin/phpunit --testsuite integration
 */
class ProductCustomizationValidatorIntegrationTest extends WP_UnitTestCase
{
    private ProductCustomizationValidator $validator;
    private ProductRepository $productRepo;
    private ProductGroupRepository $groupRepo;
    private GroupItemRepository $groupItemRepo;
    private IngredientRepository $ingredientRepo;

    // Test data IDs
    private int $productId;
    private int $groupId;
    private int $ingredient1Id;
    private int $ingredient2Id;
    private int $ingredient3Id;

    public function set_up(): void
    {
        parent::set_up();

        $this->validator = new ProductCustomizationValidator();
        $this->productRepo = new ProductRepository();
        $this->groupRepo = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        $this->ingredientRepo = new IngredientRepository();

        // Create test data
        $this->createTestData();
    }

    /**
     * Create test product with group (min=1, max=2) containing 3 ingredients
     */
    private function createTestData(): void
    {
        // Create 3 ingredients with different prices
        $this->ingredient1Id = $this->ingredientRepo->create([
            'name' => 'Lettuce',
            'price' => 0.0,
            'unit' => 'piece'
        ]);

        $this->ingredient2Id = $this->ingredientRepo->create([
            'name' => 'Tomato',
            'price' => 2.0,
            'unit' => 'piece'
        ]);

        $this->ingredient3Id = $this->ingredientRepo->create([
            'name' => 'Cheese',
            'price' => 5.0,
            'unit' => 'piece'
        ]);

        // Create GroupItems
        $groupItem1Id = $this->groupItemRepo->create([
            'item_id' => $this->ingredient1Id,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => null
        ]);

        $groupItem2Id = $this->groupItemRepo->create([
            'item_id' => $this->ingredient2Id,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => null
        ]);

        $groupItem3Id = $this->groupItemRepo->create([
            'item_id' => $this->ingredient3Id,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => null
        ]);

        // Create ProductGroup with constraints: min=1, max=2
        $this->groupId = $this->groupRepo->create([
            'name' => 'Toppings',
            'description' => 'Choose your toppings',
            'type' => 'ingredient',
            'group_item_ids' => [$groupItem1Id, $groupItem2Id, $groupItem3Id],
            'min_selections' => 1,
            'max_selections' => 2,
        ]);

        // Create product with group
        $this->productId = $this->productRepo->create([
            'name' => 'Burger',
            'description' => 'Test burger',
            'price' => 20.0,
            'product_group_ids' => [$this->groupId]
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Valid Customizations                                              */
    /* ------------------------------------------------------------------ */

    public function test_valid_customization_with_one_selection_passes(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0]
            ]
        ];

        $result = $this->validator->validateProductCustomizations($this->productId, $customizations);

        $this->assertTrue($result);
    }

    public function test_valid_customization_with_two_selections_passes(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0],
                ['id' => $this->ingredient2Id, 'name' => 'Tomato', 'price' => 2.0]
            ]
        ];

        $result = $this->validator->validateProductCustomizations($this->productId, $customizations);

        $this->assertTrue($result);
    }

    /* ------------------------------------------------------------------ */
    /*  Min/Max Constraint Violations                                     */
    /* ------------------------------------------------------------------ */

    public function test_too_few_selections_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [] // 0 selections, min is 1
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires at least 1 selection');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    public function test_too_many_selections_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0],
                ['id' => $this->ingredient2Id, 'name' => 'Tomato', 'price' => 2.0],
                ['id' => $this->ingredient3Id, 'name' => 'Cheese', 'price' => 5.0]
            ] // 3 selections, max is 2
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allows maximum 2 selection');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Price Manipulation Detection                                      */
    /* ------------------------------------------------------------------ */

    public function test_price_manipulation_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient2Id, 'name' => 'Tomato', 'price' => 0.0] // Should be 2.0
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price manipulation detected');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    public function test_inflated_price_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 10.0] // Should be 0.0
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price manipulation detected');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Invalid Item Injection                                            */
    /* ------------------------------------------------------------------ */

    public function test_invalid_item_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => 99999, 'name' => 'Fake Item', 'price' => 0.0]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to group');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Invalid Group Injection                                           */
    /* ------------------------------------------------------------------ */

    public function test_invalid_group_throws_exception(): void
    {
        $customizations = [
            99999 => [ // Fake group ID
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to Product');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Invalid Product                                                   */
    /* ------------------------------------------------------------------ */

    public function test_nonexistent_product_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found');

        $this->validator->validateProductCustomizations(99999, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Price Calculation                                                 */
    /* ------------------------------------------------------------------ */

    public function test_calculate_total_price_with_no_customizations(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0]
            ]
        ];

        $totalPrice = $this->validator->calculateTotalPrice($this->productId, $customizations);

        // Base price (20.0) + Lettuce (0.0) = 20.0
        $this->assertEquals(20.0, $totalPrice);
    }

    public function test_calculate_total_price_with_paid_customizations(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient2Id, 'name' => 'Tomato', 'price' => 2.0],
                ['id' => $this->ingredient3Id, 'name' => 'Cheese', 'price' => 5.0]
            ]
        ];

        $totalPrice = $this->validator->calculateTotalPrice($this->productId, $customizations);

        // Base price (20.0) + Tomato (2.0) + Cheese (5.0) = 27.0
        $this->assertEquals(27.0, $totalPrice);
    }

    public function test_calculate_total_price_validates_first(): void
    {
        // Invalid customization (too many selections)
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce', 'price' => 0.0],
                ['id' => $this->ingredient2Id, 'name' => 'Tomato', 'price' => 2.0],
                ['id' => $this->ingredient3Id, 'name' => 'Cheese', 'price' => 5.0]
            ]
        ];

        $this->expectException(InvalidArgumentException::class);

        // Should throw before calculating price
        $this->validator->calculateTotalPrice($this->productId, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Missing Required Fields                                           */
    /* ------------------------------------------------------------------ */

    public function test_missing_item_id_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['name' => 'Lettuce', 'price' => 0.0] // Missing 'id'
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid item format');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    public function test_missing_item_price_throws_exception(): void
    {
        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient1Id, 'name' => 'Lettuce'] // Missing 'price'
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid item format');

        $this->validator->validateProductCustomizations($this->productId, $customizations);
    }

    /* ------------------------------------------------------------------ */
    /*  Edge Cases                                                        */
    /* ------------------------------------------------------------------ */

    public function test_empty_customizations_array_passes(): void
    {
        // Create product with no groups
        $simpleProductId = $this->productRepo->create([
            'name' => 'Simple Burger',
            'price' => 15.0,
            'product_group_ids' => []
        ]);

        $customizations = [];

        $result = $this->validator->validateProductCustomizations($simpleProductId, $customizations);

        $this->assertTrue($result);
    }

    public function test_product_with_discounted_price_uses_discount(): void
    {
        // Create product with discount
        $discountedProductId = $this->productRepo->create([
            'name' => 'Discounted Burger',
            'price' => 25.0,
            'discounted_price' => 18.0,
            'product_group_ids' => [$this->groupId]
        ]);

        $customizations = [
            $this->groupId => [
                ['id' => $this->ingredient2Id, 'name' => 'Tomato', 'price' => 2.0]
            ]
        ];

        $totalPrice = $this->validator->calculateTotalPrice($discountedProductId, $customizations);

        // Discounted price (18.0) + Tomato (2.0) = 20.0
        $this->assertEquals(20.0, $totalPrice);
    }
}
