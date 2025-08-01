<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use GroupItemRepository;
use ItemType;
use ResourceInUseException;
use WP_UnitTestCase;

class GroupItemRepositoryIntegrationTest extends WP_UnitTestCase
{
    private GroupItemRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new GroupItemRepository();
    }

    public function test_delete_group_item_still_in_use_throws_exception(): void
    {
        /* create GI */
        $giId = $this->repo->create(['item_id'=>1,'item_type'=>ItemType::INGREDIENT]);

        /* wrap the GI in a ProductGroup to establish dependency */
        $pgId = wp_insert_post([
            'post_title'=>'PG',
            'post_type'=>'product_group',
            'post_status'=>'publish',
        ]);
        update_post_meta($pgId, '_type', ItemType::INGREDIENT);
        update_post_meta($pgId, '_group_item_ids', [$giId]);

        $this->expectException(ResourceInUseException::class);
        $this->repo->delete($giId, true);
    }

    public function test_create_get_delete_full_cycle(): void
    {
        $giId = $this->repo->create([
            'item_id'=>77,
            'item_type'=>ItemType::PRODUCT,
            'override_price'=>5.5,
        ]);

        $obj = $this->repo->get($giId);
        $this->assertSame(77,  $obj->item_id);
        $this->assertSame(5.5, $obj->override_price);

        $this->assertTrue($this->repo->delete($giId, true));
        $this->assertNull($this->repo->get($giId));
    }
}
