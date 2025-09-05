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
            'name' => 'Squidly Downtown',
            'phone' => '+972-3-1234567',
            'city' => 'Tel Aviv',
            'address' => '123 Main Street, Tel Aviv, Israel',
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
            'name' => 'Squidly Beach',
            'phone' => '+972-3-2345678',
            'city' => 'Tel Aviv',
            'address' => '456 Beach Boulevard, Tel Aviv, Israel',
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

    echo "<h2>🍔 Creating Hamburger Restaurant Ingredients</h2>";
    
    // Create base ingredients for hamburger restaurant
    $ingredients_data = [
        // Meat options
        ['name' => 'Beef Patty (150g)', 'price' => 18.00],
        ['name' => 'Double Beef Patty (300g)', 'price' => 32.00],
        ['name' => 'Chicken Breast', 'price' => 15.00],
        ['name' => 'Turkey Patty', 'price' => 16.00],
        ['name' => 'Veggie Patty', 'price' => 14.00],
        ['name' => 'Beyond Meat Patty', 'price' => 22.00],
        
        // Bread options
        ['name' => 'Classic Sesame Bun', 'price' => 3.00],
        ['name' => 'Brioche Bun', 'price' => 4.50],
        ['name' => 'Whole Wheat Bun', 'price' => 3.50],
        ['name' => 'Gluten-Free Bun', 'price' => 5.00],
        ['name' => 'Pretzel Bun', 'price' => 4.00],
        
        // Cheese options
        ['name' => 'American Cheese', 'price' => 2.00],
        ['name' => 'Swiss Cheese', 'price' => 2.50],
        ['name' => 'Cheddar Cheese', 'price' => 2.50],
        ['name' => 'Blue Cheese', 'price' => 3.00],
        ['name' => 'Goat Cheese', 'price' => 3.50],
        ['name' => 'Vegan Cheese', 'price' => 3.00],
        
        // Toppings
        ['name' => 'Lettuce', 'price' => 1.00],
        ['name' => 'Tomato', 'price' => 1.50],
        ['name' => 'Red Onion', 'price' => 1.00],
        ['name' => 'Pickles', 'price' => 1.50],
        ['name' => 'Bacon', 'price' => 4.00],
        ['name' => 'Avocado', 'price' => 3.00],
        ['name' => 'Mushrooms', 'price' => 2.00],
        ['name' => 'Jalapeños', 'price' => 1.50],
        
        // Sauces
        ['name' => 'Ketchup', 'price' => 0.50],
        ['name' => 'Mustard', 'price' => 0.50],
        ['name' => 'Mayo', 'price' => 0.50],
        ['name' => 'BBQ Sauce', 'price' => 1.00],
        ['name' => 'Sriracha Mayo', 'price' => 1.50],
        ['name' => 'Garlic Aioli', 'price' => 1.50],
        
        // Sides
        ['name' => 'Regular Fries', 'price' => 8.00],
        ['name' => 'Sweet Potato Fries', 'price' => 10.00],
        ['name' => 'Onion Rings', 'price' => 9.00],
        ['name' => 'Side Salad', 'price' => 7.00],
    ];
    
    $ingredient_ids = [];
    foreach ($ingredients_data as $ingredient_data) {
        $ingredient_id = $ingredientRepo->create($ingredient_data);
        $ingredient_ids[] = $ingredient_id;
        echo "<div style='color: green;'>✅ Created ingredient: {$ingredient_data['name']} (ID: {$ingredient_id})</div>";
    }

    echo "<h2>🍔 Creating Complex Hamburger Products with Product Groups</h2>";
    
    // First create some actual products that will be used in product-type groups
    $simple_products_data = [
        [
            'name' => 'Classic Cheeseburger',
            'description' => 'Traditional cheeseburger with beef patty, cheese, lettuce, tomato',
            'price' => 28.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy'],
            'preparation_time' => 12
        ],
        [
            'name' => 'Chicken Deluxe',
            'description' => 'Grilled chicken breast with premium toppings',
            'price' => 26.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy'],
            'preparation_time' => 14
        ],
        [
            'name' => 'Veggie Supreme',
            'description' => 'Plant-based patty with fresh vegetables',
            'price' => 24.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten'],
            'preparation_time' => 10
        ],
        [
            'name' => 'BBQ Bacon Burger',
            'description' => 'Beef patty with crispy bacon and BBQ sauce',
            'price' => 32.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy'],
            'preparation_time' => 16
        ]
    ];
    
    $simple_product_ids = [];
    foreach ($simple_products_data as $product_data) {
        $simple_product_id = $productRepo->create($product_data);
        $simple_product_ids[] = $simple_product_id;
        echo "<div style='color: green;'>✅ Created simple product: {$product_data['name']} (ID: {$simple_product_id})</div>";
    }

    // Create INGREDIENT Product Groups (for customizing ingredients within a product)
    $ingredient_groups_data = [
        // Meat Selection Group
        [
            'name' => 'Choose Your Protein',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 0, 6) // First 6 are meat options
        ],
        // Bread Selection Group  
        [
            'name' => 'Choose Your Bun',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 6, 5) // Bread options
        ],
        // Cheese Selection Group
        [
            'name' => 'Add Cheese',
            'type' => 'ingredient', 
            'ingredient_ids' => array_slice($ingredient_ids, 11, 6) // Cheese options
        ],
        // Toppings Group
        [
            'name' => 'Fresh Toppings',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 17, 8) // Toppings
        ],
        // Sauce Group
        [
            'name' => 'Choose Your Sauce',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 25, 6) // Sauces
        ],
        // Sides Group
        [
            'name' => 'Add a Side',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 31, 4) // Sides
        ]
    ];

    // Create PRODUCT Product Groups (for grouping related products together)
    $product_groups_data = [
        [
            'name' => 'Signature Burgers',
            'type' => 'product',
            'product_ids' => [$simple_product_ids[0], $simple_product_ids[1]] // Classic Cheeseburger, Chicken Deluxe
        ],
        [
            'name' => 'Healthy Options',
            'type' => 'product', 
            'product_ids' => [$simple_product_ids[2]] // Veggie Supreme
        ],
        [
            'name' => 'Premium Selection',
            'type' => 'product',
            'product_ids' => [$simple_product_ids[3]] // BBQ Bacon Burger
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
        
        // Create ProductGroup of type 'ingredient'
        $product_group_data = [
            'name' => $group_data['name'],
            'type' => $group_data['type'],
            'group_item_ids' => $group_item_ids
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
        
        // Create ProductGroup of type 'product'
        $product_group_data = [
            'name' => $group_data['name'],
            'type' => $group_data['type'],
            'group_item_ids' => $group_item_ids
        ];
        $group_id = $productGroupRepo->create($product_group_data);
        $product_group_ids[] = $group_id;
        echo "<div style='color: purple;'>✅ Created PRODUCT group: {$group_data['name']} (ID: {$group_id}) with " . count($group_item_ids) . " products</div>";
    }

    // Combine all group IDs for backward compatibility with existing complex products
    $group_ids = array_merge($ingredient_group_ids, $product_group_ids);

    echo "<h3>🍴 Creating Complex Customizable Products</h3>";
    // Create complex hamburger products with multiple INGREDIENT Product Groups (for customization)
    $complex_products_data = [
        [
            'name' => 'Build Your Own Burger',
            'description' => 'Create your perfect burger with our selection of premium ingredients',
            'price' => 25.00, // Base price
            'category' => 'burgers',
            'image_url' => 'https://example.com/build-burger.jpg',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy', 'eggs'],
            'preparation_time' => 15,
            'product_groups' => [$ingredient_group_ids[0], $ingredient_group_ids[1], $ingredient_group_ids[2]] // Protein, Bun, Cheese
        ],
        [
            'name' => 'Gourmet Deluxe Burger',
            'description' => 'Our signature burger with premium toppings and artisanal ingredients',
            'price' => 35.00,
            'category' => 'burgers',
            'image_url' => 'https://example.com/deluxe-burger.jpg',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy', 'eggs'],
            'preparation_time' => 20,
            'product_groups' => [$ingredient_group_ids[0], $ingredient_group_ids[1], $ingredient_group_ids[2], $ingredient_group_ids[3]] // Protein, Bun, Cheese, Toppings
        ],
        [
            'name' => 'Ultimate Combo Meal',
            'description' => 'Complete meal with burger, sauce, and side of your choice',
            'price' => 45.00,
            'category' => 'combo',
            'image_url' => 'https://example.com/combo-meal.jpg',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy', 'eggs'],
            'preparation_time' => 25,
            'product_groups' => $ingredient_group_ids // All ingredient customization groups
        ]
    ];
    
    $complex_product_ids = [];
    foreach ($complex_products_data as $product_data) {
        $product_id = $productRepo->create($product_data);
        $complex_product_ids[] = $product_id;
        echo "<div style='color: green;'>✅ Created complex product: {$product_data['name']} (ID: {$product_id}) with " . count($product_data['product_groups']) . " ingredient groups</div>";
    }

    // Combine all product IDs for backward compatibility
    $product_ids = array_merge($simple_product_ids, $complex_product_ids);

    echo "<h2>👥 Creating Customers</h2>";
    
    // Create test customers
    $customers_data = [
        [
            'first_name' => 'David',
            'last_name' => 'Cohen',
            'email' => 'david.cohen@example.com',
            'phone' => '+972-50-1234567',
            'auth_provider' => 'phone',
            'address' => '789 Rothschild Blvd, Tel Aviv',
            'city' => 'Tel Aviv',
            'postal_code' => '6578912',
            'country' => 'Israel',
            'dietary_preferences' => ['kosher', 'no_nuts'],
            'marketing_consent' => true
        ],
        [
            'first_name' => 'Sarah',
            'last_name' => 'Levy',
            'email' => 'sarah.levy@example.com',
            'phone' => '+972-52-7654321',
            'auth_provider' => 'google',
            'address' => '456 Ben Yehuda St, Tel Aviv',
            'city' => 'Tel Aviv',
            'postal_code' => '6340567',
            'country' => 'Israel',
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
    
    // Create comprehensive test orders with complex hamburger products
    $orders_data = [
        [
            'customer_id' => $customer_ids[0],
            'branch_id' => $branch_ids[0],
            'items' => [
                [
                    'product_id' => $product_ids[0], 
                    'product_name' => 'Build Your Own Burger',
                    'quantity' => 1, 
                    'unit_price' => 25.00,
                    'modifications' => [
                        'protein' => 'Beef Patty (150g)',
                        'bun' => 'Brioche Bun',
                        'cheese' => 'Cheddar Cheese'
                    ],
                    'notes' => 'Medium-well, extra pickles'
                ],
                [
                    'product_id' => $product_ids[2], 
                    'product_name' => 'Ultimate Combo Meal',
                    'quantity' => 1, 
                    'unit_price' => 45.00,
                    'modifications' => [
                        'protein' => 'Double Beef Patty (300g)',
                        'bun' => 'Pretzel Bun',
                        'cheese' => 'Swiss Cheese',
                        'toppings' => ['Bacon', 'Avocado', 'Mushrooms'],
                        'sauce' => 'Garlic Aioli',
                        'side' => 'Sweet Potato Fries'
                    ],
                    'notes' => 'Well done, light on sauce'
                ]
            ]
        ],
        [
            'customer_id' => $customer_ids[1],
            'branch_id' => $branch_ids[1],
            'items' => [
                [
                    'product_id' => $product_ids[1], 
                    'product_name' => 'Gourmet Deluxe Burger',
                    'quantity' => 2, 
                    'unit_price' => 35.00,
                    'modifications' => [
                        'protein' => 'Beyond Meat Patty',
                        'bun' => 'Whole Wheat Bun',
                        'cheese' => 'Vegan Cheese',
                        'toppings' => ['Lettuce', 'Tomato', 'Red Onion', 'Avocado']
                    ],
                    'notes' => 'Vegan option, no mayo'
                ],
                [
                    'product_id' => $product_ids[0], 
                    'product_name' => 'Build Your Own Burger',
                    'quantity' => 1, 
                    'unit_price' => 25.00,
                    'modifications' => [
                        'protein' => 'Chicken Breast',
                        'bun' => 'Gluten-Free Bun',
                        'cheese' => 'Goat Cheese'
                    ],
                    'notes' => 'Gluten-free option, grilled chicken'
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
            'delivery_address' => '123 Test Address, Tel Aviv',
            'special_instructions' => 'Test order created by script',
            'payment_method' => 'online'
        ];
        
        $order = $orderRepo->createFromCartData($cart_data);
        $order_ids[] = $order->id;
        echo "<div style='color: green;'>✅ Created complete order (ID: {$order->id}) for customer ID: {$order_data['customer_id']}</div>";
        echo "<div style='margin-left: 20px; color: blue;'>💰 Order total: ₪{$order->total_amount}</div>";
    }

    echo "<h2>✅ Complex Hamburger Restaurant Test Data Creation Complete!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>";
    echo "<h3>📊 Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Store Branches:</strong> " . count($branch_ids) . " created</li>";
    echo "<li><strong>Ingredients:</strong> " . count($ingredient_ids) . " created (meats, buns, cheese, toppings, sauces, sides)</li>";
    echo "<li><strong>Simple Products:</strong> " . count($simple_product_ids) . " created (pre-made burgers)</li>";
    echo "<li><strong>INGREDIENT Product Groups:</strong> " . count($ingredient_group_ids) . " created (for ingredient customization)</li>";
    echo "<li><strong>PRODUCT Product Groups:</strong> " . count($product_group_ids) . " created (for product collections)</li>";
    echo "<li><strong>Complex Products:</strong> " . count($complex_product_ids) . " created with ingredient customization options</li>";
    echo "<li><strong>Total Products:</strong> " . count($product_ids) . " created (simple + complex)</li>";
    echo "<li><strong>Customers:</strong> " . count($customer_ids) . " created</li>";
    echo "<li><strong>Complete Orders:</strong> " . count($order_ids) . " created with detailed modifications</li>";
    echo "</ul>";
    echo "<h4>🍔 Group Types:</h4>";
    echo "<ul>";
    echo "<li><strong>Ingredient Groups:</strong> For customizing ingredients within a product (protein, bun, cheese, toppings, sauce, sides)</li>";
    echo "<li><strong>Product Groups:</strong> For organizing related products together (Signature Burgers, Healthy Options, Premium Selection)</li>";
    echo "</ul>";
    echo "<h4>🍔 Product Structure:</h4>";
    echo "<ul>";
    echo "<li><strong>Simple Products:</strong> Ready-made burgers (Classic Cheeseburger, Chicken Deluxe, etc.)</li>";
    echo "<li><strong>Build Your Own Burger:</strong> 3 ingredient groups (protein, bun, cheese)</li>";
    echo "<li><strong>Gourmet Deluxe Burger:</strong> 4 ingredient groups (+ toppings)</li>";
    echo "<li><strong>Ultimate Combo Meal:</strong> 6 ingredient groups (all customization options)</li>";
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