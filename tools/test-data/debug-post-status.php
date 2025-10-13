<?php
/**
 * Debug post status
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/debug-post-status.php
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
echo "Created order ID: $orderId\n\n";

// Check the actual post
$post = get_post($orderId);
echo "=== Post Details ===\n";
echo "Post ID: " . $post->ID . "\n";
echo "Post type: " . $post->post_type . "\n";
echo "Post status: " . $post->post_status . "\n";
echo "Post title: " . $post->post_title . "\n";
echo "Post date: " . $post->post_date . "\n\n";

// Try to query for this specific order
echo "=== Query for specific order ID ===\n";
$query = new WP_Query([
    'post_type' => 'order',
    'p' => $orderId
]);
echo "Found by ID: " . $query->found_posts . "\n";
wp_reset_postdata();

// Try with status: any
echo "\n=== Query with post_status => 'any' ===\n";
$query2 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids'
]);
echo "Found: " . count($query2->posts) . "\n";
wp_reset_postdata();

// Try with status: publish
echo "\n=== Query with post_status => 'publish' ===\n";
$query3 = new WP_Query([
    'post_type' => 'order',
    'post_status' => 'publish',
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
