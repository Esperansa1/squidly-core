<?php
/**
 * Manual Payment Product Creation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🛒 Payment Product Creation</h1>";

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
    echo "❌ <strong>WooCommerce is NOT active!</strong><br>";
    echo "Please install and activate WooCommerce first.";
    echo "</div>";
    exit;
}

echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
echo "✅ <strong>WooCommerce is active!</strong>";
echo "</div>";

// Check current payment product status
echo "<h2>📋 Current Status:</h2>";
$existing_product_id = get_option('squidly_wc_payment_product_id');

if ($existing_product_id) {
    $product = wc_get_product($existing_product_id);
    if ($product) {
        echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
        echo "✅ <strong>Payment Product Already Exists!</strong><br>";
        echo "Product ID: {$existing_product_id}<br>";
        echo "Product Name: " . $product->get_name() . "<br>";
        echo "Product Status: " . $product->get_status() . "<br>";
        echo "Product Visibility: " . $product->get_catalog_visibility();
        echo "</div>";
    } else {
        echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
        echo "❌ <strong>Payment Product ID stored but product not found!</strong><br>";
        echo "Stored ID: {$existing_product_id}<br>";
        echo "Will create a new product...";
        echo "</div>";
    }
} else {
    echo "<div style='background: #ed8936; color: white; padding: 15px; margin: 10px 0;'>";
    echo "⚠️ <strong>No payment product found!</strong><br>";
    echo "Will create a new product...";
    echo "</div>";
}

// Create/recreate payment product
echo "<h2>🏗️ Creating Payment Product:</h2>";

try {
    // Create the product
    $product = new WC_Product_Simple();
    $product->set_name('Squidly Payment');
    $product->set_status('private');        // Not publicly visible
    $product->set_virtual(true);            // No shipping needed
    $product->set_sold_individually(true);  // Only 1 can be purchased
    $product->set_price(0);                 // Price will be overridden dynamically
    $product->set_regular_price(0);
    $product->set_catalog_visibility('hidden'); // Hidden from catalog
    $product->set_description('Hidden product used for Squidly payment processing. Do not modify.');
    $product->set_short_description('Squidly Payment Processing');
    
    // Save the product
    $product_id = $product->save();
    
    if ($product_id) {
        // Store the product ID in options
        update_option('squidly_wc_payment_product_id', $product_id);
        
        echo "<div style='background: #48bb78; color: white; padding: 15px; margin: 10px 0;'>";
        echo "✅ <strong>Payment Product Created Successfully!</strong><br>";
        echo "Product ID: {$product_id}<br>";
        echo "Product Name: " . $product->get_name() . "<br>";
        echo "Product Status: " . $product->get_status() . "<br>";
        echo "Product Type: " . $product->get_type() . "<br>";
        echo "Product Visibility: " . $product->get_catalog_visibility() . "<br>";
        echo "Option stored: squidly_wc_payment_product_id = {$product_id}";
        echo "</div>";
        
        // Verify the product was saved correctly
        echo "<h2>✅ Verification:</h2>";
        $saved_product = wc_get_product($product_id);
        if ($saved_product) {
            echo "<div style='background: #e6fffa; padding: 15px; margin: 10px 0; border: 1px solid #4fd1c7;'>";
            echo "<strong>Product Details:</strong><br>";
            echo "• ID: " . $saved_product->get_id() . "<br>";
            echo "• Name: " . $saved_product->get_name() . "<br>";
            echo "• Status: " . $saved_product->get_status() . "<br>";
            echo "• Virtual: " . ($saved_product->get_virtual() ? 'Yes' : 'No') . "<br>";
            echo "• Price: $" . $saved_product->get_price() . "<br>";
            echo "• Visibility: " . $saved_product->get_catalog_visibility() . "<br>";
            echo "• Sold Individually: " . ($saved_product->get_sold_individually() ? 'Yes' : 'No');
            echo "</div>";
        }
        
        // Test the PaymentProductActivation class
        echo "<h2>🧪 Test PaymentProductActivation Class:</h2>";
        if (class_exists('Squidly\Domains\Payments\Activation\PaymentProductActivation')) {
            echo "<div style='color: green; margin: 5px 0;'>✅ PaymentProductActivation class loaded</div>";
            
            // Test the getPaymentProductId method from WooProvider
            if (class_exists('Squidly\Domains\Payments\Gateways\WooProvider')) {
                try {
                    $provider = new \Squidly\Domains\Payments\Gateways\WooProvider();
                    $reflection = new ReflectionClass($provider);
                    $method = $reflection->getMethod('getPaymentProductId');
                    $method->setAccessible(true);
                    $retrieved_id = $method->invoke($provider);
                    
                    if ($retrieved_id === $product_id) {
                        echo "<div style='color: green; margin: 5px 0;'>✅ WooProvider can retrieve payment product correctly</div>";
                    } else {
                        echo "<div style='color: red; margin: 5px 0;'>❌ WooProvider retrieved wrong ID: {$retrieved_id} (expected: {$product_id})</div>";
                    }
                } catch (Exception $e) {
                    echo "<div style='color: red; margin: 5px 0;'>❌ WooProvider test failed: " . $e->getMessage() . "</div>";
                }
            } else {
                echo "<div style='color: red; margin: 5px 0;'>❌ WooProvider class not loaded</div>";
            }
        } else {
            echo "<div style='color: red; margin: 5px 0;'>❌ PaymentProductActivation class not loaded</div>";
        }
        
    } else {
        echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
        echo "❌ <strong>Failed to create payment product!</strong><br>";
        echo "Product save() returned: " . var_export($product_id, true);
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f56565; color: white; padding: 15px; margin: 10px 0;'>";
    echo "❌ <strong>Exception occurred:</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Trace: " . $e->getTraceAsString();
    echo "</div>";
}

// Show next steps
echo "<h2>🎯 Next Steps:</h2>";
echo "<div style='background: #bee3f8; padding: 15px; margin: 10px 0;'>";
echo "<ol>";
echo "<li><strong>Go back to test setup:</strong> <a href='test-payment-setup.php' style='color: #3182ce;'>test-payment-setup.php</a></li>";
echo "<li><strong>Verify all classes are loaded</strong> - should now show all ✅</li>";
echo "<li><strong>Test the payment flow</strong> in WordPress Admin → Squidly Orders</li>";
echo "<li><strong>Create a test order</strong> if one doesn't exist</li>";
echo "</ol>";
echo "</div>";

echo "<br><a href='test-payment-setup.php' style='background: #48bb78; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Payment Setup Test</a>";
echo " ";
echo "<a href='/wp-admin/edit.php?post_type=squidly_order' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Squidly Orders</a>";