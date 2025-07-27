<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use GroupItemRepository;
use Ingredient;
use IngredientRepository;
use ItemType;
use Product;
use ProductGroup;
use ProductRepository;
use WP_UnitTestCase;

/** @coversNothing — behavioural integration */
class ProductGroupIntegrationTest extends WP_UnitTestCase
{
    private ProductRepository   $prodRepo;
    private IngredientRepository $ingRepo;
    private GroupItemRepository  $giRepo;

    public function set_up(): void
    {
        parent::set_up();
        $this->prodRepo = new ProductRepository();
        $this->ingRepo  = new IngredientRepository();
        $this->giRepo   = new GroupItemRepository();
    }

    public function test_getGroupItems_and_getResolvedItems(): void
    {
        /* ---------- Arrange concrete items ---------- */
        $p1 = $this->prodRepo->create(['name'=>'P1','price'=>5.0]);
        $p2 = $this->prodRepo->create(['name'=>'P2','price'=>3.0]);

        /* ---------- Create GroupItems (one override) ---------- */
        $gid1 = $this->giRepo->create([
            'item_id'=>$p1,
            'item_type'=>ItemType::PRODUCT,
            'override_price'=>6.0
        ]);
        $gid2 = $this->giRepo->create([
            'item_id'=>$p2,
            'item_type'=>ItemType::PRODUCT
        ]);

        /* plus an invalid id that should be ignored */
        $invalidId = 9999;

        $pg = new ProductGroup([
            'id'=>1,
            'name'=>'PG',
            'type'=>ItemType::PRODUCT,
            'group_item_ids'=>[$gid1, $gid2, $invalidId]
        ]);

        /* ---------- getGroupItems() ---------- */
        $groupItems = $pg->getGroupItems();
        $this->assertCount(2, $groupItems);
        $this->assertSame([$gid1,$gid2], array_map(fn($g)=>$g->item_id === $p1 ? $gid1 : $gid2, $groupItems));

        /* ---------- getResolvedItems() ---------- */
        $resolved = $pg->getResolvedItems($this->giRepo, $this->prodRepo, $this->ingRepo);
        $this->assertCount(2, $resolved);

        // order preserved
        $this->assertSame('P1', $resolved[0]->name);
        $this->assertSame('P2', $resolved[1]->name);

        // override respected
        $this->assertSame(6.0, $resolved[0]->price);
        $this->assertSame(3.0, $resolved[1]->price);

        // negative: ensure invalid ID skipped
        $ids = array_map(fn($r) => $r->id, $resolved);
        $this->assertNotContains($invalidId, $ids);
    }
}
