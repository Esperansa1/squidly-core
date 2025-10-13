<?php
/**
 * Direct test of WP_Query with same args
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/test-wpquery-direct.php
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

// Test exact query that findBy would use
echo "=== Testing exact WP_Query args from findBy ===\n";
$query_args = [
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_key' => '_customer_id',
    'meta_value' => $customerId,
    'order' => 'DESC',
    'orderby' => 'date'
];

echo "Query args:\n";
print_r($query_args);

$query = new WP_Query($query_args);
echo "\nResults: " . count($query->posts) . " posts\n";
if (count($query->posts) > 0) {
    echo "Post IDs: " . implode(', ', $query->posts) . "\n";
}
wp_reset_postdata();

// Try without orderby/order
echo "\n=== Testing WITHOUT orderby/order ===\n";
$query_args2 = [
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_key' => '_customer_id',
    'meta_value' => $customerId
];

$query2 = new WP_Query($query_args2);
echo "Results: " . count($query2->posts) . " posts\n";
if (count($query2->posts) > 0) {
    echo "Post IDs: " . implode(', ', $query2->posts) . "\n";
}
wp_reset_postdata();

// Cleanup
wp_delete_post($orderId, true);
wp_delete_post($customerId, true);
wp_delete_post($productId, true);
