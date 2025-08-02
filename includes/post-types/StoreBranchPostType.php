<?php
declare(strict_types=1);


class StoreBranchPostType implements PostTypeInterface {
    public const POST_TYPE = 'store_branch';
 
    public static function register(): void {
        add_action('init', function () {
            register_post_type(self::POST_TYPE, [
                'labels' => [
                    'name' => 'Branches',
                    'singular_name' => 'Branch',
                    'add_new_item' => 'Add New Branch',
                    'edit_item' => 'Edit Branch',
                    'new_item' => 'New Branch',
                    'view_item' => 'View Branch',
                    'search_items' => 'Search Branches',
                    'not_found' => 'No branches found',
                    'not_found_in_trash' => 'No branches found in trash',
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
                'menu_icon' => 'dashicons-store',
            ]);
        });
    }
}
