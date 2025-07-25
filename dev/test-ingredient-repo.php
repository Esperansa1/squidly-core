<?php

declare(strict_types=1);

// Load WordPress and the plugin
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/squidly-core.php';

// 🧪 Test: Create and Retrieve Ingredient
try {
    $repo = new IngredientRepository();

    $new_id = $repo->create([
        'name'  => 'Test Tomato',
        'price' => 1.99,
    ]);

    $ingredient = $repo->get($new_id);

    echo '<pre>';
    echo "✅ Created Ingredient ID: {$new_id}\n\n";
    echo "📥 Stored:\n";
    print_r([
        'id'    => $new_id,
        'name'  => 'Test Tomato',
        'price' => 1.99,
    ]);

    echo "\n📤 Retrieved:\n";
    print_r($ingredient ? $ingredient->toArray() : '❌ Not found');
    echo '</pre>';

} catch (Throwable $e) {
    echo '<pre>❌ Error: ' . $e->getMessage() . '</pre>';
}
