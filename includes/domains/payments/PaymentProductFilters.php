<?php

namespace Squidly\Domains\Payments;

/**
 * Filters to customize the payment product display
 * Hides the technical payment product from customers and admins where appropriate
 */
class PaymentProductFilters
{
    private static $payment_product_id = null;

    /**
     * Initialize filters
     */
    public static function init(): void
    {
        self::$payment_product_id = get_option('squidly_wc_payment_product_id');

        if (!self::$payment_product_id) {
            return;
        }

        // Exclude from product queries (shop, search, archives)
        add_filter('woocommerce_product_query_meta_query', [__CLASS__, 'exclude_from_queries'], 10, 2);
        add_filter('pre_get_posts', [__CLASS__, 'exclude_from_admin_list'], 10, 1);

        // Customize display name on order items
        add_filter('woocommerce_order_item_name', [__CLASS__, 'customize_order_item_name'], 10, 3);

        // Hide from related products
        add_filter('woocommerce_related_products', [__CLASS__, 'exclude_from_related'], 10, 3);

        // Exclude from product shortcodes
        add_filter('woocommerce_shortcode_products_query', [__CLASS__, 'exclude_from_shortcodes'], 10, 3);
    }

    /**
     * Exclude payment product from WooCommerce queries
     */
    public static function exclude_from_queries($meta_query, $query)
    {
        if (!self::$payment_product_id) {
            return $meta_query;
        }

        // Only apply on frontend
        if (is_admin()) {
            return $meta_query;
        }

        $meta_query[] = [
            'key'     => '_payment_product',
            'compare' => 'NOT EXISTS',
        ];

        return $meta_query;
    }

    /**
     * Exclude from admin product list (optional - makes admin cleaner)
     */
    public static function exclude_from_admin_list($query)
    {
        global $pagenow, $typenow;

        if (!self::$payment_product_id) {
            return $query;
        }

        // Only on product admin pages
        if ($pagenow === 'edit.php' && $typenow === 'product' && is_admin()) {
            $query->set('post__not_in', [self::$payment_product_id]);
        }

        return $query;
    }

    /**
     * Customize the display name on order items
     * Shows "Order #123" instead of "Squidly Payment"
     */
    public static function customize_order_item_name($product_name, $item, $is_visible)
    {
        if (!self::$payment_product_id || !$item) {
            return $product_name;
        }

        $product_id = $item->get_product_id();

        if ($product_id == self::$payment_product_id) {
            // Get Squidly order ID from item meta
            $squidly_order_id = $item->get_meta('_squidly_order_id');

            if ($squidly_order_id) {
                return sprintf(__('Order #%d', 'squidly'), $squidly_order_id);
            }

            // Fallback to items summary if available
            $items_summary = $item->get_meta('_squidly_items_summary');
            if ($items_summary) {
                return $items_summary;
            }

            // Final fallback
            return __('Restaurant Order', 'squidly');
        }

        return $product_name;
    }

    /**
     * Exclude from related products
     */
    public static function exclude_from_related($related_posts, $product_id, $args)
    {
        if (!self::$payment_product_id) {
            return $related_posts;
        }

        return array_diff($related_posts, [self::$payment_product_id]);
    }

    /**
     * Exclude from product shortcodes ([products], [recent_products], etc.)
     */
    public static function exclude_from_shortcodes($query_args, $atts, $type)
    {
        if (!self::$payment_product_id) {
            return $query_args;
        }

        if (!isset($query_args['post__not_in'])) {
            $query_args['post__not_in'] = [];
        }

        $query_args['post__not_in'][] = self::$payment_product_id;

        return $query_args;
    }
}
