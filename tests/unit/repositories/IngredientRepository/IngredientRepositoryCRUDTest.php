<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientRepository;
use GroupItemRepository;
use ProductGroupRepository;
use WP_UnitTestCase;
use ResourceInUseException;

/**
 * @coversNothing
 */
class IngredientRepositoryCRUDTest extends WP_UnitTestCase
{
    private IngredientRepository $ingRepo;
    private GroupItemRepository  $giRepo;
    private ProductGroupRepository $pgRepo;

    public function set_up(): void
    {
        parent::set_up();
        $this->ingRepo = new IngredientRepository();
        $this->giRepo  = new GroupItemRepository();
        $this->pgRepo  = new ProductGroupRepository();
    }

    /* ---------------------------------------------------------------
     *  UPDATE
     * -------------------------------------------------------------*/

    public function test_update_all_fields_roundtrip(): void
    {
        $id = $this->ingRepo->create(['name'=>'Salt','price'=>1]);

        $ok = $this->ingRepo->update($id, ['name'=>'Sea & Salt','price'=>2.5]);
        $this->assertTrue($ok);

        $ing = $this->ingRepo->get($id);
        $this->assertSame('Sea &amp; Salt', $ing->name);      // sanitized
        $this->assertSame(2.5, $ing->price);
    }

    public function test_update_only_name(): void
    {
        $id = $this->ingRepo->create(['name'=>'Pepper','price'=>1.1]);
        $this->ingRepo->update($id, ['name'=>'Black Pepper']);

        $this->assertSame('Black Pepper', $this->ingRepo->get($id)->name);
        $this->assertSame(1.1, $this->ingRepo->get($id)->price);
    }

    /** @dataProvider provideInvalidUpdates */
    public function test_update_invalid_inputs_throw(array $payload): void
    {
        $id = $this->ingRepo->create(['name'=>'Guard','price'=>1]);

        $this->expectException(\InvalidArgumentException::class);
        $this->ingRepo->update($id, $payload);
    }

    public function provideInvalidUpdates(): array
    {
        return [
            'empty name'  => [['name'=>'']],
            'negative price'=>[['price'=>-3]],
        ];
    }

    /* ---------------------------------------------------------------
     *  DELETE
     * -------------------------------------------------------------*/

    public function test_delete_unused_ingredient_succeeds(): void
    {
        $id = $this->ingRepo->create(['name'=>'Unused','price'=>0.5]);
        $this->assertTrue($this->ingRepo->delete($id));

        $this->assertNull($this->ingRepo->get($id));          // gone
    }

    public function test_delete_ingredient_in_use_throws(): void
    {
        // create ingredient
        $iid = $this->ingRepo->create(['name'=>'Onion','price'=>0.3]);

        // wrap in GroupItem and ProductGroup
        $giId = $this->giRepo->create([
            'item_id'=>$iid,'item_type'=>'ingredient'
        ]);
        $this->pgRepo->create([
            'name'=>'Burger Free','type'=>'ingredient',
            'group_item_ids'=>[$giId],
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->ingRepo->delete($iid);
    }

    public function test_delete_wrong_id_returns_false(): void
    {
        $this->assertFalse($this->ingRepo->delete(999999));
    }
}
