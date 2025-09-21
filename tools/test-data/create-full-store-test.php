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

    echo "<h2>🍔 Creating Hamburger Restaurant Ingredients</h2>";
    
    // Create base ingredients for hamburger restaurant
    $ingredients_data = [
        // Meat options
        ['name' => 'קציצת בקר (150 גרם)', 'price' => 18.00],
        ['name' => 'קציצת בקר כפולה (300 גרם)', 'price' => 32.00],
        ['name' => 'חזה עוף', 'price' => 15.00],
        ['name' => 'קציצת הודו', 'price' => 16.00],
        ['name' => 'קציצה צמחונית', 'price' => 14.00],
        ['name' => 'קציצת בשר מעבר', 'price' => 22.00],
        
        // Bread options
        ['name' => 'לחמנייה קלאסית עם שומשום', 'price' => 3.00],
        ['name' => 'לחמנייה בריוש', 'price' => 4.50],
        ['name' => 'לחמנייה מחיטה מלאה', 'price' => 3.50],
        ['name' => 'לחמנייה ללא גלוטן', 'price' => 5.00],
        ['name' => 'לחמנייה פרצל', 'price' => 4.00],
        
        // Cheese options
        ['name' => 'גבינה אמריקאית', 'price' => 2.00],
        ['name' => 'גבינה שוויצרית', 'price' => 2.50],
        ['name' => 'גבינת צ\'דר', 'price' => 2.50],
        ['name' => 'גבינה כחולה', 'price' => 3.00],
        ['name' => 'גבינת עיזים', 'price' => 3.50],
        ['name' => 'גבינה טבעונית', 'price' => 3.00],
        
        // Toppings
        ['name' => 'חסה', 'price' => 1.00],
        ['name' => 'עגבנייה', 'price' => 1.50],
        ['name' => 'בצל אדום', 'price' => 1.00],
        ['name' => 'חמוצים', 'price' => 1.50],
        ['name' => 'בייקון', 'price' => 4.00],
        ['name' => 'אבוקדו', 'price' => 3.00],
        ['name' => 'פטריות', 'price' => 2.00],
        ['name' => 'הלפיניו', 'price' => 1.50],
        
        // Sauces
        ['name' => 'קטשופ', 'price' => 0.50],
        ['name' => 'חרדל', 'price' => 0.50],
        ['name' => 'מיונז', 'price' => 0.50],
        ['name' => 'רוטב ברביקיו', 'price' => 1.00],
        ['name' => 'מיונז סרירצ\'ה', 'price' => 1.50],
        ['name' => 'איולי שום', 'price' => 1.50],
        
        // Sides
        ['name' => 'צ\'יפס רגיל', 'price' => 8.00],
        ['name' => 'צ\'יפס בטטה', 'price' => 10.00],
        ['name' => 'טבעות בצל', 'price' => 9.00],
        ['name' => 'סלט קטן', 'price' => 7.00],
    ];
    
    $ingredient_ids = [];
    foreach ($ingredients_data as $ingredient_data) {
        $ingredient_id = $ingredientRepo->create($ingredient_data);
        $ingredient_ids[] = $ingredient_id;
        
        // Set branch availability for each ingredient
        // Make ingredients available in different branches for variety
        foreach ($branch_ids as $index => $branch_id) {
            // Make most ingredients available in all branches, but some only in specific branches for testing
            $is_available = true;
            
            // Make some ingredients branch-specific for testing
            if (in_array($ingredient_data['name'], ['קציצת בשר מעבר', 'לחמנייה ללא גלוטן', 'גבינה טבעונית'])) {
                // These special items only available in branch 1 (index 0)
                $is_available = ($index === 0);
            } elseif (in_array($ingredient_data['name'], ['גבינה כחולה', 'לחמנייה פרצל', 'איולי שום'])) {
                // These premium items only available in branch 2 (index 1)
                $is_available = ($index === 1);
            }
            
            update_post_meta($ingredient_id, '_branch_availability_' . $branch_id, $is_available ? '1' : '0');
        }
        
        // Show branch availability info
        $branch_info = [];
        foreach ($branch_ids as $index => $branch_id) {
            $is_available = true;
            if (in_array($ingredient_data['name'], ['קציצת בשר מעבר', 'לחמנייה ללא גלוטן', 'גבינה טבעונית'])) {
                $is_available = ($index === 0);
            } elseif (in_array($ingredient_data['name'], ['גבינה כחולה', 'לחמנייה פרצל', 'איולי שום'])) {
                $is_available = ($index === 1);
            }
            $branch_info[] = "Branch {$branch_id}: " . ($is_available ? "✅" : "❌");
        }
        
        echo "<div style='color: green;'>✅ Created ingredient: {$ingredient_data['name']} (ID: {$ingredient_id}) - " . implode(", ", $branch_info) . "</div>";
    }

    echo "<h2>🍔 Creating Complex Hamburger Products with Product Groups</h2>";
    
    // First create some actual products that will be used in product-type groups
    $simple_products_data = [
        [
            'name' => 'צ\'יזבורגר קלאסי',
            'description' => 'המבורגר מסורתי עם קציצת בקר, גבינה, חסה ועגבנייה',
            'price' => 28.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy'],
            'preparation_time' => 12
        ],
        [
            'name' => 'עוף דלוקס',
            'description' => 'חזה עוף צלוי עם תוספות פרימיום',
            'price' => 26.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy'],
            'preparation_time' => 14
        ],
        [
            'name' => 'צמחוני סופרים',
            'description' => 'קציצה צמחית עם ירקות טריים',
            'price' => 24.00,
            'category' => 'burgers',
            'is_available' => true,
            'allergens' => ['gluten'],
            'preparation_time' => 10
        ],
        [
            'name' => 'המבורגר ברביקיו בייקון',
            'description' => 'קציצת בקר עם בייקון פריך ורוטב ברביקיו',
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
            'name' => 'בחר את החלבון שלך',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 0, 6) // First 6 are meat options
        ],
        // Bread Selection Group  
        [
            'name' => 'בחר את הלחמנייה שלך',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 6, 5) // Bread options
        ],
        // Cheese Selection Group
        [
            'name' => 'הוסף גבינה',
            'type' => 'ingredient', 
            'ingredient_ids' => array_slice($ingredient_ids, 11, 6) // Cheese options
        ],
        // Toppings Group
        [
            'name' => 'תוספות טריות',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 17, 8) // Toppings
        ],
        // Sauce Group
        [
            'name' => 'בחר את הרוטב שלך',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 25, 6) // Sauces
        ],
        // Sides Group
        [
            'name' => 'הוסף תוספת',
            'type' => 'ingredient',
            'ingredient_ids' => array_slice($ingredient_ids, 31, 4) // Sides
        ]
    ];

    // Create PRODUCT Product Groups (for grouping related products together)
    $product_groups_data = [
        [
            'name' => 'המבורגרים מיוחדים',
            'type' => 'product',
            'product_ids' => [$simple_product_ids[0], $simple_product_ids[1]] // Classic Cheeseburger, Chicken Deluxe
        ],
        [
            'name' => 'אפשרויות בריאות',
            'type' => 'product', 
            'product_ids' => [$simple_product_ids[2]] // Veggie Supreme
        ],
        [
            'name' => 'מבחר פרימיום',
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

    echo "<h3>🔗 Assigning Product Groups to Simple Products</h3>";
    // Now assign product groups to the simple products to make them more complex
    $product_group_assignments = [
        $simple_product_ids[0] => [$product_group_ids[0]], // Classic Cheeseburger → Special Burgers
        $simple_product_ids[1] => [$product_group_ids[0]], // Chicken Deluxe → Special Burgers
        $simple_product_ids[2] => [$product_group_ids[1]], // Veggie Supreme → Healthy Options
        $simple_product_ids[3] => [$product_group_ids[2]], // BBQ Bacon Burger → Premium Selection
    ];

    foreach ($product_group_assignments as $product_id => $assigned_group_ids) {
        // Update the product to include the product group IDs
        $product = $productRepo->get($product_id);
        if ($product) {
            $update_data = [
                'product_group_ids' => $assigned_group_ids
            ];
            $productRepo->update($product_id, $update_data);

            $group_names = [];
            foreach ($assigned_group_ids as $group_id) {
                $group = $productGroupRepo->get($group_id);
                if ($group) {
                    $group_names[] = $group->name;
                }
            }
            echo "<div style='color: blue;'>🔗 Assigned product ID {$product_id} to groups: " . implode(', ', $group_names) . "</div>";
        }
    }

    // Combine all group IDs for backward compatibility with existing complex products
    $group_ids = array_merge($ingredient_group_ids, $product_group_ids);

    echo "<h3>🍴 Creating Complex Customizable Products</h3>";
    // Create complex hamburger products with multiple INGREDIENT Product Groups (for customization)
    $complex_products_data = [
        [
            'name' => 'בנה את ההמבורגר שלך',
            'description' => 'צור את ההמבורגר המושלם שלך עם מבחר המרכיבים הפרימיום שלנו',
            'price' => 25.00, // Base price
            'category' => 'burgers',
            'image_url' => 'https://example.com/build-burger.jpg',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy', 'eggs'],
            'preparation_time' => 15,
            'product_groups' => [$ingredient_group_ids[0], $ingredient_group_ids[1], $ingredient_group_ids[2]] // Protein, Bun, Cheese
        ],
        [
            'name' => 'המבורגר גורמה דלוקס',
            'description' => 'ההמבורגר החתימה שלנו עם תוספות פרימיום ומרכיבים אומנותיים',
            'price' => 35.00,
            'category' => 'burgers',
            'image_url' => 'https://example.com/deluxe-burger.jpg',
            'is_available' => true,
            'allergens' => ['gluten', 'dairy', 'eggs'],
            'preparation_time' => 20,
            'product_groups' => [$ingredient_group_ids[0], $ingredient_group_ids[1], $ingredient_group_ids[2], $ingredient_group_ids[3]] // Protein, Bun, Cheese, Toppings
        ],
        [
            'name' => 'ארוחת קומבו אולטימט',
            'description' => 'ארוחה מלאה עם המבורגר, רוטב ותוספת לבחירתך',
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

    echo "<h3>🔗 Assigning Additional Product Groups to Complex Products</h3>";
    // Add product groups to complex products to make them even more complex
    $complex_product_group_assignments = [
        $complex_product_ids[0] => [$product_group_ids[0]], // Build Your Own Burger → Special Burgers
        $complex_product_ids[1] => [$product_group_ids[2]], // Gourmet Deluxe → Premium Selection
        $complex_product_ids[2] => [$product_group_ids[0], $product_group_ids[2]], // Ultimate Combo → Special Burgers + Premium Selection
    ];

    foreach ($complex_product_group_assignments as $product_id => $assigned_group_ids) {
        // Get existing product groups from the product
        $product = $productRepo->get($product_id);
        if ($product) {
            $existing_groups = $product->product_group_ids ?? [];
            $all_groups = array_merge($existing_groups, $assigned_group_ids);

            $update_data = [
                'product_group_ids' => array_unique($all_groups)
            ];
            $productRepo->update($product_id, $update_data);

            $group_names = [];
            foreach ($assigned_group_ids as $group_id) {
                $group = $productGroupRepo->get($group_id);
                if ($group) {
                    $group_names[] = $group->name;
                }
            }
            echo "<div style='color: blue;'>🔗 Added to complex product ID {$product_id} additional groups: " . implode(', ', $group_names) . "</div>";
        }
    }

    // Combine all product IDs for backward compatibility
    $product_ids = array_merge($simple_product_ids, $complex_product_ids);

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
    
    // Create comprehensive test orders with complex hamburger products
    $orders_data = [
        [
            'customer_id' => $customer_ids[0],
            'branch_id' => $branch_ids[0],
            'items' => [
                [
                    'product_id' => $product_ids[0], 
                    'product_name' => 'בנה את ההמבורגר שלך',
                    'quantity' => 1, 
                    'unit_price' => 25.00,
                    'modifications' => [
                        'protein' => 'קציצת בקר (150 גרם)',
                        'bun' => 'לחמנייה בריוש',
                        'cheese' => 'גבינת צ\'דר'
                    ],
                    'notes' => 'צלייה בינונית, חמוצים נוספים'
                ],
                [
                    'product_id' => $product_ids[2], 
                    'product_name' => 'ארוחת קומבו אולטימט',
                    'quantity' => 1, 
                    'unit_price' => 45.00,
                    'modifications' => [
                        'protein' => 'קציצת בקר כפולה (300 גרם)',
                        'bun' => 'לחמנייה פרצל',
                        'cheese' => 'גבינה שוויצרית',
                        'toppings' => ['בייקון', 'אבוקדו', 'פטריות'],
                        'sauce' => 'איולי שום',
                        'side' => 'צ\'יפס בטטה'
                    ],
                    'notes' => 'צלוי היטב, מעט רוטב'
                ]
            ]
        ],
        [
            'customer_id' => $customer_ids[1],
            'branch_id' => $branch_ids[1],
            'items' => [
                [
                    'product_id' => $product_ids[1], 
                    'product_name' => 'המבורגר גורמה דלוקס',
                    'quantity' => 2, 
                    'unit_price' => 35.00,
                    'modifications' => [
                        'protein' => 'קציצת בשר מעבר',
                        'bun' => 'לחמנייה מחיטה מלאה',
                        'cheese' => 'גבינה טבעונית',
                        'toppings' => ['חסה', 'עגבנייה', 'בצל אדום', 'אבוקדו']
                    ],
                    'notes' => 'אופציה טבעונית, ללא מיונז'
                ],
                [
                    'product_id' => $product_ids[0], 
                    'product_name' => 'בנה את ההמבורגר שלך',
                    'quantity' => 1, 
                    'unit_price' => 25.00,
                    'modifications' => [
                        'protein' => 'חזה עוף',
                        'bun' => 'לחמנייה ללא גלוטן',
                        'cheese' => 'גבינת עיזים'
                    ],
                    'notes' => 'אופציה ללא גלוטן, עוף צלוי'
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

    echo "<h2>✅ Complex Hamburger Restaurant Test Data Creation Complete!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>";
    echo "<h3>📊 Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Store Branches:</strong> " . count($branch_ids) . " created</li>";
    echo "<li><strong>Ingredients:</strong> " . count($ingredient_ids) . " created with branch-specific availability (meats, buns, cheese, toppings, sauces, sides)</li>";
    echo "<li><strong>Simple Products:</strong> " . count($simple_product_ids) . " created (pre-made burgers)</li>";
    echo "<li><strong>INGREDIENT Product Groups:</strong> " . count($ingredient_group_ids) . " created (for ingredient customization)</li>";
    echo "<li><strong>PRODUCT Product Groups:</strong> " . count($product_group_ids) . " created (for product collections)</li>";
    echo "<li><strong>Complex Products:</strong> " . count($complex_product_ids) . " created with ingredient customization options</li>";
    echo "<li><strong>Total Products:</strong> " . count($product_ids) . " created (simple + complex)</li>";
    echo "<li><strong>Customers:</strong> " . count($customer_ids) . " created</li>";
    echo "<li><strong>Complete Orders:</strong> " . count($order_ids) . " created with detailed modifications</li>";
    echo "</ul>";
    echo "<h4>🏢 Branch-Specific Ingredients:</h4>";
    echo "<ul>";
    echo "<li><strong>Branch 1 Only:</strong> קציצת בשר מעבר, לחמנייה ללא גלוטן, גבינה טבעונית</li>";
    echo "<li><strong>Branch 2 Only:</strong> גבינה כחולה, לחמנייה פרצל, איולי שום</li>";
    echo "<li><strong>All Branches:</strong> All other ingredients</li>";
    echo "</ul>";
    echo "<h4>🍔 Group Types:</h4>";
    echo "<ul>";
    echo "<li><strong>Ingredient Groups:</strong> For customizing ingredients within a product (protein, bun, cheese, toppings, sauce, sides)</li>";
    echo "<li><strong>Product Groups:</strong> For organizing related products together (Signature Burgers, Healthy Options, Premium Selection)</li>";
    echo "</ul>";
    echo "<h4>🍔 Product Structure:</h4>";
    echo "<ul>";
    echo "<li><strong>Simple Products:</strong> Ready-made burgers with assigned product groups (Classic Cheeseburger, Chicken Deluxe, etc.)</li>";
    echo "<li><strong>Build Your Own Burger:</strong> 3 ingredient groups (protein, bun, cheese) + 1 product group (Special Burgers)</li>";
    echo "<li><strong>Gourmet Deluxe Burger:</strong> 4 ingredient groups (+ toppings) + 1 product group (Premium Selection)</li>";
    echo "<li><strong>Ultimate Combo Meal:</strong> 6 ingredient groups (all customization options) + 2 product groups (Special Burgers + Premium Selection)</li>";
    echo "</ul>";

    echo "<h4>🔗 Product Group Relationships:</h4>";
    echo "<ul>";
    echo "<li><strong>Classic Cheeseburger & Chicken Deluxe:</strong> → Special Burgers</li>";
    echo "<li><strong>Veggie Supreme:</strong> → Healthy Options</li>";
    echo "<li><strong>BBQ Bacon Burger:</strong> → Premium Selection</li>";
    echo "<li><strong>Build Your Own Burger:</strong> → Special Burgers</li>";
    echo "<li><strong>Gourmet Deluxe Burger:</strong> → Premium Selection</li>";
    echo "<li><strong>Ultimate Combo Meal:</strong> → Special Burgers + Premium Selection</li>";
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