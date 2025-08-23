<?php
/**
 * Test Row Actions Hook
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

// Import OrderPostType to use the constant
require_once __DIR__ . '/includes/domains/orders/post-types/OrderPostType.php';

echo "<h1>🔧 Row Actions Hook Test</h1>";

// Check if we're in admin context
echo "<h2>🏛️ Admin Context Check:</h2>";
if (is_admin()) {
    echo "<div style='color: green;'>✅ We are in admin context</div>";
} else {
    echo "<div style='color: red;'>❌ We are NOT in admin context</div>";
}

// Check current screen
if (function_exists('get_current_screen')) {
    $screen = get_current_screen();
    if ($screen) {
        echo "<div style='background: #f7fafc; padding: 15px; margin: 10px 0;'>";
        echo "<strong>Current Screen:</strong><br>";
        echo "• ID: " . $screen->id . "<br>";
        echo "• Base: " . $screen->base . "<br>";
        echo "• Post Type: " . $screen->post_type . "<br>";
        echo "</div>";
    }
}

// Test the post_row_actions filter directly
echo "<h2>🧪 Testing post_row_actions Filter:</h2>";

// Get a test order
$orders_query = new WP_Query([
    'post_type' => OrderPostType::POST_TYPE,
    'posts_per_page' => 1
]);

if ($orders_query->have_posts()) {
    $orders_query->the_post();
    $test_post = get_post();
    wp_reset_postdata();
    
    echo "<div style='background: #e2e8f0; padding: 15px; margin: 10px 0;'>";
    echo "<strong>Testing with Order ID:</strong> " . $test_post->ID . "<br>";
    echo "<strong>Post Type:</strong> " . $test_post->post_type . "<br>";
    echo "</div>";
    
    // Simulate WordPress's default row actions
    $default_actions = [];
    $default_actions['edit'] = '<a href="' . get_edit_post_link($test_post->ID) . '">Edit</a>';
    if (get_post_status($test_post->ID) === 'publish') {
        $default_actions['view'] = '<a href="' . get_permalink($test_post->ID) . '">View</a>';
    }
    
    echo "<h3>🔧 Default Row Actions:</h3>";
    echo "<div style='background: #bee3f8; padding: 10px; margin: 5px 0;'>";
    foreach ($default_actions as $key => $action) {
        echo "<strong>{$key}:</strong> " . htmlspecialchars($action) . "<br>";
    }
    echo "</div>";
    
    // Test our custom filter
    echo "<h3>🧪 Testing Custom Row Actions Filter:</h3>";
    
    // Manually create and test PaymentAdminActions
    require_once __DIR__ . '/includes/domains/payments/admin/PaymentAdminActions.php';
    
    $payment_admin = new \Squidly\Domains\Payments\Admin\PaymentAdminActions();
    $modified_actions = $payment_admin->add_payment_row_actions($default_actions, $test_post);
    
    echo "<div style='background: #c6f6d5; padding: 10px; margin: 5px 0;'>";
    echo "<strong>Actions after applying our filter:</strong><br>";
    foreach ($modified_actions as $key => $action) {
        echo "<strong>{$key}:</strong> " . htmlspecialchars($action) . "<br>";
    }
    echo "</div>";
    
    // Check payment status again
    $payment_status_underscore = get_post_meta($test_post->ID, '_payment_status', true);
    $payment_status_no_underscore = get_post_meta($test_post->ID, 'payment_status', true);
    
    echo "<div style='background: #fef5e7; padding: 10px; margin: 5px 0;'>";
    echo "<strong>Payment Status Debug:</strong><br>";
    echo "• _payment_status: '" . ($payment_status_underscore ?: 'empty') . "'<br>";
    echo "• payment_status: '" . ($payment_status_no_underscore ?: 'empty') . "'<br>";
    echo "</div>";
    
} else {
    echo "<div style='color: red;'>❌ No orders found for testing</div>";
}

// Check if the filter is actually registered
echo "<h2>🎣 WordPress Hooks Check:</h2>";
global $wp_filter;
if (isset($wp_filter['post_row_actions'])) {
    echo "<div style='color: green;'>✅ post_row_actions filter is registered</div>";
    
    echo "<h3>📋 All post_row_actions callbacks:</h3>";
    echo "<div style='background: #f7fafc; padding: 15px; margin: 10px 0;'>";
    foreach ($wp_filter['post_row_actions']->callbacks as $priority => $callbacks) {
        echo "<strong>Priority {$priority}:</strong><br>";
        foreach ($callbacks as $callback_id => $callback_data) {
            $function = $callback_data['function'];
            if (is_array($function)) {
                $class = is_object($function[0]) ? get_class($function[0]) : $function[0];
                $method = $function[1];
                echo "  • {$class}::{$method}()<br>";
            } else {
                echo "  • {$function}()<br>";
            }
        }
    }
    echo "</div>";
} else {
    echo "<div style='color: red;'>❌ post_row_actions filter is NOT registered</div>";
}

// Check if PaymentBootstrap is initialized
echo "<h2>🚀 PaymentBootstrap Check:</h2>";
if (class_exists('Squidly\\Domains\\Payments\\Bootstrap\\PaymentBootstrap')) {
    echo "<div style='color: green;'>✅ PaymentBootstrap class exists</div>";
    
    // Check if the init hooks are set up
    if (has_action('init', ['Squidly\\Domains\\Payments\\Bootstrap\\PaymentBootstrap', 'initialize_components'])) {
        echo "<div style='color: green;'>✅ PaymentBootstrap initialize_components is hooked to init</div>";
    } else {
        echo "<div style='color: red;'>❌ PaymentBootstrap initialize_components is NOT hooked to init</div>";
    }
} else {
    echo "<div style='color: red;'>❌ PaymentBootstrap class does NOT exist</div>";
}

echo "<br><a href='/wp-admin/edit.php?post_type=" . OrderPostType::POST_TYPE . "' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Orders Admin</a>";