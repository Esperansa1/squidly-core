<?php
/**
 * Check Squidly Orders Status
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Import OrderPostType to use the constant
require_once __DIR__ . '/includes/domains/orders/post-types/OrderPostType.php';

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>📋 Squidly Orders Status Check</h1>";

// Check if squidly_order post type is registered
echo "<h2>📝 Post Type Registration:</h2>";
$post_types = get_post_types(['_builtin' => false], 'objects');

if (isset($post_types['squidly_order'])) {
    $squidly_order_type = $post_types['squidly_order'];
    echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
    echo "✅ <strong>squidly_order post type is registered!</strong><br>";
    echo "• Label: " . $squidly_order_type->labels->name . "<br>";
    echo "• Public: " . ($squidly_order_type->public ? 'Yes' : 'No') . "<br>";
    echo "• Show in menu: " . ($squidly_order_type->show_in_menu ? 'Yes' : 'No') . "<br>";
    echo "• Show UI: " . ($squidly_order_type->show_ui ? 'Yes' : 'No');
    echo "</div>";
} else {
    echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
    echo "❌ <strong>squidly_order post type is NOT registered!</strong><br>";
    echo "This is why you can't see orders in the admin.";
    echo "</div>";
}

// List all custom post types for reference
echo "<h3>📋 All Custom Post Types:</h3>";
echo "<div style='background: #f7fafc; padding: 15px; margin: 10px 0;'>";
foreach ($post_types as $post_type => $object) {
    echo "• <strong>{$post_type}</strong> - " . $object->labels->name . "<br>";
}
echo "</div>";

// Query for order posts directly (using correct post type name)
echo "<h2>🔍 Direct Database Query for Squidly Orders:</h2>";

$orders_query = new WP_Query([
    'post_type' => OrderPostType::POST_TYPE,
    'post_status' => ['publish', 'draft', 'private'],
    'posts_per_page' => -1,
    'meta_query' => []
]);

if ($orders_query->have_posts()) {
    echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
    echo "✅ <strong>Found " . $orders_query->found_posts . " Squidly orders in database!</strong>";
    echo "</div>";
    
    echo "<h3>📋 Order Details:</h3>";
    while ($orders_query->have_posts()) {
        $orders_query->the_post();
        $order_id = get_the_ID();
        $order_meta = get_post_meta($order_id);
        
        echo "<div style='background: #e6fffa; padding: 15px; margin: 10px 0; border: 1px solid #4fd1c7;'>";
        echo "<strong>Order ID:</strong> {$order_id}<br>";
        echo "<strong>Title:</strong> " . get_the_title() . "<br>";
        echo "<strong>Status:</strong> " . get_post_status() . "<br>";
        echo "<strong>Date:</strong> " . get_the_date('Y-m-d H:i:s') . "<br>";
        
        // Show important meta fields
        $important_meta = ['total_amount', 'payment_status', 'payment_method', 'customer_id', '_wc_order_id', '_payment_status', '_tx_id'];
        echo "<strong>Meta Data:</strong><br>";
        foreach ($important_meta as $meta_key) {
            $meta_value = get_post_meta($order_id, $meta_key, true);
            if ($meta_value) {
                echo "  • {$meta_key}: {$meta_value}<br>";
            }
        }
        echo "</div>";
    }
    wp_reset_postdata();
} else {
    echo "<div style='background: #ed8936; color: white; padding: 15px; margin: 10px 0;'>";
    echo "⚠️ <strong>No Squidly orders found in database!</strong><br>";
    echo "The test setup script may not have run successfully.";
    echo "</div>";
}

// Create a test order right now
echo "<h2>🏗️ Create Test Order Now:</h2>";

$test_order_data = [
    'post_title' => 'Test Order #' . time(),
    'post_type' => OrderPostType::POST_TYPE,
    'post_status' => 'publish',
    'post_content' => 'Test order created for payment gateway testing',
    'meta_input' => [
        'customer_id' => 1,
        'total_amount' => '35.75',
        'subtotal' => '32.50',
        'tax_amount' => '3.25',
        'delivery_fee' => '0.00',
        'payment_status' => 'pending',
        'payment_method' => 'not_set',
        'status' => 'confirmed',
        'order_date' => current_time('mysql'),
        'notes' => 'Created by check-orders.php script for testing'
    ]
];

$new_order_id = wp_insert_post($test_order_data);

if ($new_order_id && !is_wp_error($new_order_id)) {
    echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
    echo "✅ <strong>New Test Order Created!</strong><br>";
    echo "Order ID: {$new_order_id}<br>";
    echo "Total: $35.75<br>";
    echo "Status: pending";
    echo "</div>";
} else {
    echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
    echo "❌ <strong>Failed to create test order!</strong><br>";
    if (is_wp_error($new_order_id)) {
        echo "Error: " . $new_order_id->get_error_message();
    }
    echo "</div>";
}

// Check WordPress admin menu
echo "<h2>📑 WordPress Admin Menu Check:</h2>";
global $menu, $submenu;

$squidly_menu_found = false;
if ($menu) {
    foreach ($menu as $menu_item) {
        if (isset($menu_item[0]) && (strpos($menu_item[0], 'Squidly') !== false || strpos($menu_item[2], 'squidly') !== false)) {
            echo "<div style='color: green; margin: 5px 0;'>✅ Found Squidly menu: " . $menu_item[0] . " → " . $menu_item[2] . "</div>";
            $squidly_menu_found = true;
        }
    }
}

if (!$squidly_menu_found) {
    echo "<div style='color: red; margin: 5px 0;'>❌ No Squidly menu found in admin</div>";
}

// Check if edit.php?post_type=squidly_order works
echo "<h2>🔗 Admin Links:</h2>";
echo "<div style='background: #bee3f8; padding: 15px; margin: 10px 0;'>";
echo "<strong>Try these links:</strong><br>";
echo "• <a href='/wp-admin/edit.php?post_type=" . OrderPostType::POST_TYPE . "' target='_blank' style='color: #3182ce;'>Direct link to Orders</a><br>";
echo "• <a href='/wp-admin/post-new.php?post_type=" . OrderPostType::POST_TYPE . "' target='_blank' style='color: #3182ce;'>Add New Order</a><br>";
echo "• <a href='/wp-admin/' target='_blank' style='color: #3182ce;'>WordPress Admin Dashboard</a>";
echo "</div>";

// Show PostTypeRegistry check
echo "<h2>🔧 PostTypeRegistry Check:</h2>";
if (class_exists('PostTypeRegistry')) {
    echo "<div style='color: green; margin: 5px 0;'>✅ PostTypeRegistry class exists</div>";
    
    // Try to manually register post types
    try {
        \PostTypeRegistry::register_all();
        echo "<div style='color: green; margin: 5px 0;'>✅ PostTypeRegistry::register_all() called successfully</div>";
    } catch (Exception $e) {
        echo "<div style='color: red; margin: 5px 0;'>❌ PostTypeRegistry::register_all() failed: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div style='color: red; margin: 5px 0;'>❌ PostTypeRegistry class not found</div>";
}

echo "<h2>💡 Troubleshooting Steps:</h2>";
echo "<div style='background: #fef5e7; padding: 15px; margin: 10px 0;'>";
echo "<ol>";
echo "<li><strong>If post type is not registered:</strong> Check if PostTypeRegistry is loading correctly</li>";
echo "<li><strong>If orders exist but not visible:</strong> Try the direct admin links above</li>";
echo "<li><strong>If no orders found:</strong> Use the test order creation above</li>";
echo "<li><strong>Check WordPress admin menu:</strong> Look for 'Squidly Orders' or similar menu item</li>";
echo "<li><strong>Clear cache:</strong> If using any caching plugins, clear the cache</li>";
echo "</ol>";
echo "</div>";

echo "<br><a href='/wp-admin/edit.php?post_type=" . OrderPostType::POST_TYPE . "' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Try Squidly Orders Admin</a>";
echo "<a href='test-payment-setup.php' style='background: #48bb78; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Payment Setup</a>";