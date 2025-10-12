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
echo "Verification: {$remaining_count} orders remaining in database\n";

if ($remaining_count === 0) {
    echo "✓ SUCCESS: All orders deleted!\n\n";
} else {
    echo "⚠ WARNING: {$remaining_count} orders still exist!\n\n";
}
