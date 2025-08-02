<?php
declare(strict_types=1);


class GroupItemPostType implements PostTypeInterface {
    public const POST_TYPE = 'group_item';

    public static function register(): void {
        add_action('init', function () {
            register_post_type(self::POST_TYPE, [
                'labels' => [
                    'name' => 'Group Items',
                    'singular_name' => 'Group Item',
                    'add_new_item' => 'Add New Group Item',
                    'edit_item' => 'Edit Group Item',
                    'new_item' => 'New Group Item',
                    'view_item' => 'View Group Item',
                    'search_items' => 'Search Group Items',
                    'not_found' => 'No group items found',
                    'not_found_in_trash' => 'No group items found in trash',
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
                'menu_icon' => 'dashicons-tag',
            ]);
        });
    }
}
