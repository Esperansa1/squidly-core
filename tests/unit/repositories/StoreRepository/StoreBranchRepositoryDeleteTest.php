<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use StoreBranchRepository;
use WP_UnitTestCase;

/**
 * @coversNothing
 */
class StoreBranchRepositoryDeleteTest extends WP_UnitTestCase
{
    private StoreBranchRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new StoreBranchRepository();
    }

    public function test_delete_existing_branch(): void
    {
        $id = $this->repo->create([
            'name'      => 'Deletable',
            'phone'     => '050-000-0000',
            'city'      => 'Haifa',
            'address'   => '1 Port St',
            'is_open'   => false,
            'activity_times'=>[],
            'kosher_type'=>'',
            'accessibility_list'=>[],
        ]);

        $this->assertTrue($this->repo->delete($id));      // returns true
        $this->assertNull($this->repo->get($id));         // gone
    }

    public function test_delete_nonexistent_returns_false(): void
    {
        $this->assertFalse($this->repo->delete(999999));
    }
}
