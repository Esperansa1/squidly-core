<?php
/**
 * Create Full Store Test Data
 * 
 * Creates comprehensive test data including:
 * - Store branches
 * - Complex products (with groups, ingredients)
 * - Customers
 * - Complete orders with all custom details
 * - Simulates full order creation and payment flow
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
require_once __DIR__ . '/../../includes/domains/orders/models/OrderItem.php';
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

echo "<h1>🏪 Creating Full Store Test Data</h1>";

try {
    // Initialize repositories
    $storeBranchRepo = new StoreBranchRepository();
    $productRepo = new ProductRepository();
    $ingredientRepo = new IngredientRepository();
    $productGroupRepo = new ProductGroupRepository();
    $groupItemRepo = new GroupItemRepository();
    $customerRepo = new CustomerRepository();
    $orderRepo = new OrderRepository();

    echo "<h2>🏢 Creating Store Branches</h2>";
    
    // Create store branches with correct data structure
    $branches = [
        [
            'name' => 'סקווידלי מרכז העיר',
            'phone' => '+972-3-1234567',
            'city' => 'תל אביב',
            'address' => 'רחוב הראשי 123, תל אביב, ישראל',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['10:00-22:00'],
                'MONDAY' => ['10:00-22:00'],
                'TUESDAY' => ['10:00-22:00'],
                'WEDNESDAY' => ['10:00-22:00'],
                'THURSDAY' => ['10:00-23:00'],
                'FRIDAY' => ['10:00-15:00'],
                'SATURDAY' => []
            ],
            'kosher_type' => 'kosher',
            'accessibility_list' => ['wheelchair_accessible', 'braille_menu']
        ],
        [
            'name' => 'סקווידלי החוף',
            'phone' => '+972-3-2345678',
            'city' => 'תל אביב',
            'address' => 'שדרות החוף 456, תל אביב, ישראל',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['09:00-22:00'],
                'MONDAY' => ['09:00-22:00'],
                'TUESDAY' => ['09:00-22:00'],
                'WEDNESDAY' => ['09:00-22:00'],
                'THURSDAY' => ['09:00-22:00'],
                'FRIDAY' => ['09:00-14:00'],
                'SATURDAY' => []
            ],
            'kosher_type' => 'kosher',
            'accessibility_list' => ['wheelchair_accessible', 'outdoor_seating']
        ]
    ];
    
    $branch_ids = [];
    foreach ($branches as $branch_data) {
        $branch_id = $storeBranchRepo->create($branch_data);
        $branch_ids[] = $branch_id;
        echo "<div style='color: green;'>✅ Created branch: {$branch_data['name']} (ID: {$branch_id})</div>";
    }

    echo "<h2>🍔 Creating Hamburger Ingredients</h2>";

    // Create hamburger addon ingredients for ingredient group
    $ingredients_data = [
        // Hamburger Add-ons
        ['name' => 'גבינה אמריקאית', 'price' => 2.00],
        ['name' => 'בייקון', 'price' => 4.00],
        ['name' => 'אבוקדו', 'price' => 3.00],
        ['name' => 'פטריות', 'price' => 2.00],
        ['name' => 'חסה נוספת', 'price' => 1.00],
        ['name' => 'עגבנייה נוספת', 'price' => 1.50],
        ['name' => 'בצל אדום', 'price' => 1.00],
        ['name' => 'חמוצים', 'price' => 1.50],
    ];
    
    $ingredient_ids = [];
    foreach ($ingredients_data as $ingredient_data) {
        $ingredient_id = $ingredientRepo->create($ingredient_data);
        $ingredient_ids[] = $ingredient_id;
        
        // Set branch availability for each ingredient
        foreach ($branch_ids as $index => $branch_id) {
            // Make most ingredients available in all branches, but some only in specific branches for testing
            $is_available = true;

            // Make some ingredients branch-specific for testing
            if (in_array($ingredient_data['name'], ['בייקון', 'אבוקדו'])) {
                // These special items only available in branch 1 (index 0)
                $is_available = ($index === 0);
            } elseif (in_array($ingredient_data['name'], ['פטריות', 'חמוצים'])) {
                // These items only available in branch 2 (index 1)
                $is_available = ($index === 1);
            }

            update_post_meta($ingredient_id, '_branch_availability_' . $branch_id, $is_available ? '1' : '0');
        }

        // Show branch availability info
        $branch_info = [];
        foreach ($branch_ids as $index => $branch_id) {
            $is_available = true;
            if (in_array($ingredient_data['name'], ['בייקון', 'אבוקדו'])) {
                $is_available = ($index === 0);
            } elseif (in_array($ingredient_data['name'], ['פטריות', 'חמוצים'])) {
                $is_available = ($index === 1);
            }
            $branch_info[] = "Branch {$branch_id}: " . ($is_available ? "✅" : "❌");
        }
        
        echo "<div style='color: green;'>✅ Created ingredient: {$ingredient_data['name']} (ID: {$ingredient_id}) - " . implode(", ", $branch_info) . "</div>";
    }

    echo "<h2>🍟 Creating Sides and Drinks Products</h2>";

    // Create sides products for product group
    $sides_data = [
        [
            'name' => 'צ\'יפס רגיל',
            'description' => 'צ\'יפס זהוב ופריך',
            'price' => 8.00,
            'category' => 'sides',
            'tags' => ['תוספת', 'פריך']
        ],
        [
            'name' => 'צ\'יפס בטטה',
            'description' => 'צ\'יפס בטטה מתוקה',
            'price' => 10.00,
            'category' => 'sides',
            'tags' => ['תוספת', 'בטטה', 'מתוק']
        ],
        [
            'name' => 'טבעות בצל',
            'description' => 'טבעות בצל פריכות וזהובות',
            'price' => 9.00,
            'category' => 'sides',
            'tags' => ['תוספת', 'בצל', 'פריך']
        ],
        [
            'name' => 'סלט קטן',
            'description' => 'סלט ירקות טרי',
            'price' => 7.00,
            'category' => 'sides',
            'tags' => ['תוספת', 'בריא', 'ירקות']
        ]
    ];

    // Create drinks products for product group
    $drinks_data = [
        [
            'name' => 'קוקה קולה',
            'description' => 'משקה קולה קר',
            'price' => 5.00,
            'category' => 'drinks',
            'tags' => ['משקה', 'קר', 'קולה']
        ],
        [
            'name' => 'ספרייט',
            'description' => 'משקה לימון-ליים קר',
            'price' => 5.00,
            'category' => 'drinks',
            'tags' => ['משקה', 'קר', 'לימון']
        ],
        [
            'name' => 'מיץ תפוזים',
            'description' => 'מיץ תפוזים טבעי',
            'price' => 6.00,
            'category' => 'drinks',
            'tags' => ['משקה', 'מיץ', 'טבעי']
        ],
        [
            'name' => 'מים',
            'description' => 'מים מינרלים',
            'price' => 3.00,
            'category' => 'drinks',
            'tags' => ['משקה', 'מים', 'מינרלים']
        ]
    ];

    $sides_ids = [];
    foreach ($sides_data as $product_data) {
        // Set availability for all branches
        $availability = [];
        foreach ($branch_ids as $branch_id) {
            $availability[$branch_id] = true;
        }
        $product_data['availability'] = $availability;

        $product_id = $productRepo->create($product_data);
        $sides_ids[] = $product_id;
        echo "<div style='color: green;'>✅ Created sides product: {$product_data['name']} (ID: {$product_id})</div>";
    }

    $drinks_ids = [];
    foreach ($drinks_data as $product_data) {
        // Set availability for all branches
        $availability = [];
        foreach ($branch_ids as $branch_id) {
            $availability[$branch_id] = true;
        }
        $product_data['availability'] = $availability;

        $product_id = $productRepo->create($product_data);
        $drinks_ids[] = $product_id;
        echo "<div style='color: green;'>✅ Created drinks product: {$product_data['name']} (ID: {$product_id})</div>";
    }

    echo "<h2>🎯 Creating Product Groups</h2>";

    // Create INGREDIENT Product Group (for hamburger add-ons)
    $ingredient_groups_data = [
        [
            'name' => 'תוספות להמבורגר',
            'description' => 'תוספות נוספות להמבורגר שלך',
            'type' => 'ingredient',
            'ingredient_ids' => $ingredient_ids // All ingredients are hamburger add-ons
        ]
    ];

    // Create PRODUCT Product Groups (for sides and drinks)
    $product_groups_data = [
        [
            'name' => 'תוספות',
            'description' => 'צ\'יפס, טבעות בצל וסלטים',
            'type' => 'product',
            'product_ids' => $sides_ids
        ],
        [
            'name' => 'משקאות',
            'description' => 'משקאות קרים ומיצים',
            'type' => 'product',
            'product_ids' => $drinks_ids
        ]
    ];
    
    echo "<h3>🥬 Creating Ingredient Product Groups</h3>";
    $ingredient_group_ids = [];
    foreach ($ingredient_groups_data as $group_data) {
        // Create GroupItems first for ingredients
        $group_item_ids = [];
        foreach ($group_data['ingredient_ids'] as $ingredient_id) {
            $group_item_data = [
                'item_id' => $ingredient_id,
                'item_type' => 'ingredient',
                'override_price' => null
            ];
            $group_item_id = $groupItemRepo->create($group_item_data);
            $group_item_ids[] = $group_item_id;
        }
        
        // Set availability for all branches
        $availability = [];
        foreach ($branch_ids as $branch_id) {
            $availability[$branch_id] = true;
        }

        // Create ProductGroup of type 'ingredient'
        $product_group_data = [
            'name' => $group_data['name'],
            'description' => $group_data['description'],
            'type' => $group_data['type'],
            'group_item_ids' => $group_item_ids,
            'availability' => $availability
        ];
        $group_id = $productGroupRepo->create($product_group_data);
        $ingredient_group_ids[] = $group_id;
        echo "<div style='color: orange;'>✅ Created INGREDIENT group: {$group_data['name']} (ID: {$group_id}) with " . count($group_item_ids) . " ingredients</div>";
    }

    echo "<h3>🍔 Creating Product Product Groups</h3>";
    $product_group_ids = [];
    foreach ($product_groups_data as $group_data) {
        // Create GroupItems first for products
        $group_item_ids = [];
        foreach ($group_data['product_ids'] as $product_id) {
            $group_item_data = [
                'item_id' => $product_id,
                'item_type' => 'product',
                'override_price' => null
            ];
            $group_item_id = $groupItemRepo->create($group_item_data);
            $group_item_ids[] = $group_item_id;
        }

        // Set availability for all branches
        $availability = [];
        foreach ($branch_ids as $branch_id) {
            $availability[$branch_id] = true;
        }

        // Create ProductGroup of type 'product'
        $product_group_data = [
            'name' => $group_data['name'],
            'description' => $group_data['description'],
            'type' => $group_data['type'],
            'group_item_ids' => $group_item_ids,
            'availability' => $availability
        ];
        $group_id = $productGroupRepo->create($product_group_data);
        $product_group_ids[] = $group_id;
        echo "<div style='color: purple;'>✅ Created PRODUCT group: {$group_data['name']} (ID: {$group_id}) with " . count($group_item_ids) . " products</div>";
    }

    echo "<h2>🍔 Creating Hamburger Product</h2>";

    // Create a single hamburger product with product groups (sides and drinks) and ingredient group (add-ons)
    $hamburger_data = [
        'name' => 'המבורגר קלאסי',
        'description' => 'המבורגר עם קציצת בקר, חסה, עגבנייה וגבינה',
        'price' => 28.00,
        'category' => 'burgers',
        'tags' => ['בשר', 'קלאסי', 'המבורגר'],
        'product_group_ids' => array_merge($ingredient_group_ids, $product_group_ids) // All groups
    ];

    // Set availability for all branches
    $availability = [];
    foreach ($branch_ids as $branch_id) {
        $availability[$branch_id] = true;
    }
    $hamburger_data['availability'] = $availability;

    $hamburger_id = $productRepo->create($hamburger_data);
    echo "<div style='color: green;'>✅ Created hamburger: {$hamburger_data['name']} (ID: {$hamburger_id})</div>";
    echo "<div style='color: blue;'>🔗 Assigned ingredient group (תוספות להמבורגר) and product groups (תוספות, משקאות)</div>";

    $product_ids = [$hamburger_id];

    echo "<h2>👥 Creating Customers</h2>";
    
    // Create test customers
    $customers_data = [
        [
            'first_name' => 'דוד',
            'last_name' => 'כהן',
            'email' => 'david.cohen@example.com',
            'phone' => '+972-50-1234567',
            'auth_provider' => 'phone',
            'address' => 'שדרות רוטשילד 789, תל אביב',
            'city' => 'תל אביב',
            'postal_code' => '6578912',
            'country' => 'ישראל',
            'dietary_preferences' => ['kosher', 'no_nuts'],
            'marketing_consent' => true
        ],
        [
            'first_name' => 'שרה',
            'last_name' => 'לוי',
            'email' => 'sarah.levy@example.com',
            'phone' => '+972-52-7654321',
            'auth_provider' => 'google',
            'address' => 'רחוב בן יהודה 456, תל אביב',
            'city' => 'תל אביב',
            'postal_code' => '6340567',
            'country' => 'ישראל',
            'dietary_preferences' => ['vegetarian'],
            'marketing_consent' => false
        ]
    ];
    
    $customer_ids = [];
    foreach ($customers_data as $customer_data) {
        $customer_id = $customerRepo->create($customer_data);
        $customer_ids[] = $customer_id;
        echo "<div style='color: green;'>✅ Created customer: {$customer_data['first_name']} {$customer_data['last_name']} (ID: {$customer_id})</div>";
    }

    echo "<h2>📦 Creating Complete Orders</h2>";

    // Create test orders with hamburger and add-ons
    $orders_data = [
        [
            'customer_id' => $customer_ids[0],
            'branch_id' => $branch_ids[0],
            'items' => [
                [
                    'product_id' => $hamburger_id,
                    'product_name' => 'המבורגר קלאסי',
                    'quantity' => 1,
                    'unit_price' => 28.00,
                    'modifications' => [
                        'addons' => ['גבינה אמריקאית', 'בייקון'],
                        'sides' => ['צ\'יפס רגיל'],
                        'drinks' => ['קוקה קולה']
                    ],
                    'notes' => 'צלייה בינונית'
                ]
            ]
        ],
        [
            'customer_id' => $customer_ids[1],
            'branch_id' => $branch_ids[1],
            'items' => [
                [
                    'product_id' => $hamburger_id,
                    'product_name' => 'המבורגר קלאסי',
                    'quantity' => 2,
                    'unit_price' => 28.00,
                    'modifications' => [
                        'addons' => ['אבוקדו', 'חסה נוספת'],
                        'sides' => ['טבעות בצל'],
                        'drinks' => ['מיץ תפוזים']
                    ],
                    'notes' => 'אופציה בריאה'
                ]
            ]
        ]
    ];
    
    $order_ids = [];
    foreach ($orders_data as $order_data) {
        // Use the createFromCartData method to create complete orders
        $cart_data = [
            'customer_id' => $order_data['customer_id'],
            'branch_id' => $order_data['branch_id'],
            'items' => $order_data['items'],
            'delivery_address' => 'כתובת בדיקה 123, תל אביב',
            'special_instructions' => 'הזמנת בדיקה שנוצרה על ידי סקריפט',
            'payment_method' => 'online'
        ];
        
        $order = $orderRepo->createFromCartData($cart_data);
        $order_ids[] = $order->id;
        echo "<div style='color: green;'>✅ Created complete order (ID: {$order->id}) for customer ID: {$order_data['customer_id']}</div>";
        echo "<div style='margin-left: 20px; color: blue;'>💰 Order total: ₪{$order->total_amount}</div>";
    }

    echo "<h2>✅ Simple Hamburger Restaurant Test Data Creation Complete!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>";
    echo "<h3>📊 Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Store Branches:</strong> " . count($branch_ids) . " created</li>";
    echo "<li><strong>Hamburger Add-on Ingredients:</strong> " . count($ingredient_ids) . " created with branch-specific availability</li>";
    echo "<li><strong>Sides Products:</strong> " . count($sides_ids) . " created (fries, onion rings, salad)</li>";
    echo "<li><strong>Drinks Products:</strong> " . count($drinks_ids) . " created (coke, sprite, juice, water)</li>";
    echo "<li><strong>INGREDIENT Product Groups:</strong> " . count($ingredient_group_ids) . " created (hamburger add-ons)</li>";
    echo "<li><strong>PRODUCT Product Groups:</strong> " . count($product_group_ids) . " created (sides and drinks)</li>";
    echo "<li><strong>Hamburger Product:</strong> 1 created with all groups assigned</li>";
    echo "<li><strong>Customers:</strong> " . count($customer_ids) . " created</li>";
    echo "<li><strong>Complete Orders:</strong> " . count($order_ids) . " created with modifications</li>";
    echo "</ul>";
    echo "<h4>🏢 Branch-Specific Ingredients:</h4>";
    echo "<ul>";
    echo "<li><strong>Branch 1 Only:</strong> בייקון, אבוקדו</li>";
    echo "<li><strong>Branch 2 Only:</strong> פטריות, חמוצים</li>";
    echo "<li><strong>All Branches:</strong> גבינה אמריקאית, חסה נוספת, עגבנייה נוספת, בצל אדום</li>";
    echo "</ul>";
    echo "<h4>🍔 Group Structure:</h4>";
    echo "<ul>";
    echo "<li><strong>Ingredient Group:</strong> תוספות להמבורגר (hamburger add-ons)</li>";
    echo "<li><strong>Product Groups:</strong> תוספות (sides), משקאות (drinks)</li>";
    echo "</ul>";
    echo "<h4>🍔 Product Structure:</h4>";
    echo "<ul>";
    echo "<li><strong>המבורגר קלאסי:</strong> Has 1 ingredient group (add-ons) + 2 product groups (sides + drinks)</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
    echo "<h3>🧪 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to WordPress Admin → Orders</li>";
    echo "<li>Find the created test orders</li>";
    echo "<li>Click the 'Pay' button to test the payment flow</li>";
    echo "<li>Verify the payment integration works correctly</li>";
    echo "</ol>";
    echo "<p><strong>Order IDs created:</strong> " . implode(', ', $order_ids) . "</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='color: red; background: #fed7d7; padding: 20px; margin: 20px 0;'>";
    echo "<h2>❌ Error Creating Test Data</h2>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>