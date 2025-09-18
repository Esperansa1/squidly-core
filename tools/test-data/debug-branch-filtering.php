<?php
/**
 * Debug Branch Filtering Test Data
 * 
 * Creates minimal test data to debug branch filtering:
 * - 2 branches
 * - 2 ingredients with specific branch availability
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
require_once __DIR__ . '/../../includes/domains/products/repositories/IngredientRepository.php';
require_once __DIR__ . '/../../includes/domains/stores/repositories/StoreBranchRepository.php';

echo "<h1>🔍 Debug Branch Filtering Test Data</h1>";

try {
    // Initialize repositories
    $storeBranchRepo = new StoreBranchRepository();
    $ingredientRepo = new IngredientRepository();

    echo "<h2>🏢 Creating 2 Test Branches</h2>";
    
    // Create 2 simple branches
    $branches = [
        [
            'name' => 'סניף א',
            'phone' => '+972-3-1111111',
            'city' => 'תל אביב',
            'address' => 'רחוב א 1, תל אביב',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['10:00-22:00'],
                'MONDAY' => ['10:00-22:00'],
                'TUESDAY' => ['10:00-22:00'],
                'WEDNESDAY' => ['10:00-22:00'],
                'THURSDAY' => ['10:00-22:00'],
                'FRIDAY' => ['10:00-15:00'],
                'SATURDAY' => []
            ],
            'kosher_type' => 'kosher',
            'accessibility_list' => ['wheelchair_accessible']
        ],
        [
            'name' => 'סניף ב',
            'phone' => '+972-3-2222222',
            'city' => 'תל אביב',
            'address' => 'רחוב ב 2, תל אביב',
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
            'accessibility_list' => ['wheelchair_accessible']
        ]
    ];
    
    $branch_ids = [];
    foreach ($branches as $branch_data) {
        $branch_id = $storeBranchRepo->create($branch_data);
        $branch_ids[] = $branch_id;
        echo "<div style='color: green;'>✅ Created branch: {$branch_data['name']} (ID: {$branch_id})</div>";
    }

    echo "<h2>🍅 Creating Test Ingredients with Specific Branch Availability</h2>";
    
    // Create 2 test ingredients
    $ingredients_data = [
        ['name' => 'עגבנייה', 'price' => 2.50],
        ['name' => 'מלפפון', 'price' => 1.75],
    ];
    
    $ingredient_ids = [];
    foreach ($ingredients_data as $index => $ingredient_data) {
        $ingredient_id = $ingredientRepo->create($ingredient_data);
        $ingredient_ids[] = $ingredient_id;
        
        echo "<h3>Setting availability for: {$ingredient_data['name']} (ID: {$ingredient_id})</h3>";
        
        // Set specific branch availability based on the debug requirements
        foreach ($branch_ids as $branch_index => $branch_id) {
            if ($index === 0) {
                // Tomato (עגבנייה) - Available in Branch 1, Not available in Branch 2
                $is_available = ($branch_index === 0) ? '1' : '0';
                $availability_text = ($branch_index === 0) ? 'Available' : 'Not Available';
            } else {
                // Cucumber (מלפפון) - Not available in Branch 1, Available in Branch 2
                $is_available = ($branch_index === 1) ? '1' : '0';
                $availability_text = ($branch_index === 1) ? 'Available' : 'Not Available';
            }
            
            update_post_meta($ingredient_id, '_branch_availability_' . $branch_id, $is_available);
            
            echo "<div style='margin-left: 20px; color: " . ($is_available === '1' ? 'green' : 'red') . ";'>";
            echo "Branch {$branch_id} ({$branches[$branch_index]['name']}): {$availability_text} (meta: {$is_available})</div>";
        }
        
        echo "<div style='color: blue; margin: 10px 0;'>✅ Created ingredient: {$ingredient_data['name']} (ID: {$ingredient_id})</div>";
    }

    echo "<h2>🔍 Debug Information</h2>";
    echo "<div style='background: #f0f8ff; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;'>";
    echo "<h3>Expected Behavior:</h3>";
    echo "<ul>";
    echo "<li><strong>Branch {$branch_ids[0]} (סניף א):</strong> Should show only 'עגבנייה' (Tomato)</li>";
    echo "<li><strong>Branch {$branch_ids[1]} (סניף ב):</strong> Should show only 'מלפפון' (Cucumber)</li>";
    echo "<li><strong>All Branches (ID: 0):</strong> Should show both ingredients</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
    echo "<h3>🧪 Testing Instructions:</h3>";
    echo "<ol>";
    echo "<li>Go to the admin interface</li>";
    echo "<li>Navigate to the ingredients section</li>";
    echo "<li>Switch between branches using the branch selector</li>";
    echo "<li>Verify that each branch shows only its assigned ingredient</li>";
    echo "</ol>";
    echo "</div>";

    echo "<div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>";
    echo "<h3>📊 Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Branches Created:</strong> " . count($branch_ids) . " (IDs: " . implode(', ', $branch_ids) . ")</li>";
    echo "<li><strong>Ingredients Created:</strong> " . count($ingredient_ids) . " (IDs: " . implode(', ', $ingredient_ids) . ")</li>";
    echo "<li><strong>Branch Availability Set:</strong> Explicit meta data for each ingredient-branch combination</li>";
    echo "</ul>";
    echo "</div>";

    // Debug: Show raw meta data
    echo "<h2>🔧 Raw Meta Data for Debugging</h2>";
    foreach ($ingredient_ids as $index => $ingredient_id) {
        $ingredient_name = $ingredients_data[$index]['name'];
        echo "<h4>Ingredient: {$ingredient_name} (ID: {$ingredient_id})</h4>";
        
        foreach ($branch_ids as $branch_id) {
            $meta_value = get_post_meta($ingredient_id, '_branch_availability_' . $branch_id, true);
            echo "<div style='margin-left: 20px; font-family: monospace;'>";
            echo "Meta key: _branch_availability_{$branch_id} = '{$meta_value}'</div>";
        }
    }

} catch (Exception $e) {
    echo "<div style='color: red; background: #fed7d7; padding: 20px; margin: 20px 0;'>";
    echo "<h2>❌ Error Creating Debug Test Data</h2>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>