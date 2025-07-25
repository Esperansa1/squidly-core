<?php
/**
 * Interactive shell for IngredientRepository CRUD testing
 * Usage: php ingredient-shell.php
 */

declare(strict_types=1);

// ── Bootstrap WordPress & plugin ─────────────────────────────
require_once dirname(__DIR__, 4) . '/wp-load.php';      // adjust if path differs
require_once dirname(__DIR__)     . '/squidly-core.php';

// ── Ensure update/delete helpers exist in the repo ───────────
if (!method_exists('IngredientRepository', 'update')) {
    class IngredientRepositoryExt extends IngredientRepository
    {
        /** Update name and/or price */
        public function update(int $id, array $data): bool
        {
            $post = get_post($id);
            if (!$post || $post->post_type !== self::POST_TYPE) {
                return false;
            }

            if (isset($data['name'])) {
                wp_update_post([
                    'ID'         => $id,
                    'post_title' => sanitize_text_field($data['name']),
                ]);
            }
            if (isset($data['price'])) {
                update_post_meta($id, '_price', (float) $data['price']);
            }

            return true;
        }

        /** Delete ingredient (moves to trash) */
        public function delete(int $id): bool
        {
            return (bool) wp_trash_post($id);
        }
    }
    $repo = new IngredientRepositoryExt();
} else {
    $repo = new IngredientRepository();
}

// ── Helper to print an Ingredient object nicely ──────────────
function printIngredient(?Ingredient $ing): void
{
    if (!$ing) {
        echo "❌ Not found\n";
        return;
    }
    echo "ID: {$ing->id} | Name: {$ing->name} | Price: {$ing->price}\n";
}

// ── Shell loop ───────────────────────────────────────────────
echo "Interactive Ingredient Shell (type 'help' for commands)\n";
while (true) {
    $input = readline("[Ingredient Shell] > ");
    $cmd   = trim($input);

    switch (true) {
        case $cmd === 'exit':
        case $cmd === 'quit':
            exit("Bye!\n");

        case $cmd === 'help':
            echo <<<TXT
Commands:
  create <name> <price>        Create new ingredient
  get <id>                     Fetch ingredient by ID
  list                         List all ingredients
  update <id> <name> <price>   Update name and/or price
  delete <id>                  Trash an ingredient
  help                         Show this help
  exit                         Quit shell

TXT;
            break;

        case str_starts_with($cmd, 'create '):
            [, $name, $price] = array_pad(explode(' ', $cmd, 3), 3, null);
            try {
                $newId = $repo->create(['name' => $name, 'price' => (float)$price]);
                echo "✅ Created ingredient ID {$newId}\n";
            } catch (Throwable $e) {
                echo "❌ {$e->getMessage()}\n";
            }
            break;

        case str_starts_with($cmd, 'get '):
            [, $id] = explode(' ', $cmd, 2);
            printIngredient($repo->get((int)$id));
            break;

        case $cmd === 'list':
            $all = $repo->getAll();
            if (!$all) {
                echo "No ingredients found.\n";
            } else {
                foreach ($all as $ing) {
                    printIngredient($ing);
                }
            }
            break;

        case str_starts_with($cmd, 'update '):
            [, $id, $name, $price] = array_pad(explode(' ', $cmd, 4), 4, null);
            $ok = $repo->update((int)$id, array_filter([
                'name'  => $name,
                'price' => $price !== null ? (float)$price : null,
            ]));
            echo $ok ? "✅ Updated\n" : "❌ Update failed\n";
            break;

        case str_starts_with($cmd, 'delete '):
            [, $id] = explode(' ', $cmd, 2);
            echo $repo->delete((int)$id) ? "🗑️  Trashed\n" : "❌ Delete failed\n";
            break;

        default:
            echo "Unknown command. Type 'help'.\n";
    }
}
