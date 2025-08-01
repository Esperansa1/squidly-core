<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductRepository;
use GroupItemRepository;
use ProductGroupRepository;
use ResourceInUseException;
use WP_UnitTestCase;

/**
 * @coversNothing
 */
class ProductRepositoryDeleteTest extends WP_UnitTestCase
{
    private ProductRepository    $prodRepo;
    private GroupItemRepository  $giRepo;
    private ProductGroupRepository $pgRepo;

    public function set_up(): void
    {
        parent::set_up();
        $this->prodRepo = new ProductRepository();
        $this->giRepo   = new GroupItemRepository();
        $this->pgRepo   = new ProductGroupRepository();
    }

    public function test_delete_unused_product_succeeds(): void
    {
        $pid = $this->prodRepo->create(['name'=>'Solo','price'=>5]);
        $this->assertTrue($this->prodRepo->delete($pid));
        $this->assertNull($this->prodRepo->get($pid));
    }

    public function test_delete_product_in_use_throws_exception(): void
    {
        // create product
        $pid = $this->prodRepo->create(['name'=>'Fries','price'=>10]);

        // create GroupItem -> ProductGroup referencing it
        $gi = $this->giRepo->create([
            'item_id'=>$pid,'item_type'=>'product'
        ]);
        $this->pgRepo->create([
            'name'=>'Sides','type'=>'product','group_item_ids'=>[$gi]
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->prodRepo->delete($pid);
    }
}
