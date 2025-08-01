<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * Comprehensive Integration Tests for StoreBranchRepository
 * Tests complex multi-component integration, business hours, availability management
 */
class StoreBranchRepositoryIntegrationTest extends WP_UnitTestCase
{
    private StoreBranchRepository $repository;
    private ProductRepository $productRepo;
    private IngredientRepository $ingredientRepo;
    private int $test_product_id;
    private int $test_ingredient_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->repository = new StoreBranchRepository();
        $this->productRepo = new ProductRepository();
        $this->ingredientRepo = new IngredientRepository();

        // Create test products and ingredients for availability testing
        $this->test_product_id = $this->productRepo->create([
            'name' => 'Test Product',
            'price' => 25.99
        ]);

        $this->test_ingredient_id = $this->ingredientRepo->create([
            'name' => 'Test Ingredient',
            'price' => 5.50
        ]);
    }

    /* =====================================================================
     * BASIC CRUD OPERATIONS
     * ===================================================================*/

    public function test_create_and_retrieve_complete_store_branch(): void
    {
        // Arrange - Complete store branch data
        $branch_data = [
            'name' => 'Downtown Branch',
            'phone' => '+1-555-0123',
            'city' => 'New York',
            'address' => '123 Main Street, NY 10001',
            'is_open' => true,
            'activity_times' => [
                'MONDAY' => ['09:00-17:00'],
                'TUESDAY' => ['09:00-17:00', '19:00-22:00'],
                'SUNDAY' => ['11:00-15:00']
            ],
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => ['wheelchair_accessible', 'braille_menu'],
            'products' => [$this->test_product_id],
            'ingredients' => [$this->test_ingredient_id],
            'product_availability' => [$this->test_product_id => true],
            'ingredient_availability' => [$this->test_ingredient_id => false]
        ];

        // Act - Create store branch
        $branch_id = $this->repository->create($branch_data);

        // Assert - Creation successful
        $this->assertIsInt($branch_id);
        $this->assertGreaterThan(0, $branch_id);

        // Act - Retrieve store branch
        $retrieved = $this->repository->get($branch_id);

        // Assert - All data preserved correctly
        $this->assertInstanceOf(StoreBranch::class, $retrieved);
        $this->assertEquals($branch_id, $retrieved->id);
        $this->assertEquals('Downtown Branch', $retrieved->name);
        $this->assertEquals('+1-555-0123', $retrieved->phone);
        $this->assertEquals('New York', $retrieved->city);
        $this->assertEquals('123 Main Street, NY 10001', $retrieved->address);
        $this->assertTrue($retrieved->is_open);

        // Assert - Activity times preserved
        $this->assertArrayHasKey('MONDAY', $retrieved->activity_times);
        $this->assertEquals(['09:00-17:00'], $retrieved->activity_times['MONDAY']);
        $this->assertArrayHasKey('TUESDAY', $retrieved->activity_times);
        $this->assertCount(2, $retrieved->activity_times['TUESDAY']);

        // Assert - Kosher and accessibility data
        $this->assertEquals('Kosher Dairy', $retrieved->kosher_type);
        $this->assertContains('wheelchair_accessible', $retrieved->accessibility_list);
        $this->assertContains('braille_menu', $retrieved->accessibility_list);

        // Assert - Product and ingredient relationships
        $this->assertCount(1, $retrieved->products);
        $this->assertCount(1, $retrieved->ingredients);
        $this->assertEquals($this->test_product_id, $retrieved->products[0]->id);
        $this->assertEquals($this->test_ingredient_id, $retrieved->ingredients[0]->id);

        // Assert - Availability tracking
        $this->assertTrue($retrieved->isProductAvailable($this->test_product_id));
        $this->assertFalse($retrieved->isIngredientAvailable($this->test_ingredient_id));
    }

    public function test_create_with_minimal_required_data(): void
    {
        // Arrange - Only required fields
        $minimal_data = [
            'name' => 'Minimal Branch',
            'phone' => '+1-555-9999',
            'city' => 'Boston',
            'address' => '456 Side Street',
            'is_open' => false,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => []
        ];

        // Act
        $branch_id = $this->repository->create($minimal_data);
        $retrieved = $this->repository->get($branch_id);

        // Assert - Minimal data preserved
        $this->assertInstanceOf(StoreBranch::class, $retrieved);
        $this->assertEquals('Minimal Branch', $retrieved->name);
        $this->assertFalse($retrieved->is_open);
        $this->assertEmpty($retrieved->activity_times);
        $this->assertEmpty($retrieved->kosher_type);
        $this->assertEmpty($retrieved->accessibility_list);
        $this->assertEmpty($retrieved->products);
        $this->assertEmpty($retrieved->ingredients);
    }

    /**
     * @dataProvider invalid_store_data_provider
     */
    public function test_create_with_invalid_data_throws_exception(array $invalid_data, string $expected_message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expected_message);

        $this->repository->create($invalid_data);
    }

    public function invalid_store_data_provider(): array
    {
        return [
            'missing name' => [
                ['phone' => '123', 'city' => 'NYC', 'address' => '123 St', 'is_open' => true, 'activity_times' => [], 'kosher_type' => '', 'accessibility_list' => []],
                'Missing required key: name'
            ],
            'missing phone' => [
                ['name' => 'Test', 'city' => 'NYC', 'address' => '123 St', 'is_open' => true, 'activity_times' => [], 'kosher_type' => '', 'accessibility_list' => []],
                'Missing required key: phone'
            ],
            'missing city' => [
                ['name' => 'Test', 'phone' => '123', 'address' => '123 St', 'is_open' => true, 'activity_times' => [], 'kosher_type' => '', 'accessibility_list' => []],
                'Missing required key: city'
            ],
            'missing address' => [
                ['name' => 'Test', 'phone' => '123', 'city' => 'NYC', 'is_open' => true, 'activity_times' => [], 'kosher_type' => '', 'accessibility_list' => []],
                'Missing required key: address'
            ]
        ];
    }

    /* =====================================================================
     * BUSINESS HOURS MANAGEMENT
     * ===================================================================*/

    public function test_activity_times_management(): void
    {
        // Arrange - Create branch
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        // Act - Add activity times
        $this->repository->addActivityTime($branch_id, 'MONDAY', '08:00-12:00');
        $this->repository->addActivityTime($branch_id, 'MONDAY', '14:00-18:00');
        $this->repository->addActivityTime($branch_id, 'FRIDAY', '10:00-22:00');

        // Assert - Activity times added correctly
        $branch = $this->repository->get($branch_id);
        $this->assertArrayHasKey('MONDAY', $branch->activity_times);
        $this->assertCount(2, $branch->activity_times['MONDAY']);
        $this->assertContains('08:00-12:00', $branch->activity_times['MONDAY']);
        $this->assertContains('14:00-18:00', $branch->activity_times['MONDAY']);
        $this->assertEquals(['10:00-22:00'], $branch->activity_times['FRIDAY']);

        // Act - Remove activity time
        $this->repository->removeActivityTime($branch_id, 'MONDAY', '08:00-12:00');

        // Assert - Specific time removed
        $updated_branch = $this->repository->get($branch_id);
        $this->assertCount(1, $updated_branch->activity_times['MONDAY']);
        $this->assertContains('14:00-18:00', $updated_branch->activity_times['MONDAY']);
        $this->assertNotContains('08:00-12:00', $updated_branch->activity_times['MONDAY']);
    }

    /**
     * @dataProvider invalid_day_provider
     */
    public function test_add_activity_time_with_invalid_day_throws_exception(string $invalid_day): void
    {
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid week-day');

        $this->repository->addActivityTime($branch_id, $invalid_day, '09:00-17:00');
    }

    public function invalid_day_provider(): array
    {
        return [
            ['INVALID_DAY'],
            ['WEEKDAY'],
            [''],
            ['1'],
        ];
    }

    public function test_activity_times_prevent_duplicates(): void
    {
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        // Act - Add same time slot multiple times
        $this->repository->addActivityTime($branch_id, 'WEDNESDAY', '09:00-17:00');
        $this->repository->addActivityTime($branch_id, 'WEDNESDAY', '09:00-17:00');
        $this->repository->addActivityTime($branch_id, 'WEDNESDAY', '09:00-17:00');

        // Assert - No duplicates
        $branch = $this->repository->get($branch_id);
        $this->assertCount(1, $branch->activity_times['WEDNESDAY']);
        $this->assertEquals(['09:00-17:00'], $branch->activity_times['WEDNESDAY']);
    }

    /* =====================================================================
     * PRODUCT AVAILABILITY MANAGEMENT
     * ===================================================================*/

    public function test_product_availability_management(): void
    {
        // Arrange - Create branch and additional products
        $branch_id = $this->repository->create($this->getMinimalBranchData());
        $product2_id = $this->productRepo->create(['name' => 'Product 2', 'price' => 15.00]);

        // Act - Add products with different availability
        $this->repository->addProduct($branch_id, $this->test_product_id, true);
        $this->repository->addProduct($branch_id, $product2_id, false);

        // Assert - Products added with correct availability
        $branch = $this->repository->get($branch_id);
        $this->assertCount(2, $branch->products);
        $this->assertTrue($branch->isProductAvailable($this->test_product_id));
        $this->assertFalse($branch->isProductAvailable($product2_id));

        // Act - Change availability
        $this->repository->setProductAvailability($branch_id, $product2_id, true);
        $this->repository->setProductAvailability($branch_id, $this->test_product_id, false);

        // Assert - Availability updated
        $updated_branch = $this->repository->get($branch_id);
        $this->assertFalse($updated_branch->isProductAvailable($this->test_product_id));
        $this->assertTrue($updated_branch->isProductAvailable($product2_id));

        // Act - Remove product
        $this->repository->removeProduct($branch_id, $this->test_product_id);

        // Assert - Product removed
        $final_branch = $this->repository->get($branch_id);
        $this->assertCount(1, $final_branch->products);
        $this->assertFalse($final_branch->isProductAvailable($this->test_product_id));
    }

    public function test_ingredient_availability_management(): void
    {
        // Arrange
        $branch_id = $this->repository->create($this->getMinimalBranchData());
        $ingredient2_id = $this->ingredientRepo->create(['name' => 'Ingredient 2', 'price' => 3.25]);

        // Act - Add ingredients
        $this->repository->addIngredient($branch_id, $this->test_ingredient_id, true);
        $this->repository->addIngredient($branch_id, $ingredient2_id, false);

        // Assert - Ingredients added correctly
        $branch = $this->repository->get($branch_id);
        $this->assertCount(2, $branch->ingredients);
        $this->assertTrue($branch->isIngredientAvailable($this->test_ingredient_id));
        $this->assertFalse($branch->isIngredientAvailable($ingredient2_id));

        // Act - Update availability
        $this->repository->setIngredientAvailability($branch_id, $ingredient2_id, true);

        // Assert - Availability changed
        $updated_branch = $this->repository->get($branch_id);
        $this->assertTrue($updated_branch->isIngredientAvailable($ingredient2_id));

        // Act - Remove ingredient
        $this->repository->removeIngredient($branch_id, $this->test_ingredient_id);

        // Assert - Ingredient removed
        $final_branch = $this->repository->get($branch_id);
        $this->assertCount(1, $final_branch->ingredients);
        $this->assertFalse($final_branch->isIngredientAvailable($this->test_ingredient_id));
    }

    /* =====================================================================
     * KOSHER AND ACCESSIBILITY MANAGEMENT
     * ===================================================================*/

    public function test_kosher_type_management(): void
    {
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        // Act - Set kosher type
        $this->repository->setKosherType($branch_id, 'Kosher Meat');

        // Assert - Kosher type set
        $branch = $this->repository->get($branch_id);
        $this->assertEquals('Kosher Meat', $branch->kosher_type);

        // Act - Change kosher type
        $this->repository->setKosherType($branch_id, 'Kosher Dairy');

        // Assert - Kosher type updated
        $updated_branch = $this->repository->get($branch_id);
        $this->assertEquals('Kosher Dairy', $updated_branch->kosher_type);

        // Act - Clear kosher type
        $this->repository->clearKosherType($branch_id);

        // Assert - Kosher type cleared
        $cleared_branch = $this->repository->get($branch_id);
        $this->assertEmpty($cleared_branch->kosher_type);
    }

    public function test_accessibility_features_management(): void
    {
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        // Act - Add accessibility features
        $this->repository->addAccessibility($branch_id, 'wheelchair_accessible');
        $this->repository->addAccessibility($branch_id, 'braille_menu');
        $this->repository->addAccessibility($branch_id, 'hearing_loop');

        // Assert - Features added
        $branch = $this->repository->get($branch_id);
        $this->assertCount(3, $branch->accessibility_list);
        $this->assertContains('wheelchair_accessible', $branch->accessibility_list);
        $this->assertContains('braille_menu', $branch->accessibility_list);
        $this->assertContains('hearing_loop', $branch->accessibility_list);

        // Act - Remove a feature
        $this->repository->removeAccessibility($branch_id, 'braille_menu');

        // Assert - Feature removed
        $updated_branch = $this->repository->get($branch_id);
        $this->assertCount(2, $updated_branch->accessibility_list);
        $this->assertNotContains('braille_menu', $updated_branch->accessibility_list);
        $this->assertContains('wheelchair_accessible', $updated_branch->accessibility_list);

        // Act - Try to add duplicate feature
        $this->repository->addAccessibility($branch_id, 'wheelchair_accessible');

        // Assert - No duplicates
        $final_branch = $this->repository->get($branch_id);
        $this->assertCount(2, $final_branch->accessibility_list);
    }

    /* =====================================================================
     * UPDATE OPERATIONS
     * ===================================================================*/

    public function test_update_branch_information(): void
    {
        // Arrange - Create branch
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        // Act - Update various fields
        $update_data = [
            'name' => 'Updated Branch Name',
            'phone' => '+1-555-UPDATED',
            'city' => 'Updated City',
            'address' => 'Updated Address 789',
            'is_open' => true,
            'kosher_type' => 'Updated Kosher',
            'accessibility_list' => ['new_feature_1', 'new_feature_2'],
            'activity_times' => [
                'SATURDAY' => ['10:00-14:00'],
                'SUNDAY' => ['12:00-20:00']
            ]
        ];

        $success = $this->repository->update($branch_id, $update_data);

        // Assert - Update successful
        $this->assertTrue($success);

        // Assert - All fields updated
        $updated_branch = $this->repository->get($branch_id);
        $this->assertEquals('Updated Branch Name', $updated_branch->name);
        $this->assertEquals('+1-555-UPDATED', $updated_branch->phone);
        $this->assertEquals('Updated City', $updated_branch->city);
        $this->assertEquals('Updated Address 789', $updated_branch->address);
        $this->assertTrue($updated_branch->is_open);
        $this->assertEquals('Updated Kosher', $updated_branch->kosher_type);
        $this->assertEquals(['new_feature_1', 'new_feature_2'], $updated_branch->accessibility_list);
        $this->assertArrayHasKey('SATURDAY', $updated_branch->activity_times);
        $this->assertEquals(['10:00-14:00'], $updated_branch->activity_times['SATURDAY']);
    }

    public function test_update_with_invalid_day_throws_exception(): void
    {
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid day:');

        $this->repository->update($branch_id, [
            'activity_times' => [
                'INVALID_DAY' => ['09:00-17:00']
            ]
        ]);
    }

    public function test_update_nonexistent_branch_returns_false(): void
    {
        $result = $this->repository->update(99999, ['name' => 'New Name']);
        $this->assertFalse($result);
    }

    /* =====================================================================
     * DELETE OPERATIONS
     * ===================================================================*/

    public function test_force_delete_branch(): void
    {
        $branch_id = $this->repository->create($this->getMinimalBranchData());

        // Act - Force delete
        $success = $this->repository->delete($branch_id, true);

        // Assert - Completely removed
        $this->assertTrue($success);
        $this->assertNull(get_post($branch_id));
    }

    public function test_delete_nonexistent_branch_returns_false(): void
    {
        $result = $this->repository->delete(99999);
        $this->assertFalse($result);
    }

    /* =====================================================================
     * GETALL OPERATIONS
     * ===================================================================*/

    public function test_get_all_branches(): void
    {
        // Arrange - Create multiple branches
        $branch1_id = $this->repository->create($this->getMinimalBranchData('Branch 1'));
        $branch2_id = $this->repository->create($this->getMinimalBranchData('Branch 2'));
        $branch3_id = $this->repository->create($this->getMinimalBranchData('Branch 3'));

        // Act
        $all_branches = $this->repository->getAll();

        // Assert
        $this->assertCount(3, $all_branches);
        $this->assertContainsOnlyInstancesOf(StoreBranch::class, $all_branches);

        $branch_ids = array_map(fn($branch) => $branch->id, $all_branches);
        $this->assertContains($branch1_id, $branch_ids);
        $this->assertContains($branch2_id, $branch_ids);
        $this->assertContains($branch3_id, $branch_ids);
    }

    public function test_get_all_excludes_trashed_branches(): void
    {
        // Arrange - Create branches and trash one
        $branch1_id = $this->repository->create($this->getMinimalBranchData('Branch 1'));
        $branch2_id = $this->repository->create($this->getMinimalBranchData('Branch 2'));
        
        $this->repository->delete($branch1_id); // Trash it

        // Act
        $all_branches = $this->repository->getAll();

        // Assert - Only published branch returned
        $this->assertCount(1, $all_branches);
        $branch_ids = array_map(fn($branch) => $branch->id, $all_branches);
        $this->assertNotContains($branch1_id, $branch_ids);
        $this->assertContains($branch2_id, $branch_ids);
    }

    /* =====================================================================
     * COMPLEX INTEGRATION SCENARIOS
     * ===================================================================*/

    public function test_cascading_product_addition_with_dependencies(): void
    {
        // This test would require ProductGroup and GroupItem setup
        // Testing the addProduct() method that automatically includes sub-components
        
        $branch_id = $this->repository->create($this->getMinimalBranchData());
        
        // For now, test basic product addition
        $this->repository->addProduct($branch_id, $this->test_product_id, true);
        
        $branch = $this->repository->get($branch_id);
        $this->assertCount(1, $branch->products);
        $this->assertTrue($branch->isProductAvailable($this->test_product_id));
    }

    public function test_serialization_and_deserialization(): void
    {
        // Test toArray() method functionality
        $branch_id = $this->repository->create([
            'name' => 'Serialization Test',
            'phone' => '+1-555-0000',
            'city' => 'Test City',
            'address' => 'Test Address',
            'is_open' => true,
            'activity_times' => ['MONDAY' => ['09:00-17:00']],
            'kosher_type' => 'Test Kosher',
            'accessibility_list' => ['test_feature']
        ]);

        $branch = $this->repository->get($branch_id);
        $array = $branch->toArray();

        // Assert - All data properly serialized
        $this->assertEquals($branch_id, $array['id']);
        $this->assertEquals('Serialization Test', $array['name']);
        $this->assertEquals('+1-555-0000', $array['phone']);
        $this->assertTrue($array['is_open']);
        $this->assertArrayHasKey('activity_times', $array);
        $this->assertArrayHasKey('kosher_type', $array);
        $this->assertArrayHasKey('accessibility_list', $array);
        $this->assertArrayHasKey('products', $array);
        $this->assertArrayHasKey('ingredients', $array);
    }

    /* =====================================================================
     * HELPER METHODS
     * ===================================================================*/

    private function getMinimalBranchData(string $name = 'Test Branch'): array
    {
        return [
            'name' => $name,
            'phone' => '+1-555-0123',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'is_open' => false,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => []
        ];
    }
}