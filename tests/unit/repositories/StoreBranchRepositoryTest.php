<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use InvalidArgumentException;
use StoreBranch;
use StoreBranchRepository;
use WP_UnitTestCase;

/**
 * Integration tests for StoreBranchRepository.
 *
 * @coversNothing
 */
class StoreBranchRepositoryTest extends WP_UnitTestCase
{
    private StoreBranchRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new StoreBranchRepository();
    }

    /* ------------------------------------------------------------------
     * 1. Happy-path round-trip
     * ----------------------------------------------------------------*/
    public function test_create_and_get_full_payload(): void
    {
        $payload = [
            'name'      => 'Central Branch',
            'phone'     => '03-555-1234',
            'city'      => 'Tel Aviv',
            'address'   => '1 Herzl St.',
            'is_open'   => true,
            'activity_times' => [
                'Sunday'  => ['08:00-14:00','16:00-22:00'],
                'Monday'  => ['08:00-22:00'],
            ],
            'kosher_type'        => 'Kosher Lemehadrin',
            'accessibility_list' => ['Wheelchair','Braille menu'],

            // simple IDs instead of full objects to keep test tight
            'products'               => [],
            'ingredients'            => [],
            'product_availability'   => [101 => true],
            'ingredient_availability'=> [202 => false],
        ];

        $id = $this->repo->create(array_merge(['id'=>0], $payload));

        /* check the post really exists */
        $this->assertSame(
            StoreBranchRepository::POST_TYPE,
            get_post_type($id)
        );

        /* round-trip via get() */
        $branch = $this->repo->get($id);
        $this->assertInstanceOf(StoreBranch::class, $branch);

        $this->assertSame('Central Branch', $branch->name);
        $this->assertSame('03-555-1234',    $branch->phone);
        $this->assertTrue($branch->is_open);
        $this->assertSame('Kosher Lemehadrin', $branch->kosher_type);
        $this->assertSame(['Wheelchair','Braille menu'], $branch->accessibility_list);

        /* helpers */
        $this->assertTrue($branch->isProductAvailable(101));
        $this->assertFalse($branch->isIngredientAvailable(202));

        /* activity times retained */
        $this->assertArrayHasKey('Sunday', $branch->activity_times);
        $this->assertSame(
            ['08:00-14:00','16:00-22:00'],
            $branch->activity_times['Sunday']
        );
    }

    /* ------------------------------------------------------------------
     * 2. Required fields validation
     * ----------------------------------------------------------------*/
    /** @dataProvider provideMissingRequired */
    public function test_create_missing_required_field_throws(array $incomplete): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create(array_merge(['id'=>0], $incomplete));
    }

    public function provideMissingRequired(): array
    {
        $base = [
            'name'=>'x','phone'=>'y','city'=>'c','address'=>'a','is_open'=>false,
            'activity_times'=>[],'kosher_type'=>'','accessibility_list'=>[],
        ];
        return [
            ['name'                => array_diff_key($base,['name'=>1])],
            ['phone'               => array_diff_key($base,['phone'=>1])],
            ['city'                => array_diff_key($base,['city'=>1])],
            ['address'             => array_diff_key($base,['address'=>1])],
            ['is_open'             => array_diff_key($base,['is_open'=>1])],
            ['activity_times'      => array_diff_key($base,['activity_times'=>1])],
            ['kosher_type'         => array_diff_key($base,['kosher_type'=>1])],
            ['accessibility_list'  => array_diff_key($base,['accessibility_list'=>1])],
        ];
    }

    /* ------------------------------------------------------------------
     * 3. get() returns null on wrong post-type or missing ID
     * ----------------------------------------------------------------*/
    public function test_get_returns_null_when_not_found_or_wrong_type(): void
    {
        /* non-existent ID */
        $this->assertNull($this->repo->get(999999));

        /* existing WP post but not a branch */
        $pid = $this->factory()->post->create(['post_title'=>'dummy']);
        $this->assertNull($this->repo->get($pid));
    }
}
