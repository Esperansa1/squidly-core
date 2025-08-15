<?php
/**
 * Updated Post Type Registry
 * includes/PostTypeRegistry.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../shared/interfaces/PostTypeInterface.php';
require_once __DIR__ . '/../shared/abstracts/BasePostType.php';
require_once __DIR__ . '/../domains/stores/post-types/StoreBranchPostType.php';
require_once __DIR__ . '/../domains/products/post-types/ProductPostType.php';
require_once __DIR__ . '/../domains/products/post-types/ProductGroupPostType.php';
require_once __DIR__ . '/../domains/products/post-types/IngredientPostType.php';
require_once __DIR__ . '/../domains/products/post-types/GroupItemPostType.php';
require_once __DIR__ . '/../domains/customers/post-types/CustomerPostType.php';
require_once __DIR__ . '/../domains/orders/post-types/OrderPostType.php';

class PostTypeRegistry
{
    /**
     * Register all post types for the restaurant system
     */
    public static function register_all(): void
    {
        // Initialize all post types
        StoreBranchPostType::init();
        ProductPostType::init();
        ProductGroupPostType::init();
        IngredientPostType::init();
        GroupItemPostType::init();
        CustomerPostType::init();
        OrderPostType::init();
        
        // Add AJAX handlers for dynamic loading
        add_action('wp_ajax_get_items_by_type', [self::class, 'ajaxGetItemsByType']);
    }

    /**
     * AJAX handler for getting items by type (for Group Item post type)
     */
    public static function ajaxGetItemsByType(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $type = sanitize_text_field($_GET['type'] ?? '');
        
        if (!in_array($type, ['product', 'ingredient'], true)) {
            wp_send_json_error('Invalid type');
        }

        $items = get_posts([
            'post_type' => $type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $response = [];
        foreach ($items as $item) {
            $response[] = [
                'id' => $item->ID,
                'title' => $item->post_title
            ];
        }

        wp_send_json_success($response);
    }

    /**
     * Get all registered post types for the restaurant system
     */
    public static function getRegisteredPostTypes(): array
    {
        return [
            StoreBranchPostType::getPostType() => StoreBranchPostType::class,
            ProductPostType::getPostType() => ProductPostType::class,
            ProductGroupPostType::getPostType() => ProductGroupPostType::class,
            IngredientPostType::getPostType() => IngredientPostType::class,
            GroupItemPostType::getPostType() => GroupItemPostType::class,
            CustomerPostType::getPostType() => CustomerPostType::class,
            OrderPostType::getPostType() => OrderPostType::class,
        ];
    }

    /**
     * Check if a post type is managed by our system
     */
    public static function isSquidlyPostType(string $post_type): bool
    {
        return array_key_exists($post_type, self::getRegisteredPostTypes());
    }

    /**
     * Get post type class by post type slug
     */
    public static function getPostTypeClass(string $post_type): ?string
    {
        $types = self::getRegisteredPostTypes();
        return $types[$post_type] ?? null;
    }
}
