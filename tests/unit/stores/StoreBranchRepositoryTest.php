<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use StoreBranchRepository;
use StoreBranch;
use InvalidArgumentException;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StoreBranchRepository
 *
 * Tests repository validation logic, data transformation,
 * and business rules without WordPress database dependencies.
 */
class StoreBranchRepositoryTest extends TestCase
{
    private StoreBranchRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new StoreBranchRepository();
    }

    /* ---------------------------------------------------------------------
     *  Creation Validation Tests
     * -------------------------------------------------------------------*/

    public function test_create_with_valid_data_returns_id(): void
    {
        $validData = [
            'name' => 'Test Branch',
            'phone' => '555-0123',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'is_open' => true,
            'activity_times' => ['SUNDAY' => ['09:00-17:00']],
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => ['wheelchair_accessible'],
            'products' => [],
            'ingredients' => [],
            'product_availability' => [],
            'ingredient_availability' => []
        ];

        $id = $this->repo->create($validData);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /** @dataProvider provideInvalidCreateData */
    public function test_create_with_missing_required_fields_throws_exception(array $data, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->repo->create($data);
    }

    public function provideInvalidCreateData(): array
    {
        $validBase = [
            'name' => 'Test Branch',
            'phone' => '555-0123',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => []
        ];

        return [
            'missing name' => [
                array_diff_key($validBase, ['name' => '']),
                'Missing required key: name'
            ],
            'missing phone' => [
                array_diff_key($validBase, ['phone' => '']),
                'Missing required key: phone'
            ],
            'missing city' => [
                array_diff_key($validBase, ['city' => '']),
                'Missing required key: city'
            ],
            'missing address' => [
                array_diff_key($validBase, ['address' => '']),
                'Missing required key: address'
            ],
            'missing is_open' => [
                array_diff_key($validBase, ['is_open' => '']),
                'Missing required key: is_open'
            ],
            'missing activity_times' => [
                array_diff_key($validBase, ['activity_times' => '']),
                'Missing required key: activity_times'
            ],
            'missing kosher_type' => [
                array_diff_key($validBase, ['kosher_type' => '']),
                'Missing required key: kosher_type'
            ],
            'missing accessibility_list' => [
                array_diff_key($validBase, ['accessibility_list' => '']),
                'Missing required key: accessibility_list'
            ]
        ];
    }

    /* ---------------------------------------------------------------------
     *  Activity Times Validation Tests
     * -------------------------------------------------------------------*/

    public function test_add_activity_time_with_valid_day_succeeds(): void
    {
        $branchId = $this->createTestBranch();

        // Should not throw exception
        $this->repo->addActivityTime($branchId, 'MONDAY', '08:00-14:00');
        $this->assertTrue(true); // If we get here, test passed
    }

    /** @dataProvider provideValidDays */
    public function test_add_activity_time_accepts_all_valid_days(string $day): void
    {
        $branchId = $this->createTestBranch();

        // Should not throw exception
        $this->repo->addActivityTime($branchId, $day, '09:00-17:00');
        $this->assertTrue(true);
    }

    public function provideValidDays(): array
    {
        return [
            ['SUNDAY'],
            ['MONDAY'],
            ['TUESDAY'],
            ['WEDNESDAY'],
            ['THURSDAY'],
            ['FRIDAY'],
            ['SATURDAY']
        ];
    }

    public function test_add_activity_time_with_invalid_day_throws_exception(): void
    {
        $branchId = $this->createTestBranch();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid week-day');

        $this->repo->addActivityTime($branchId, 'INVALIDDAY', '09:00-17:00');
    }

    /* Activity time validation is tested in integration tests
     * as it requires WordPress database operations */

    /* ---------------------------------------------------------------------
     *  Update Validation Tests
     * -------------------------------------------------------------------*/

    public function test_update_with_valid_data_succeeds(): void
    {
        $branchId = $this->createTestBranch();

        $result = $this->repo->update($branchId, [
            'name' => 'Updated Branch Name',
            'phone' => '555-9999',
            'is_open' => false
        ]);

        $this->assertTrue($result);
    }

    public function test_update_with_invalid_day_in_activity_times_throws_exception(): void
    {
        $branchId = $this->createTestBranch();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid day: INVALIDDAY');

        $this->repo->update($branchId, [
            'activity_times' => ['INVALIDDAY' => ['09:00-17:00']]
        ]);
    }

    public function test_update_nonexistent_branch_returns_false(): void
    {
        $result = $this->repo->update(99999, ['name' => 'Test']);
        $this->assertFalse($result);
    }

    public function test_update_with_partial_data_succeeds(): void
    {
        $branchId = $this->createTestBranch();

        // Update only name
        $result = $this->repo->update($branchId, ['name' => 'New Name']);
        $this->assertTrue($result);

        // Update only phone
        $result = $this->repo->update($branchId, ['phone' => '555-8888']);
        $this->assertTrue($result);

        // Update only city
        $result = $this->repo->update($branchId, ['city' => 'New City']);
        $this->assertTrue($result);
    }

    /* ---------------------------------------------------------------------
     *  Delete Tests
     * -------------------------------------------------------------------*/

    public function test_delete_existing_branch_returns_true(): void
    {
        $branchId = $this->createTestBranch();

        $result = $this->repo->delete($branchId);
        $this->assertTrue($result);
    }

    public function test_delete_with_force_returns_true(): void
    {
        $branchId = $this->createTestBranch();

        $result = $this->repo->delete($branchId, true);
        $this->assertTrue($result);
    }

    public function test_delete_nonexistent_branch_returns_false(): void
    {
        $result = $this->repo->delete(99999);
        $this->assertFalse($result);
    }

    /* ---------------------------------------------------------------------
     *  Exists Tests
     * -------------------------------------------------------------------*/

    public function test_exists_returns_true_for_valid_branch(): void
    {
        $branchId = $this->createTestBranch();
        $this->assertTrue($this->repo->exists($branchId));
    }

    public function test_exists_returns_false_for_invalid_id(): void
    {
        $this->assertFalse($this->repo->exists(99999));
        $this->assertFalse($this->repo->exists(0));
        $this->assertFalse($this->repo->exists(-1));
    }

    /* ---------------------------------------------------------------------
     *  Get Tests
     * -------------------------------------------------------------------*/

    public function test_get_returns_null_for_nonexistent_branch(): void
    {
        $this->assertNull($this->repo->get(99999));
    }

    public function test_get_returns_branch_for_valid_id(): void
    {
        $branchId = $this->createTestBranch();
        $branch = $this->repo->get($branchId);

        $this->assertInstanceOf(StoreBranch::class, $branch);
        $this->assertEquals($branchId, $branch->id);
    }

    /* ---------------------------------------------------------------------
     *  FindBy Tests
     * -------------------------------------------------------------------*/

    public function test_find_by_city_returns_matching_branches(): void
    {
        $this->createTestBranch(['city' => 'Tel Aviv']);
        $this->createTestBranch(['city' => 'Tel Aviv']);
        $this->createTestBranch(['city' => 'Jerusalem']);

        $branches = $this->repo->findByCity('Tel Aviv');
        $this->assertCount(2, $branches);
    }

    /* Query operations (findOpen, findByKosherType) are tested in integration tests
     * as they require WordPress WP_Query operations */

    /* ---------------------------------------------------------------------
     *  Setter Methods Tests
     * -------------------------------------------------------------------*/

    public function test_set_name_updates_branch_name(): void
    {
        $branchId = $this->createTestBranch();

        $this->repo->setName($branchId, 'New Branch Name');

        $branch = $this->repo->get($branchId);
        $this->assertEquals('New Branch Name', $branch->name);
    }

    public function test_set_phone_updates_branch_phone(): void
    {
        $branchId = $this->createTestBranch();

        $this->repo->setPhone($branchId, '555-9999');

        $branch = $this->repo->get($branchId);
        $this->assertEquals('555-9999', $branch->phone);
    }

    public function test_set_city_updates_branch_city(): void
    {
        $branchId = $this->createTestBranch();

        $this->repo->setCity($branchId, 'New City');

        $branch = $this->repo->get($branchId);
        $this->assertEquals('New City', $branch->city);
    }

    public function test_set_address_updates_branch_address(): void
    {
        $branchId = $this->createTestBranch();

        $this->repo->setAddress($branchId, 'New Address');

        $branch = $this->repo->get($branchId);
        $this->assertEquals('New Address', $branch->address);
    }

    public function test_set_is_open_updates_branch_status(): void
    {
        $branchId = $this->createTestBranch(['is_open' => true]);

        $this->repo->setIsOpen($branchId, false);

        $branch = $this->repo->get($branchId);
        $this->assertFalse($branch->is_open);
    }

    /* ---------------------------------------------------------------------
     *  Kosher Type Tests
     * -------------------------------------------------------------------*/

    public function test_set_kosher_type_updates_value(): void
    {
        $branchId = $this->createTestBranch();

        $this->repo->setKosherType($branchId, 'Kosher Mehadrin');

        $branch = $this->repo->get($branchId);
        $this->assertEquals('Kosher Mehadrin', $branch->kosher_type);
    }

    public function test_clear_kosher_type_removes_value(): void
    {
        $branchId = $this->createTestBranch(['kosher_type' => 'Kosher Dairy']);

        $this->repo->clearKosherType($branchId);

        $branch = $this->repo->get($branchId);
        $this->assertEmpty($branch->kosher_type);
    }

    /* ---------------------------------------------------------------------
     *  Accessibility Tests
     * -------------------------------------------------------------------*/

    public function test_add_accessibility_feature(): void
    {
        $branchId = $this->createTestBranch(['accessibility_list' => []]);

        $this->repo->addAccessibility($branchId, 'wheelchair_accessible');

        $branch = $this->repo->get($branchId);
        $this->assertContains('wheelchair_accessible', $branch->accessibility_list);
    }

    public function test_remove_accessibility_feature(): void
    {
        $branchId = $this->createTestBranch(['accessibility_list' => ['wheelchair_accessible', 'braille_menu']]);

        $this->repo->removeAccessibility($branchId, 'wheelchair_accessible');

        $branch = $this->repo->get($branchId);
        $this->assertNotContains('wheelchair_accessible', $branch->accessibility_list);
        $this->assertContains('braille_menu', $branch->accessibility_list);
    }

    /* ---------------------------------------------------------------------
     *  Product/Ingredient Management Tests
     * Note: These are tested in integration tests as they require
     * WordPress database operations and repository dependencies
     * -------------------------------------------------------------------*/

    /* ---------------------------------------------------------------------
     *  Availability Management Tests
     * -------------------------------------------------------------------*/

    public function test_set_product_availability_updates_status(): void
    {
        $branchId = $this->createTestBranch();
        $productId = 123;

        $this->repo->setProductAvailability($branchId, $productId, false);

        $branch = $this->repo->get($branchId);
        $this->assertFalse($branch->product_availability[$productId] ?? true);
    }

    public function test_set_ingredient_availability_updates_status(): void
    {
        $branchId = $this->createTestBranch();
        $ingredientId = 456;

        $this->repo->setIngredientAvailability($branchId, $ingredientId, false);

        $branch = $this->repo->get($branchId);
        $this->assertFalse($branch->ingredient_availability[$ingredientId] ?? true);
    }

    /* ---------------------------------------------------------------------
     *  CountBy Tests
     * -------------------------------------------------------------------*/

    /* countBy operations are tested in integration tests
     * as they require WordPress WP_Query operations */

    /* ---------------------------------------------------------------------
     *  Helper Methods
     * -------------------------------------------------------------------*/

    private function createTestBranch(array $overrides = []): int
    {
        $defaults = [
            'name' => 'Test Branch',
            'phone' => '555-0123',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => [],
            'products' => [],
            'ingredients' => [],
            'product_availability' => [],
            'ingredient_availability' => []
        ];

        return $this->repo->create(array_merge($defaults, $overrides));
    }
}
