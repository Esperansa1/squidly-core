<?php
declare(strict_types=1);

/**
 * Customer Page Handler
 *
 * Creates a public WordPress page for the customer ordering interface
 */
class CustomerPageHandler
{
    public static function init(): void
    {
        add_action('init', [self::class, 'create_customer_page']);
        add_filter('page_template', [self::class, 'customer_page_template']);
    }

    /**
     * Create the customer page programmatically
     */
    public static function create_customer_page(): void
    {
        // Check if page already exists
        $existing_page = get_page_by_path('orders');

        if (!$existing_page) {
            wp_insert_post([
                'post_title' => 'Orders',
                'post_name' => 'orders',
                'post_content' => '<!-- Squidly Customer Ordering Interface -->',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'meta_input' => [
                    '_squidly_customer_page' => true
                ]
            ]);
        }
    }

    /**
     * Use custom template for customer page
     */
    public static function customer_page_template($template)
    {
        if (is_page('orders')) {
            $plugin_template = plugin_dir_path(__FILE__) . '../templates/customer-page.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}
