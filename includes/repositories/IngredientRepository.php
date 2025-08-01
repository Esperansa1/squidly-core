<?php

declare(strict_types=1);
class IngredientRepository implements RepositoryInterface
{

    /**
     * Create a new Ingredient and save it to the database.
     *
     * @param array $data Should contain:
     *  - name (string)
     *  - price (float)
     * @return int The post ID of the created ingredient
     */
    public function create(array $data): int
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Ingredient name is required.');
        }

        $name = sanitize_text_field($data['name']);
        $price = (float) ($data['price'] ?? 0);

        if($price < 0){
            throw new InvalidArgumentException('Ingredient price is non-negative.');
        }

        $post_id = wp_insert_post([
            'post_title'  => $name,
            'post_type'   => IngredientPostType::POST_TYPE,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create ingredient: ' . $post_id->get_error_message());
        }

        update_post_meta($post_id, '_price', $price);

        return $post_id;
    }

    /**
     * Get an Ingredient by ID and return it as an Ingredient object.
     *
     * @param int $id
     * @return Ingredient|null
     */
    public function get(int $id): ?Ingredient
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== IngredientPostType::POST_TYPE) {
            return null;
        }

        return new Ingredient([
            'id'    => $post->ID,
            'name'  => $post->post_title,
            'price' => (float) get_post_meta($post->ID, '_price', true),
        ]);
    }

    /**
     * Get all published Ingredients.
     *
     * @return Ingredient[]
     */
    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type'      => IngredientPostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $ingredients = [];

        foreach ($query->posts as $post) {
            $ingredient = $this->get((int) $post->ID);
            if ($ingredient) {
                $ingredients[] = $ingredient;
            }
        }

        return $ingredients;
    }

    /* ----------------------------------------------------------------------
    *  update()
    * --------------------------------------------------------------------*/
    /**
     * Update an existing ingredient.
     *
     * Accepts any subset of ['name','price'].
     * Returns true on success, false if the post does not exist / wrong type.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, array $data): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== IngredientPostType::POST_TYPE) {
            return false;
        }

        // --- validations ----------------------------------------------------
        if (isset($data['name']) && $data['name'] === '') {
            throw new InvalidArgumentException('Ingredient name cannot be empty.');
        }
        if (isset($data['price']) && $data['price'] < 0) {
            throw new InvalidArgumentException('Ingredient price cannot be negative.');
        }

        // --- update post title ---------------------------------------------
        if (isset($data['name'])) {
            wp_update_post([
                'ID'         => $id,
                'post_title' => sanitize_text_field($data['name']),
            ]);
        }

        // --- update price meta ---------------------------------------------
        if (array_key_exists('price', $data)) {
            update_post_meta($id, '_price', (float) $data['price']);
        }

        return true;
    }

    /* ----------------------------------------------------------------------
    *  delete()
    * --------------------------------------------------------------------*/
    /**
     * Delete an ingredient (moves to trash by default).
     *
     * @param bool $force Force deletion (true = bypass trash)
     * @return bool True if deleted, false if not found / wrong type.
     */
    public function delete(int $id, bool $force = false): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== IngredientPostType::POST_TYPE) {
            return false;
        }

        $dependants = $this->findIngredientDependants($id);
        if ($dependants) {
            throw new ResourceInUseException($dependants);
        }

        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Failed to delete ingredient: '.$result->get_error_message()
            );
        }
        return (bool) $result;
    }

    private function findIngredientDependants(int $iid): array
    {
        $names    = [];
        $pgRepo   = new ProductGroupRepository();
        $prodRepo = new ProductRepository();

        /* --- 1. group-items that reference this ingredient ---------------- */
        $giIds = get_posts([
            'post_type'   => GroupItemPostType::POST_TYPE,
            'fields'      => 'ids',
            'nopaging'    => true,
            'post_status' => 'publish',
            'meta_query'  => [
                [ 'key'   => '_item_id',  'value' => $iid,           'compare' => '=', 'type' => 'NUMERIC' ],
                [ 'key'   => '_item_type','value' => 'ingredient',   'compare' => '='                       ],
            ],
        ]);

        /* Every matching GroupItem itself counts as a dependant ------------- */
        foreach ($giIds as $giId) {
            $names[] = "GroupItem #{$giId}";
        }

        /* --- 2. product-groups containing those group-items ---------------- */
        foreach ($giIds as $giId) {
            $pgIds = get_posts([
                'post_type'  => ProductGroupPostType::POST_TYPE,
                'fields'     => 'ids',
                'nopaging'   => true,
                'post_status'=> 'publish',
                'meta_query' => [[
                    'key'     => '_group_item_ids',
                    'value'   => 'i:' . $giId . ';',   // serialized int
                    'compare' => 'LIKE',
                ]],
            ]);

            foreach ($pgIds as $pgId) {
                $pg = $pgRepo->get((int) $pgId);
                if ($pg) {
                    $names[] = $pg->name;
                }

                /* --- 3. products that include this product-group ----------- */
                $prodIds = get_posts([
                    'post_type'  => ProductPostType::POST_TYPE,
                    'fields'     => 'ids',
                    'nopaging'   => true,
                    'post_status'=> 'publish',
                    'meta_query' => [[
                        'key'     => '_product_group_ids',
                        'value'   => 'i:' . $pgId . ';',
                        'compare' => 'LIKE',
                    ]],
                ]);

                foreach ($prodIds as $pid) {
                    $p = $prodRepo->get((int) $pid);
                    if ($p) {
                        $names[] = $p->name;
                    }
                }
            }
        }

        return array_unique($names);
    }
}
