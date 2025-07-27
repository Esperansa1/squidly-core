<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientRepository;
use InvalidArgumentException;
use WP_Error;
use WP_UnitTestCase;

/** @covers \IngredientRepository */
class IngredientRepositoryTest extends WP_UnitTestCase {

    private IngredientRepository $repo;

    public function set_up(): void {
        parent::set_up();
        $this->repo = new IngredientRepository();
    }

    /* ---------- create() ---------- */

    public function test_create_and_get_roundtrip(): void {
        $id   = $this->repo->create(['name' => 'Salt', 'price' => 1.1]);
        $item = $this->repo->get($id);

        $this->assertNotNull($item);
        $this->assertSame('Salt', $item->name);
        $this->assertSame(1.1,   $item->price);
    }

    public function test_create_without_name_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create(['price' => 2.0]);
    }

    /* ---------- get() ---------- */

    public function test_get_returns_null_for_nonexistent(): void {
        $this->assertNull($this->repo->get(999999));
    }

    public function test_get_returns_null_for_wrong_post_type(): void {
        // Create a normal WP post, then ask the repository for it.
        $id = $this->factory()->post->create(['post_title' => 'Not an ingredient']);
        $this->assertNull($this->repo->get($id));
    }

    /* ---------- getAll() ---------- */

    public function test_getAll_returns_complete_and_only_published(): void
    {
        // Arrange
        $idA = $this->repo->create(['name'=>'A','price'=>0.1]);
        $idB = $this->repo->create(['name'=>'B','price'=>0.2]);
        wp_insert_post([
            'post_title'=>'draft','post_type'=>IngredientRepository::POST_TYPE,'post_status'=>'draft'
        ]);

        // Act
        $items = $this->repo->getAll();

        // Assert count
        $this->assertCount(2, $items);

        // map for deterministic compare
        $map = array_column(
            array_map(fn($i)=>$i->toArray(), $items),
            null, 'id'
        );

        $this->assertEqualsCanonicalizing(
            ['id' => $idA, 'name' => 'A', 'price' => 0.1],
            $map[$idA],
            'Item A mismatch'
        );
        $this->assertEqualsCanonicalizing(
            ['id' => $idB, 'name' => 'B', 'price' => 0.2],
            $map[$idB],
            'Item B mismatch'
        );
        // Assert negatives
        $this->assertArrayNotHasKey('draft', array_column($map,'name'), 'Draft item leaked in results');
    }

}
