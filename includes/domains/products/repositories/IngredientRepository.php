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
     * @param array $filters Optional filters: branch_id, search, price_min, price_max
     * @return Ingredient[]
     */
    public function getAll(array $filters = []): array
    {
        $query_args = [
            'post_type'      => IngredientPostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];

        // Add meta query for filters
        $meta_query = [];

        // Filter by price range
        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $price_query = ['key' => '_price'];
            
            if (!empty($filters['price_min']) && !empty($filters['price_max'])) {
                $price_query['value'] = [(float)$filters['price_min'], (float)$filters['price_max']];
                $price_query['compare'] = 'BETWEEN';
                $price_query['type'] = 'NUMERIC';
            } elseif (!empty($filters['price_min'])) {
                $price_query['value'] = (float)$filters['price_min'];
                $price_query['compare'] = '>=';
                $price_query['type'] = 'NUMERIC';
            } elseif (!empty($filters['price_max'])) {
                $price_query['value'] = (float)$filters['price_max'];
                $price_query['compare'] = '<=';
                $price_query['type'] = 'NUMERIC';
            }
            
            $meta_query[] = $price_query;
        }

        // Filter by branch availability (if branch system is implemented)
        if (!empty($filters['branch_id'])) {
            // Include ingredients that either:
            // 1. Have the branch availability meta set to '1' OR
            // 2. Don't have any branch availability meta set (default to available)
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => '_branch_availability_' . (int)$filters['branch_id'],
                    'value' => '1',
                    'compare' => '='
                ],
                [
                    'key' => '_branch_availability_' . (int)$filters['branch_id'],
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }

        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query_args['s'] = sanitize_text_field($filters['search']);
        }

        $query = new WP_Query($query_args);
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

    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $meta_query = ['relation' => 'AND'];
        $search_query = [];

        // Build meta query from criteria
        foreach ($criteria as $key => $value) {
            switch ($key) {
                case 'name':
                    // Search in post title
                    $search_query['s'] = $value;
                    break;
                    
                case 'category':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_category',
                            'value' => $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'min_price':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_price',
                            'value' => (float) $value,
                            'compare' => '>='
                        ];
                    }
                    break;
                    
                case 'max_price':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_price',
                            'value' => (float) $value,
                            'compare' => '<='
                        ];
                    }
                    break;
                    
                case 'is_available':
                    $meta_query[] = [
                        'key' => '_is_available',
                        'value' => (bool) $value,
                        'compare' => '='
                    ];
                    break;
                    
                case 'allergen':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_allergens',
                            'value' => $value,
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'dietary_info':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_dietary_info',
                            'value' => $value,
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'vegetarian':
                    if ($value) {
                        $meta_query[] = [
                            'key' => '_dietary_info',
                            'value' => 'vegetarian',
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'vegan':
                    if ($value) {
                        $meta_query[] = [
                            'key' => '_dietary_info',
                            'value' => 'vegan',
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'gluten_free':
                    if ($value) {
                        $meta_query[] = [
                            'key' => '_dietary_info',
                            'value' => 'gluten_free',
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
            }
        }

        $query_args = [
            'post_type' => IngredientPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit ?? -1,
            'offset' => $offset,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if (!empty($meta_query) && count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        if (!empty($search_query)) {
            $query_args = array_merge($query_args, $search_query);
        }

        $query = new WP_Query($query_args);

        $ingredients = [];
        foreach ($query->posts as $post_id) {
            $ingredient = $this->get((int) $post_id);
            if ($ingredient) {
                $ingredients[] = $ingredient;
            }
        }

        return $ingredients;
    }

    /**
     * Count ingredients by criteria
     */
    public function countBy(array $criteria): int
    {
        $ingredients = $this->findBy($criteria);
        return count($ingredients);
    }

    /**
     * Check if ingredient exists
     */
    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        return $post && $post->post_type === IngredientPostType::POST_TYPE;
    }

    /**
     * Find ingredients by category
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category]);
    }

    /**
     * Find available ingredients
     */
    public function findAvailable(): array
    {
        return $this->findBy(['is_available' => true]);
    }

    /**
     * Find ingredients by dietary requirements
     */
    public function findByDietaryInfo(string $dietary_info): array
    {
        return $this->findBy(['dietary_info' => $dietary_info]);
    }

    /**
     * Find ingredients in price range
     */
    public function findInPriceRange(float $min_price, float $max_price): array
    {
        return $this->findBy([
            'min_price' => $min_price,
            'max_price' => $max_price
        ]);
    }

}
