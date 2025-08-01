<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientRepository;
use GroupItemRepository;
use ItemType;
use InvalidArgumentException;
use ResourceInUseException;
use WP_UnitTestCase;

/**
 * Integration tests for IngredientRepository.
 * Runs against the WordPress core test suite (WP_INTEGRATION=1).
 *
 * @covers IngredientRepository
 */
class IngredientRepositoryIntegrationTest extends WP_UnitTestCase
{
    private IngredientRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new IngredientRepository();
    }

    /* =====================================================================
     *  CREATE + GET
     * ===================================================================*/

    public function test_create_and_get_roundtrip(): void
    {
        $id = $this->repo->create(['name' => 'Salt', 'price' => 1.5]);

        // post actually exists
        $this->assertSame(IngredientRepository::POST_TYPE, get_post_type($id));

        $ing = $this->repo->get($id);
        $this->assertSame('Salt', $ing->name);
        $this->assertSame(1.5,   $ing->price);
    }

    /** @dataProvider provideBadCreatePayloads */
    public function test_create_invalid_payloads_throw(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function provideBadCreatePayloads(): array
    {
        return [
            'missing name'      => [['price'=>1]],
            'empty name'        => [['name'=>'', 'price'=>1]],
            'negative price'    => [['name'=>'Pepper','price'=>-2]],
        ];
    }

    /* =====================================================================
     *  UPDATE
     * ===================================================================*/

    public function test_update_changes_name_and_price(): void
    {
        $id = $this->repo->create(['name'=>'Sugar','price'=>2.0]);

        $ok = $this->repo->update($id, ['name'=>'Brown Sugar','price'=>2.2]);
        $this->assertTrue($ok);

        $updated = $this->repo->get($id);
        $this->assertSame('Brown Sugar', $updated->name);
        $this->assertSame(2.2,           $updated->price);
    }

    /** @dataProvider provideBadUpdatePayloads */
    public function test_update_invalid_payloads_throw(array $payload): void
    {
        $id = $this->repo->create(['name'=>'Cocoa','price'=>3]);
        $this->expectException(InvalidArgumentException::class);
        $this->repo->update($id, $payload);
    }

    public function provideBadUpdatePayloads(): array
    {
        return [
            'empty name'      => [['name'=>'']],
            'negative price'  => [['price'=>-1]],
        ];
    }

    public function test_update_on_missing_record_returns_false(): void
    {
        $this->assertFalse($this->repo->update(999999, ['name'=>'noop']));
    }

    /* =====================================================================
     *  GET helpers
     * ===================================================================*/

    public function test_get_returns_null_when_not_found_or_wrong_type(): void
    {
        $this->assertNull($this->repo->get(424242));

        // create a regular WP post
        $pid = $this->factory()->post->create(['post_title'=>'dummy']);
        $this->assertNull($this->repo->get($pid));
    }

    public function test_getAll_returns_only_published_ingredients(): void
    {
        $a = $this->repo->create(['name'=>'A','price'=>0.1]);
        $b = $this->repo->create(['name'=>'B','price'=>0.2]);

        // Draft – should be ignored
        $draft = wp_insert_post([
            'post_title'=>'C',
            'post_type'=>IngredientRepository::POST_TYPE,
            'post_status'=>'draft',
        ]);

        $all   = $this->repo->getAll();
        $names = array_map(fn($i)=>$i->name,$all);
        sort($names);

        $this->assertSame(['A','B'], $names);
    }

    /* =====================================================================
     *  DELETE
     * ===================================================================*/

    public function test_delete_unused_ingredient_succeeds(): void
    {
        $id = $this->repo->create(['name'=>'Free','price'=>0]);
        $this->assertTrue($this->repo->delete($id, true));
        $this->assertFalse(get_post_status($id));        // post is gone
    }

    public function test_delete_with_dependants_throws_exception(): void
    {
        // 1) ingredient
        $iid = $this->repo->create(['name'=>'Onion','price'=>0.4]);

        // 2) group-item wrapping that ingredient  (creates dependency)
        $giRepo = new GroupItemRepository();
        $giRepo->create([
            'item_id'   => $iid,
            'item_type' => ItemType::INGREDIENT,
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->repo->delete($iid, true);
    }
}

