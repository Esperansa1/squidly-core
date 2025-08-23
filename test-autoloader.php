<?php
/**
 * Test Autoloader for Payment Classes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🔍 Autoloader Test</h1>";

// Test individual class loading
$classes_to_test = [
    'Squidly\Domains\Payments\Bootstrap\PaymentBootstrap',
    'Squidly\Domains\Payments\Services\PaymentService', 
    'Squidly\Domains\Payments\Gateways\WooProvider',
    'Squidly\Domains\Payments\Rest\PaymentRestController',
    'Squidly\Domains\Payments\Admin\PaymentAdminActions',
    'Squidly\Domains\Payments\Hooks\PaymentStatusSync',
    'Squidly\Domains\Payments\Activation\PaymentProductActivation',
    'Squidly\Domains\Payments\Interfaces\PaymentProvider'
];

echo "<h2>📦 Individual Class Loading:</h2>";
foreach ($classes_to_test as $class) {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>";
    echo "<strong>Testing:</strong> {$class}<br>";
    
    if (class_exists($class) || interface_exists($class)) {
        echo "<div style='color: green;'>✅ <strong>SUCCESS:</strong> Class/Interface loaded</div>";
        
        // Check if it's a class and try to get reflection info
        if (class_exists($class)) {
            try {
                $reflection = new ReflectionClass($class);
                echo "<div style='color: blue;'>📁 <strong>File:</strong> " . $reflection->getFileName() . "</div>";
                echo "<div style='color: purple;'>🔧 <strong>Methods:</strong> " . count($reflection->getMethods()) . "</div>";
            } catch (Exception $e) {
                echo "<div style='color: orange;'>⚠️ <strong>Reflection failed:</strong> " . $e->getMessage() . "</div>";
            }
        }
    } else {
        echo "<div style='color: red;'>❌ <strong>FAILED:</strong> Class/Interface not found</div>";
        
        // Try to debug file path
        $relative_class = str_replace('Squidly\\Domains\\Payments\\', '', $class);
        $parts = explode('\\', $relative_class);
        if (count($parts) === 2) {
            $folder = strtolower($parts[0]);
            $filename = $parts[1];
            $expected_file = SQUIDLY_CORE_PATH . 'includes/domains/payments/' . $folder . '/' . $filename . '.php';
            echo "<div style='color: gray;'>📄 <strong>Expected file:</strong> " . $expected_file . "</div>";
            echo "<div style='color: gray;'>📂 <strong>File exists:</strong> " . (file_exists($expected_file) ? 'Yes' : 'No') . "</div>";
        }
    }
    echo "</div>";
}

// Test instantiation
echo "<h2>🏗️ Class Instantiation Test:</h2>";

try {
    if (class_exists('Squidly\Domains\Payments\Services\PaymentService')) {
        $service = new \Squidly\Domains\Payments\Services\PaymentService();
        echo "<div style='color: green;'>✅ <strong>PaymentService:</strong> Instantiated successfully</div>";
    } else {
        echo "<div style='color: red;'>❌ <strong>PaymentService:</strong> Class not found</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ <strong>PaymentService instantiation failed:</strong> " . $e->getMessage() . "</div>";
}

try {
    if (class_exists('Squidly\Domains\Payments\Gateways\WooProvider')) {
        $provider = new \Squidly\Domains\Payments\Gateways\WooProvider();
        echo "<div style='color: green;'>✅ <strong>WooProvider:</strong> Instantiated successfully</div>";
    } else {
        echo "<div style='color: red;'>❌ <strong>WooProvider:</strong> Class not found</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ <strong>WooProvider instantiation failed:</strong> " . $e->getMessage() . "</div>";
}

// Test static method call
echo "<h2>📞 Static Method Call Test:</h2>";

try {
    if (class_exists('Squidly\Domains\Payments\Bootstrap\PaymentBootstrap')) {
        // Don't actually call init() to avoid side effects, just check if method exists
        $reflection = new ReflectionClass('Squidly\Domains\Payments\Bootstrap\PaymentBootstrap');
        if ($reflection->hasMethod('init')) {
            echo "<div style='color: green;'>✅ <strong>PaymentBootstrap::init():</strong> Method exists and can be called</div>";
        } else {
            echo "<div style='color: red;'>❌ <strong>PaymentBootstrap::init():</strong> Method not found</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ <strong>PaymentBootstrap:</strong> Class not found</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ <strong>PaymentBootstrap method check failed:</strong> " . $e->getMessage() . "</div>";
}

echo "<br><br>";
echo "<div style='background: #e2e8f0; padding: 15px; margin: 10px 0;'>";
echo "<strong>🔧 Debug Info:</strong><br>";
echo "<strong>SQUIDLY_CORE_PATH:</strong> " . SQUIDLY_CORE_PATH . "<br>";
echo "<strong>Payment folder exists:</strong> " . (is_dir(SQUIDLY_CORE_PATH . 'includes/domains/payments/') ? 'Yes' : 'No') . "<br>";
echo "</div>";

echo "<br><a href='test-payment-setup.php' style='background: #3182ce; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Payment Setup</a>";