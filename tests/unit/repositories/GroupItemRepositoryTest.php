<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use GroupItemRepository;
use ItemType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure-PHP unit tests for GroupItemRepository.
 * WordPress is stubbed with simple globals to avoid external deps.
 */
class GroupItemRepositoryTest extends TestCase
{

    private GroupItemRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new GroupItemRepository();
    }

    /* ----------------------------- create() ----------------------------- */

    public function test_create_valid_data_returns_id_and_saves_meta(): void
    {
        $id = $this->repo->create([
            'item_id' => 123,
            'item_type' => ItemType::INGREDIENT,
            'override_price' => 4.5,
        ]);
        $this->assertIsInt($id);

        $this->assertEquals(123, get_post_meta($id, '_item_id', true));
        $this->assertEquals(ItemType::INGREDIENT, get_post_meta($id, '_item_type', true));
        $this->assertEquals(4.5, get_post_meta($id, '_override_price', true));
    }

    /** @dataProvider provideBadCreatePayloads */
    public function test_create_invalid_payload_throws(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function provideBadCreatePayloads(): array
    {
        return [
            'missing item_id'   => [['item_type'=>ItemType::PRODUCT]],
            'missing item_type' => [['item_id'=>9]],
            'invalid type'      => [['item_id'=>1,'item_type'=>'foo']],
        ];
    }

    /* ----------------------------- get() -------------------------------- */

    public function test_get_round_trip_data_ok(): void
    {
        $id = $this->repo->create([
            'item_id'=>42,'item_type'=>ItemType::PRODUCT,'override_price'=>null
        ]);
        $gi = $this->repo->get($id);

        $this->assertSame(42, $gi->item_id);
        $this->assertSame(ItemType::PRODUCT, $gi->item_type->value);
        $this->assertNull($gi->override_price);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repo->get(9999));
    }

    /* ----------------------------- update() ----------------------------- */

    public function test_update_changes_stored_meta(): void
    {
        $id = $this->repo->create([
            'item_id'=>5,'item_type'=>ItemType::INGREDIENT,'override_price'=>1.0
        ]);

        $ok = $this->repo->update($id, ['override_price'=>2.2]);
        $this->assertTrue($ok);
        $this->assertEquals(2.2, get_post_meta($id, '_override_price', true));
    }

    public function test_update_on_missing_record_returns_false(): void
    {
        $this->assertFalse($this->repo->update(777, ['override_price'=>1]));
    }

    /* ----------------------------- delete() ----------------------------- */

    public function test_delete_unused_record_succeeds(): void
    {
        $id = $this->repo->create(['item_id'=>1,'item_type'=>ItemType::PRODUCT]);
        $this->assertTrue($this->repo->delete($id, true));
        $this->assertNull($this->repo->get($id));
    }
}
