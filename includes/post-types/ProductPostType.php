<?php
declare(strict_types=1);

/**
 * Product Post Type
 * includes/post-types/ProductPostType.php
 */
class ProductPostType implements PostTypeInterface
{
    public const POST_TYPE = 'product';

    public static function getPostType(): string
    {
        return self::POST_TYPE;
    }

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, self::getArgs());
    }

    public static function init(): void
    {
        add_action('init', [self::class, 'register']);
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post', [self::class, 'saveCustomFields']);
    }

    public static function getLabels(): array
    {
        return [
            'name' => 'Products',
            'singular_name' => 'Product',
            'add_new' => 'Add New Product',
            'add_new_item' => 'Add New Product',
            'edit_item' => 'Edit Product',
            'new_item' => 'New Product',
            'view_item' => 'View Product',
            'view_items' => 'View Products',
            'search_items' => 'Search Products',
            'not_found' => 'No products found',
            'not_found_in_trash' => 'No products found in trash',
            'all_items' => 'All Products',
            'archives' => 'Product Archives',
            'attributes' => 'Product Attributes',
            'insert_into_item' => 'Insert into product',
            'uploaded_to_this_item' => 'Uploaded to this product',
            'filter_items_list' => 'Filter products list',
            'items_list_navigation' => 'Products list navigation',
            'items_list' => 'Products list',
        ];
    }

    public static function getArgs(): array
    {
        return [
            'labels' => self::getLabels(),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'squidly-restaurant',
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
            'supports' => self::getSupports(),
            'show_in_rest' => false,
            'delete_with_user' => false,
            'menu_icon' => 'dashicons-cart',
        ];
    }

    public static function getSupports(): array
    {
        return ['title', 'editor', 'custom-fields'];
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'product_pricing',
            'Pricing',
            [self::class, 'pricingMetaBox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'product_details',
            'Product Details',
            [self::class, 'detailsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'product_groups',
            'Product Groups',
            [self::class, 'groupsMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'product_availability',
            'Availability',
            [self::class, 'availabilityMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function pricingMetaBox($post): void
    {
        wp_nonce_field('product_meta_nonce', 'product_meta_nonce');
        
        $price = get_post_meta($post->ID, '_regular_price', true);
        $sale_price = get_post_meta($post->ID, '_sale_price', true);

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="product_price">Regular Price (₪)</label></th>';
        echo '<td><input type="number" step="0.01" min="0" id="product_price" name="product_price" value="' . esc_attr($price) . '" class="small-text" required /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="product_sale_price">Sale Price (₪)</label></th>';
        echo '<td>';
        echo '<input type="number" step="0.01" min="0" id="product_sale_price" name="product_sale_price" value="' . esc_attr($sale_price) . '" class="small-text" />';
        echo '<p class="description">Leave empty if not on sale</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        if ($sale_price && $price) {
            $discount_percent = round((($price - $sale_price) / $price) * 100, 1);
            echo '<p><strong>Discount:</strong> ' . $discount_percent . '%</p>';
        }
    }

    public static function detailsMetaBox($post): void
    {
        $category = get_post_meta($post->ID, '_category', true);
        $tags = get_post_meta($post->ID, '_tags', true) ?: [];

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="product_category">Category</label></th>';
        echo '<td>';
        echo '<input type="text" id="product_category" name="product_category" value="' . esc_attr($category) . '" class="regular-text" />';
        echo '<p class="description">e.g., "Main Dishes", "Appetizers", "Desserts"</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="product_tags">Tags</label></th>';
        echo '<td>';
        echo '<input type="text" id="product_tags" name="product_tags" value="' . esc_attr(implode(', ', $tags)) . '" class="regular-text" />';
        echo '<p class="description">Separate tags with commas (e.g., "spicy, vegetarian, gluten-free")</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function groupsMetaBox($post): void
    {
        $product_group_ids = get_post_meta($post->ID, '_product_group_ids', true) ?: [];
        
        // Get all available product groups
        $groups = get_posts([
            'post_type' => 'product_group',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        echo '<p><strong>Select which product groups this product includes:</strong></p>';
        echo '<div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">';
        
        if (empty($groups)) {
            echo '<p><em>No product groups available.</em></p>';
            echo '<p><a href="' . admin_url('post-new.php?post_type=product_group') . '" class="button">Create Product Group</a></p>';
        } else {
            foreach ($groups as $group) {
                $checked = in_array($group->ID, $product_group_ids) ? 'checked' : '';
                $group_type = get_post_meta($group->ID, '_type', true);
                
                echo '<label style="display: block; margin-bottom: 8px; padding: 5px; background: white; border: 1px solid #ddd;">';
                echo '<input type="checkbox" name="product_groups[]" value="' . $group->ID . '" ' . $checked . ' /> ';
                echo '<strong>' . esc_html($group->post_title) . '</strong>';
                echo ' <small style="color: #666;">(' . ucfirst($group_type) . ' Group)</small>';
                echo '</label>';
            }
        }
        
        echo '</div>';
        echo '<p class="description">Product groups define customizable options like toppings, sides, or ingredients that customers can modify.</p>';
    }

    public static function availabilityMetaBox($post): void
    {
        $is_available = get_post_meta($post->ID, '_is_available', true);
        $is_featured = get_post_meta($post->ID, '_is_featured', true);
        $sort_order = get_post_meta($post->ID, '_sort_order', true) ?: 0;

        // Default to available if not set
        if ($is_available === '') {
            $is_available = true;
        }

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th>Status</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="product_is_available" value="1" ' . checked($is_available, true, false) . ' /> Available for Order</label><br>';
        echo '<label><input type="checkbox" name="product_is_featured" value="1" ' . checked($is_featured, true, false) . ' /> Featured Product</label>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="product_sort_order">Sort Order</label></th>';
        echo '<td>';
        echo '<input type="number" id="product_sort_order" name="product_sort_order" value="' . esc_attr($sort_order) . '" class="small-text" />';
        echo '<p class="description">Lower numbers appear first in listings</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function saveCustomFields(int $post_id): void
    {
        // Verify nonce
        if (!isset($_POST['product_meta_nonce']) || 
            !wp_verify_nonce($_POST['product_meta_nonce'], 'product_meta_nonce')) {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        // Save pricing
        if (isset($_POST['product_price'])) {
            $price = floatval($_POST['product_price']);
            update_post_meta($post_id, '_regular_price', $price);
            
            // Set _price to sale price if exists, otherwise regular price (WooCommerce compatibility)
            $sale_price = isset($_POST['product_sale_price']) ? floatval($_POST['product_sale_price']) : 0;
            if ($sale_price > 0) {
                update_post_meta($post_id, '_price', $sale_price);
            } else {
                update_post_meta($post_id, '_price', $price);
            }
        }
        
        if (isset($_POST['product_sale_price'])) {
            $sale_price = floatval($_POST['product_sale_price']);
            if ($sale_price > 0) {
                update_post_meta($post_id, '_sale_price', $sale_price);
            } else {
                delete_post_meta($post_id, '_sale_price');
            }
        }

        // Save details
        if (isset($_POST['product_category'])) {
            update_post_meta($post_id, '_category', sanitize_text_field($_POST['product_category']));
        }
        
        if (isset($_POST['product_tags'])) {
            $tags = array_map('trim', explode(',', $_POST['product_tags']));
            $tags = array_filter($tags); // Remove empty tags
            $tags = array_map('sanitize_text_field', $tags);
            update_post_meta($post_id, '_tags', $tags);
        }

        // Save product groups
        $groups = isset($_POST['product_groups']) ? array_map('intval', $_POST['product_groups']) : [];
        update_post_meta($post_id, '_product_group_ids', $groups);

        // Save availability
        update_post_meta($post_id, '_is_available', isset($_POST['product_is_available']));
        update_post_meta($post_id, '_is_featured', isset($_POST['product_is_featured']));
        
        if (isset($_POST['product_sort_order'])) {
            update_post_meta($post_id, '_sort_order', intval($_POST['product_sort_order']));
        }
    }
}