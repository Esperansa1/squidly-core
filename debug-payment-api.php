<?php
/**
 * Debug Payment API
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

// Import OrderPostType
require_once __DIR__ . '/includes/domains/orders/post-types/OrderPostType.php';

echo "<h1>🔍 Payment API Debug</h1>";

// Check if REST API is registered
echo "<h2>🌐 REST API Registration Check:</h2>";

global $wp_rest_server;
if (empty($wp_rest_server)) {
    $wp_rest_server = rest_get_server();
}

$routes = $wp_rest_server->get_routes();
$squidly_routes = [];

foreach ($routes as $route => $methods) {
    if (strpos($route, '/squidly/') === 0) {
        $squidly_routes[$route] = $methods;
    }
}

if (empty($squidly_routes)) {
    echo "<div style='color: red;'>❌ No Squidly REST routes found!</div>";
} else {
    echo "<div style='color: green;'>✅ Found Squidly REST routes:</div>";
    echo "<div style='background: #f7fafc; padding: 15px; margin: 10px 0;'>";
    foreach ($squidly_routes as $route => $methods) {
        echo "<strong>{$route}</strong><br>";
        foreach ($methods as $method => $details) {
            if (is_array($details)) {
                echo "  • {$method}<br>";
            }
        }
    }
    echo "</div>";
}

// Check if PaymentRestController class exists
echo "<h2>📦 PaymentRestController Check:</h2>";
if (class_exists('Squidly\\Domains\\Payments\\Rest\\PaymentRestController')) {
    echo "<div style='color: green;'>✅ PaymentRestController class exists</div>";
    
    // Try to instantiate it
    try {
        $controller = new \Squidly\Domains\Payments\Rest\PaymentRestController();
        echo "<div style='color: green;'>✅ PaymentRestController can be instantiated</div>";
    } catch (Exception $e) {
        echo "<div style='color: red;'>❌ PaymentRestController instantiation failed: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div style='color: red;'>❌ PaymentRestController class NOT found</div>";
}

// Check PaymentService
echo "<h2>🔧 PaymentService Check:</h2>";
if (class_exists('Squidly\\Domains\\Payments\\Services\\PaymentService')) {
    echo "<div style='color: green;'>✅ PaymentService class exists</div>";
    
    try {
        $service = new \Squidly\Domains\Payments\Services\PaymentService();
        echo "<div style='color: green;'>✅ PaymentService can be instantiated</div>";
    } catch (Exception $e) {
        echo "<div style='color: red;'>❌ PaymentService instantiation failed: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div style='color: red;'>❌ PaymentService class NOT found</div>";
}

// Check WooCommerce integration
echo "<h2>🛒 WooCommerce Integration Check:</h2>";
if (class_exists('WooCommerce')) {
    echo "<div style='color: green;'>✅ WooCommerce is active</div>";
    
    // Check payment product
    $payment_product_id = get_option('squidly_wc_payment_product_id');
    if ($payment_product_id) {
        $product = wc_get_product($payment_product_id);
        if ($product) {
            echo "<div style='color: green;'>✅ Payment product exists (ID: {$payment_product_id})</div>";
        } else {
            echo "<div style='color: red;'>❌ Payment product ID exists but product not found</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ Payment product not configured</div>";
    }
    
    if (class_exists('Squidly\\Domains\\Payments\\Gateways\\WooProvider')) {
        echo "<div style='color: green;'>✅ WooProvider class exists</div>";
        
        try {
            $provider = new \Squidly\Domains\Payments\Gateways\WooProvider();
            echo "<div style='color: green;'>✅ WooProvider can be instantiated</div>";
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ WooProvider instantiation failed: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ WooProvider class NOT found</div>";
    }
} else {
    echo "<div style='color: red;'>❌ WooCommerce is NOT active</div>";
}

// Get a test order for simulation
echo "<h2>🧪 Test Payment Simulation:</h2>";

$orders_query = new WP_Query([
    'post_type' => \OrderPostType::POST_TYPE,
    'posts_per_page' => 1,
    'post_status' => 'publish'
]);

if ($orders_query->have_posts()) {
    $orders_query->the_post();
    $test_order_id = get_the_ID();
    wp_reset_postdata();
    
    echo "<div style='background: #e2e8f0; padding: 15px; margin: 10px 0;'>";
    echo "<strong>Using Test Order ID:</strong> {$test_order_id}<br>";
    echo "</div>";
    
    // Simulate the payment request
    if (class_exists('Squidly\\Domains\\Payments\\Services\\PaymentService') && 
        class_exists('WooCommerce') && 
        get_option('squidly_wc_payment_product_id')) {
        
        echo "<h3>🔄 Simulating Payment Request:</h3>";
        
        try {
            $payment_service = new \Squidly\Domains\Payments\Services\PaymentService();
            
            // Test with amount 25.50
            $result = $payment_service->startPayment($test_order_id, 25.50);
            
            if (isset($result['checkout_url'])) {
                echo "<div style='color: green;'>✅ Payment simulation successful!</div>";
                echo "<div style='background: #c6f6d5; padding: 10px; margin: 5px 0;'>";
                echo "<strong>Result:</strong><br>";
                echo "• Checkout URL: " . $result['checkout_url'] . "<br>";
                if (isset($result['wc_order_id'])) {
                    echo "• WooCommerce Order ID: " . $result['wc_order_id'] . "<br>";
                }
                echo "</div>";
            } else {
                echo "<div style='color: red;'>❌ Payment simulation failed - no checkout URL returned</div>";
                echo "<div style='background: #fed7d7; padding: 10px; margin: 5px 0;'>";
                echo "<strong>Result:</strong><br>";
                echo "<pre>" . print_r($result, true) . "</pre>";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Payment simulation threw exception: " . $e->getMessage() . "</div>";
            echo "<div style='background: #fed7d7; padding: 10px; margin: 5px 0;'>";
            echo "<strong>Stack trace:</strong><br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
            echo "</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠️ Cannot simulate payment - missing required components</div>";
    }
} else {
    echo "<div style='color: red;'>❌ No test orders found</div>";
}

// Check WordPress error log
echo "<h2>📝 Error Log Check:</h2>";
echo "<div style='background: #bee3f8; padding: 15px; margin: 10px 0;'>";
echo "<strong>Check these for errors:</strong><br>";
echo "• Browser Console (F12 → Console)<br>";
echo "• WordPress Debug Log (if WP_DEBUG is enabled)<br>";
echo "• Server Error Log<br>";
echo "• Network tab in Developer Tools when clicking Pay button<br>";
echo "</div>";

// REST API test URLs
echo "<h2>🔗 Test REST API Directly:</h2>";
$nonce = wp_create_nonce('wp_rest');
echo "<div style='background: #fef5e7; padding: 15px; margin: 10px 0;'>";
echo "<strong>Test these URLs in browser or Postman:</strong><br>";
echo "• GET: <code>http://squidly.local/wp-json/squidly/v1/pay/</code><br>";
echo "• POST: <code>http://squidly.local/wp-json/squidly/v1/pay/start</code><br>";
echo "• Headers: <code>X-WP-Nonce: {$nonce}</code><br>";
echo "• Body: <code>{\"order_id\": {$test_order_id}, \"amount\": 25.50}</code><br>";
echo "</div>";

echo "<br><a href='/wp-admin/edit.php?post_type=" . \OrderPostType::POST_TYPE . "' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Orders</a>";