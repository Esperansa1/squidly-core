<?php

class ProductGroupPostType {
    public const POST_TYPE = 'product_group';

    public static function register(): void {
        add_action('init', function () {
            register_post_type(self::POST_TYPE, [
                'labels' => [
                    'name' => 'Product Groups',
                    'singular_name' => 'Product Group',
                    'add_new_item' => 'Add New Group',
                    'edit_item' => 'Edit Group',
                    'new_item' => 'New Group',
                    'view_item' => 'View Group',
                    'search_items' => 'Search Groups',
                    'not_found' => 'No groups found',
                    'not_found_in_trash' => 'No groups found in trash',
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
                'menu_icon' => 'dashicons-screenoptions',
            ]);
        });
    }
}
