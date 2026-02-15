<?php

use PHPUnit\Framework\TestCase;

/**
 * Test system product filtering in ProductRepository
 */
class ProductRepositorySystemProductTest extends TestCase
{
    private $repo;
    private $regular_product_id;
    private $system_product_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ProductRepository();
    }

    protected function tearDown(): void
    {
        // Clean up created products
        if ($this->regular_product_id) {
            wp_delete_post($this->regular_product_id, true);
        }
        if ($this->system_product_id) {
            wp_delete_post($this->system_product_id, true);
        }
        parent::tearDown();
    }

    public function testGetAllExcludesSystemProductsByDefault()
    {
        // Arrange: Create regular product and system product
        $this->regular_product_id = $this->createRegularProduct();
        $this->system_product_id = $this->createSystemProduct();

        // Act: Fetch all products
        $products = $this->repo->getAll();
        $product_ids = array_column(array_map(fn($p) => $p->toArray(), $products), 'id');

        // Assert: System product NOT in results
        $this->assertContains($this->regular_product_id, $product_ids, 'Regular product should be included');
        $this->assertNotContains($this->system_product_id, $product_ids, 'System product should be excluded');
    }

    public function testGetAllIncludesSystemProductsWhenOptedIn()
    {
        // Arrange
        $this->regular_product_id = $this->createRegularProduct();
        $this->system_product_id = $this->createSystemProduct();

        // Act: Fetch with include_system_products = true
        $products = $this->repo->getAll([], true);
        $product_ids = array_column(array_map(fn($p) => $p->toArray(), $products), 'id');

        // Assert: Both products in results
        $this->assertContains($this->regular_product_id, $product_ids, 'Regular product should be included');
        $this->assertContains($this->system_product_id, $product_ids, 'System product should be included when opted in');
    }

    public function testFindByExcludesSystemProductsByDefault()
    {
        // Arrange
        $this->regular_product_id = $this->createRegularProduct();
        $this->system_product_id = $this->createSystemProduct();

        // Act: Search by partial name
        $products = $this->repo->findBy(['name' => 'Product']);
        $product_ids = array_column(array_map(fn($p) => $p->toArray(), $products), 'id');

        // Assert: System product NOT in results
        $this->assertContains($this->regular_product_id, $product_ids);
        $this->assertNotContains($this->system_product_id, $product_ids);
    }

    public function testFindByIncludesSystemProductsWhenOptedIn()
    {
        // Arrange
        $this->regular_product_id = $this->createRegularProduct();
        $this->system_product_id = $this->createSystemProduct();

        // Act: Search with include_system_products = true
        $products = $this->repo->findBy(['name' => 'Product'], null, 0, true);
        $product_ids = array_column(array_map(fn($p) => $p->toArray(), $products), 'id');

        // Assert: Both products in results
        $this->assertContains($this->regular_product_id, $product_ids);
        $this->assertContains($this->system_product_id, $product_ids);
    }

    private function createRegularProduct(): int
    {
        $post_id = wp_insert_post([
            'post_title'   => 'Regular Product',
            'post_content' => 'A regular product for testing',
            'post_type'    => ProductPostType::POST_TYPE,
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create test product: ' . $post_id->get_error_message());
        }

        // Set required meta
        update_post_meta($post_id, '_regular_price', 10.00);
        update_post_meta($post_id, '_price', 10.00);

        return $post_id;
    }

    private function createSystemProduct(): int
    {
        $post_id = wp_insert_post([
            'post_title'   => '[System] Test Product',
            'post_content' => 'A system product for testing',
            'post_type'    => ProductPostType::POST_TYPE,
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create test system product: ' . $post_id->get_error_message());
        }

        // Set required meta
        update_post_meta($post_id, '_regular_price', 0.00);
        update_post_meta($post_id, '_price', 0.00);
        update_post_meta($post_id, '_squidly_system_product', 'yes');
        update_post_meta($post_id, '_squidly_product_type', 'payment_processor');

        return $post_id;
    }
}
