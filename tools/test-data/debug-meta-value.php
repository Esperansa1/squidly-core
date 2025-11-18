<?php
/**
 * Debug meta value
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/debug-meta-value.php
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
$customerId = $customerRepo->create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'phone' => '+972501234567',
    'auth_provider' => 'phone'
]);

$productId = $productRepo->create([
    'name' => 'Test Pizza',
    'price' => 35.0,
    'description' => 'Test',
    'category' => 'Main'
]);

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
echo "Created order ID: $orderId\n";
echo "Customer ID: $customerId\n\n";

// Check what meta was actually saved
echo "=== Checking saved meta ===\n";
$saved_customer_id = get_post_meta($orderId, '_customer_id', true);
echo "_customer_id value: '" . $saved_customer_id . "'\n";
echo "_customer_id type: " . gettype($saved_customer_id) . "\n";
echo "Expected customer_id: " . $customerId . "\n";
echo "Expected type: " . gettype($customerId) . "\n";
echo "Are they equal? " . ($saved_customer_id == $customerId ? 'YES' : 'NO') . "\n";
echo "Are they identical? " . ($saved_customer_id === $customerId ? 'YES' : 'NO') . "\n";

// Check all post meta
echo "\n=== All post meta ===\n";
$all_meta = get_post_meta($orderId);
foreach ($all_meta as $key => $values) {
    echo "$key: " . print_r($values, true) . "\n";
}

// Try different query approaches
echo "\n=== Testing different queries ===\n";

// Test 1: Simple meta_key/meta_value
echo "Test 1: meta_key + meta_value (int)\n";
$query1 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_key' => '_customer_id',
    'meta_value' => (int)$customerId
]);
echo "Found: " . count($query1->posts) . "\n";
wp_reset_postdata();

// Test 2: meta_query
echo "\nTest 2: meta_query\n";
$query2 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_customer_id',
            'value' => (int)$customerId,
            'compare' => '='
        ]
    ]
]);
echo "Found: " . count($query2->posts) . "\n";
wp_reset_postdata();

// Test 3: Get all orders and check manually
echo "\nTest 3: Get all orders\n";
$all_orders = get_posts([
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids'
]);
echo "Total orders: " . count($all_orders) . "\n";
if (count($all_orders) > 0) {
    foreach ($all_orders as $id) {
        $cid = get_post_meta($id, '_customer_id', true);
        echo "Order $id has customer_id: '$cid' (type: " . gettype($cid) . ")\n";
    }
}

// Cleanup
wp_delete_post($orderId, true);
wp_delete_post($customerId, true);
wp_delete_post($productId, true);
