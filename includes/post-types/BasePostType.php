<?php
/**
 * Base Post Type Class
 * includes/post-types/BasePostType.php
 */
declare(strict_types=1);

abstract class BasePostType implements PostTypeInterface
{
    /**
     * Initialize hooks for this post type
     */
    public static function init(): void
    {
        add_action('init', [static::class, 'register']);
        add_action('add_meta_boxes', [static::class, 'addMetaBoxes']);
        add_action('save_post', [static::class, 'saveCustomFields']);
    }

    /**
     * Register the post type
     */
    public static function register(): void
    {
        register_post_type(static::getPostType(), static::getArgs());
    }

    /**
     * Default post type arguments - can be overridden by child classes
     */
    public static function getArgs(): array
    {
        return [
            'labels' => static::getLabels(),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => AdminMenuManager::MENU_SLUG,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => static::getSupports(),
            'show_in_rest' => false,
            'delete_with_user' => false,
        ];
    }

    /**
     * Default supports - can be overridden
     */
    public static function getSupports(): array
    {
        return ['title', 'custom-fields'];
    }

    /**
     * Verify nonce and permissions for saving
     */
    protected static function canSavePost(int $post_id, string $nonce_field): bool
    {
        // Verify nonce
        if (!isset($_POST[$nonce_field]) || 
            !wp_verify_nonce($_POST[$nonce_field], $nonce_field)) {
            return false;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        // Check post type
        if (get_post_type($post_id) !== static::getPostType()) {
            return false;
        }

        return true;
    }

    /**
     * Helper method to create a meta box
     */
    protected static function addMetaBox(string $id, string $title, callable $callback, string $context = 'normal', string $priority = 'default'): void
    {
        add_meta_box(
            $id,
            $title,
            $callback,
            static::getPostType(),
            $context,
            $priority
        );
    }
}
