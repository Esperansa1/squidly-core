<?php
/**
 * Payment Gateway Debug Script
 * 
 * Use this to debug payment gateway issues
 * Access via: /wp-content/plugins/squidly-core/debug-payment.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🔍 Payment Gateway Debug Info</h1>";

// Check JavaScript loading
echo "<h2>📜 JavaScript Status:</h2>";
echo "<script>";
echo "console.log('🔍 Payment Debug Script Loaded');";
echo "if (typeof jQuery !== 'undefined') {";
echo "    console.log('✅ jQuery is loaded');";
echo "    if (typeof squidly_payment !== 'undefined') {";
echo "        console.log('✅ squidly_payment object found:', squidly_payment);";
echo "    } else {";
echo "        console.log('❌ squidly_payment object NOT found');";
echo "    }";
echo "} else {";
echo "    console.log('❌ jQuery is NOT loaded');";
echo "}";
echo "</script>";

// Check WordPress hooks
echo "<h2>🪝 WordPress Hooks Status:</h2>";
global $wp_filter;

$payment_hooks = [
    'init',
    'rest_api_init', 
    'admin_enqueue_scripts',
    'post_row_actions'
];

foreach ($payment_hooks as $hook) {
    if (isset($wp_filter[$hook])) {
        echo "<div style='color: green;'>✅ Hook '{$hook}' has " . count($wp_filter[$hook]->callbacks) . " callbacks</div>";
    } else {
        echo "<div style='color: red;'>❌ Hook '{$hook}' has no callbacks</div>";
    }
}

// Check REST API
echo "<h2>🌐 REST API Test:</h2>";
echo "<button onclick='testRestAPI()' style='background: #3182ce; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test REST API</button>";
echo "<div id='rest-result' style='margin-top: 10px; padding: 10px; border: 1px solid #ccc;'></div>";

echo "<script>";
echo "function testRestAPI() {";
echo "    const resultDiv = document.getElementById('rest-result');";
echo "    resultDiv.innerHTML = '⏳ Testing REST API...';";
echo "    ";
echo "    fetch('/wp-json/squidly/v1/pay/start', {";
echo "        method: 'POST',";
echo "        headers: {";
echo "            'Content-Type': 'application/json',";
echo "            'X-WP-Nonce': '" . wp_create_nonce('wp_rest') . "'";
echo "        },";
echo "        body: JSON.stringify({";
echo "            order_id: 999999,";  // Non-existent order
echo "            amount: '1.00'";
echo "        })";
echo "    })";
echo "    .then(response => response.json())";
echo "    .then(data => {";
echo "        console.log('REST API Response:', data);";
echo "        if (data.error && data.error === 'Order not found') {";
echo "            resultDiv.innerHTML = '<div style=\"color: green;\">✅ REST API is working! (Expected \"Order not found\" error)</div>';";
echo "        } else {";
echo "            resultDiv.innerHTML = '<div style=\"color: orange;\">⚠️ Unexpected response: ' + JSON.stringify(data) + '</div>';";
echo "        }";
echo "    })";
echo "    .catch(error => {";
echo "        console.error('REST API Error:', error);";
echo "        resultDiv.innerHTML = '<div style=\"color: red;\">❌ REST API Error: ' + error.message + '</div>';";
echo "    });";
echo "}";
echo "</script>";

// Manual payment test
if (isset($_POST['test_payment'])) {
    echo "<h2>💳 Manual Payment Test Result:</h2>";
    
    $order_id = intval($_POST['order_id']);
    $amount = sanitize_text_field($_POST['amount']);
    
    if (class_exists('Squidly\Domains\Payments\Services\PaymentService')) {
        try {
            $payment_service = new \Squidly\Domains\Payments\Services\PaymentService();
            $result = $payment_service->startPayment($order_id, $amount, []);
            
            if (isset($result['checkout_url'])) {
                echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
                echo "✅ <strong>Payment initiated successfully!</strong><br>";
                echo "Checkout URL: <a href='{$result['checkout_url']}' target='_blank' style='color: white; text-decoration: underline;'>{$result['checkout_url']}</a>";
                echo "</div>";
            } else {
                echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
                echo "❌ <strong>Payment failed:</strong><br>";
                echo "Error: " . (isset($result['error']) ? $result['error'] : 'Unknown error');
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
            echo "❌ <strong>Exception occurred:</strong><br>";
            echo "Error: " . $e->getMessage();
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
        echo "❌ <strong>PaymentService class not found!</strong>";
        echo "</div>";
    }
}

// Manual test form
echo "<h2>🧪 Manual Payment Test:</h2>";
echo "<form method='POST' style='background: #f7fafc; padding: 20px; border-radius: 5px;'>";
echo "<label>Order ID: <input type='number' name='order_id' value='1' style='margin-left: 10px; padding: 5px;'></label><br><br>";
echo "<label>Amount: <input type='text' name='amount' value='25.50' style='margin-left: 10px; padding: 5px;'></label><br><br>";
echo "<input type='submit' name='test_payment' value='Test Payment' style='background: #38a169; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>";
echo "</form>";

echo "<br><br>";
echo "<div style='background: #e2e8f0; padding: 15px; margin: 10px 0;'>";
echo "<strong>💡 Debugging Tips:</strong><br>";
echo "• Check browser console (F12) for JavaScript errors<br>";
echo "• Check WordPress debug.log for PHP errors<br>";
echo "• Ensure WooCommerce is properly configured<br>";
echo "• Verify payment gateway is set up in WooCommerce<br>";
echo "• Check that Squidly orders exist with proper meta fields";
echo "</div>";

echo "<br><a href='/wp-admin/edit.php?post_type=squidly_order' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Squidly Orders</a>";