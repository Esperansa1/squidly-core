<?php
/**
 * Test Data Management Hub
 * 
 * Central interface for managing all test data and debugging utilities
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🎛️ Test Data Management Hub</h1>";

echo "<div style='background: #e7f3ff; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;'>";
echo "<h2>📋 Available Operations</h2>";
echo "<p>Choose from the options below to manage your test data and debug the system:</p>";
echo "</div>";

// Main Operations
echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;'>";

// Create Test Data Section
echo "<div style='background: #d4edda; padding: 20px; border-left: 4px solid #28a745;'>";
echo "<h3>🍔 Create Test Data</h3>";
echo "<p>Generate comprehensive test data including complex hamburger restaurant products, customers, and orders.</p>";
echo "<ul>";
echo "<li>35+ Ingredients (meats, buns, cheese, toppings, sauces, sides)</li>";
echo "<li>6 Product Groups for customization</li>";
echo "<li>3 Complex products with multiple groups</li>";
echo "<li>2 Store branches with full details</li>";
echo "<li>2 Customers with preferences</li>";
echo "<li>2 Complete orders with modifications</li>";
echo "</ul>";
echo "<a href='../test-data/create-full-store-test.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>🏪 Create Full Store Test Data</a>";
echo "</div>";

// Cleanup Section  
echo "<div style='background: #f8d7da; padding: 20px; border-left: 4px solid #dc3545;'>";
echo "<h3>🧹 Cleanup All Data</h3>";
echo "<p>Remove all test data from the system including orders, customers, products, and WooCommerce integration data.</p>";
echo "<ul>";
echo "<li>All Orders and Order Items</li>";
echo "<li>All Customers</li>";
echo "<li>All Products and Product Groups</li>";
echo "<li>All Ingredients and Group Items</li>";
echo "<li>All Store Branches</li>";
echo "<li>All WooCommerce Payment Orders</li>";
echo "</ul>";
echo "<strong style='color: #dc3545;'>⚠️ WARNING: This cannot be undone!</strong><br>";
echo "<a href='../test-data/cleanup-all-test-data.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>🗑️ Cleanup All Test Data</a>";
echo "</div>";

echo "</div>";

// Debug Scripts Section
echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
echo "<h3>🔧 Debug Scripts</h3>";
echo "<p>Access various debugging utilities for development and troubleshooting:</p>";
echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;'>";

$debug_scripts = [
    'debug-payment.php' => ['Payment System Debug', 'Debug payment processing and integration'],
    'debug-payment-api.php' => ['Payment API Test', 'Test payment API endpoints'],
    'debug-pay-button.php' => ['Pay Button Debug', 'Debug admin pay button functionality'],
    'test-payment-setup.php' => ['Payment Setup Check', 'Validate payment system setup'],
    'create-payment-product.php' => ['Create Payment Product', 'Manually create WooCommerce payment product'],
    'check-orders.php' => ['Order Inspector', 'Inspect order data and structure'],
];

foreach ($debug_scripts as $file => $info) {
    echo "<div style='background: white; padding: 10px; border: 1px solid #ddd; border-radius: 5px;'>";
    echo "<strong>{$info[0]}</strong><br>";
    echo "<small style='color: #666;'>{$info[1]}</small><br>";
    echo "<a href='../../debug-scripts/{$file}' style='color: #007cba; font-size: 12px; text-decoration: none;'>→ Run Script</a>";
    echo "</div>";
}

echo "</div>";
echo "</div>";

// System Status
echo "<div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>";
echo "<h3>📊 Quick System Status</h3>";

// Check current data counts
$order_count = wp_count_posts('order')->publish ?? 0;
$customer_count = wp_count_posts('customer')->publish ?? 0;
$product_count = wp_count_posts('product')->publish ?? 0;
$ingredient_count = wp_count_posts('ingredient')->publish ?? 0;

echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;'>";
echo "<div><strong>Orders:</strong> {$order_count}</div>";
echo "<div><strong>Customers:</strong> {$customer_count}</div>";  
echo "<div><strong>Products:</strong> {$product_count}</div>";
echo "<div><strong>Ingredients:</strong> {$ingredient_count}</div>";

// Check payment system
$payment_product_exists = get_posts([
    'post_type' => 'product',
    'meta_query' => [['key' => '_squidly_payment_product', 'value' => 'yes']],
    'posts_per_page' => 1
]);

$payment_status = $payment_product_exists ? '✅ Ready' : '❌ Missing';
echo "<div><strong>Payment System:</strong> {$payment_status}</div>";

// Check WooCommerce
$woo_active = class_exists('WC_Order') ? '✅ Active' : '❌ Inactive';
echo "<div><strong>WooCommerce:</strong> {$woo_active}</div>";

echo "</div>";
echo "</div>";

// Recent Activity Log
echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-left: 4px solid #6c757d;'>";
echo "<h3>📋 Usage Instructions</h3>";
echo "<ol>";
echo "<li><strong>Fresh Start:</strong> Run cleanup script, then create new test data</li>";
echo "<li><strong>Test Payment Flow:</strong> Create test data, then test payments in WordPress admin</li>";
echo "<li><strong>Debug Issues:</strong> Use debug scripts to troubleshoot specific problems</li>";
echo "<li><strong>Development:</strong> Use debug scripts to test new features</li>";
echo "</ol>";
echo "</div>";
?>