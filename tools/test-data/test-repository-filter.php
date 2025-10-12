<?php
/**
 * Test OrderRepository delivery_type filtering
 * Usage: wp eval-file wp-content/plugins/squidly-core/tools/test-data/test-repository-filter.php
 */

require_once __DIR__ . '/../../includes/domains/orders/post-types/OrderPostType.php';
require_once __DIR__ . '/../../includes/domains/orders/models/Order.php';
require_once __DIR__ . '/../../includes/domains/orders/repositories/OrderRepository.php';

if (!function_exists('get_posts')) {
    die("ERROR: WordPress not loaded.\n");
}

echo "\n========================================\n";
echo "🧪 TESTING OrderRepository FILTERING\n";
echo "========================================\n\n";

$repo = new OrderRepository();

// Test 1: All orders
$all_orders = $repo->findBy([]);
echo "Total orders: " . count($all_orders) . "\n\n";

// Test 2: Pickup orders
echo "Testing PICKUP filter via repository...\n";
$pickup_orders = $repo->findBy(['delivery_type' => 'pickup']);
echo "Pickup orders found: " . count($pickup_orders) . "\n";
echo "Pickup order IDs: " . implode(', ', array_map(fn($o) => $o->id, $pickup_orders)) . "\n";
foreach ($pickup_orders as $order) {
    echo "  - Order {$order->id}: payment={$order->payment_method}, address=" . ($order->delivery_address ? "'{$order->delivery_address}'" : "EMPTY") . "\n";
}
echo "\n";

// Test 3: Delivery orders
echo "Testing DELIVERY filter via repository...\n";
$delivery_orders = $repo->findBy(['delivery_type' => 'delivery']);
echo "Delivery orders found: " . count($delivery_orders) . "\n";
echo "Delivery order IDs: " . implode(', ', array_map(fn($o) => $o->id, $delivery_orders)) . "\n";
foreach ($delivery_orders as $order) {
    echo "  - Order {$order->id}: payment={$order->payment_method}, address=" . ($order->delivery_address ? "'{$order->delivery_address}'" : "EMPTY") . "\n";
}
echo "\n";

echo "========================================\n";
echo "Pickup + Delivery = " . (count($pickup_orders) + count($delivery_orders)) . " (should equal total: " . count($all_orders) . ")\n";

if (count($pickup_orders) + count($delivery_orders) === count($all_orders)) {
    echo "✓ SUCCESS: All orders accounted for!\n";
} else {
    echo "✗ WARNING: Some orders missing!\n";
}

echo "========================================\n\n";
