<?php
/**
 * Create 300 Test Orders
 *
 * Generates 300 diverse test orders with:
 * - Random customers
 * - Random branches
 * - Random order items and quantities
 * - Random order types (pickup/delivery)
 * - Random payment methods
 * - Random order statuses
 * - Random payment statuses
 * - Realistic date distribution over past 90 days
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

// Import required classes
require_once __DIR__ . '/../../includes/domains/orders/post-types/OrderPostType.php';
require_once __DIR__ . '/../../includes/domains/orders/models/Order.php';
require_once __DIR__ . '/../../includes/domains/orders/models/OrderItem.php';
require_once __DIR__ . '/../../includes/domains/orders/repositories/OrderRepository.php';
require_once __DIR__ . '/../../includes/domains/customers/models/Customer.php';
require_once __DIR__ . '/../../includes/domains/customers/repositories/CustomerRepository.php';
require_once __DIR__ . '/../../includes/domains/products/models/Product.php';
require_once __DIR__ . '/../../includes/domains/products/repositories/ProductRepository.php';
require_once __DIR__ . '/../../includes/domains/stores/models/StoreBranch.php';
require_once __DIR__ . '/../../includes/domains/stores/repositories/StoreBranchRepository.php';

echo "<h1>📦 Creating 300 Test Orders</h1>";

try {
    // Initialize repositories
    $orderRepo = new OrderRepository();
    $customerRepo = new CustomerRepository();
    $productRepo = new ProductRepository();
    $storeBranchRepo = new StoreBranchRepository();

    echo "<h2>📋 Loading Existing Data</h2>";

    // Get existing customers
    $customers = $customerRepo->findBy([]);
    if (empty($customers)) {
        throw new Exception('No customers found. Please create customers first.');
    }
    $customer_ids = array_map(fn($c) => $c->id, $customers);
    echo "<div style='color: green;'>✅ Found " . count($customer_ids) . " customers</div>";

    // Get existing branches
    $branches = $storeBranchRepo->findBy([]);
    if (empty($branches)) {
        throw new Exception('No branches found. Please create branches first.');
    }
    $branch_ids = array_map(fn($b) => $b->id, $branches);
    echo "<div style='color: green;'>✅ Found " . count($branch_ids) . " branches</div>";

    // Get existing products
    $products = $productRepo->findBy([]);
    if (empty($products)) {
        throw new Exception('No products found. Please create products first.');
    }
    $product_ids = array_map(fn($p) => $p->id, $products);
    $products_by_id = [];
    foreach ($products as $product) {
        $products_by_id[$product->id] = $product;
    }
    echo "<div style='color: green;'>✅ Found " . count($product_ids) . " products</div>";

    // Order statuses for variety
    $statuses = [
        Order::STATUS_PENDING,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_COMPLETED,
        Order::STATUS_CANCELLED
    ];

    // Payment statuses for variety
    $payment_statuses = [
        Order::PAYMENT_PENDING,
        Order::PAYMENT_PAID,
        Order::PAYMENT_FAILED
    ];

    // Payment methods for variety
    $payment_methods = [
        Order::PAYMENT_CASH,
        Order::PAYMENT_CARD,
        Order::PAYMENT_ONLINE
    ];

    // Delivery addresses for variety
    $delivery_addresses = [
        'רחוב הרצל 123, תל אביב',
        'שדרות בן גוריון 456, תל אביב',
        'רחוב דיזנגוף 789, תל אביב',
        'רחוב אלנבי 321, תל אביב',
        'שדרות רוטשילד 654, תל אביב',
        'רחוב בן יהודה 987, תל אביב',
        'רחוב שינקין 111, תל אביב',
        'שדרות חן 222, תל אביב',
        'רחוב פרישמן 333, תל אביב',
        'רחוב בוגרשוב 444, תל אביב'
    ];

    $special_instructions = [
        'אנא התקשרו בהגעה',
        'השאירו ליד הדלת',
        'בקומה שנייה',
        'דירה 8',
        'בבניין הכחול',
        'בצד ימין של הבניין',
        'קוד כניסה: 1234',
        'אל תצלצלו - התינוק ישן',
        'השאירו עם השומר',
        null,
        null,
        null
    ];

    echo "<h2>🔄 Creating 300 Orders</h2>";
    echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>This may take a minute...</div>";

    $order_ids = [];
    $pickup_count = 0;
    $delivery_count = 0;
    $status_counts = [];
    $payment_status_counts = [];

    for ($i = 1; $i <= 300; $i++) {
        // Randomly select customer and branch
        $customer_id = $customer_ids[array_rand($customer_ids)];
        $branch_id = $branch_ids[array_rand($branch_ids)];

        // Randomly select 1-5 products
        $num_items = rand(1, 5);
        $items = [];

        for ($j = 0; $j < $num_items; $j++) {
            $product_id = $product_ids[array_rand($product_ids)];
            $product = $products_by_id[$product_id];
            $quantity = rand(1, 3);

            $items[] = [
                'product_id' => $product_id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'modifications' => [],
                'notes' => null
            ];
        }

        // Randomly determine if delivery or pickup
        $is_delivery = rand(0, 1) === 1;
        $payment_method = $payment_methods[array_rand($payment_methods)];

        // Online payment implies delivery
        if ($payment_method === Order::PAYMENT_ONLINE) {
            $is_delivery = true;
        }

        $delivery_address = $is_delivery ? $delivery_addresses[array_rand($delivery_addresses)] : null;
        $special_instruction = $special_instructions[array_rand($special_instructions)];

        if ($is_delivery) {
            $delivery_count++;
        } else {
            $pickup_count++;
        }

        // Create order using createFromCartData
        $cart_data = [
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
            'items' => $items,
            'delivery_address' => $delivery_address,
            'special_instructions' => $special_instruction,
            'payment_method' => $payment_method,
            'delivery_fee' => $is_delivery ? rand(5, 15) : 0
        ];

        $order = $orderRepo->createFromCartData($cart_data);

        // Randomly assign status and payment status
        $status = $statuses[array_rand($statuses)];
        $payment_status = $payment_statuses[array_rand($payment_statuses)];

        // Logic: completed orders should be paid
        if ($status === Order::STATUS_COMPLETED) {
            $payment_status = Order::PAYMENT_PAID;
        }

        // Logic: cancelled orders might be refunded if they were paid
        if ($status === Order::STATUS_CANCELLED && rand(0, 1) === 1) {
            $payment_status = Order::PAYMENT_PAID; // Some cancelled orders were paid
        }

        // Update order status and payment status
        $orderRepo->updateStatus($order->id, $status);
        $orderRepo->updatePaymentStatus($order->id, $payment_status);

        // Randomly assign order date within last 90 days
        $days_ago = rand(0, 90);
        $order_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));

        // Update the post date
        wp_update_post([
            'ID' => $order->id,
            'post_date' => $order_date,
            'post_date_gmt' => get_gmt_from_date($order_date)
        ]);

        $order_ids[] = $order->id;

        // Track statistics
        if (!isset($status_counts[$status])) {
            $status_counts[$status] = 0;
        }
        $status_counts[$status]++;

        if (!isset($payment_status_counts[$payment_status])) {
            $payment_status_counts[$payment_status] = 0;
        }
        $payment_status_counts[$payment_status]++;

        // Show progress every 50 orders
        if ($i % 50 === 0) {
            echo "<div style='color: blue;'>⏳ Created {$i} orders...</div>";
            flush();
        }
    }

    echo "<h2>✅ Successfully Created 300 Test Orders!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>";
    echo "<h3>📊 Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Total Orders:</strong> " . count($order_ids) . "</li>";
    echo "<li><strong>Pickup Orders:</strong> {$pickup_count} 🛍️</li>";
    echo "<li><strong>Delivery Orders:</strong> {$delivery_count} 🚚</li>";
    echo "<li><strong>Date Range:</strong> Last 90 days</li>";
    echo "</ul>";

    echo "<h4>📦 Order Status Breakdown:</h4>";
    echo "<ul>";
    foreach ($status_counts as $status => $count) {
        echo "<li><strong>{$status}:</strong> {$count} orders</li>";
    }
    echo "</ul>";

    echo "<h4>💳 Payment Status Breakdown:</h4>";
    echo "<ul>";
    foreach ($payment_status_counts as $payment_status => $count) {
        echo "<li><strong>{$payment_status}:</strong> {$count} orders</li>";
    }
    echo "</ul>";
    echo "</div>";

    echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
    echo "<h3>📋 Order IDs Range:</h3>";
    echo "<p>First Order ID: <strong>" . $order_ids[0] . "</strong></p>";
    echo "<p>Last Order ID: <strong>" . $order_ids[count($order_ids) - 1] . "</strong></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='color: red; background: #fed7d7; padding: 20px; margin: 20px 0;'>";
    echo "<h2>❌ Error Creating Test Orders</h2>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Trace:</strong><pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>
