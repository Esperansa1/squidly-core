<?php

namespace Squidly\Domains\Payments\Hooks;

/**
 * Customizes how Squidly order items display in WooCommerce admin and emails
 *
 * Enhances the display of fee-based Squidly orders in WooCommerce admin interface
 * by making metadata more readable and adding Squidly order information.
 */
class OrderItemDisplay
{
    /**
     * Initialize hooks for order display customization
     */
    public static function init(): void
    {
        // Customize fee meta display in admin
        add_filter('woocommerce_order_item_display_meta_key', [__CLASS__, 'customize_meta_key'], 10, 3);

        // Add Squidly order info to admin order details
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'display_squidly_order_link']);
    }

    /**
     * Make Squidly metadata more readable in admin
     *
     * @param string $display_key The key to display
     * @param object $meta The meta object
     * @param object $item The order item object
     * @return string The customized display key
     */
    public static function customize_meta_key($display_key, $meta, $item)
    {
        $key_map = [
            '_squidly_product_id' => 'Squidly Product ID',
            '_squidly_item_type' => 'Item Type',
            '_squidly_customizations' => 'Customizations',
        ];

        return $key_map[$meta->key] ?? $display_key;
    }

    /**
     * Display link to Squidly order in WC admin
     *
     * @param WC_Order $order The WooCommerce order object
     */
    public static function display_squidly_order_link($order)
    {
        $squidly_order_id = $order->get_meta('_squidly_order_id');

        if ($squidly_order_id) {
            echo '<div class="order_data_column" style="clear:both; padding-top: 13px;">';
            echo '<h3>' . esc_html__('Squidly Order Information', 'squidly') . '</h3>';
            echo '<p><strong>' . esc_html__('Squidly Order ID:', 'squidly') . '</strong> #' . esc_html($squidly_order_id) . '</p>';

            $items_count = $order->get_meta('_squidly_order_items_count');
            if ($items_count) {
                echo '<p><strong>' . esc_html__('Items Count:', 'squidly') . '</strong> ' . esc_html($items_count) . '</p>';
            }

            // Add link to Squidly admin if available
            $admin_url = admin_url('admin.php?page=squidly-orders&order_id=' . $squidly_order_id);
            echo '<p><a href="' . esc_url($admin_url) . '" class="button">' . esc_html__('View in Squidly', 'squidly') . '</a></p>';
            echo '</div>';
        }
    }
}
