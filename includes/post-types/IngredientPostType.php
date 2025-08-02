<?php
declare(strict_types=1);

/**
 * Ingredient Post Type
 * includes/post-types/IngredientPostType.php
 */
class IngredientPostType implements PostTypeInterface
{
    public const POST_TYPE = 'ingredient';

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
            'name' => 'Ingredients',
            'singular_name' => 'Ingredient',
            'add_new' => 'Add New Ingredient',
            'add_new_item' => 'Add New Ingredient',
            'edit_item' => 'Edit Ingredient',
            'new_item' => 'New Ingredient',
            'view_item' => 'View Ingredient',
            'view_items' => 'View Ingredients',
            'search_items' => 'Search Ingredients',
            'not_found' => 'No ingredients found',
            'not_found_in_trash' => 'No ingredients found in trash',
            'all_items' => 'All Ingredients',
            'archives' => 'Ingredient Archives',
            'attributes' => 'Ingredient Attributes',
            'insert_into_item' => 'Insert into ingredient',
            'uploaded_to_this_item' => 'Uploaded to this ingredient',
            'filter_items_list' => 'Filter ingredients list',
            'items_list_navigation' => 'Ingredients list navigation',
            'items_list' => 'Ingredients list',
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
            'menu_icon' => 'dashicons-carrot',
        ];
    }

    public static function getSupports(): array
    {
        return ['title', 'custom-fields'];
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'ingredient_pricing',
            'Pricing & Availability',
            [self::class, 'pricingMetaBox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'ingredient_details',
            'Ingredient Details',
            [self::class, 'detailsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'ingredient_usage',
            'Usage Information',
            [self::class, 'usageMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function pricingMetaBox($post): void
    {
        wp_nonce_field('ingredient_meta_nonce', 'ingredient_meta_nonce');
        
        $price = get_post_meta($post->ID, '_price', true);
        $is_available = get_post_meta($post->ID, '_is_available', true);
        
        // Default to available if not set
        if ($is_available === '') {
            $is_available = true;
        }

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="ingredient_price">Base Price (₪)</label></th>';
        echo '<td>';
        echo '<input type="number" step="0.01" min="0" id="ingredient_price" name="ingredient_price" value="' . esc_attr($price) . '" class="small-text" />';
        echo '<p class="description">Default price - can be overridden in Group Items</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Availability</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ingredient_is_available" value="1" ' . checked($is_available, true, false) . ' /> Currently Available</label>';
        echo '<p class="description">Uncheck if ingredient is out of stock</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function detailsMetaBox($post): void
    {
        $category = get_post_meta($post->ID, '_category', true);
        $allergens = get_post_meta($post->ID, '_allergens', true) ?: [];
        $dietary_info = get_post_meta($post->ID, '_dietary_info', true) ?: [];

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="ingredient_category">Category</label></th>';
        echo '<td>';
        echo '<select id="ingredient_category" name="ingredient_category" style="width: 100%;">';
        echo '<option value="">Select Category</option>';
        
        $categories = [
            'protein' => 'Protein',
            'vegetable' => 'Vegetable', 
            'dairy' => 'Dairy',
            'grain' => 'Grain/Bread',
            'sauce' => 'Sauce/Condiment',
            'spice' => 'Spice/Seasoning',
            'fruit' => 'Fruit',
            'other' => 'Other'
        ];
        
        foreach ($categories as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($category, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '<h4>Allergen Information</h4>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">';
        
        $common_allergens = [
            'gluten' => 'Contains Gluten',
            'dairy' => 'Contains Dairy',
            'eggs' => 'Contains Eggs',
            'nuts' => 'Contains Nuts',
            'peanuts' => 'Contains Peanuts',
            'sesame' => 'Contains Sesame',
            'soy' => 'Contains Soy',
            'fish' => 'Contains Fish'
        ];
        
        foreach ($common_allergens as $key => $label) {
            $checked = in_array($key, $allergens) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="ingredient_allergens[]" value="' . $key . '" ' . $checked . ' /> ';
            echo $label;
            echo '</label>';
        }
        echo '</div>';

        echo '<h4>Dietary Information</h4>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
        
        $dietary_options = [
            'vegetarian' => 'Vegetarian',
            'vegan' => 'Vegan',
            'kosher' => 'Kosher',
            'halal' => 'Halal',
            'gluten_free' => 'Gluten Free',
            'sugar_free' => 'Sugar Free',
            'organic' => 'Organic',
            'local' => 'Locally Sourced'
        ];
        
        foreach ($dietary_options as $key => $label) {
            $checked = in_array($key, $dietary_info) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="ingredient_dietary_info[]" value="' . $key . '" ' . $checked . ' /> ';
            echo $label;
            echo '</label>';
        }
        echo '</div>';
    }

    public static function usageMetaBox($post): void
    {
        // Find where this ingredient is used
        $ingredient_id = $post->ID;
        
        // Find Group Items that reference this ingredient
        $group_items = get_posts([
            'post_type' => 'group_item',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_item_id',
                    'value' => $ingredient_id,
                    'compare' => '='
                ],
                [
                    'key' => '_item_type',
                    'value' => 'ingredient',
                    'compare' => '='
                ]
            ]
        ]);

        if (empty($group_items)) {
            echo '<p><em>This ingredient is not currently used in any product groups.</em></p>';
            echo '<p><a href="' . admin_url('post-new.php?post_type=group_item') . '" class="button">Create Group Item</a> to start using this ingredient.</p>';
            return;
        }

        echo '<p><strong>This ingredient is used in the following:</strong></p>';
        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Group Item</th>';
        echo '<th>Override Price</th>';
        echo '<th>Used In Groups</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($group_items as $group_item) {
            $override_price = get_post_meta($group_item->ID, '_override_price', true);
            $base_price = get_post_meta($ingredient_id, '_price', true) ?: 0;
            
            // Find Product Groups that use this Group Item
            $product_groups = get_posts([
                'post_type' => 'product_group',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_group_item_ids',
                        'value' => 'i:' . $group_item->ID . ';',
                        'compare' => 'LIKE'
                    ]
                ]
            ]);

            echo '<tr>';
            echo '<td>';
            echo '<strong>' . esc_html($group_item->post_title) . '</strong><br>';
            echo '<small>ID: ' . $group_item->ID . '</small>';
            echo '</td>';
            echo '<td>';
            if ($override_price) {
                echo '₪' . number_format($override_price, 2);
                $difference = $override_price - $base_price;
                if ($difference != 0) {
                    $color = $difference > 0 ? '#d63638' : '#00a32a';
                    echo '<br><small style="color: ' . $color . ';">';
                    echo ($difference > 0 ? '+' : '') . '₪' . number_format($difference, 2);
                    echo '</small>';
                }
            } else {
                echo '<em>Uses base price</em><br>';
                echo '<small>₪' . number_format($base_price, 2) . '</small>';
            }
            echo '</td>';
            echo '<td>';
            if (empty($product_groups)) {
                echo '<em>Not in any groups yet</em>';
            } else {
                foreach ($product_groups as $pg) {
                    echo '<a href="' . admin_url('post.php?post=' . $pg->ID . '&action=edit') . '">';
                    echo esc_html($pg->post_title);
                    echo '</a><br>';
                }
            }
            echo '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('post.php?post=' . $group_item->ID . '&action=edit') . '" class="button button-small">Edit</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    public static function saveCustomFields(int $post_id): void
    {
        // Verify nonce
        if (!isset($_POST['ingredient_meta_nonce']) || 
            !wp_verify_nonce($_POST['ingredient_meta_nonce'], 'ingredient_meta_nonce')) {
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

        // Save price
        if (isset($_POST['ingredient_price'])) {
            update_post_meta($post_id, '_price', floatval($_POST['ingredient_price']));
        }

        // Save availability
        update_post_meta($post_id, '_is_available', isset($_POST['ingredient_is_available']));

        // Save category
        if (isset($_POST['ingredient_category'])) {
            update_post_meta($post_id, '_category', sanitize_text_field($_POST['ingredient_category']));
        }

        // Save allergens
        $allergens = isset($_POST['ingredient_allergens']) ? array_map('sanitize_text_field', $_POST['ingredient_allergens']) : [];
        update_post_meta($post_id, '_allergens', $allergens);

        // Save dietary info
        $dietary_info = isset($_POST['ingredient_dietary_info']) ? array_map('sanitize_text_field', $_POST['ingredient_dietary_info']) : [];
        update_post_meta($post_id, '_dietary_info', $dietary_info);
    }
}