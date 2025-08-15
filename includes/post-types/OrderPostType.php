<?php
declare(strict_types=1);

/**
 * Order Post Type
 * includes/post-types/OrderPostType.php
 */
class OrderPostType implements PostTypeInterface
{
    public const POST_TYPE = 'order';

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
            'name' => 'Orders',
            'singular_name' => 'Order',
            'add_new' => 'Add New Order',
            'add_new_item' => 'Add New Order',
            'edit_item' => 'Edit Order',
            'new_item' => 'New Order',
            'view_item' => 'View Order',
            'view_items' => 'View Orders',
            'search_items' => 'Search Orders',
            'not_found' => 'No orders found',
            'not_found_in_trash' => 'No orders found in trash',
            'all_items' => 'All Orders',
            'archives' => 'Order Archives',
            'attributes' => 'Order Attributes',
            'insert_into_item' => 'Insert into order',
            'uploaded_to_this_item' => 'Uploaded to this order',
            'filter_items_list' => 'Filter orders list',
            'items_list_navigation' => 'Orders list navigation',
            'items_list' => 'Orders list',
        ];
    }

    public static function getArgs(): array
    {
        return [
            'labels' => self::getLabels(),
            'public' => false,
            'publicly_queryable' => true, // Allow queries even though not public
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-cart',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => self::getSupports(),
            'has_archive' => false,
            'show_in_rest' => false,
            'rewrite' => false,
        ];
    }

    public static function getSupports(): array
    {
        return ['title', 'custom-fields'];
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'order_details',
            'Order Details',
            [self::class, 'renderOrderDetailsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'order_items',
            'Order Items',
            [self::class, 'renderOrderItemsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'order_payment',
            'Payment Information',
            [self::class, 'renderPaymentMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function renderOrderDetailsMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('order_meta_box', 'order_meta_box_nonce');

        $customer_id = get_post_meta($post->ID, '_customer_id', true);
        $status = get_post_meta($post->ID, '_status', true) ?: Order::STATUS_PENDING;
        $delivery_address = get_post_meta($post->ID, '_delivery_address', true);
        $pickup_time = get_post_meta($post->ID, '_pickup_time', true);
        $special_instructions = get_post_meta($post->ID, '_special_instructions', true);

        echo '<table class="form-table">';
        
        // Customer selection
        echo '<tr>';
        echo '<th><label for="customer_id">Customer:</label></th>';
        echo '<td>';
        echo '<select name="customer_id" id="customer_id" class="regular-text">';
        echo '<option value="">Select Customer</option>';
        
        // Get customers for dropdown
        $customers = get_posts([
            'post_type' => 'customer',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        foreach ($customers as $customer) {
            $selected = ($customer_id == $customer->ID) ? 'selected' : '';
            echo "<option value='{$customer->ID}' {$selected}>{$customer->post_title}</option>";
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Order status
        echo '<tr>';
        echo '<th><label for="order_status">Status:</label></th>';
        echo '<td>';
        echo '<select name="order_status" id="order_status" class="regular-text">';
        foreach (Order::getValidStatuses() as $status_option) {
            $selected = ($status === $status_option) ? 'selected' : '';
            $display = ucfirst(str_replace('_', ' ', $status_option));
            echo "<option value='{$status_option}' {$selected}>{$display}</option>";
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Delivery address
        echo '<tr>';
        echo '<th><label for="delivery_address">Delivery Address:</label></th>';
        echo '<td>';
        echo '<textarea name="delivery_address" id="delivery_address" class="regular-text" rows="3">' . esc_textarea($delivery_address) . '</textarea>';
        echo '</td>';
        echo '</tr>';

        // Pickup time
        echo '<tr>';
        echo '<th><label for="pickup_time">Pickup Time:</label></th>';
        echo '<td>';
        echo '<input type="datetime-local" name="pickup_time" id="pickup_time" value="' . esc_attr($pickup_time) . '" class="regular-text" />';
        echo '</td>';
        echo '</tr>';

        // Special instructions
        echo '<tr>';
        echo '<th><label for="special_instructions">Special Instructions:</label></th>';
        echo '<td>';
        echo '<textarea name="special_instructions" id="special_instructions" class="regular-text" rows="3">' . esc_textarea($special_instructions) . '</textarea>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
    }

    public static function renderOrderItemsMetaBox(\WP_Post $post): void
    {
        $order_items = get_post_meta($post->ID, '_order_items', true) ?: [];
        $subtotal = get_post_meta($post->ID, '_subtotal', true) ?: 0;
        $tax_amount = get_post_meta($post->ID, '_tax_amount', true) ?: 0;
        $delivery_fee = get_post_meta($post->ID, '_delivery_fee', true) ?: 0;
        $total_amount = get_post_meta($post->ID, '_total_amount', true) ?: 0;

        echo '<div id="order-items-container">';
        echo '<h4>Items</h4>';
        
        if (!empty($order_items)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($order_items as $item) {
                echo '<tr>';
                echo '<td>' . esc_html($item['product_name']) . '</td>';
                echo '<td>' . esc_html($item['quantity']) . '</td>';
                echo '<td>₪' . number_format((float)$item['unit_price'], 2) . '</td>';
                echo '<td>₪' . number_format((float)$item['total_price'], 2) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No items in this order.</p>';
        }

        echo '<h4>Order Totals</h4>';
        echo '<table class="form-table">';
        echo '<tr><th>Subtotal:</th><td>₪' . number_format((float)$subtotal, 2) . '</td></tr>';
        echo '<tr><th>Tax:</th><td>₪' . number_format((float)$tax_amount, 2) . '</td></tr>';
        echo '<tr><th>Delivery Fee:</th><td>₪' . number_format((float)$delivery_fee, 2) . '</td></tr>';
        echo '<tr><th><strong>Total:</strong></th><td><strong>₪' . number_format((float)$total_amount, 2) . '</strong></td></tr>';
        echo '</table>';

        echo '</div>';
    }

    public static function renderPaymentMetaBox(\WP_Post $post): void
    {
        $payment_status = get_post_meta($post->ID, '_payment_status', true) ?: Order::PAYMENT_PENDING;
        $payment_method = get_post_meta($post->ID, '_payment_method', true) ?: Order::PAYMENT_CASH;

        echo '<table class="form-table">';
        
        // Payment status
        echo '<tr>';
        echo '<th><label for="payment_status">Payment Status:</label></th>';
        echo '<td>';
        echo '<select name="payment_status" id="payment_status" class="regular-text">';
        foreach (Order::getValidPaymentStatuses() as $status_option) {
            $selected = ($payment_status === $status_option) ? 'selected' : '';
            $display = ucfirst(str_replace('_', ' ', $status_option));
            echo "<option value='{$status_option}' {$selected}>{$display}</option>";
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Payment method
        echo '<tr>';
        echo '<th><label for="payment_method">Payment Method:</label></th>';
        echo '<td>';
        echo '<select name="payment_method" id="payment_method" class="regular-text">';
        foreach (Order::getValidPaymentMethods() as $method_option) {
            $selected = ($payment_method === $method_option) ? 'selected' : '';
            $display = ucfirst($method_option);
            echo "<option value='{$method_option}' {$selected}>{$display}</option>";
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
    }

    public static function saveCustomFields(int $post_id): void
    {
        // Verify nonce
        if (!isset($_POST['order_meta_box_nonce']) || !wp_verify_nonce($_POST['order_meta_box_nonce'], 'order_meta_box')) {
            return;
        }

        // Check if not autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save meta fields
        $fields = [
            'customer_id',
            'order_status' => '_status',
            'delivery_address',
            'pickup_time',
            'special_instructions',
            'payment_status',
            'payment_method',
        ];

        foreach ($fields as $field_key => $meta_key) {
            if (is_numeric($field_key)) {
                $field_key = $meta_key;
                $meta_key = "_{$field_key}";
            }

            if (isset($_POST[$field_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field_key]));
            }
        }
    }
}