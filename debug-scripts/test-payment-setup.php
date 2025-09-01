<?php
/**
 * Test Payment Gateway Setup Script
 * 
 * Run this once to create test data for payment gateway testing
 * Access via: /wp-content/plugins/squidly-core/test-payment-setup.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🧪 Payment Gateway Test Setup</h1>";

// Step 1: Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
    echo "❌ <strong>WooCommerce is NOT active!</strong><br>";
    echo "Please install and activate WooCommerce first.";
    echo "</div>";
} else {
    echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
    echo "✅ <strong>WooCommerce is active!</strong>";
    echo "</div>";
}

// Step 2: Check if payment classes are loaded
$classes_to_check = [
    'Squidly\Domains\Payments\Bootstrap\PaymentBootstrap',
    'Squidly\Domains\Payments\Services\PaymentService', 
    'Squidly\Domains\Payments\Gateways\WooProvider',
    'Squidly\Domains\Payments\Rest\PaymentRestController',
    'Squidly\Domains\Payments\Admin\PaymentAdminActions'
];

echo "<h2>📦 Payment Classes Status:</h2>";
foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "<div style='color: green;'>✅ {$class}</div>";
    } else {
        echo "<div style='color: red;'>❌ {$class} - NOT FOUND</div>";
    }
}

// Step 3: Create a test Squidly order
echo "<h2>📝 Creating Test Squidly Order:</h2>";

// Import OrderPostType to use the constant
require_once __DIR__ . '/includes/domains/orders/post-types/OrderPostType.php';

$test_order_data = [
    'post_title' => 'Test Order #' . time(),
    'post_type' => OrderPostType::POST_TYPE,
    'post_status' => 'publish',
    'meta_input' => [
        'customer_id' => 1,
        'total_amount' => '25.50',
        'subtotal' => '23.00',
        'tax_amount' => '2.50',
        'delivery_fee' => '0.00',
        'payment_status' => 'pending',
        'payment_method' => 'not_set',
        'status' => 'confirmed',
        'order_date' => current_time('mysql'),
        'notes' => 'Test order for payment gateway testing'
    ]
];

$test_order_id = wp_insert_post($test_order_data);

if ($test_order_id) {
    echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
    echo "✅ <strong>Test Order Created!</strong><br>";
    echo "Order ID: {$test_order_id}<br>";
    echo "Total: $25.50<br>";
    echo "Status: pending";
    echo "</div>";
} else {
    echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
    echo "❌ <strong>Failed to create test order!</strong>";
    echo "</div>";
}

// Step 4: Check if payment product exists
echo "<h2>🛒 Payment Product Status:</h2>";
$payment_product_id = get_option('squidly_wc_payment_product_id');

if ($payment_product_id) {
    $product = wc_get_product($payment_product_id);
    if ($product) {
        echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
        echo "✅ <strong>Payment Product Found!</strong><br>";
        echo "Product ID: {$payment_product_id}<br>";
        echo "Product Name: " . $product->get_name() . "<br>";
        echo "Product Status: " . $product->get_status();
        echo "</div>";
    } else {
        echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
        echo "❌ <strong>Payment Product ID exists but product not found!</strong><br>";
        echo "Stored ID: {$payment_product_id}";
        echo "</div>";
    }
} else {
    echo "<div style='background: #ed8936; color: white; padding: 15px; margin: 10px 0;'>";
    echo "⚠️ <strong>No payment product found!</strong><br>";
    echo "Try deactivating and reactivating the Squidly Core plugin.";
    echo "</div>";
}

// Step 5: Test REST API endpoints
echo "<h2>🌐 REST API Endpoints:</h2>";
echo "<div style='background: #e2e8f0; padding: 15px; margin: 10px 0;'>";
echo "<strong>Available endpoints:</strong><br>";
echo "• POST /wp-json/squidly/v1/pay/start<br>";
echo "• POST /wp-json/squidly/v1/pay/refund<br>";
echo "</div>";

// Step 6: Provide testing instructions
if ($test_order_id) {
    echo "<h2>🧪 How to Test:</h2>";
    echo "<div style='background: #bee3f8; padding: 15px; margin: 10px 0;'>";
    echo "<ol>";
    echo "<li>Go to <strong>WordPress Admin → Squidly Orders</strong></li>";
    echo "<li>Find Order ID: <strong>{$test_order_id}</strong></li>";
    echo "<li>Look for <strong>'Pay'</strong> button in row actions</li>";
    echo "<li>Click 'Pay' and enter amount: <strong>25.50</strong></li>";
    echo "<li>You should see WooCommerce checkout page open</li>";
    echo "<li>Complete payment using your payment gateway (PayPlus, etc.)</li>";
    echo "<li>Return to Squidly Orders and check if status updated to 'paid'</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='background: #fef5e7; padding: 15px; margin: 10px 0;'>";
    echo "<strong>⚠️ Important Notes:</strong><br>";
    echo "• Make sure you have a payment gateway configured in WooCommerce<br>";
    echo "• Use test/sandbox mode for testing<br>";
    echo "• Check browser console for any JavaScript errors<br>";
    echo "• Check WordPress debug log for PHP errors";
    echo "</div>";
}

echo "<br><a href='/wp-admin/edit.php?post_type=" . OrderPostType::POST_TYPE . "' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Squidly Orders</a>";