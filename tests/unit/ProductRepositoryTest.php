<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductRepository;
use InvalidArgumentException;
use WP_UnitTestCase;

/** @covers \ProductRepository */
class ProductRepositoryTest extends WP_UnitTestCase
{
    private ProductRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        $this->repo = new ProductRepository();

        // Register minimal taxonomies so wp_set_object_terms works.
        register_taxonomy( 'product_cat', 'product' );
        register_taxonomy( 'product_tag', 'product' );
    }

    /* ---------- create() positive ---------- */

    public function test_create_and_get_full_data(): void
    {
        $id = $this->repo->create([
            'name'             => 'Salt',
            'price'            => 1.1,
            'discounted_price' => 0.9,
            'category'         => 'Spices',
            'tags'             => ['kitchen'],
        ]);

        $p = $this->repo->get($id);

        // Positive assertions
        $this->assertSame('Salt',  $p->name);
        $this->assertSame(1.1,     $p->price);
        $this->assertSame(0.9,     $p->discounted_price);
        $this->assertSame('Spices',$p->category);
        $this->assertContains('kitchen', $p->tags);

        // Negative counterpart: ensure category is not wrong
        $this->assertNotSame('Herbs', $p->category);
    }

    /* ---------- create() negatives ---------- */

    public function test_create_missing_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create(['price'=>2.0]);
    }

    public function test_create_missing_price_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create(['name'=>'NoPrice']);
    }

    /* ---------- get() negatives ---------- */

    public function test_get_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->repo->get(999999));
    }

    public function test_get_returns_null_for_wrong_post_type(): void
    {
        $postId = $this->factory()->post->create(['post_title'=>'not product']);
        $this->assertNull($this->repo->get($postId));
    }

    /* ---------- getAll() ---------- */

    public function test_getAll_returns_only_published_and_correct_data(): void
    {
        $idA = $this->repo->create(['name'=>'A','price'=>0.5]);
        $idB = $this->repo->create(['name'=>'B','price'=>0.7]);

        // create draft (should be excluded)
        wp_insert_post([
            'post_title'=>'draft',
            'post_type' =>'product',
            'post_status'=>'draft',
        ]);

        $items = $this->repo->getAll();
        $this->assertCount(2, $items);

        /** index by id for deterministic asserts */
        $map = [];
        foreach ($items as $item) {
            $map[$item->id] = $item;
        }

        /* Positive checks */
        $names = array_values(array_map(fn($i) => $i->name, $map));
        sort($names);                               // ← added
        $this->assertSame(['A', 'B'], $names);

        $this->assertSame(0.5, $map[$idA]->price);
        $this->assertSame(0.7, $map[$idB]->price);

        /* Negative */
        $this->assertNotContains('draft', $names);
    }
}
