<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use ProductRepository;
use ProductGroupRepository;
use GroupItemRepository;
use ItemType;
use WP_UnitTestCase;
use ResourceInUseException;
use InvalidArgumentException;

/**
 * WordPress-backed integration tests for ProductRepository.
 *
 * Run with:  WP_INTEGRATION=1 vendor/bin/phpunit --testsuite integration
 */
class ProductRepositoryIntegrationTest extends WP_UnitTestCase
{
    private ProductRepository $repo;

    public function set_up(): void
    {
        parent::set_up();

        // minimal taxonomies so wp_set_object_terms() works
        register_taxonomy('product_cat', 'product');
        register_taxonomy('product_tag', 'product');

        $this->repo = new ProductRepository();
    }

    /* ------------------------------------------------------------------ */
    public function test_full_create_get_update_delete_cycle(): void
    {
        /* create */
        $id = $this->repo->create([
            'name'        => 'Hamburger',
            'price'       => 35.0,
            'discounted_price'=> 29.0,
            'description' => 'Integration burger',
            'category'    => 'Burgers',
            'tags'        => ['signature','grilled'],
        ]);
        $this->assertSame('product', get_post_type($id));

        /* get */
        $p = $this->repo->get($id);
        $this->assertSame(29.0, $p->discounted_price);

        /* update */
        $this->repo->update($id, ['price'=>38.0,'discounted_price'=>null]);
        $p2 = $this->repo->get($id);
        $this->assertSame(38.0, $p2->price);
        $this->assertNull($p2->discounted_price);

        /* delete – no dependants yet */
        $this->assertTrue($this->repo->delete($id, true));
        $this->assertFalse(get_post_status($id));
    }

    public function test_getAll_returns_only_published_products(): void
    {
        // create 2 published + 1 draft
        $a = $this->repo->create(['name'=>'A','price'=>1]);
        $b = $this->repo->create(['name'=>'B','price'=>2]);
        $draft = wp_insert_post([
            'post_title'=>'Draft P',
            'post_type'=>'product',
            'post_status'=>'draft',
        ]);

        $all = $this->repo->getAll();
        $names = array_map(fn($p)=>$p->name, $all);
        sort($names);

        $this->assertSame(['A','B'], $names);
    }

    public function test_delete_with_dependants_throws_exception(): void
    {
        //   P0  <- we try to delete
        //   PG  (product group) contains GI referencing P0
        $p0   = $this->repo->create(['name'=>'Mustard','price'=>1]);

        $giId = (new GroupItemRepository())->create([
            'item_id'=>$p0,
            'item_type'=>ItemType::PRODUCT,
        ]);

        (new ProductGroupRepository())->create([
            'name'=>'Condiments',
            'type'=>ItemType::PRODUCT,
            'group_item_ids'=>[$giId],
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->repo->delete($p0, true);
    }

    /** @dataProvider provideInvalidCreate */
    public function test_create_invalid_payload_throws(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function provideInvalidCreate(): array
    {
        return [
            'missing name'   => [['price'=>5]],
            'negative price' => [['name'=>'Bad','price'=>-10]],
        ];
    }
}
