<?php
/**
 * Debug findBy method
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/debug-findby.php
 */

if (!function_exists('get_posts')) {
    die("ERROR: WordPress not loaded.\n");
}

require_once __DIR__ . '/../../includes/domains/orders/repositories/OrderRepository.php';
require_once __DIR__ . '/../../includes/domains/orders/models/Order.php';
require_once __DIR__ . '/../../includes/domains/orders/post-types/OrderPostType.php';

$repo = new OrderRepository();

echo "\n=== Testing findBy with customer_id ===\n";

// First, let's see if there are ANY orders
$all_orders = get_posts([
    'post_type' => 'order',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids'
]);

echo "Total orders in database: " . count($all_orders) . "\n";

if (count($all_orders) > 0) {
    echo "First order ID: " . $all_orders[0] . "\n";

    // Get customer_id from first order
    $customer_id = get_post_meta($all_orders[0], '_customer_id', true);
    echo "Customer ID from first order: " . $customer_id . "\n";

    // Try findBy with customer_id
    echo "\nTrying findBy(['customer_id' => $customer_id])...\n";
    $orders = $repo->findBy(['customer_id' => (int)$customer_id]);
    echo "Result count: " . count($orders) . "\n";

    if (count($orders) > 0) {
        echo "SUCCESS: Found orders\n";
    } else {
        echo "FAILED: findBy returned 0 orders\n";

        // Debug the actual query
        echo "\n=== Manual WP_Query test ===\n";
        $test_query = new WP_Query([
            'post_type' => 'order',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_customer_id',
            'meta_value' => $customer_id
        ]);
        echo "Manual query found: " . count($test_query->posts) . " orders\n";
        wp_reset_postdata();
    }
}
