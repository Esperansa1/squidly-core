<?php
declare(strict_types=1);

/**
 * Group Item Post Type
 * includes/post-types/GroupItemPostType.php
 */
class GroupItemPostType implements PostTypeInterface
{
    public const POST_TYPE = 'group_item';

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
        add_action('wp_ajax_squidly_get_items_by_type', [self::class, 'ajaxGetItemsByType']);
    }

    public static function getLabels(): array
    {
        return [
            'name' => 'Group Items',
            'singular_name' => 'Group Item',
            'add_new' => 'Add New Group Item',
            'add_new_item' => 'Add New Group Item',
            'edit_item' => 'Edit Group Item',
            'new_item' => 'New Group Item',
            'view_item' => 'View Group Item',
            'view_items' => 'View Group Items',
            'search_items' => 'Search Group Items',
            'not_found' => 'No group items found',
            'not_found_in_trash' => 'No group items found in trash',
            'all_items' => 'All Group Items',
            'archives' => 'Group Item Archives',
            'attributes' => 'Group Item Attributes',
            'insert_into_item' => 'Insert into group item',
            'uploaded_to_this_item' => 'Uploaded to this group item',
            'filter_items_list' => 'Filter group items list',
            'items_list_navigation' => 'Group items list navigation',
            'items_list' => 'Group items list',
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
            'menu_icon' => 'dashicons-tag',
        ];
    }

    public static function getSupports(): array
    {
        return ['title', 'custom-fields'];
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'group_item_reference',
            'Item Reference',
            [self::class, 'referenceMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'group_item_pricing',
            'Price Override',
            [self::class, 'pricingMetaBox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'group_item_preview',
            'Item Preview',
            [self::class, 'previewMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'group_item_usage',
            'Usage in Groups',
            [self::class, 'usageMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function referenceMetaBox($post): void
    {
        wp_nonce_field('group_item_meta_nonce', 'group_item_meta_nonce');
        
        $item_id = get_post_meta($post->ID, '_item_id', true);
        $item_type = get_post_meta($post->ID, '_item_type', true);

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="item_type">Item Type</label></th>';
        echo '<td>';
        echo '<select id="item_type" name="item_type" required onchange="loadItems(this.value)" style="width: 100%;">';
        echo '<option value="">Select Type</option>';
        echo '<option value="product" ' . selected($item_type, 'product', false) . '>Product</option>';
        echo '<option value="ingredient" ' . selected($item_type, 'ingredient', false) . '>Ingredient</option>';
        echo '</select>';
        echo '<p class="description">';
        echo '<strong>Product:</strong> Reference another product (e.g., side dishes)<br>';
        echo '<strong>Ingredient:</strong> Reference an ingredient (e.g., toppings, sauces)';
        echo '</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="item_id">Select Item</label></th>';
        echo '<td>';
        echo '<select id="item_id" name="item_id" required style="width: 100%;">';
        echo '<option value="">Select Item</option>';
        
        // Populate based on selected type
        if ($item_type && $item_id) {
            $items = get_posts([
                'post_type' => $item_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            foreach ($items as $item) {
                $item_price = '';
                if ($item_type === 'product') {
                    $price = get_post_meta($item->ID, '_regular_price', true);
                    $item_price = $price ? ' (₪' . number_format($price, 2) . ')' : '';
                } elseif ($item_type === 'ingredient') {
                    $price = get_post_meta($item->ID, '_price', true);
                    $item_price = $price ? ' (₪' . number_format($price, 2) . ')' : '';
                }
                
                echo '<option value="' . $item->ID . '" ' . selected($item_id, $item->ID, false) . '>';
                echo esc_html($item->post_title . $item_price);
                echo '</option>';
            }
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        // JavaScript for dynamic loading
        ?>
        <script>
        function loadItems(type) {
            const itemSelect = document.getElementById('item_id');
            const originalValue = itemSelect.value;
            
            itemSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (!type) {
                itemSelect.innerHTML = '<option value="">Select Item</option>';
                return;
            }
            
            // Use WordPress AJAX
            const data = {
                action: 'squidly_get_items_by_type',
                type: type,
                nonce: '<?php echo wp_create_nonce('squidly_ajax_nonce'); ?>'
            };
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(data => {
                itemSelect.innerHTML = '<option value="">Select Item</option>';
                if (data.success) {
                    data.data.forEach(item => {
                        const selected = item.id == originalValue ? ' selected' : '';
                        itemSelect.innerHTML += '<option value="' + item.id + '"' + selected + '>' + item.title + '</option>';
                    });
                } else {
                    console.error('Error loading items:', data.data);
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                itemSelect.innerHTML = '<option value="">Error loading items</option>';
            });
        }
        </script>
        <?php
    }

    public static function pricingMetaBox($post): void
    {
        $override_price = get_post_meta($post->ID, '_override_price', true);
        $item_id = get_post_meta($post->ID, '_item_id', true);
        $item_type = get_post_meta($post->ID, '_item_type', true);
        
        // Get original price for comparison
        $original_price = 0;
        if ($item_id && $item_type) {
            if ($item_type === 'product') {
                $original_price = get_post_meta($item_id, '_regular_price', true) ?: 0;
            } elseif ($item_type === 'ingredient') {
                $original_price = get_post_meta($item_id, '_price', true) ?: 0;
            }
        }

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="override_price">Override Price (₪)</label></th>';
        echo '<td>';
        echo '<input type="number" step="0.01" min="0" id="override_price" name="override_price" value="' . esc_attr($override_price) . '" class="small-text" />';
        echo '<p class="description">Leave empty to use original item price</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        if ($original_price > 0) {
            echo '<div style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-top: 10px;">';
            echo '<strong>Original Price:</strong> ₪' . number_format($original_price, 2);
            
            if ($override_price) {
                $difference = $override_price - $original_price;
                echo '<br><strong>Override Price:</strong> ₪' . number_format($override_price, 2);
                echo '<br><strong>Difference:</strong> ';
                if ($difference > 0) {
                    echo '<span style="color: #d63638;">+₪' . number_format($difference, 2) . ' (increase)</span>';
                } elseif ($difference < 0) {
                    echo '<span style="color: #00a32a;">₪' . number_format($difference, 2) . ' (discount)</span>';
                } else {
                    echo '<span style="color: #666;">No change</span>';
                }
            }
            echo '</div>';
        }
    }

    public static function previewMetaBox($post): void
    {
        $item_id = get_post_meta($post->ID, '_item_id', true);
        $item_type = get_post_meta($post->ID, '_item_type', true);
        $override_price = get_post_meta($post->ID, '_override_price', true);

        if (!$item_id || !$item_type) {
            echo '<p><em>Select an item above to see preview information.</em></p>';
            return;
        }

        $referenced_item = get_post($item_id);
        if (!$referenced_item) {
            echo '<p><em>Referenced item not found.</em></p>';
            return;
        }

        echo '<table class="widefat">';
        echo '<tbody>';
        echo '<tr>';
        echo '<th>Item Name</th>';
        echo '<td><strong>' . esc_html($referenced_item->post_title) . '</strong></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Item Type</th>';
        echo '<td>' . ucfirst($item_type) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Item ID</th>';
        echo '<td>#' . $item_id . '</td>';
        echo '</tr>';

        // Get original price
        $original_price = 0;
        if ($item_type === 'product') {
            $original_price = get_post_meta($item_id, '_regular_price', true) ?: 0;
            $sale_price = get_post_meta($item_id, '_sale_price', true);
            
            echo '<tr>';
            echo '<th>Original Price</th>';
            echo '<td>₪' . number_format($original_price, 2);
            if ($sale_price) {
                echo ' <small>(Sale: ₪' . number_format($sale_price, 2) . ')</small>';
            }
            echo '</td>';
            echo '</tr>';
            
            $category = get_post_meta($item_id, '_category', true);
            if ($category) {
                echo '<tr>';
                echo '<th>Category</th>';
                echo '<td>' . esc_html($category) . '</td>';
                echo '</tr>';
            }
        } elseif ($item_type === 'ingredient') {
            $original_price = get_post_meta($item_id, '_price', true) ?: 0;
            
            echo '<tr>';
            echo '<th>Base Price</th>';
            echo '<td>₪' . number_format($original_price, 2) . '</td>';
            echo '</tr>';
            
            $category = get_post_meta($item_id, '_category', true);
            if ($category) {
                echo '<tr>';
                echo '<th>Category</th>';
                echo '<td>' . ucfirst($category) . '</td>';
                echo '</tr>';
            }
            
            $allergens = get_post_meta($item_id, '_allergens', true);
            if (!empty($allergens)) {
                echo '<tr>';
                echo '<th>Allergens</th>';
                echo '<td>' . implode(', ', array_map('ucfirst', $allergens)) . '</td>';
                echo '</tr>';
            }
            
            $dietary_info = get_post_meta($item_id, '_dietary_info', true);
            if (!empty($dietary_info)) {
                echo '<tr>';
                echo '<th>Dietary Info</th>';
                echo '<td>' . implode(', ', array_map('ucfirst', str_replace('_', ' ', $dietary_info))) . '</td>';
                echo '</tr>';
            }
        }

        if ($override_price) {
            echo '<tr style="background: #f0f0f1;">';
            echo '<th><strong>Group Price</strong></th>';
            echo '<td><strong>₪' . number_format($override_price, 2) . '</strong></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '<p style="margin-top: 15px;">';
        echo '<a href="' . admin_url('post.php?post=' . $item_id . '&action=edit') . '" class="button">';
        echo 'Edit ' . ucfirst($item_type);
        echo '</a>';
        echo '</p>';
    }

    public static function usageMetaBox($post): void
    {
        $group_item_id = $post->ID;
        
        // Find Product Groups that use this Group Item
        $product_groups = get_posts([
            'post_type' => 'product_group',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_group_item_ids',
                    'value' => 'i:' . $group_item_id . ';',
                    'compare' => 'LIKE'
                ]
            ]
        ]);

        if (empty($product_groups)) {
            echo '<p><em>This group item is not used in any product groups yet.</em></p>';
            echo '<p><a href="' . admin_url('post-new.php?post_type=product_group') . '" class="button button-small">Create Product Group</a></p>';
            return;
        }

        echo '<p><strong>Used in ' . count($product_groups) . ' group(s):</strong></p>';
        echo '<ul>';
        
        foreach ($product_groups as $group) {
            $group_type = get_post_meta($group->ID, '_type', true);
            
            echo '<li>';
            echo '<a href="' . admin_url('post.php?post=' . $group->ID . '&action=edit') . '">';
            echo '<strong>' . esc_html($group->post_title) . '</strong>';
            echo '</a>';
            echo '<br><small>' . ucfirst($group_type) . ' Group</small>';
            echo '</li>';
        }
        
        echo '</ul>';

        // Also find products that use these groups
        $products_using_groups = [];
        foreach ($product_groups as $group) {
            $products = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_product_group_ids',
                        'value' => 'i:' . $group->ID . ';',
                        'compare' => 'LIKE'
                    ]
                ]
            ]);
            $products_using_groups = array_merge($products_using_groups, $products);
        }

        $products_using_groups = array_unique($products_using_groups, SORT_REGULAR);

        if (!empty($products_using_groups)) {
            echo '<hr>';
            echo '<p><strong>Available in ' . count($products_using_groups) . ' product(s):</strong></p>';
            echo '<ul>';
            foreach ($products_using_groups as $product) {
                echo '<li>';
                echo '<a href="' . admin_url('post.php?post=' . $product->ID . '&action=edit') . '">';
                echo esc_html($product->post_title);
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }
    }

    public static function saveCustomFields(int $post_id): void
    {
        // Verify nonce
        if (!isset($_POST['group_item_meta_nonce']) || 
            !wp_verify_nonce($_POST['group_item_meta_nonce'], 'group_item_meta_nonce')) {
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

        // Save item reference
        if (isset($_POST['item_id'])) {
            update_post_meta($post_id, '_item_id', intval($_POST['item_id']));
        }
        
        if (isset($_POST['item_type'])) {
            update_post_meta($post_id, '_item_type', sanitize_text_field($_POST['item_type']));
        }

        // Save price override
        if (isset($_POST['override_price'])) {
            $price = floatval($_POST['override_price']);
            update_post_meta($post_id, '_override_price', $price > 0 ? $price : '');
        }

        // Update post title for better identification
        if (isset($_POST['item_type']) && isset($_POST['item_id'])) {
            $item = get_post(intval($_POST['item_id']));
            if ($item) {
                $override_price = isset($_POST['override_price']) ? floatval($_POST['override_price']) : '';
                $price_text = $override_price ? ' (₪' . number_format($override_price, 2) . ')' : '';
                $new_title = $item->post_title . ' (' . ucfirst($_POST['item_type']) . ')' . $price_text;
                
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $new_title
                ]);
            }
        }
    }

    /**
     * AJAX handler for getting items by type
     */
    public static function ajaxGetItemsByType(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'squidly_ajax_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $type = sanitize_text_field($_POST['type'] ?? '');
        
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
            $price = '';
            if ($type === 'product') {
                $item_price = get_post_meta($item->ID, '_regular_price', true);
                $price = $item_price ? ' (₪' . number_format($item_price, 2) . ')' : '';
            } elseif ($type === 'ingredient') {
                $item_price = get_post_meta($item->ID, '_price', true);
                $price = $item_price ? ' (₪' . number_format($item_price, 2) . ')' : '';
            }
            
            $response[] = [
                'id' => $item->ID,
                'title' => $item->post_title . $price
            ];
        }

        wp_send_json_success($response);
    }
}