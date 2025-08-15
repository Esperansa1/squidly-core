<?php
declare(strict_types=1);

/**
 * Store Branch Post Type
 * includes/post-types/StoreBranchPostType.php
 */
class StoreBranchPostType implements PostTypeInterface
{
    public const POST_TYPE = 'store_branch';

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
            'name' => 'Store Branches',
            'singular_name' => 'Store Branch',
            'add_new' => 'Add New Branch',
            'add_new_item' => 'Add New Store Branch',
            'edit_item' => 'Edit Store Branch',
            'new_item' => 'New Store Branch',
            'view_item' => 'View Store Branch',
            'view_items' => 'View Store Branches',
            'search_items' => 'Search Store Branches',
            'not_found' => 'No store branches found',
            'not_found_in_trash' => 'No store branches found in trash',
            'all_items' => 'All Store Branches',
            'archives' => 'Store Branch Archives',
            'attributes' => 'Store Branch Attributes',
            'insert_into_item' => 'Insert into store branch',
            'uploaded_to_this_item' => 'Uploaded to this store branch',
            'filter_items_list' => 'Filter store branches list',
            'items_list_navigation' => 'Store branches list navigation',
            'items_list' => 'Store branches list',
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
            'menu_icon' => 'dashicons-store',
            'menu_position' => 26,
        ];
    }

    public static function getSupports(): array
    {
        return ['title', 'custom-fields'];
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'branch_contact_info',
            'Contact Information',
            [self::class, 'contactInfoMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'branch_location',
            'Location Details',
            [self::class, 'locationMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'branch_hours',
            'Business Hours',
            [self::class, 'businessHoursMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'branch_features',
            'Features & Accessibility',
            [self::class, 'featuresMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function contactInfoMetaBox($post): void
    {
        wp_nonce_field('branch_meta_nonce', 'branch_meta_nonce');
        
        $phone = get_post_meta($post->ID, '_phone', true);
        $is_open = get_post_meta($post->ID, '_is_open', true);

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="branch_phone">Phone</label></th>';
        echo '<td><input type="tel" id="branch_phone" name="branch_phone" value="' . esc_attr($phone) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Status</th>';
        echo '<td><label><input type="checkbox" name="branch_is_open" value="1" ' . checked($is_open, true, false) . ' /> Currently Open</label></td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function locationMetaBox($post): void
    {
        $city = get_post_meta($post->ID, '_city', true);
        $address = get_post_meta($post->ID, '_address', true);

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="branch_city">City</label></th>';
        echo '<td><input type="text" id="branch_city" name="branch_city" value="' . esc_attr($city) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="branch_address">Address</label></th>';
        echo '<td><textarea id="branch_address" name="branch_address" rows="3" class="large-text">' . esc_textarea($address) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';
    }

    public static function businessHoursMetaBox($post): void
    {
        $activity_times = get_post_meta($post->ID, '_activity_times', true) ?: [];
        $days = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

        echo '<table class="form-table">';
        foreach ($days as $day) {
            $times = $activity_times[$day] ?? [];
            echo '<tr>';
            echo '<th>' . ucfirst(strtolower($day)) . '</th>';
            echo '<td>';
            echo '<input type="text" name="branch_hours[' . $day . ']" value="' . esc_attr(implode(', ', $times)) . '" class="regular-text" />';
            echo '<p class="description">Format: 09:00-17:00, 19:00-23:00 (separate multiple periods with commas)</p>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public static function featuresMetaBox($post): void
    {
        $kosher_type = get_post_meta($post->ID, '_kosher_type', true);
        $accessibility_list = get_post_meta($post->ID, '_accessibility_list', true) ?: [];

        echo '<p><strong>Kosher Type:</strong></p>';
        echo '<select name="branch_kosher_type" style="width: 100%;">';
        echo '<option value="">None</option>';
        echo '<option value="Kosher Dairy" ' . selected($kosher_type, 'Kosher Dairy', false) . '>Kosher Dairy</option>';
        echo '<option value="Kosher Meat" ' . selected($kosher_type, 'Kosher Meat', false) . '>Kosher Meat</option>';
        echo '<option value="Kosher Mehadrin" ' . selected($kosher_type, 'Kosher Mehadrin', false) . '>Kosher Mehadrin</option>';
        echo '</select>';

        echo '<p style="margin-top: 15px;"><strong>Accessibility Features:</strong></p>';
        $features = [
            'wheelchair_accessible' => 'Wheelchair Accessible',
            'braille_menu' => 'Braille Menu',
            'hearing_loop' => 'Hearing Loop',
            'elevator' => 'Elevator Access'
        ];
        
        foreach ($features as $key => $label) {
            $checked = in_array($key, $accessibility_list) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="branch_accessibility[]" value="' . $key . '" ' . $checked . ' /> ';
            echo $label;
            echo '</label>';
        }
    }

    public static function saveCustomFields(int $post_id): void
    {
        // Verify nonce
        if (!isset($_POST['branch_meta_nonce']) || 
            !wp_verify_nonce($_POST['branch_meta_nonce'], 'branch_meta_nonce')) {
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

        // Save contact info
        if (isset($_POST['branch_phone'])) {
            update_post_meta($post_id, '_phone', sanitize_text_field($_POST['branch_phone']));
        }
        
        update_post_meta($post_id, '_is_open', isset($_POST['branch_is_open']));

        // Save location
        if (isset($_POST['branch_city'])) {
            update_post_meta($post_id, '_city', sanitize_text_field($_POST['branch_city']));
        }
        if (isset($_POST['branch_address'])) {
            update_post_meta($post_id, '_address', sanitize_textarea_field($_POST['branch_address']));
        }

        // Save business hours
        if (isset($_POST['branch_hours'])) {
            $activity_times = [];
            foreach ($_POST['branch_hours'] as $day => $hours) {
                $hours = sanitize_text_field($hours);
                if (!empty($hours)) {
                    $activity_times[$day] = array_map('trim', explode(',', $hours));
                }
            }
            update_post_meta($post_id, '_activity_times', $activity_times);
        }

        // Save features
        if (isset($_POST['branch_kosher_type'])) {
            update_post_meta($post_id, '_kosher_type', sanitize_text_field($_POST['branch_kosher_type']));
        }
        
        $accessibility = isset($_POST['branch_accessibility']) ? array_map('sanitize_text_field', $_POST['branch_accessibility']) : [];
        update_post_meta($post_id, '_accessibility_list', $accessibility);
    }
}