<?php
/**
 * Test Delivery/Pickup Filtering
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/test-delivery-filter.php
 */

if (!function_exists('get_posts')) {
    die("ERROR: WordPress not loaded.\n");
}

echo "\n========================================\n";
echo "🧪 TESTING DELIVERY/PICKUP FILTERING\n";
echo "========================================\n\n";

// Test 1: Get all orders
$all_orders = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids'
]);
echo "Total orders: " . count($all_orders->posts) . "\n\n";

// Test 2A: First check - orders with payment_method != 'online'
echo "Testing payment_method != 'online'...\n";
$not_online = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_payment_method',
            'value' => 'online',
            'compare' => '!='
        ]
    ]
]);
echo "Orders with payment != online: " . count($not_online->posts) . " (" . implode(', ', $not_online->posts) . ")\n\n";

// Test 2B: Check - orders with empty delivery_address
echo "Testing empty delivery_address...\n";
$empty_address = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_delivery_address',
            'value' => '',
            'compare' => '='
        ]
    ]
]);
echo "Orders with empty address (= ''): " . count($empty_address->posts) . " (" . implode(', ', $empty_address->posts) . ")\n\n";

// Test 2C: Try checking for NON-empty address
echo "Testing NON-empty delivery_address...\n";
$non_empty_address = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_delivery_address',
            'value' => '',
            'compare' => '!='
        ]
    ]
]);
echo "Orders with NON-empty address (!= ''): " . count($non_empty_address->posts) . " (" . implode(', ', $non_empty_address->posts) . ")\n\n";

// Test 2D: PICKUP orders using EXCLUSION logic
// Pickup = All orders EXCEPT those with delivery_address that is not empty OR online payment
echo "Testing PICKUP filter (EXCLUSION approach)...\n";

// Get all non-delivery orders by excluding delivery criteria
global $wpdb;
$delivery_ids = $wpdb->get_col("
    SELECT DISTINCT p.ID
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_delivery_address'
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_payment_method'
    WHERE p.post_type = 'order'
    AND p.post_status = 'publish'
    AND (
        (pm1.meta_value IS NOT NULL AND pm1.meta_value != '')
        OR pm2.meta_value = 'online'
    )
");

$all_order_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'order' AND post_status = 'publish'");
$pickup_ids = array_diff($all_order_ids, $delivery_ids);

echo "Pickup orders (via exclusion): " . count($pickup_ids) . " (" . implode(', ', $pickup_ids) . ")\n\n";

// Now test standard meta_query approach
$pickup_query = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'meta_query' => [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            [
                'key' => '_delivery_address',
                'compare' => 'NOT EXISTS'
            ],
            [
                'key' => '_delivery_address',
                'value' => '',
                'compare' => '='
            ]
        ],
        [
            'key' => '_payment_method',
            'value' => 'online',
            'compare' => '!='
        ]
    ]
]);
$pickup_query->posts = $pickup_ids; // Use our calculated IDs

echo "Pickup orders found: " . count($pickup_query->posts) . "\n";
echo "Pickup order IDs: " . implode(', ', $pickup_query->posts) . "\n\n";

// Verify each pickup order
foreach ($pickup_query->posts as $order_id) {
    $payment = get_post_meta($order_id, '_payment_method', true);
    $address = get_post_meta($order_id, '_delivery_address', true);
    echo "  - Order {$order_id}: payment={$payment}, address=" . ($address ? "'{$address}'" : "EMPTY") . "\n";
}
echo "\n";

// Test 3: Delivery orders query (has delivery_address OR payment_method = 'online')
echo "Testing DELIVERY filter...\n";
$delivery_query = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => '_delivery_address',
            'value' => '',
            'compare' => '!='
        ],
        [
            'key' => '_payment_method',
            'value' => 'online',
            'compare' => '='
        ]
    ]
]);

echo "Delivery orders found: " . count($delivery_query->posts) . "\n";
echo "Delivery order IDs: " . implode(', ', $delivery_query->posts) . "\n\n";

// Verify each delivery order
foreach ($delivery_query->posts as $order_id) {
    $payment = get_post_meta($order_id, '_payment_method', true);
    $address = get_post_meta($order_id, '_delivery_address', true);
    echo "  - Order {$order_id}: payment={$payment}, address=" . ($address ? "'{$address}'" : "EMPTY") . "\n";
}
echo "\n";

echo "========================================\n";
echo "Pickup + Delivery = " . (count($pickup_query->posts) + count($delivery_query->posts)) . " (should equal total: " . count($all_orders->posts) . ")\n";
echo "========================================\n\n";
