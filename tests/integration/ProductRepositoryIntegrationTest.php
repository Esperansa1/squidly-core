<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductRepository;
use WP_UnitTestCase;

/**
 * Integration tests for price meta-handling and duplicate-name behaviour.
 *
 * @covers \ProductRepository
 */
class ProductRepositoryIntegrationTest extends WP_UnitTestCase
{
    private ProductRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        // Needed by ProductRepository::create()
        register_taxonomy('product_cat', 'product');
        register_taxonomy('product_tag', 'product');

        $this->repo = new ProductRepository();
    }

    /* ---------------------------------------------------------------------
     * 1) discounted_price meta semantics
     * -------------------------------------------------------------------*/
    public function test_create_with_discounted_price_stores_correct_meta(): void
    {
        $id = $this->repo->create([
            'name'             => 'Sale Burger',
            'price'            => 20.0,    // regular
            'discounted_price' => 15.0,    // sale (lower)
        ]);

        /* meta checks (WooCommerce style) */
        $this->assertSame('20', get_post_meta($id, '_regular_price', true));
        $this->assertSame('15', get_post_meta($id, '_sale_price', true));
        $this->assertSame('15', get_post_meta($id, '_price', true));  // active price

        /* DTO round-trip */
        $product = $this->repo->get($id);
        $this->assertSame(20.0, $product->price);
        $this->assertSame(15.0, $product->discounted_price);
    }

    /* ---------------------------------------------------------------------
     * 2) duplicate names (case-insensitive) create distinct posts
     * -------------------------------------------------------------------*/
    public function test_create_duplicate_name_creates_unique_products(): void
    {
        $id1 = $this->repo->create(['name' => 'Burger', 'price' => 10.0]);
        $id2 = $this->repo->create(['name' => 'burger', 'price' => 12.0]);

        /* IDs must differ */
        $this->assertNotSame($id1, $id2);

        /* round-trip confirms two separate objects */
        $p1 = $this->repo->get($id1);
        $p2 = $this->repo->get($id2);

        $this->assertSame('Burger', $p1->name);
        $this->assertSame('burger', $p2->name);
        $this->assertSame(10.0, $p1->price);
        $this->assertSame(12.0, $p2->price);

        /* slugs (post_name) are unique */
        $slug1 = get_post($id1)->post_name;
        $slug2 = get_post($id2)->post_name;
        $this->assertNotSame($slug1, $slug2);
    }
}
