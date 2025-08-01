<?php

class ProductPostType {
    public const POST_TYPE = 'product';

    public static function register(): void {
        add_action('init', function () {
            register_post_type(self::POST_TYPE, [
                'labels' => [
                    'name' => 'Products',
                    'singular_name' => 'Product',
                    'add_new_item' => 'Add New Product',
                    'edit_item' => 'Edit Product',
                    'new_item' => 'New Product',
                    'view_item' => 'View Product',
                    'search_items' => 'Search Products',
                    'not_found' => 'No products found',
                    'not_found_in_trash' => 'No products found in trash',
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'supports' => ['title', 'custom-fields'],
                'hierarchical' => false,
                'has_archive' => false,
                'rewrite' => false,
                'menu_icon' => 'dashicons-cart',
            ]);
        });
    }
}
