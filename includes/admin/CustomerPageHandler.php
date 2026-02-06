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
        add_action('template_redirect', [self::class, 'ensure_public_access'], 1); // Early priority
        add_filter('page_template', [self::class, 'customer_page_template']);
    }

    /**
     * Ensure the orders page is always publicly accessible (no login required)
     */
    public static function ensure_public_access(): void
    {
        // Check if we're on the orders page
        if (!is_page('orders')) {
            return;
        }

        // Get the page to verify its status
        $page = get_page_by_path('orders');

        if ($page) {
            // Force update to published if not already
            if ($page->post_status !== 'publish') {
                wp_update_post([
                    'ID' => $page->ID,
                    'post_status' => 'publish',
                    'post_password' => '',
                ]);
            }
        }

        // No authentication checks - just let the page load
        // This runs early (priority 1) to prevent other plugins from redirecting
    }

    /**
     * Create the customer page programmatically
     */
    public static function create_customer_page(): void
    {
        // Check if page already exists
        $existing_page = get_page_by_path('orders');

        if (!$existing_page) {
            // Create new page
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
        } else {
            // Ensure existing page is always published and publicly accessible
            $updates = [];

            if ($existing_page->post_status !== 'publish') {
                $updates['post_status'] = 'publish';
            }

            // Remove password protection if any
            if (!empty($existing_page->post_password)) {
                $updates['post_password'] = '';
            }

            if (!empty($updates)) {
                $updates['ID'] = $existing_page->ID;
                wp_update_post($updates);
            }

            // Update meta to mark as customer page
            update_post_meta($existing_page->ID, '_squidly_customer_page', true);
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
