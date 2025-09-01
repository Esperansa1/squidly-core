<?php
/**
 * Debug Pay Button Visibility
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

echo "<h1>🔍 Pay Button Debug</h1>";

// Check if PaymentAdminActions class is loaded
echo "<h2>📦 Class Loading Check:</h2>";
$payment_admin_class = 'Squidly\\Domains\\Payments\\Admin\\PaymentAdminActions';
if (class_exists($payment_admin_class)) {
    echo "<div style='color: green;'>✅ PaymentAdminActions class is loaded</div>";
} else {
    echo "<div style='color: red;'>❌ PaymentAdminActions class NOT loaded</div>";
}

// Get all orders
echo "<h2>📋 Order Analysis:</h2>";
$orders_query = new WP_Query([
    'post_type' => OrderPostType::POST_TYPE,
    'post_status' => ['publish', 'draft', 'private'],
    'posts_per_page' => 10,
    'meta_query' => []
]);

if ($orders_query->have_posts()) {
    echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
    echo "✅ <strong>Found " . $orders_query->found_posts . " orders</strong>";
    echo "</div>";
    
    echo "<h3>🧪 Testing add_payment_row_actions for each order:</h3>";
    
    while ($orders_query->have_posts()) {
        $orders_query->the_post();
        $order_id = get_the_ID();
        $post = get_post($order_id);
        
        // Get payment status
        $payment_status = get_post_meta($order_id, '_payment_status', true);
        $payment_status_legacy = get_post_meta($order_id, 'payment_status', true);
        
        echo "<div style='background: #e2e8f0; padding: 15px; margin: 10px 0; border: 1px solid #cbd5e0;'>";
        echo "<strong>Order ID:</strong> {$order_id}<br>";
        echo "<strong>Post Type:</strong> " . $post->post_type . "<br>";
        echo "<strong>Payment Status (meta _payment_status):</strong> '" . ($payment_status ?: 'empty') . "'<br>";
        echo "<strong>Payment Status (meta payment_status):</strong> '" . ($payment_status_legacy ?: 'empty') . "'<br>";
        
        // Test if post type matches
        if ($post->post_type !== OrderPostType::POST_TYPE) {
            echo "<div style='color: red;'>❌ Post type mismatch! Expected '" . OrderPostType::POST_TYPE . "', got '" . $post->post_type . "'</div>";
        } else {
            echo "<div style='color: green;'>✅ Post type matches: " . OrderPostType::POST_TYPE . "</div>";
        }
        
        // Test user permissions
        if (!current_user_can('manage_options')) {
            echo "<div style='color: red;'>❌ User doesn't have manage_options capability</div>";
        } else {
            echo "<div style='color: green;'>✅ User has manage_options capability</div>";
        }
        
        // Test payment status condition
        if ($payment_status !== 'paid') {
            echo "<div style='color: green;'>✅ Payment status allows Pay button (not 'paid')</div>";
            echo "<strong>Expected result:</strong> Pay button should appear<br>";
        } else {
            echo "<div style='color: orange;'>⚠️ Payment status is 'paid' - Pay button should not appear</div>";
        }
        
        // Simulate the add_payment_row_actions method
        $actions = [];
        
        // Test the actual logic from PaymentAdminActions
        if ($post->post_type === OrderPostType::POST_TYPE && current_user_can('manage_options')) {
            if ($payment_status !== 'paid') {
                $pay_nonce = wp_create_nonce('squidly_pay_' . $post->ID);
                $actions['pay'] = sprintf(
                    '<a href="#" class="squidly-pay-action" data-order-id="%d" data-nonce="%s">%s</a>',
                    $post->ID,
                    $pay_nonce,
                    'Pay'
                );
                echo "<div style='color: green;'><strong>✅ Pay button would be added:</strong><br>";
                echo htmlspecialchars($actions['pay']) . "</div>";
            } else {
                echo "<div style='color: orange;'>⚠️ Pay button would NOT be added (already paid)</div>";
            }
        } else {
            echo "<div style='color: red;'>❌ Pay button would NOT be added (conditions not met)</div>";
        }
        
        echo "</div>";
    }
    wp_reset_postdata();
} else {
    echo "<div style='background: #ed8936; color: white; padding: 15px; margin: 10px 0;'>";
    echo "⚠️ <strong>No orders found!</strong> Run test-payment-setup.php first.";
    echo "</div>";
}

// Check if JavaScript is being loaded
echo "<h2>🌐 JavaScript Loading Check:</h2>";
echo "<div style='background: #bee3f8; padding: 15px; margin: 10px 0;'>";
echo "<strong>To verify JavaScript is loading:</strong><br>";
echo "1. Go to WordPress Admin → Orders<br>";
echo "2. Open browser Developer Tools (F12)<br>";
echo "3. Go to Console tab<br>";
echo "4. Check for JavaScript errors<br>";
echo "5. Check if 'squidly_payment' object exists: <code>console.log(squidly_payment)</code><br>";
echo "</div>";

// Check current page context
echo "<h2>📄 Current Page Context:</h2>";
global $pagenow;
echo "<div style='background: #f7fafc; padding: 15px; margin: 10px 0;'>";
echo "<strong>Current page:</strong> " . ($pagenow ?: 'unknown') . "<br>";
echo "<strong>GET parameters:</strong> " . print_r($_GET, true) . "<br>";
echo "</div>";

echo "<br><a href='/wp-admin/edit.php?post_type=" . OrderPostType::POST_TYPE . "' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Go to Orders Admin</a>";
echo "<a href='test-payment-setup.php' style='background: #48bb78; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Setup Test Data</a>";