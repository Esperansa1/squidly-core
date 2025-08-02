<?php
declare(strict_types=1);

/**
 * Customer Post Type Registration
 * 
 * Registers the 'customer' post type for storing customer data.
 * Handles both registered customers and guest customer sessions.
 */
class CustomerPostType implements PostTypeInterface
{
    public const POST_TYPE = 'customer';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerPostType']);
        add_action('admin_init', [self::class, 'addMetaBoxes']);
        add_action('save_post', [self::class, 'saveCustomFields']);
    }

    /**
     * Register the customer post type
     */
    public static function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Customers',
                'singular_name' => 'Customer',
                'add_new' => 'Add New Customer',
                'add_new_item' => 'Add New Customer',
                'edit_item' => 'Edit Customer',
                'new_item' => 'New Customer',
                'view_item' => 'View Customer',
                'view_items' => 'View Customers',
                'search_items' => 'Search Customers',
                'not_found' => 'No customers found',
                'not_found_in_trash' => 'No customers found in trash',
                'all_items' => 'All Customers',
                'archives' => 'Customer Archives',
                'attributes' => 'Customer Attributes',
                'insert_into_item' => 'Insert into customer',
                'uploaded_to_this_item' => 'Uploaded to this customer',
                'filter_items_list' => 'Filter customers list',
                'items_list_navigation' => 'Customers list navigation',
                'items_list' => 'Customers list',
            ],
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
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-groups',
            'menu_position' => 26,
            'show_in_rest' => false, // No REST API exposure for privacy
            'delete_with_user' => false,
        ]);
    }

    /**
     * Add meta boxes for customer data
     */
    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'customer_personal_info',
            'Personal Information',
            [self::class, 'personalInfoMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'customer_auth_info',
            'Authentication',
            [self::class, 'authInfoMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'customer_order_stats',
            'Order Statistics',
            [self::class, 'orderStatsMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'customer_loyalty',
            'Loyalty Points',
            [self::class, 'loyaltyMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'customer_staff_notes',
            'Staff Notes',
            [self::class, 'staffNotesMetaBox'],
            self::POST_TYPE,
            'normal',
            'low'
        );
    }

    /**
     * Personal information meta box
     */
    public static function personalInfoMetaBox($post): void
    {
        wp_nonce_field('customer_meta_nonce', 'customer_meta_nonce');
        
        $email = get_post_meta($post->ID, '_email', true);
        $phone = get_post_meta($post->ID, '_phone', true);
        $is_guest = get_post_meta($post->ID, '_is_guest', true);
        $is_active = get_post_meta($post->ID, '_is_active', true);
        $allow_sms = get_post_meta($post->ID, '_allow_sms_notifications', true);
        $allow_email = get_post_meta($post->ID, '_allow_email_notifications', true);

        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="customer_email">Email</label></th>';
        echo '<td><input type="email" id="customer_email" name="customer_email" value="' . esc_attr($email) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="customer_phone">Phone</label></th>';
        echo '<td><input type="tel" id="customer_phone" name="customer_phone" value="' . esc_attr($phone) . '" class="regular-text" required /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Account Type</th>';
        echo '<td>';
        echo '<label><input type="radio" name="customer_is_guest" value="0" ' . checked($is_guest, false, false) . ' /> Registered Customer</label><br>';
        echo '<label><input type="radio" name="customer_is_guest" value="1" ' . checked($is_guest, true, false) . ' /> Guest Customer</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Account Status</th>';
        echo '<td><label><input type="checkbox" name="customer_is_active" value="1" ' . checked($is_active, true, false) . ' /> Active Account</label></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Notifications</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="customer_allow_sms" value="1" ' . checked($allow_sms, true, false) . ' /> SMS Notifications</label><br>';
        echo '<label><input type="checkbox" name="customer_allow_email" value="1" ' . checked($allow_email, true, false) . ' /> Email Notifications</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
    }

    /**
     * Authentication information meta box
     */
    public static function authInfoMetaBox($post): void
    {
        $auth_provider = get_post_meta($post->ID, '_auth_provider', true);
        $google_id = get_post_meta($post->ID, '_google_id', true);
        $phone_verified_at = get_post_meta($post->ID, '_phone_verified_at', true);

        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th>Authentication Provider</th>';
        echo '<td>';
        echo '<select name="customer_auth_provider" required>';
        echo '<option value="">Select Provider</option>';
        echo '<option value="google" ' . selected($auth_provider, 'google', false) . '>Google</option>';
        echo '<option value="phone" ' . selected($auth_provider, 'phone', false) . '>Phone</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="customer_google_id">Google ID</label></th>';
        echo '<td><input type="text" id="customer_google_id" name="customer_google_id" value="' . esc_attr($google_id) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Phone Verified</th>';
        echo '<td>';
        if ($phone_verified_at) {
            echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ';
            echo 'Verified on ' . esc_html(date('Y-m-d H:i:s', strtotime($phone_verified_at)));
        } else {
            echo '<span class="dashicons dashicons-dismiss" style="color: red;"></span> Not verified';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
    }

    /**
     * Order statistics meta box
     */
    public static function orderStatsMetaBox($post): void
    {
        $total_orders = get_post_meta($post->ID, '_total_orders', true) ?: 0;
        $total_spent = get_post_meta($post->ID, '_total_spent', true) ?: 0;
        $last_order_date = get_post_meta($post->ID, '_last_order_date', true);
        $order_ids = get_post_meta($post->ID, '_order_ids', true) ?: [];

        echo '<p><strong>Total Orders:</strong> ' . esc_html($total_orders) . '</p>';
        echo '<p><strong>Total Spent:</strong> ₪' . number_format((float)$total_spent, 2) . '</p>';
        
        if ($last_order_date) {
            echo '<p><strong>Last Order:</strong> ' . esc_html(date('Y-m-d H:i:s', strtotime($last_order_date))) . '</p>';
        }
        
        if (!empty($order_ids) && is_array($order_ids)) {
            echo '<p><strong>Recent Order IDs:</strong><br>';
            $recent_orders = array_slice(array_reverse($order_ids), 0, 5);
            foreach ($recent_orders as $order_id) {
                echo '<a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '">#' . $order_id . '</a><br>';
            }
            if (count($order_ids) > 5) {
                echo '<em>... and ' . (count($order_ids) - 5) . ' more</em>';
            }
            echo '</p>';
        }
    }

    /**
     * Loyalty points meta box
     */
    public static function loyaltyMetaBox($post): void
    {
        $points_balance = get_post_meta($post->ID, '_loyalty_points_balance', true) ?: 0;
        $lifetime_points = get_post_meta($post->ID, '_lifetime_points_earned', true) ?: 0;
        $is_guest = get_post_meta($post->ID, '_is_guest', true);

        if ($is_guest) {
            echo '<p><em>Guest customers do not earn loyalty points.</em></p>';
            return;
        }

        echo '<p><strong>Current Balance:</strong> ' . number_format((float)$points_balance, 1) . ' points</p>';
        echo '<p><strong>Lifetime Earned:</strong> ' . number_format((float)$lifetime_points, 1) . ' points</p>';
        
        if ($lifetime_points > 0) {
            $usage_percentage = ($lifetime_points - $points_balance) / $lifetime_points * 100;
            echo '<p><strong>Points Used:</strong> ' . number_format($usage_percentage, 1) . '%</p>';
        }

        echo '<hr>';
        echo '<p><strong>Adjust Points:</strong></p>';
        echo '<input type="number" step="0.1" name="customer_points_adjustment" placeholder="Enter points to add/subtract" style="width: 100%;" />';
        echo '<p><small>Enter positive number to add points, negative to subtract.</small></p>';
    }

    /**
     * Staff notes meta box
     */
    public static function staffNotesMetaBox($post): void
    {
        $staff_labels = get_post_meta($post->ID, '_staff_labels', true);

        echo '<div>';
        echo '<label for="customer_staff_labels">Staff Notes & Labels:</label><br>';
        echo '<textarea id="customer_staff_labels" name="customer_staff_labels" rows="6" style="width: 100%;">' . esc_textarea($staff_labels) . '</textarea>';
        echo '<p><small>Internal notes about customer behavior, complaints, preferences, etc.</small></p>';
        echo '</div>';
        
        echo '<hr>';
        echo '<div>';
        echo '<label for="customer_new_staff_note">Add New Note:</label><br>';
        echo '<textarea id="customer_new_staff_note" name="customer_new_staff_note" rows="3" style="width: 100%;" placeholder="Enter new staff note..."></textarea>';
        echo '<p><small>This will be automatically timestamped and added to existing notes.</small></p>';
        echo '</div>';
    }

    /**
     * Save custom fields
     */
    public static function saveCustomFields($post_id): void
    {
        // Verify nonce
        if (!isset($_POST['customer_meta_nonce']) || 
            !wp_verify_nonce($_POST['customer_meta_nonce'], 'customer_meta_nonce')) {
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

        // Save personal information
        if (isset($_POST['customer_email'])) {
            update_post_meta($post_id, '_email', sanitize_email($_POST['customer_email']));
        }
        
        if (isset($_POST['customer_phone'])) {
            update_post_meta($post_id, '_phone', sanitize_text_field($_POST['customer_phone']));
        }
        
        if (isset($_POST['customer_is_guest'])) {
            update_post_meta($post_id, '_is_guest', (bool) $_POST['customer_is_guest']);
        }
        
        if (isset($_POST['customer_is_active'])) {
            update_post_meta($post_id, '_is_active', true);
        } else {
            update_post_meta($post_id, '_is_active', false);
        }
        
        if (isset($_POST['customer_allow_sms'])) {
            update_post_meta($post_id, '_allow_sms_notifications', true);
        } else {
            update_post_meta($post_id, '_allow_sms_notifications', false);
        }
        
        if (isset($_POST['customer_allow_email'])) {
            update_post_meta($post_id, '_allow_email_notifications', true);
        } else {
            update_post_meta($post_id, '_allow_email_notifications', false);
        }

        // Save authentication information
        if (isset($_POST['customer_auth_provider'])) {
            update_post_meta($post_id, '_auth_provider', sanitize_text_field($_POST['customer_auth_provider']));
        }
        
        if (isset($_POST['customer_google_id'])) {
            update_post_meta($post_id, '_google_id', sanitize_text_field($_POST['customer_google_id']));
        }

        // Handle loyalty points adjustment
        if (isset($_POST['customer_points_adjustment']) && !empty($_POST['customer_points_adjustment'])) {
            $adjustment = (float) $_POST['customer_points_adjustment'];
            $current_balance = (float) get_post_meta($post_id, '_loyalty_points_balance', true);
            $current_lifetime = (float) get_post_meta($post_id, '_lifetime_points_earned', true);
            
            $new_balance = $current_balance + $adjustment;
            
            // Don't allow negative balance
            if ($new_balance >= 0) {
                update_post_meta($post_id, '_loyalty_points_balance', $new_balance);
                
                // If adding points, update lifetime earned
                if ($adjustment > 0) {
                    update_post_meta($post_id, '_lifetime_points_earned', $current_lifetime + $adjustment);
                }
            }
        }

        // Save staff notes
        if (isset($_POST['customer_staff_labels'])) {
            update_post_meta($post_id, '_staff_labels', wp_kses_post($_POST['customer_staff_labels']));
        }
        
        // Add new staff note if provided
        if (isset($_POST['customer_new_staff_note']) && !empty(trim($_POST['customer_new_staff_note']))) {
            $existing_notes = get_post_meta($post_id, '_staff_labels', true);
            $new_note = sanitize_textarea_field($_POST['customer_new_staff_note']);
            $timestamped_note = date('Y-m-d H:i:s') . ': ' . $new_note;
            
            if (!empty($existing_notes)) {
                $updated_notes = $existing_notes . "\n" . $timestamped_note;
            } else {
                $updated_notes = $timestamped_note;
            }
            
            update_post_meta($post_id, '_staff_labels', $updated_notes);
        }
    }

    /**
     * Get the customer post type name
     */
    public static function getPostType(): string
    {
        return self::POST_TYPE;
    }
}