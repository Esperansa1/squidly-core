<?php
/**
 * Force Delete All Orders via WP-CLI
 *
 * This script permanently deletes ALL orders from the database.
 * No confirmation prompts, no safeguards - use with caution!
 *
 * Usage:
 *   wp eval-file wp-content/plugins/squidly-core/tools/test-data/force-delete-all-orders.php
 *
 * Or via direct PHP (if WordPress is loaded):
 *   php -r "require 'wp-load.php'; include 'wp-content/plugins/squidly-core/tools/test-data/force-delete-all-orders.php';"
 */

// Check if WordPress is loaded
if (!function_exists('get_posts')) {
    die("ERROR: WordPress not loaded. Run via WP-CLI: wp eval-file path/to/this/file.php\n");
}

echo "\n";
echo "========================================\n";
echo "🗑️  FORCE DELETE ALL ORDERS\n";
echo "========================================\n\n";

// Get all order posts using WP_Query (more reliable than get_posts)
echo "Querying database for orders...\n";

$query = new WP_Query([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'no_found_rows' => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false
]);

$orders = $query->posts;
wp_reset_postdata();

echo "Query completed. Found " . count($orders) . " orders\n\n";

$count = count($orders);

if ($count === 0) {
    echo "✓ No orders found. Database is clean!\n\n";
    exit(0);
}

echo "Found {$count} orders to delete...\n";
echo "Starting deletion process...\n\n";

$deleted = 0;
$failed = 0;

foreach ($orders as $order_id) {
    // Force delete (bypass trash, permanently delete)
    // Second parameter = true means bypass trash and force delete
    $result = wp_delete_post($order_id, true);

    if ($result) {
        $deleted++;
        echo "✓ Deleted order ID: {$order_id}\n";
    } else {
        $failed++;
        echo "✗ Failed to delete order ID: {$order_id}\n";
    }
}

echo "\n========================================\n";
echo "DELETION COMPLETE!\n";
echo "========================================\n";
echo "Total orders found:    {$count}\n";
echo "Successfully deleted:  {$deleted}\n";
echo "Failed to delete:      {$failed}\n";
echo "========================================\n\n";

// Verify deletion
$remaining = get_posts([
    'post_type' => 'order',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids'
]);

$remaining_count = count($remaining);
echo "Verification: {$remaining_count} Squidly orders remaining in database\n";

if ($remaining_count === 0) {
    echo "✓ SUCCESS: All Squidly orders deleted!\n\n";
} else {
    echo "⚠ WARNING: {$remaining_count} Squidly orders still exist!\n\n";
}

// Delete WooCommerce orders
echo "\n========================================\n";
echo "🗑️  DELETING WOOCOMMERCE ORDERS\n";
echo "========================================\n\n";

if (class_exists('WooCommerce')) {
    echo "Querying WooCommerce orders...\n";

    // Get payment product ID to exclude it from deletion
    $payment_product_id = get_option('squidly_wc_payment_product_id');

    $wc_query = new WP_Query([
        'post_type' => 'shop_order',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false
    ]);

    $wc_orders = $wc_query->posts;
    wp_reset_postdata();

    // Exclude payment product from deletion
    if ($payment_product_id) {
        $wc_orders = array_diff($wc_orders, [$payment_product_id]);
        echo "ℹ️  Excluding payment product (ID: {$payment_product_id}) from deletion\n";
    }

    echo "Query completed. Found " . count($wc_orders) . " WooCommerce orders\n\n";

    $wc_count = count($wc_orders);

    if ($wc_count === 0) {
        echo "✓ No WooCommerce orders found. Database is clean!\n\n";
    } else {
        echo "Found {$wc_count} WooCommerce orders to delete...\n";
        echo "Starting deletion process...\n\n";

        $wc_deleted = 0;
        $wc_failed = 0;

        foreach ($wc_orders as $wc_order_id) {
            $result = wp_delete_post($wc_order_id, true);

            if ($result) {
                $wc_deleted++;
                echo "✓ Deleted WooCommerce order ID: {$wc_order_id}\n";
            } else {
                $wc_failed++;
                echo "✗ Failed to delete WooCommerce order ID: {$wc_order_id}\n";
            }
        }

        echo "\n========================================\n";
        echo "WOOCOMMERCE DELETION COMPLETE!\n";
        echo "========================================\n";
        echo "Total WC orders found:    {$wc_count}\n";
        echo "Successfully deleted:     {$wc_deleted}\n";
        echo "Failed to delete:         {$wc_failed}\n";
        echo "========================================\n\n";

        // Verify WC deletion
        $wc_remaining = get_posts([
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ]);

        $wc_remaining_count = count($wc_remaining);
        echo "Verification: {$wc_remaining_count} WooCommerce orders remaining\n";

        if ($wc_remaining_count === 0) {
            echo "✓ SUCCESS: All WooCommerce orders deleted!\n\n";
        } else {
            echo "⚠ WARNING: {$wc_remaining_count} WooCommerce orders still exist!\n\n";
        }
    }
} else {
    echo "⚠ WooCommerce not active. Skipping WooCommerce order deletion.\n\n";
}

echo "========================================\n";
echo "✅ ALL DONE!\n";
echo "========================================\n";
