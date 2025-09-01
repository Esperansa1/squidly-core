<?php
/**
 * Cleanup All Test Data Script
 * 
 * This script completely removes all test data created by the system including:
 * - All orders and order items
 * - All customers 
 * - All products and their relationships
 * - All ingredients
 * - All product groups and group items
 * - All store branches
 * - All payment-related test data
 * - All WooCommerce orders created by the payment system
 * 
 * WARNING: This will delete ALL data, not just test data!
 * Use with caution and only in development environments.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

// Import all required classes
require_once __DIR__ . '/../../includes/domains/orders/post-types/OrderPostType.php';
require_once __DIR__ . '/../../includes/domains/orders/models/Order.php';
require_once __DIR__ . '/../../includes/domains/orders/repositories/OrderRepository.php';
require_once __DIR__ . '/../../includes/domains/customers/models/Customer.php';
require_once __DIR__ . '/../../includes/domains/customers/repositories/CustomerRepository.php';
require_once __DIR__ . '/../../includes/domains/products/models/Product.php';
require_once __DIR__ . '/../../includes/domains/products/models/Ingredient.php';
require_once __DIR__ . '/../../includes/domains/products/models/ProductGroup.php';
require_once __DIR__ . '/../../includes/domains/products/models/GroupItem.php';
require_once __DIR__ . '/../../includes/shared/models/enums/ItemType.php';
require_once __DIR__ . '/../../includes/domains/products/repositories/ProductRepository.php';
require_once __DIR__ . '/../../includes/domains/products/repositories/IngredientRepository.php';
require_once __DIR__ . '/../../includes/domains/products/repositories/ProductGroupRepository.php';
require_once __DIR__ . '/../../includes/domains/products/repositories/GroupItemRepository.php';
require_once __DIR__ . '/../../includes/domains/stores/models/StoreBranch.php';
require_once __DIR__ . '/../../includes/domains/stores/repositories/StoreBranchRepository.php';

echo "<h1>🧹 Cleanup All Test Data</h1>";
echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
echo "<h2>⚠️ WARNING</h2>";
echo "<p><strong>This script will DELETE ALL DATA from the following:</strong></p>";
echo "<ul>";
echo "<li>All Orders and Order Items</li>";
echo "<li>All Customers</li>";
echo "<li>All Products and Product Groups</li>";
echo "<li>All Ingredients and Group Items</li>";
echo "<li>All Store Branches</li>";
echo "<li>All WooCommerce Orders created by payment system</li>";
echo "</ul>";
echo "<p><strong>This action cannot be undone!</strong></p>";
echo "</div>";

// Safety confirmation
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px 0; border-left: 4px solid #dc3545;'>";
    echo "<h3>Confirmation Required</h3>";
    echo "<p>To proceed with the cleanup, click the button below:</p>";
    echo "<a href='" . $_SERVER['REQUEST_URI'] . "?confirm=yes' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗑️ YES, DELETE ALL DATA</a>";
    echo "</div>";
    exit;
}

try {
    $deleted_counts = [];
    
    // Initialize repositories
    $orderRepo = new OrderRepository();
    $customerRepo = new CustomerRepository();
    $productRepo = new ProductRepository();
    $ingredientRepo = new IngredientRepository();
    $productGroupRepo = new ProductGroupRepository();
    $groupItemRepo = new GroupItemRepository();
    $storeBranchRepo = new StoreBranchRepository();

    echo "<h2>🗑️ Starting Cleanup Process</h2>";

    // 1. Delete all orders first (to avoid foreign key issues)
    echo "<h3>📦 Deleting Orders</h3>";
    $orders = get_posts([
        'post_type' => OrderPostType::POST_TYPE,
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['orders'] = 0;
    foreach ($orders as $order_post) {
        // Also delete associated WooCommerce orders
        $woo_order_id = get_post_meta($order_post->ID, '_woocommerce_order_id', true);
        if ($woo_order_id && class_exists('WC_Order')) {
            $woo_order = wc_get_order($woo_order_id);
            if ($woo_order) {
                $woo_order->delete(true);
                echo "<div style='color: orange;'>🛒 Deleted WooCommerce order ID: {$woo_order_id}</div>";
            }
        }
        
        wp_delete_post($order_post->ID, true);
        $deleted_counts['orders']++;
        echo "<div style='color: red;'>❌ Deleted order ID: {$order_post->ID}</div>";
    }

    // 2. Delete all customers
    echo "<h3>👥 Deleting Customers</h3>";
    $customers = get_posts([
        'post_type' => 'customer', // Assuming customer post type
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['customers'] = 0;
    foreach ($customers as $customer_post) {
        wp_delete_post($customer_post->ID, true);
        $deleted_counts['customers']++;
        echo "<div style='color: red;'>❌ Deleted customer ID: {$customer_post->ID}</div>";
    }

    // 3. Delete all product groups first (they reference group items)
    echo "<h3>📋 Deleting Product Groups</h3>";
    $product_groups = get_posts([
        'post_type' => 'product_group', // Assuming product group post type
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['product_groups'] = 0;
    foreach ($product_groups as $group_post) {
        wp_delete_post($group_post->ID, true);
        $deleted_counts['product_groups']++;
        echo "<div style='color: red;'>❌ Deleted product group ID: {$group_post->ID}</div>";
    }

    // 4. Delete all group items
    echo "<h3>🔗 Deleting Group Items</h3>";
    $group_items = get_posts([
        'post_type' => 'group_item', // Assuming group item post type
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['group_items'] = 0;
    foreach ($group_items as $item_post) {
        wp_delete_post($item_post->ID, true);
        $deleted_counts['group_items']++;
        echo "<div style='color: red;'>❌ Deleted group item ID: {$item_post->ID}</div>";
    }

    // 5. Delete all products
    echo "<h3>🍔 Deleting Products</h3>";
    $products = get_posts([
        'post_type' => 'product', // Assuming product post type
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['products'] = 0;
    foreach ($products as $product_post) {
        wp_delete_post($product_post->ID, true);
        $deleted_counts['products']++;
        echo "<div style='color: red;'>❌ Deleted product ID: {$product_post->ID}</div>";
    }

    // 6. Delete all ingredients
    echo "<h3>🥬 Deleting Ingredients</h3>";
    $ingredients = get_posts([
        'post_type' => 'ingredient', // Assuming ingredient post type
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['ingredients'] = 0;
    foreach ($ingredients as $ingredient_post) {
        wp_delete_post($ingredient_post->ID, true);
        $deleted_counts['ingredients']++;
        echo "<div style='color: red;'>❌ Deleted ingredient ID: {$ingredient_post->ID}</div>";
    }

    // 7. Delete all store branches
    echo "<h3>🏢 Deleting Store Branches</h3>";
    $branches = get_posts([
        'post_type' => 'store_branch', // Assuming store branch post type
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['store_branches'] = 0;
    foreach ($branches as $branch_post) {
        wp_delete_post($branch_post->ID, true);
        $deleted_counts['store_branches']++;
        echo "<div style='color: red;'>❌ Deleted store branch ID: {$branch_post->ID}</div>";
    }

    // 8. Clean up WooCommerce payment product if it exists
    echo "<h3>💳 Cleaning WooCommerce Payment Products</h3>";
    $payment_products = get_posts([
        'post_type' => 'product',
        'meta_query' => [
            [
                'key' => '_squidly_payment_product',
                'value' => 'yes',
                'compare' => '='
            ]
        ],
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    $deleted_counts['payment_products'] = 0;
    foreach ($payment_products as $payment_product) {
        wp_delete_post($payment_product->ID, true);
        $deleted_counts['payment_products']++;
        echo "<div style='color: orange;'>💳 Deleted payment product ID: {$payment_product->ID}</div>";
    }

    // 9. Clean up orphaned post meta
    echo "<h3>🧹 Cleaning Orphaned Meta Data</h3>";
    global $wpdb;
    
    $orphaned_meta = $wpdb->query("
        DELETE pm FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.ID IS NULL
    ");
    
    echo "<div style='color: blue;'>🧹 Cleaned {$orphaned_meta} orphaned meta entries</div>";

    echo "<h2>✅ Cleanup Complete!</h2>";
    echo "<div style='background: #d4edda; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;'>";
    echo "<h3>📊 Deletion Summary:</h3>";
    echo "<ul>";
    foreach ($deleted_counts as $type => $count) {
        $formatted_type = ucfirst(str_replace('_', ' ', $type));
        echo "<li><strong>{$formatted_type}:</strong> {$count} deleted</li>";
    }
    echo "<li><strong>Orphaned Meta:</strong> {$orphaned_meta} cleaned</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;'>";
    echo "<h3>🎯 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>All test data has been completely removed</li>";
    echo "<li>Database is clean and ready for fresh data</li>";
    echo "<li>You can now run create-full-store-test.php to generate new test data</li>";
    echo "<li>All WooCommerce integration data has been cleaned</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='color: red; background: #fed7d7; padding: 20px; margin: 20px 0;'>";
    echo "<h2>❌ Error During Cleanup</h2>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>