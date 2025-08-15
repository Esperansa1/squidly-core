<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Repositories;

use ProductRepository;
use InvalidArgumentException;
use ResourceInUseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level tests for ProductRepository.
 *
 * Relies on the same “tiny WP” stubs already used in the other unit tests
 * (wp_insert_post(), update_post_meta() …). No WordPress bootstrap needed.
 */
class ProductRepositoryTest extends TestCase
{
    private ProductRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new ProductRepository();
    }

    /* ---------------------------------------------------------------------
     *  create()
     * -------------------------------------------------------------------*/
    public function test_create_valid_payload_returns_id_and_saves_meta(): void
    {
        $id = $this->repo->create([
            'name'  => 'Cola',
            'price' => 8.0,
            'description' => 'Test drink',
            'category'    => 'Drinks',
            'tags'        => ['fizzy', 'cold'],
        ]);

        

        $this->assertIsInt($id);
        $this->assertEquals(8.0, get_post_meta($id, '_regular_price', true));
        $this->assertEquals([],   get_post_meta($id, '_product_group_ids', true));
    }

    /** @dataProvider provideBadCreateData */
    public function test_create_invalid_payload_throws(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create($payload);
    }

    public function provideBadCreateData(): array
    {
        return [
            'missing name'        => [['price'=>5]],
            'negative price'      => [['name'=>'X','price'=>-1]],
            'price missing'       => [['name'=>'X']],
        ];
    }

    /* ---------------------------------------------------------------------
     *  get()
     * -------------------------------------------------------------------*/
    public function test_get_round_trip(): void
    {
        $id = $this->repo->create(['name'=>'Water','price'=>3]);
        $p  = $this->repo->get($id);

        $this->assertSame('Water', $p->name);
        $this->assertSame(3.0,     $p->price);
    }

    public function test_get_returns_null_when_missing_or_wrong_type(): void
    {
        // non-existent
        $this->assertNull($this->repo->get(99999));

        // wrong post-type (stubbed plain post)
        $pid = wp_insert_post(['post_title'=>'dummy','post_type'=>'post','post_status'=>'publish']);
        $this->assertNull($this->repo->get($pid));
    }

    /* ---------------------------------------------------------------------
     *  update()
     * -------------------------------------------------------------------*/
    public function test_update_changes_regular_and_sale_price(): void
    {
        $id = $this->repo->create(['name'=>'Beer','price'=>10]);
        $this->repo->update($id, ['price'=>12.5,'discounted_price'=>9.9]);

        $this->assertEquals(12.5, get_post_meta($id, '_regular_price', true));
        $this->assertEquals(9.9,  get_post_meta($id, '_sale_price',    true));
    }

    public function test_update_on_missing_product_returns_false(): void
    {
        $this->assertFalse($this->repo->update(123456, ['price'=>1]));
    }

    /* ---------------------------------------------------------------------
     *  delete()
     * -------------------------------------------------------------------*/
    public function test_delete_unused_product_succeeds(): void
    {
        $id = $this->repo->create(['name'=>'Juice','price'=>6]);
        $this->assertTrue($this->repo->delete($id, true));
        $this->assertFalse(get_post_status($id));      // row is gone
    }

    public function test_delete_product_with_dependants_throws_exception(): void
    {
        // P → GI → PG  (establish dependency chain)
        $pid  = $this->repo->create(['name'=>'Sauce','price'=>4]);
        $giId = (new \GroupItemRepository())->create([
            'item_id'=>$pid,'item_type'=>'product'
        ]);
        (new \ProductGroupRepository())->create([
            'name'=>'Sauce Group',
            'type'=>'product',
            'group_item_ids'=>[$giId],
        ]);

        $this->expectException(ResourceInUseException::class);
        $this->repo->delete($pid, true);
    }

    /* ---------------------------------------------------------------------
     *  exists() Tests (Simple Unit Tests)
     * -------------------------------------------------------------------*/

    public function test_exists_returns_true_for_valid_product(): void
    {
        $productId = $this->repo->create(['name' => 'Test Product', 'price' => 10.0]);
        
        $this->assertTrue($this->repo->exists($productId));
    }

    public function test_exists_returns_false_for_invalid_id(): void
    {
        $this->assertFalse($this->repo->exists(99999));
        $this->assertFalse($this->repo->exists(0));
        $this->assertFalse($this->repo->exists(-1));
    }

    public function test_exists_returns_false_for_wrong_post_type(): void
    {
        // Create a regular post (not product)
        $postId = wp_insert_post([
            'post_title' => 'Regular Post',
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);
        
        $this->assertFalse($this->repo->exists($postId));
    }
}
