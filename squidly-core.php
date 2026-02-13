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
    // Fast path: Check pre-computed class map first (O(1) lookup)
    static $classMap = null;
    if ($classMap === null) {
        $classMap = require_once SQUIDLY_CORE_PATH . 'includes/autoload-map.php';
    }

    if (isset($classMap[$class])) {
        require_once SQUIDLY_CORE_PATH . $classMap[$class];
        return;
    }

    // Handle namespaced classes (e.g., Squidly\Domains\Payments\Bootstrap\PaymentBootstrap)
    if (strpos($class, 'Squidly\\Domains\\Payments\\') === 0) {
        // Convert namespace to file path
        $relative_class = str_replace('Squidly\\Domains\\Payments\\', '', $class);
        $parts = explode('\\', $relative_class);

        if (count($parts) === 2) {
            $folder = strtolower($parts[0]);  // e.g., 'bootstrap'
            $filename = $parts[1];            // e.g., 'PaymentBootstrap'
            $file = SQUIDLY_CORE_PATH . 'includes/domains/payments/' . $folder . '/' . $filename . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }

    // Fallback to original paths for non-namespaced classes not in map
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

        // Domain services
        'includes/domains/products/services/',

        // Domain REST controllers
        'includes/domains/products/rest/',
        'includes/domains/stores/rest/',

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
        'includes/domains/payments/rest/',
        'includes/domains/payments/admin/',
        'includes/domains/payments/hooks/',
        'includes/domains/payments/activation/',
        'includes/domains/payments/bootstrap/',

        // Admin components
        'includes/admin/',

        // API controllers
        'includes/api/controllers/',
    ];

    foreach ($paths as $path) {
        $file = SQUIDLY_CORE_PATH . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Initialize role manager
require_once __DIR__ . '/includes/admin/RoleManager.php';
\SquidlyCore\Admin\RoleManager::init();

// Register settings
// Consolidated admin initialization
add_action('admin_init', function() {
    // Register settings
    register_setting('squidly_settings', 'squidly_currency');
    register_setting('squidly_settings', 'squidly_allow_guest_checkout');
    register_setting('squidly_settings', 'squidly_guest_cleanup_days');

    // Initialize admin handlers if in admin context
    if (function_exists('AdminPageHandler::init')) {
        AdminPageHandler::init();
    }
    if (function_exists('CustomerPageHandler::init')) {
        CustomerPageHandler::init();
    }
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
    $customerRepo->cleanupOldGuests($days);
});

// Essential payment system classes (needed for hooks/activation)
require_once __DIR__ . '/includes/domains/payments/interfaces/PaymentProvider.php';
require_once __DIR__ . '/includes/domains/payments/services/PaymentService.php';
require_once __DIR__ . '/includes/domains/payments/gateways/WooProvider.php';
require_once __DIR__ . '/includes/domains/payments/hooks/PaymentStatusSync.php';
require_once __DIR__ . '/includes/domains/payments/hooks/OrderItemDisplay.php';
require_once __DIR__ . '/includes/domains/payments/activation/PaymentProductActivation.php';
require_once __DIR__ . '/includes/domains/payments/bootstrap/PaymentBootstrap.php';

// Essential services (needed for hooks/frontend)
require_once __DIR__ . '/includes/domains/orders/models/Cart.php';
require_once __DIR__ . '/includes/domains/orders/models/CartItem.php';
require_once __DIR__ . '/includes/domains/orders/services/CartService.php';
require_once __DIR__ . '/includes/domains/orders/services/DeliveryFeeService.php';
require_once __DIR__ . '/includes/domains/orders/activation/DeliveryPricingActivation.php';

// Load REST API controllers only when REST API is being initialized
add_action('rest_api_init', function() {
    // Admin REST API Controllers
    require_once __DIR__ . '/includes/domains/products/rest/ProductGroupRestController.php';
    require_once __DIR__ . '/includes/domains/products/rest/IngredientRestController.php';
    require_once __DIR__ . '/includes/domains/products/rest/IngredientGroupRestController.php';
    require_once __DIR__ . '/includes/domains/stores/rest/StoreBranchRestController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/OrderRestController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/DashboardAnalyticsController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/DeliveryTierRestController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/DeliveryTimeSurchargeRestController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/DeliveryLoyaltyDiscountRestController.php';
    require_once __DIR__ . '/includes/domains/customers/rest/CustomerRestController.php';
    require_once __DIR__ . '/includes/domains/payments/rest/PaymentRestController.php';

    // Public REST API Controllers (no authentication required)
    require_once __DIR__ . '/includes/api/PublicRestController.php';
    require_once __DIR__ . '/includes/domains/stores/rest/PublicBranchRestController.php';
    require_once __DIR__ . '/includes/domains/products/rest/PublicProductRestController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/PublicOrderRestController.php';
    require_once __DIR__ . '/includes/domains/customers/rest/PublicCustomerRestController.php';
    require_once __DIR__ . '/includes/domains/customers/rest/PublicAuthRestController.php';
    require_once __DIR__ . '/includes/domains/orders/rest/PublicCartRestController.php';

    // Initialize API bootstraps
    require_once __DIR__ . '/includes/api/AdminApiBootstrap.php';
    require_once __DIR__ . '/includes/api/PublicApiBootstrap.php';
    AdminApiBootstrap::init();
    PublicApiBootstrap::init();
}, 5);

// Initialize Payment Gateway System immediately after classes are loaded
if (class_exists('Squidly\Domains\Payments\Bootstrap\PaymentBootstrap')) {
    \Squidly\Domains\Payments\Bootstrap\PaymentBootstrap::init();
}

// Initialize Order Item Display customization
add_action('init', function() {
    if (class_exists('Squidly\Domains\Payments\Hooks\OrderItemDisplay')) {
        \Squidly\Domains\Payments\Hooks\OrderItemDisplay::init();
    }
}, 15);

// Load admin-specific components only in admin context
if (is_admin()) {
    require_once __DIR__ . '/includes/admin/AdminPageHandler.php';
    require_once __DIR__ . '/includes/admin/CustomerPageHandler.php';
    require_once __DIR__ . '/includes/domains/payments/admin/PaymentAdminActions.php';
}

// Payment system activation hooks
register_activation_hook(__FILE__, function() {
    // Ensure WooCommerce is loaded before creating payment product
    if (class_exists('WooCommerce')) {
        if (class_exists('Squidly\Domains\Payments\Activation\PaymentProductActivation')) {
            \Squidly\Domains\Payments\Activation\PaymentProductActivation::createPaymentProduct();
        }
    } else {
        // Schedule creation for later when WooCommerce is available
        add_action('init', function() {
            if (class_exists('WooCommerce') && class_exists('Squidly\Domains\Payments\Activation\PaymentProductActivation')) {
                // Only create if not already created
                $existing = get_option('squidly_wc_payment_product_id');
                if (!$existing || !wc_get_product($existing)) {
                    \Squidly\Domains\Payments\Activation\PaymentProductActivation::createPaymentProduct();
                }
            }
        });
    }
});

register_deactivation_hook(__FILE__, function() {
    if (class_exists('WooCommerce') && class_exists('Squidly\Domains\Payments\Activation\PaymentProductActivation')) {
        \Squidly\Domains\Payments\Activation\PaymentProductActivation::cleanupPaymentProduct();
    }
});

// Plugin activation hook updates
register_activation_hook(__FILE__, function() {
    // Create delivery pricing database tables
    DeliveryPricingActivation::createTables();

    // Set default options
    add_option('squidly_currency', 'ILS');
    add_option('squidly_currency_symbol', '₪');
    add_option('squidly_loyalty_rate', 2.0);
    add_option('squidly_allow_guest_checkout', true);
    add_option('squidly_guest_cleanup_days', 30);
    add_option('squidly_default_order_status', 'pending');
    add_option('squidly_enable_online_ordering', true);
    add_option('squidly_google_client_id', '');
    add_option('squidly_sms_provider', 'mock');

    // Register post types before flushing rewrite rules
    PostTypeRegistry::register_all();
    
    // Flush rewrite rules
    flush_rewrite_rules();
});