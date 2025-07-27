<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use GroupItem;
use GroupItemRepository;
use ItemType;
use InvalidArgumentException;
use WP_UnitTestCase;

/** @covers \GroupItemRepository */
class GroupItemRepositoryTest extends WP_UnitTestCase
{
    private GroupItemRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new GroupItemRepository();
    }

    /* ----------------------------- create() ----------------------------- */

    public function test_create_and_get_full_payload(): void
    {
        $id = $this->repo->create([
            'item_id'        => 15,
            'item_type'      => ItemType::PRODUCT,      // ← constant
            'override_price' => 9.9,
        ]);

        $this->assertSame('group_item', get_post_type($id));

        $gi = $this->repo->get($id);
        $this->assertInstanceOf(GroupItem::class, $gi);

        $this->assertSame(15,        $gi->item_id);
        $this->assertSame(ItemType::PRODUCT, $gi->item_type->value); // round-trip
        $this->assertSame(9.9,       $gi->override_price);

        /* negative */
        $this->assertNotSame(ItemType::INGREDIENT, $gi->item_type->value);
    }

    public function test_create_without_override_price(): void
    {
        $id = $this->repo->create([
            'item_id'   => 22,
            'item_type' => ItemType::INGREDIENT,
        ]);

        $gi = $this->repo->get($id);

        $this->assertSame(22,                  $gi->item_id);
        $this->assertSame(ItemType::INGREDIENT,$gi->item_type->value);
        $this->assertNull($gi->override_price);
    }

    /** @dataProvider provideInvalidCreate */
    public function test_create_missing_fields_throws(array $bad): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($bad);
    }

    public function provideInvalidCreate(): array
    {
        return [
            'missing item_id'   => [['item_type' => ItemType::PRODUCT]],
            'missing item_type' => [['item_id'   => 5]],
        ];
    }

    /* ------------------------------- get() ------------------------------ */

    public function test_get_returns_null_for_nonexistent_or_wrong_type(): void
    {
        $this->assertNull($this->repo->get(999999));          // nonexistent

        $postId = $this->factory()->post->create();           // wrong type
        $this->assertNull($this->repo->get($postId));
    }


    public function test_create_rejects_invalid_item_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GroupItemRepository())->create([
            'item_id'   => 123,
            'item_type' => 'invalid-type',   // not 'product' or 'ingredient'
        ]);
    }

}
