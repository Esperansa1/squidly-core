<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientRepository;
use GroupItemRepository;
use ProductGroupRepository;
use ResourceInUseException;
use WP_UnitTestCase;

/**
 * Integration tests for the new delete-with-dependencies logic.
 *
 * @coversNothing
 */
class IngredientRepositoryDeleteTest extends WP_UnitTestCase
{
    private IngredientRepository   $ingRepo;
    private GroupItemRepository    $giRepo;
    private ProductGroupRepository $pgRepo;

    public function set_up(): void
    {
        parent::set_up();
        $this->ingRepo = new IngredientRepository();
        $this->giRepo  = new GroupItemRepository();
        $this->pgRepo  = new ProductGroupRepository();
    }

    /* ------------------------------------------------------------------
     * 1. Deleting ingredient IN USE should throw
     * ----------------------------------------------------------------*/
    public function test_delete_ingredient_in_use_throws_exception(): void
    {
        /* create ingredient */
        $ingId = $this->ingRepo->create(['name'=>'Salt','price'=>0]);

        /* create group-item using that ingredient */
        $giId = $this->giRepo->create([
            'item_id'   => $ingId,
            'item_type' => 'ingredient',
        ]);

        /* create product-group that contains the group-item */
        $pgId = $this->pgRepo->create([
            'name'            => 'Seasonings',
            'type'            => 'ingredient',
            'group_item_ids'  => [$giId],
        ]);

        /* expect ResourceInUseException with dependants containing the PG name */
        try {
            $this->ingRepo->delete($ingId);
            $this->fail('ResourceInUseException was not thrown');
        } catch (ResourceInUseException $e) {
            $this->assertContains(
                'Seasonings',
                $e->dependants,
                'Dependants list missing product-group name'
            );
        }
    }

    /* ------------------------------------------------------------------
     * 2. Deleting ingredient NOT in use should succeed
     * ----------------------------------------------------------------*/
    public function test_delete_unused_ingredient_succeeds(): void
    {
        $ingId = $this->ingRepo->create(['name'=>'Unused','price'=>1.0]);

        $this->assertTrue(
            $this->ingRepo->delete($ingId),   // default: soft delete (trash)
            'Delete returned false'
        );

        // post is now in trash
        $status = get_post_status($ingId);
        $this->assertTrue(
            $status === 'trash' || $status === false,
            'Ingredient should be trashed or deleted'
        );
        $this->assertNull($this->ingRepo->get($ingId));



    }
}
