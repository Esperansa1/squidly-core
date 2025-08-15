<?php
/**
 * Plugin Name: Squidly Core
 * Plugin URI: https://squidly.local
 * Description: Core functionality for the Squidly restaurant system.
 * Version: 1.0.0
 * Author: Esperansa
 * License: MIT
 * Text Domain: squidly-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 🔒 Prevent direct access
}


require_once __DIR__ . '/vendor/autoload.php';	

// 📍 Define plugin constants
define( 'SQUIDLY_CORE_VERSION', '1.0.0' );
define( 'SQUIDLY_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SQUIDLY_CORE_URL', plugin_dir_url( __FILE__ ) );

# Register Post-Types
require_once __DIR__ . '/includes/core/PostTypeRegistry.php';
\PostTypeRegistry::register_all();


spl_autoload_register(function ($class) {
    $paths = [
        // Shared components
        'includes/shared/models/',
        'includes/shared/models/enums/',
        'includes/shared/interfaces/',
        'includes/shared/exceptions/',
        'includes/shared/abstracts/',
        
        // Domain models
        'includes/domains/customers/models/',
        'includes/domains/orders/models/',
        'includes/domains/products/models/',
        'includes/domains/payments/models/',
        'includes/domains/stores/models/',
        
        // Domain repositories
        'includes/domains/customers/repositories/',
        'includes/domains/orders/repositories/',
        'includes/domains/products/repositories/',
        'includes/domains/stores/repositories/',
        
        // Domain post types
        'includes/domains/customers/post-types/',
        'includes/domains/orders/post-types/',
        'includes/domains/products/post-types/',
        'includes/domains/stores/post-types/',
        
        // Payment system
        'includes/domains/payments/interfaces/',
        'includes/domains/payments/exceptions/',
        'includes/domains/payments/gateways/',
        'includes/domains/payments/services/',
        'includes/domains/payments/managers/',
        
        // Admin components
        'includes/admin/',
    ];

    foreach ($paths as $path) {
        $file = SQUIDLY_CORE_PATH . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Initialize admin menu system
require_once __DIR__ . '/includes/admin/AdminMenuManager.php';
AdminMenuManager::init();

// Register settings
add_action('admin_init', function() {
    register_setting('squidly_settings', 'squidly_currency');
    register_setting('squidly_settings', 'squidly_loyalty_rate');
    register_setting('squidly_settings', 'squidly_allow_guest_checkout');
    register_setting('squidly_settings', 'squidly_guest_cleanup_days');
});

// Add cleanup cron job
add_action('wp', function() {
    if (!wp_next_scheduled('squidly_cleanup_guests')) {
        wp_schedule_event(time(), 'daily', 'squidly_cleanup_guests');
    }
});

add_action('squidly_cleanup_guests', function() {
    $days = get_option('squidly_guest_cleanup_days', 30);
    $customerRepo = new CustomerRepository();
    $deleted = $customerRepo->cleanupOldGuests($days);
    
    if ($deleted > 0) {
        error_log("Squidly: Cleaned up {$deleted} old guest customers");
    }
});

// Plugin activation hook updates
register_activation_hook(__FILE__, function() {
    // Create necessary database tables (if needed in future)
    
    // Set default options
    add_option('squidly_currency', 'ILS');
    add_option('squidly_currency_symbol', '₪');
    add_option('squidly_loyalty_rate', 2.0);
    add_option('squidly_allow_guest_checkout', true);
    add_option('squidly_guest_cleanup_days', 30);
    add_option('squidly_default_order_status', 'pending');
    add_option('squidly_enable_online_ordering', true);
    
    // Register post types before flushing rewrite rules
    PostTypeRegistry::register_all();
    
    // Flush rewrite rules
    flush_rewrite_rules();
});