<?php
/**
 * Manual Payment Class Loading Test
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access  
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🔧 Manual Class Loading Test</h1>";

// Manual require of all payment files
$payment_files = [
    'includes/domains/payments/interfaces/PaymentProvider.php',
    'includes/domains/payments/services/PaymentService.php',
    'includes/domains/payments/gateways/WooProvider.php', 
    'includes/domains/payments/rest/PaymentRestController.php',
    'includes/domains/payments/admin/PaymentAdminActions.php',
    'includes/domains/payments/hooks/PaymentStatusSync.php',
    'includes/domains/payments/activation/PaymentProductActivation.php',
    'includes/domains/payments/bootstrap/PaymentBootstrap.php'
];

echo "<h2>📁 Manual File Loading:</h2>";

foreach ($payment_files as $file) {
    $full_path = SQUIDLY_CORE_PATH . $file;
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>";
    echo "<strong>File:</strong> {$file}<br>";
    
    if (file_exists($full_path)) {
        echo "<div style='color: blue;'>📄 <strong>File exists:</strong> {$full_path}</div>";
        
        try {
            require_once $full_path;
            echo "<div style='color: green;'>✅ <strong>Successfully loaded</strong></div>";
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ <strong>Loading failed:</strong> " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ <strong>File not found:</strong> {$full_path}</div>";
    }
    echo "</div>";
}

// Now test if classes are available
echo "<h2>🧪 Class Availability Test:</h2>";

$classes = [
    'Squidly\Domains\Payments\Bootstrap\PaymentBootstrap',
    'Squidly\Domains\Payments\Services\PaymentService',
    'Squidly\Domains\Payments\Gateways\WooProvider',
    'Squidly\Domains\Payments\Rest\PaymentRestController',
    'Squidly\Domains\Payments\Admin\PaymentAdminActions'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "<div style='color: green; margin: 5px 0;'>✅ {$class}</div>";
    } else {
        echo "<div style='color: red; margin: 5px 0;'>❌ {$class}</div>";
    }
}

// Test bootstrap initialization
echo "<h2>🚀 Bootstrap Test:</h2>";

try {
    if (class_exists('Squidly\Domains\Payments\Bootstrap\PaymentBootstrap')) {
        echo "<div style='color: green;'>✅ PaymentBootstrap class is available</div>";
        
        // Test calling init (in a controlled way)
        $reflection = new ReflectionClass('Squidly\Domains\Payments\Bootstrap\PaymentBootstrap');
        $init_method = $reflection->getMethod('init');
        
        if ($init_method->isStatic()) {
            echo "<div style='color: green;'>✅ init() method is static and callable</div>";
            echo "<div style='color: orange;'>⚠️ Ready to call PaymentBootstrap::init() - this will register hooks</div>";
        } else {
            echo "<div style='color: red;'>❌ init() method is not static</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ PaymentBootstrap class not found</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Bootstrap test failed: " . $e->getMessage() . "</div>";
}

echo "<br><br>";
echo "<div style='background: #fef5e7; padding: 15px; margin: 10px 0;'>";
echo "<strong>💡 Next Steps:</strong><br>";
echo "1. If all classes show ✅, the manual loading works<br>";
echo "2. The issue is with the autoloader in squidly-core.php<br>";  
echo "3. Try deactivating and reactivating the plugin<br>";
echo "4. Or add the manual requires to squidly-core.php temporarily";
echo "</div>";

echo "<br><a href='test-payment-setup.php' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Payment Setup</a>";