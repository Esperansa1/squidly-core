<?php

/**
 * Test ProductGroupRepository convenience methods for filtering by ItemType
 */
class ProductGroupRepositoryTest extends WP_UnitTestCase
{
    private ProductGroupRepository $repository;
    private GroupItemRepository $groupItemRepo;
    
    private array $testProductGroupIds = [];
    private array $testIngredientGroupIds = [];
    private array $testGroupItemIds = [];

    public function setUp(): void
    {
        parent::setUp();
        
        // Initialize repositories
        $this->repository = new ProductGroupRepository();
        $this->groupItemRepo = new GroupItemRepository();
        
        // Create test data
        $this->createTestData();
    }

    public function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();
        
        parent::tearDown();
    }

    /**
     * Create test ProductGroups of both types
     */
    private function createTestData(): void
    {
        // Create test GroupItems first (we'll use dummy IDs for items)
        $groupItemData = [
            ['item_id' => 1, 'item_type' => 'product', 'override_price' => null],
            ['item_id' => 2, 'item_type' => 'product', 'override_price' => null],
            ['item_id' => 3, 'item_type' => 'ingredient', 'override_price' => null],
            ['item_id' => 4, 'item_type' => 'ingredient', 'override_price' => null],
        ];

        foreach ($groupItemData as $data) {
            $groupItemId = $this->groupItemRepo->create($data);
            $this->testGroupItemIds[] = $groupItemId;
        }

        // Create PRODUCT type ProductGroups
        $productGroupsData = [
            [
                'name' => 'Test Product Group 1',
                'type' => 'product',
                'group_item_ids' => [$this->testGroupItemIds[0], $this->testGroupItemIds[1]]
            ],
            [
                'name' => 'Test Product Group 2',
                'type' => 'product',
                'group_item_ids' => [$this->testGroupItemIds[0]]
            ]
        ];

        foreach ($productGroupsData as $data) {
            $groupId = $this->repository->create($data);
            $this->testProductGroupIds[] = $groupId;
        }

        // Create INGREDIENT type ProductGroups
        $ingredientGroupsData = [
            [
                'name' => 'Test Ingredient Group 1',
                'type' => 'ingredient',
                'group_item_ids' => [$this->testGroupItemIds[2], $this->testGroupItemIds[3]]
            ],
            [
                'name' => 'Test Ingredient Group 2',
                'type' => 'ingredient',
                'group_item_ids' => [$this->testGroupItemIds[2]]
            ],
            [
                'name' => 'Test Ingredient Group 3',
                'type' => 'ingredient',
                'group_item_ids' => [$this->testGroupItemIds[3]]
            ]
        ];

        foreach ($ingredientGroupsData as $data) {
            $groupId = $this->repository->create($data);
            $this->testIngredientGroupIds[] = $groupId;
        }
    }

    /**
     * Clean up test data
     */
    private function cleanupTestData(): void
    {
        // Delete ProductGroups
        foreach (array_merge($this->testProductGroupIds, $this->testIngredientGroupIds) as $groupId) {
            wp_delete_post($groupId, true);
        }

        // Delete GroupItems
        foreach ($this->testGroupItemIds as $groupItemId) {
            wp_delete_post($groupItemId, true);
        }
    }

    /**
     * Test getProductGroups() returns only product-type groups
     */
    public function testGetProductGroupsReturnsOnlyProductTypeGroups(): void
    {
        $productGroups = $this->repository->getProductGroups();
        
        // Should return at least our 2 test product groups
        $this->assertGreaterThanOrEqual(2, count($productGroups), 'Should return at least 2 product groups');
        
        // All returned groups should be of type 'product'
        foreach ($productGroups as $group) {
            $this->assertInstanceOf(ProductGroup::class, $group, 'Should return ProductGroup objects');
            $this->assertEquals('product', $group->type->value, 'All groups should have type "product"');
        }
        
        // Should contain our test product groups
        $returnedIds = array_map(fn($group) => $group->id, $productGroups);
        foreach ($this->testProductGroupIds as $testId) {
            $this->assertContains($testId, $returnedIds, "Should contain test product group ID {$testId}");
        }
        
        // Should NOT contain any of our test ingredient groups
        foreach ($this->testIngredientGroupIds as $testId) {
            $this->assertNotContains($testId, $returnedIds, "Should NOT contain test ingredient group ID {$testId}");
        }
    }

    /**
     * Test getIngredientGroups() returns only ingredient-type groups
     */
    public function testGetIngredientGroupsReturnsOnlyIngredientTypeGroups(): void
    {
        $ingredientGroups = $this->repository->getIngredientGroups();
        
        // Should return at least our 3 test ingredient groups
        $this->assertGreaterThanOrEqual(3, count($ingredientGroups), 'Should return at least 3 ingredient groups');
        
        // All returned groups should be of type 'ingredient'
        foreach ($ingredientGroups as $group) {
            $this->assertInstanceOf(ProductGroup::class, $group, 'Should return ProductGroup objects');
            $this->assertEquals('ingredient', $group->type->value, 'All groups should have type "ingredient"');
        }
        
        // Should contain our test ingredient groups
        $returnedIds = array_map(fn($group) => $group->id, $ingredientGroups);
        foreach ($this->testIngredientGroupIds as $testId) {
            $this->assertContains($testId, $returnedIds, "Should contain test ingredient group ID {$testId}");
        }
        
        // Should NOT contain any of our test product groups
        foreach ($this->testProductGroupIds as $testId) {
            $this->assertNotContains($testId, $returnedIds, "Should NOT contain test product group ID {$testId}");
        }
    }

    /**
     * Test getAllByItemType() works correctly with ItemType objects
     */
    public function testGetAllByItemTypeWithProductType(): void
    {
        $productType = ItemType::from('product');
        $productGroups = $this->repository->getAllByItemType($productType);
        
        // Should return same results as getProductGroups()
        $directProductGroups = $this->repository->getProductGroups();
        $this->assertCount(count($directProductGroups), $productGroups, 'getAllByItemType should return same count as getProductGroups');
        
        // All should be product type
        foreach ($productGroups as $group) {
            $this->assertEquals('product', $group->type->value, 'All groups should have type "product"');
        }
    }

    /**
     * Test getAllByItemType() works correctly with ingredient ItemType
     */
    public function testGetAllByItemTypeWithIngredientType(): void
    {
        $ingredientType = ItemType::from('ingredient');
        $ingredientGroups = $this->repository->getAllByItemType($ingredientType);
        
        // Should return same results as getIngredientGroups()
        $directIngredientGroups = $this->repository->getIngredientGroups();
        $this->assertCount(count($directIngredientGroups), $ingredientGroups, 'getAllByItemType should return same count as getIngredientGroups');
        
        // All should be ingredient type
        foreach ($ingredientGroups as $group) {
            $this->assertEquals('ingredient', $group->type->value, 'All groups should have type "ingredient"');
        }
    }

    /**
     * Test that getAll() returns both types
     */
    public function testGetAllReturnsBothTypes(): void
    {
        $allGroups = $this->repository->getAll();
        $productGroups = $this->repository->getProductGroups();
        $ingredientGroups = $this->repository->getIngredientGroups();
        
        // getAll should return at least as many as both types combined
        $expectedMinCount = count($productGroups) + count($ingredientGroups);
        $this->assertGreaterThanOrEqual($expectedMinCount, count($allGroups), 'getAll should return at least the sum of both types');
        
        // Should contain both product and ingredient types
        $allIds = array_map(fn($group) => $group->id, $allGroups);
        
        // Should contain all our test groups
        foreach (array_merge($this->testProductGroupIds, $this->testIngredientGroupIds) as $testId) {
            $this->assertContains($testId, $allIds, "getAll should contain test group ID {$testId}");
        }
    }

    /**
     * Test convenience methods return empty array when no groups exist
     */
    public function testEmptyResultsWhenNoGroupsExist(): void
    {
        // Clean up our test data temporarily
        $this->cleanupTestData();
        
        $productGroups = $this->repository->getProductGroups();
        $ingredientGroups = $this->repository->getIngredientGroups();
        
        // Both should return arrays (possibly empty if no other test data exists)
        $this->assertIsArray($productGroups, 'getProductGroups should return array');
        $this->assertIsArray($ingredientGroups, 'getIngredientGroups should return array');
        
        // Recreate test data for cleanup in tearDown
        $this->createTestData();
    }

    /**
     * Test that the methods handle invalid data gracefully
     */
    public function testHandlesInvalidItemTypes(): void
    {
        // Test with invalid ItemType - this should throw an exception
        $this->expectException(InvalidArgumentException::class);
        ItemType::from('invalid_type');
    }
    
    /**
     * Integration test: Verify groups are properly filtered in meta query
     */
    public function testMetaQueryFiltering(): void
    {
        // Directly query the database to verify our meta query is working
        $productIds = get_posts([
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => [
                [
                    'key'     => '_type',
                    'value'   => 'product',
                    'compare' => '='
                ],
                [
                    'key'     => '_type',
                    'compare' => 'EXISTS'
                ]
            ],
            'meta_query_relation' => 'AND'
        ]);
        
        $ingredientIds = get_posts([
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'post_status' => 'publish', 
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => [
                [
                    'key'     => '_type',
                    'value'   => 'ingredient',
                    'compare' => '='
                ],
                [
                    'key'     => '_type',
                    'compare' => 'EXISTS'
                ]
            ],
            'meta_query_relation' => 'AND'
        ]);
        
        // Our test product groups should be in productIds
        foreach ($this->testProductGroupIds as $testId) {
            $this->assertContains($testId, $productIds, "Meta query should find product group {$testId}");
        }
        
        // Our test ingredient groups should be in ingredientIds
        foreach ($this->testIngredientGroupIds as $testId) {
            $this->assertContains($testId, $ingredientIds, "Meta query should find ingredient group {$testId}");
        }
        
        // Verify separation - no product IDs should be in ingredient results
        foreach ($this->testProductGroupIds as $testId) {
            $this->assertNotContains($testId, $ingredientIds, "Ingredient query should NOT find product group {$testId}");
        }
        
        // Verify separation - no ingredient IDs should be in product results  
        foreach ($this->testIngredientGroupIds as $testId) {
            $this->assertNotContains($testId, $productIds, "Product query should NOT find ingredient group {$testId}");
        }
    }
}