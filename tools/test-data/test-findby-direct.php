<?php
/**
 * Direct test of findBy
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/test-findby-direct.php
 */

if (!function_exists('get_posts')) {
    die("ERROR: WordPress not loaded.\n");
}

require_once __DIR__ . '/../../includes/domains/orders/repositories/OrderRepository.php';
require_once __DIR__ . '/../../includes/domains/customers/repositories/CustomerRepository.php';
require_once __DIR__ . '/../../includes/domains/products/repositories/ProductRepository.php';
require_once __DIR__ . '/../../includes/domains/orders/models/Order.php';
require_once __DIR__ . '/../../includes/domains/orders/post-types/OrderPostType.php';
require_once __DIR__ . '/../../includes/domains/customers/post-types/CustomerPostType.php';
require_once __DIR__ . '/../../includes/domains/products/post-types/ProductPostType.php';

$customerRepo = new CustomerRepository();
$productRepo = new ProductRepository();
$orderRepo = new OrderRepository();

// Create test data
echo "Creating test customer...\n";
$customerId = $customerRepo->create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'phone' => '+972501234567',
    'auth_provider' => 'phone'
]);
echo "Customer ID: $customerId\n";

echo "Creating test product...\n";
$productId = $productRepo->create([
    'name' => 'Test Pizza',
    'price' => 35.0,
    'description' => 'Test',
    'category' => 'Main'
]);
echo "Product ID: $productId\n";

echo "Creating test order...\n";
$orderData = [
    'customer_id' => $customerId,
    'order_items' => [
        [
            'product_id' => $productId,
            'product_name' => 'Test Pizza',
            'quantity' => 1,
            'unit_price' => 35.0,
            'total_price' => 35.0
        ]
    ],
    'subtotal' => 35.0,
    'tax_amount' => 5.95,
    'total_amount' => 40.95,
    'payment_method' => 'card'
];

$orderId = $orderRepo->create($orderData);
echo "Order ID: $orderId\n\n";

// Test findBy directly
echo "=== Testing findBy(['customer_id' => $customerId]) ===\n";
try {
    $orders = $orderRepo->findBy(['customer_id' => $customerId]);
    echo "Result count: " . count($orders) . "\n";

    if (count($orders) > 0) {
        echo "SUCCESS! Found orders:\n";
        foreach ($orders as $order) {
            echo "- Order #{$order->id}, Customer: {$order->customer_id}\n";
        }
    } else {
        echo "FAILED: No orders found\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Cleanup
echo "\nCleaning up...\n";
wp_delete_post($orderId, true);
wp_delete_post($customerId, true);
wp_delete_post($productId, true);
echo "Done!\n";
