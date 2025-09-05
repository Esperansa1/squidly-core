<?php
declare(strict_types=1);

/**
 * Admin Page Handler
 * 
 * Creates a clean WordPress page for the admin interface
 */
class AdminPageHandler
{
    public static function init(): void
    {
        add_action('init', [self::class, 'create_admin_page']);
        add_action('template_redirect', [self::class, 'handle_admin_page']);
        add_filter('page_template', [self::class, 'admin_page_template']);
    }

    /**
     * Create the admin page programmatically
     */
    public static function create_admin_page(): void
    {
        // Check if page already exists
        $existing_page = get_page_by_path('restaurant-admin');
        
        if (!$existing_page) {
            wp_insert_post([
                'post_title' => 'Restaurant Admin',
                'post_name' => 'restaurant-admin',
                'post_content' => '<!-- Squidly Admin Interface -->',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'meta_input' => [
                    '_squidly_admin_page' => true
                ]
            ]);
        }
    }

    /**
     * Handle admin page access and authentication
     */
    public static function handle_admin_page(): void
    {
        if (!is_page('restaurant-admin')) {
            return;
        }

        // Check authentication
        if (!current_user_can('manage_options')) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
    }

    /**
     * Use custom template for admin page
     */
    public static function admin_page_template($template)
    {
        if (is_page('restaurant-admin')) {
            $plugin_template = plugin_dir_path(__FILE__) . '../templates/admin-page.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}