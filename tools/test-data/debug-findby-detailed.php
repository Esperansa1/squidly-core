<?php
/**
 * Debug findBy method in detail
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/debug-findby-detailed.php
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

// Create test customer
echo "Creating test customer...\n";
$customerId = $customerRepo->create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'phone' => '+972501234567',
    'auth_provider' => 'phone'
]);
echo "Customer ID: $customerId\n";

// Create test product
echo "Creating test product...\n";
$productId = $productRepo->create([
    'name' => 'Test Pizza',
    'price' => 35.0,
    'description' => 'Test',
    'category' => 'Main'
]);
echo "Product ID: $productId\n";

// Create test order
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
echo "Order ID: $orderId\n";

// Verify order was created
$order = $orderRepo->get($orderId);
if ($order) {
    echo "Order retrieved successfully\n";
    echo "Order customer_id: {$order->customer_id}\n";
} else {
    echo "ERROR: Could not retrieve order\n";
}

// Now test findBy
echo "\n=== Testing findBy ===\n";
$orders = $orderRepo->findBy(['customer_id' => $customerId]);
echo "findBy result count: " . count($orders) . "\n";

if (count($orders) === 0) {
    echo "ERROR: findBy returned no orders!\n";

    // Manual WP_Query test
    echo "\n=== Manual WP_Query test ===\n";
    $query = new WP_Query([
        'post_type' => 'order',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_customer_id',
        'meta_value' => $customerId,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    echo "WP_Query found: " . count($query->posts) . " orders\n";
    if (count($query->posts) > 0) {
        echo "Order IDs: " . implode(', ', $query->posts) . "\n";
    }
    wp_reset_postdata();
} else {
    echo "SUCCESS: Found " . count($orders) . " order(s)\n";
}

// Cleanup
wp_delete_post($orderId, true);
wp_delete_post($customerId, true);
wp_delete_post($productId, true);
