<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use PostTypeRegistry;
use WP_UnitTestCase;

/**
 * Integration tests for PostTypeRegistry.
 * 
 * Tests post type registration and validation functionality in WordPress environment.
 */
class PostTypeRegistryTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Initialize PostTypeRegistry
        PostTypeRegistry::register_all();
        
        // Reset global state
        $_GET = [];
        $_POST = [];
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $_GET = [];
        $_POST = [];
    }

    /* ---------------------------------------------------------------------
     *  Post Type Validation Tests
     * -------------------------------------------------------------------*/

    public function test_get_registered_post_types_returns_expected_types(): void
    {
        $postTypes = PostTypeRegistry::getRegisteredPostTypes();

        $this->assertIsArray($postTypes);
        $this->assertArrayHasKey('customer', $postTypes);
        $this->assertArrayHasKey('product', $postTypes);
        $this->assertArrayHasKey('ingredient', $postTypes);
        $this->assertArrayHasKey('store_branch', $postTypes);
        $this->assertArrayHasKey('product_group', $postTypes);
        $this->assertArrayHasKey('group_item', $postTypes);
    }

    public function test_is_squidly_post_type_correctly_identifies_managed_types(): void
    {
        // Test managed post types
        $this->assertTrue(PostTypeRegistry::isSquidlyPostType('customer'));
        $this->assertTrue(PostTypeRegistry::isSquidlyPostType('product'));
        $this->assertTrue(PostTypeRegistry::isSquidlyPostType('ingredient'));
        $this->assertTrue(PostTypeRegistry::isSquidlyPostType('store_branch'));
        $this->assertTrue(PostTypeRegistry::isSquidlyPostType('product_group'));
        $this->assertTrue(PostTypeRegistry::isSquidlyPostType('group_item'));

        // Test unmanaged post types
        $this->assertFalse(PostTypeRegistry::isSquidlyPostType('post'));
        $this->assertFalse(PostTypeRegistry::isSquidlyPostType('page'));
        $this->assertFalse(PostTypeRegistry::isSquidlyPostType('attachment'));
        $this->assertFalse(PostTypeRegistry::isSquidlyPostType('non_existent'));
    }

    public function test_get_post_type_class_returns_correct_class(): void
    {
        $this->assertEquals('CustomerPostType', PostTypeRegistry::getPostTypeClass('customer'));
        $this->assertEquals('ProductPostType', PostTypeRegistry::getPostTypeClass('product'));
        $this->assertEquals('IngredientPostType', PostTypeRegistry::getPostTypeClass('ingredient'));
        
        $this->assertNull(PostTypeRegistry::getPostTypeClass('non_existent'));
        $this->assertNull(PostTypeRegistry::getPostTypeClass('post'));
    }


}