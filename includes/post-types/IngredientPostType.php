<?php
declare(strict_types=1);


class IngredientPostType implements PostTypeInterface {
    public const POST_TYPE = 'ingredient';
    
    public static function register(): void {
        add_action('init', function () {
            register_post_type(self::POST_TYPE, [
                'labels' => [
                    'name' => 'Ingredients',
                    'singular_name' => 'Ingredient',
                    'add_new_item' => 'Add New Ingredient',
                    'edit_item' => 'Edit Ingredient',
                    'new_item' => 'New Ingredient',
                    'view_item' => 'View Ingredient',
                    'search_items' => 'Search Ingredients',
                    'not_found' => 'No ingredients found',
                    'not_found_in_trash' => 'No ingredients found in trash',
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
                'menu_icon' => 'dashicons-carrot',
            ]);
        });
    }
}
