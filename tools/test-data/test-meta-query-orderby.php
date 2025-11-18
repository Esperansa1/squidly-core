<?php
/**
 * Test meta_query with orderby
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/test-meta-query-orderby.php
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

// Test 1: meta_query WITH orderby=date
echo "=== Test 1: meta_query WITH orderby=date ===\n";
$query1 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_customer_id',
            'value' => $customerId,
            'compare' => '='
        ]
    ],
    'orderby' => 'date',
    'order' => 'DESC'
]);
echo "Found: " . count($query1->posts) . "\n";
if (count($query1->posts) > 0) {
    echo "IDs: " . implode(', ', $query1->posts) . "\n";
}
wp_reset_postdata();

// Test 2: meta_query WITHOUT orderby
echo "\n=== Test 2: meta_query WITHOUT orderby ===\n";
$query2 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_customer_id',
            'value' => $customerId,
            'compare' => '='
        ]
    ]
]);
echo "Found: " . count($query2->posts) . "\n";
if (count($query2->posts) > 0) {
    echo "IDs: " . implode(', ', $query2->posts) . "\n";
}
wp_reset_postdata();

// Test 3: Just get all orders
echo "\n=== Test 3: All orders (any status) ===\n";
$query3 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids'
]);
echo "Found: " . count($query3->posts) . "\n";
if (count($query3->posts) > 0) {
    echo "IDs: " . implode(', ', $query3->posts) . "\n";
}
wp_reset_postdata();

// Cleanup
wp_delete_post($orderId, true);
wp_delete_post($customerId, true);
wp_delete_post($productId, true);
