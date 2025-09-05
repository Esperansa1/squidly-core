<?php
declare(strict_types=1);

/**
 * Product Group Post Type
 * includes/post-types/ProductGroupPostType.php
 */
class ProductGroupPostType implements PostTypeInterface
{
    public const POST_TYPE = 'product_group';

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
            'name' => 'Product Groups',
            'singular_name' => 'Product Group',
            'add_new' => 'Add New Group',
            'add_new_item' => 'Add New Product Group',
            'edit_item' => 'Edit Product Group',
            'new_item' => 'New Product Group',
            'view_item' => 'View Product Group',
            'view_items' => 'View Product Groups',
            'search_items' => 'Search Product Groups',
            'not_found' => 'No product groups found',
            'not_found_in_trash' => 'No product groups found in trash',
            'all_items' => 'All Product Groups',
            'archives' => 'Product Group Archives',
            'attributes' => 'Product Group Attributes',
            'insert_into_item' => 'Insert into product group',
            'uploaded_to_this_item' => 'Uploaded to this product group',
            'filter_items_list' => 'Filter product groups list',
            'items_list_navigation' => 'Product groups list navigation',
            'items_list' => 'Product groups list',
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
            'menu_icon' => 'dashicons-screenoptions',
        ];
    }

    public static function getSupports(): array
    {
        return ['title', 'custom-fields'];
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'group_settings',
            'Group Settings',
            [self::class, 'settingsMetaBox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'group_items',
            'Group Items',
            [self::class, 'itemsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'group_preview',
            'Group Preview',
            [self::class, 'previewMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function settingsMetaBox($post): void
    {
        wp_nonce_field('product_group_meta_nonce', 'product_group_meta_nonce');
        
        $type = get_post_meta($post->ID, '_type', true);

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="group_type">Group Type</label></th>';
        echo '<td>';
        echo '<select id="group_type" name="group_type" required style="width: 100%;">';
        echo '<option value="">Select Type</option>';
        echo '<option value="product" ' . selected($type, 'product', false) . '>Product Group</option>';
        echo '<option value="ingredient" ' . selected($type, 'ingredient', false) . '>Ingredient Group</option>';
        echo '</select>';
        echo '<p class="description">';
        echo '<strong>Product Group:</strong> Contains other products (e.g., "Sides" with fries, salad)<br>';
        echo '<strong>Ingredient Group:</strong> Contains ingredients (e.g., "Toppings" with cheese, lettuce)';
        echo '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function itemsMetaBox($post): void
    {
        $group_item_ids = get_post_meta($post->ID, '_group_item_ids', true) ?: [];
        
        // Get all available group items
        $group_items = get_posts([
            'post_type' => 'group_item',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        echo '<p><strong>Select which group items to include in this group:</strong></p>';
        
        if (empty($group_items)) {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>No group items available.</strong></p>';
            echo '<p>You need to create Group Items first before you can add them to Product Groups.</p>';
            echo '<p><a href="' . admin_url('post-new.php?post_type=group_item') . '" class="button button-primary">Create Group Item</a></p>';
            echo '</div>';
            return;
        }

        echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">';
        
        // Group items by type for better organization
        $items_by_type = [];
        foreach ($group_items as $item) {
            $item_type = get_post_meta($item->ID, '_item_type', true);
            $items_by_type[$item_type][] = $item;
        }

        foreach (['product', 'ingredient'] as $type) {
            if (!isset($items_by_type[$type])) continue;
            
            echo '<h4 style="margin: 10px 0 5px 0; color: #2271b1;">' . ucfirst($type) . ' Items:</h4>';
            
            foreach ($items_by_type[$type] as $item) {
                $checked = in_array($item->ID, $group_item_ids) ? 'checked' : '';
                
                // Get item details for display
                $item_id = get_post_meta($item->ID, '_item_id', true);
                $item_type = get_post_meta($item->ID, '_item_type', true);
                $override_price = (float) (get_post_meta($item->ID, '_override_price', true) ?: 0);
                
                // Get the referenced item name
                $referenced_item = get_post($item_id);
                $referenced_name = $referenced_item ? $referenced_item->post_title : 'Unknown Item';
                
                echo '<label style="display: block; margin-bottom: 8px; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 3px;">';
                echo '<input type="checkbox" name="group_items[]" value="' . $item->ID . '" ' . $checked . ' /> ';
                echo '<strong>' . esc_html($referenced_name) . '</strong>';
                echo ' <small style="color: #666;">(ID: ' . $item_id . ')</small>';
                
                if ($override_price > 0) {
                    echo '<br><small style="color: #d63638;">Override Price: ₪' . number_format($override_price, 2) . '</small>';
                } else {
                    echo '<br><small style="color: #666;">Uses original price</small>';
                }
                echo '</label>';
            }
        }
        
        echo '</div>';
        
        echo '<p class="description">';
        echo '<strong>Note:</strong> Group Items are wrappers around actual Products or Ingredients. ';
        echo 'They allow you to set custom prices that override the original item prices.';
        echo '</p>';
    }

    public static function previewMetaBox($post): void
    {
        $group_item_ids = get_post_meta($post->ID, '_group_item_ids', true) ?: [];
        $group_type = get_post_meta($post->ID, '_type', true);
        
        if (empty($group_item_ids)) {
            echo '<p><em>No items selected yet. Add items above to see a preview.</em></p>';
            return;
        }

        echo '<p><strong>This group contains:</strong></p>';
        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Item Name</th>';
        echo '<th>Type</th>';
        echo '<th>Original Price</th>';
        echo '<th>Group Price</th>';
        echo '<th>Difference</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $total_original = 0;
        $total_group = 0;

        foreach ($group_item_ids as $group_item_id) {
            $group_item = get_post($group_item_id);
            if (!$group_item) continue;

            $item_id = get_post_meta($group_item_id, '_item_id', true);
            $item_type = get_post_meta($group_item_id, '_item_type', true);
            $override_price = (float) (get_post_meta($group_item_id, '_override_price', true) ?: 0);

            $referenced_item = get_post($item_id);
            if (!$referenced_item) continue;

            // Get original price
            $original_price = 0;
            if ($item_type === 'product') {
                $original_price = (float) (get_post_meta($item_id, '_regular_price', true) ?: 0);
            } elseif ($item_type === 'ingredient') {
                $original_price = (float) (get_post_meta($item_id, '_price', true) ?: 0);
            }

            $group_price = ($override_price > 0) ? $override_price : $original_price;
            $difference = $group_price - $original_price;

            $total_original += $original_price;
            $total_group += $group_price;

            echo '<tr>';
            echo '<td><strong>' . esc_html($referenced_item->post_title) . '</strong></td>';
            echo '<td>' . ucfirst($item_type) . '</td>';
            echo '<td>₪' . number_format($original_price, 2) . '</td>';
            echo '<td>₪' . number_format($group_price, 2) . '</td>';
            echo '<td>';
            if ($difference > 0) {
                echo '<span style="color: #d63638;">+₪' . number_format($difference, 2) . '</span>';
            } elseif ($difference < 0) {
                echo '<span style="color: #00a32a;">₪' . number_format($difference, 2) . '</span>';
            } else {
                echo '<span style="color: #666;">No change</span>';
            }
            echo '</td>';
            echo '</tr>';
        }

        // Totals row
        $total_difference = $total_group - $total_original;
        echo '<tr style="background: #f0f0f1; font-weight: bold;">';
        echo '<td>TOTALS</td>';
        echo '<td>-</td>';
        echo '<td>₪' . number_format($total_original, 2) . '</td>';
        echo '<td>₪' . number_format($total_group, 2) . '</td>';
        echo '<td>';
        if ($total_difference > 0) {
            echo '<span style="color: #d63638;">+₪' . number_format($total_difference, 2) . '</span>';
        } elseif ($total_difference < 0) {
            echo '<span style="color: #00a32a;">₪' . number_format($total_difference, 2) . '</span>';
        } else {
            echo '<span style="color: #666;">No change</span>';
        }
        echo '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
    }

    public static function saveCustomFields(int $post_id): void
    {
        // Verify nonce
        if (!isset($_POST['product_group_meta_nonce']) || 
            !wp_verify_nonce($_POST['product_group_meta_nonce'], 'product_group_meta_nonce')) {
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

        // Save group type
        if (isset($_POST['group_type'])) {
            update_post_meta($post_id, '_type', sanitize_text_field($_POST['group_type']));
        }

        // Save group items
        $items = isset($_POST['group_items']) ? array_map('intval', $_POST['group_items']) : [];
        update_post_meta($post_id, '_group_item_ids', $items);
    }
}