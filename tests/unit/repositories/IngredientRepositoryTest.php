<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use IngredientRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ResourceInUseException;

/**
 * Pure-PHP unit tests for IngredientRepository.
 * ───────────────────────────────────────────────
 *  • No external WP-Mock libraries
 *  • We rely on the same super-light stubs the
 *    GroupItemRepositoryTest already uses.
 *
 * @covers IngredientRepository
 */
class IngredientRepositoryTest extends TestCase
{

    private IngredientRepository $repo;

    /* ------------------------------------------------------------
     *  Mini-stub helpers (identical to ones you already had)
     * ---------------------------------------------------------- */
    protected function setUp(): void
    {
        $this->repo = new IngredientRepository();
    }

    /* ============================================================
     *  1. create()
     * ========================================================== */
    public function test_create_valid_data_returns_id_and_saves_meta(): void
    {
        $id = $this->repo->create(['name'=>'Salt','price'=>1.2]);

        // post exists & meta stored
        $this->assertSame('ingredient', get_post_type($id));
        $this->assertEquals(1.2, get_post_meta($id,'_price',true));  // == not ===
    }

    /** @dataProvider badCreateProvider */
    public function test_create_invalid_payload_throws(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function badCreateProvider(): array
    {
        return [
            'missing name' => [['price'=>1]],
            'empty name'   => [['name'=>'','price'=>1]],
        ];
    }

    /* ============================================================
     *  2. get()
     * ========================================================== */
    public function test_get_round_trip_returns_correct_object(): void
    {
        $id = $this->repo->create(['name'=>'Sugar','price'=>2.5]);
        $ing = $this->repo->get($id);

        $this->assertSame('Sugar', $ing->name);
        $this->assertSame(2.5,     $ing->price);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repo->get(999));
    }

    /* ============================================================
     *  3. update()
     * ========================================================== */
    public function test_update_changes_stored_meta(): void
    {
        $id = $this->repo->create(['name'=>'Pepper','price'=>1]);
        $this->repo->update($id,['price'=>3.3]);

        $this->assertEquals(3.3, get_post_meta($id,'_price',true));
    }

    public function test_update_invalid_inputs_throw(): void
    {
        $id = $this->repo->create(['name'=>'Oregano','price'=>1]);
        $this->expectException(InvalidArgumentException::class);
        $this->repo->update($id,['price'=>-7]);
    }

    /* ============================================================
     *  4. delete()
     * ========================================================== */
    public function test_delete_unused_record_succeeds(): void
    {
        $id = $this->repo->create(['name'=>'Basil','price'=>1]);
        $this->assertTrue($this->repo->delete($id,true));
        $this->assertNull($this->repo->get($id));
    }


}
