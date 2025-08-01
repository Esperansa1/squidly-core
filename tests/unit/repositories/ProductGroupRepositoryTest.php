<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use ProductGroupRepository;
use GroupItemRepository;
use ItemType;
use InvalidArgumentException;
use ResourceInUseException;
use PHPUnit\Framework\TestCase;

/**
 * Pure-PHP tests (no real WP bootstrap) for ProductGroupRepository.
 */
class ProductGroupRepositoryTest extends TestCase
{
    private ProductGroupRepository $repo;
    private GroupItemRepository    $giRepo;

    protected function setUp(): void
    {
        $this->repo   = new ProductGroupRepository();
        $this->giRepo = new GroupItemRepository();
    }

    /* ---------------------------------------------------------------------
     *  create()
     * -------------------------------------------------------------------*/
    public function test_create_valid_payload_returns_id_and_meta(): void
    {
        $gi = $this->giRepo->create(['item_id'=>1,'item_type'=>'ingredient']);
        $id = $this->repo->create([
            'name'            => 'Salads',
            'type'            => ItemType::INGREDIENT,
            'group_item_ids'  => [$gi],
        ]);

        $this->assertIsInt($id);
        $this->assertEquals(ItemType::INGREDIENT, get_post_meta($id,'_type',true));
        $this->assertEquals([$gi],               get_post_meta($id,'_group_item_ids',true));
    }

    /** @dataProvider provideBadCreate */
    public function test_create_invalid_payload_throws(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function provideBadCreate(): array
    {
        return [
            'missing name' => [['type'=>'product']],
            'invalid type' => [['name'=>'X','type'=>'foo']],
        ];
    }

    /* ---------------------------------------------------------------------
     *  get()
     * -------------------------------------------------------------------*/
    public function test_get_round_trip(): void
    {
        $gi = $this->giRepo->create(['item_id'=>2,'item_type'=>'product']);
        $id = $this->repo->create([
            'name'=>'Extras','type'=>'product','group_item_ids'=>[$gi],
        ]);

        $pg = $this->repo->get($id);
        $this->assertSame('Extras',  $pg->name);
        $this->assertSame('product', $pg->type->value);
        $this->assertSame([$gi],     $pg->group_item_ids);
    }

    public function test_get_returns_null_when_missing_or_wrong_type(): void
    {
        $this->assertNull($this->repo->get(55555));

        $postId = wp_insert_post(['post_type'=>'post','post_title'=>'x','post_status'=>'publish']);
        $this->assertNull($this->repo->get($postId));
    }

    /* ---------------------------------------------------------------------
     *  update()
     * -------------------------------------------------------------------*/
    public function test_update_changes_name_and_items(): void
    {
        $giA = $this->giRepo->create(['item_id'=>3,'item_type'=>'ingredient']);
        $id  = $this->repo->create([
            'name'=>'Old','type'=>'ingredient','group_item_ids'=>[$giA],
        ]);

        $giB = $this->giRepo->create(['item_id'=>4,'item_type'=>'ingredient']);
        $this->repo->update($id, [
            'name'=>'New Name',
            'group_item_ids'=>[$giA,$giB],
        ]);

        $pg = $this->repo->get($id);
        $this->assertSame('New Name', $pg->name);
        $this->assertEqualsCanonicalizing([$giA,$giB], $pg->group_item_ids);
    }

    public function test_update_on_missing_record_returns_false(): void
    {
        $this->assertFalse($this->repo->update(999,['name'=>'foo']));
    }

    /* ---------------------------------------------------------------------
     *  delete()
     * -------------------------------------------------------------------*/
    public function test_delete_unused_group_succeeds(): void
    {
        $gi = $this->giRepo->create(['item_id'=>6,'item_type'=>'product']);
        $id = $this->repo->create([
            'name'=>'Temp','type'=>'product','group_item_ids'=>[$gi],
        ]);
        $this->assertTrue($this->repo->delete($id,true));
        $this->assertFalse(get_post_status($id));
    }

    public function test_delete_group_with_dependant_product_throws_exception(): void
    {
        // PG  →  Product
        $pgId = $this->repo->create([
            'name'=>'Bundled','type'=>'product','group_item_ids'=>[],
        ]);

        $prodRepo = new \ProductRepository();
        $prodRepo->create([
            'name'=>'Parent','price'=>10,'product_group_ids'=>[$pgId],
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->repo->delete($pgId,true);
    }
}
