<?php

declare(strict_types=1);

class ProductRepository implements RepositoryInterface
{

    /* ======================================================================
     *  CREATE
     * ====================================================================*/
    public function create(array $data): int
    {
        // Input validation
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new InvalidArgumentException('Product name is required and must be a string.');
        }

        if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
            throw new InvalidArgumentException('Product price is required and must be a non-negative number.');
        }

        // Sanitize and prepare data
        $name = sanitize_text_field($data['name']);
        $description = isset($data['description']) ? wp_kses_post($data['description']) : '';
        $regular_price = (float) $data['price'];
        $discounted_price = $this->validateDiscountedPrice($data['discounted_price'] ?? null);

        // Create post with error handling
        $post_id = wp_insert_post([
            'post_title'   => $name,
            'post_content' => $description,
            'post_type'    => ProductPostType::POST_TYPE,
            'post_status'  => 'publish',
        ]);
        
        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create product: ' . $post_id->get_error_message());
        }

        if (!is_int($post_id) || $post_id <= 0) {
            throw new RuntimeException('Invalid post ID returned from wp_insert_post.');
        }

        try {
            // Set prices with validation
            $this->setPricesMeta($post_id, $regular_price, $discounted_price);

            // Set taxonomy terms safely
            $this->setCategorySafely($post_id, $data['category'] ?? null);
            $this->setTagsSafely($post_id, $data['tags'] ?? []);

            // Set product groups safely
            $this->setProductGroupsSafely($post_id, $data['product_group_ids'] ?? []);

            // Handle product image if provided
            if (isset($data['image_id'])) {
                $this->setProductImage($post_id, $data['image_id']);
            }

            // Handle branch availability if provided
            if (isset($data['availability']) && is_array($data['availability'])) {
                $this->updateAvailability($post_id, $data['availability']);
            }

        } catch (Exception $e) {
            // Rollback: delete the created post
            wp_delete_post($post_id, true);
            throw new RuntimeException('Failed to create product metadata: ' . $e->getMessage());
        }

        return $post_id;
    }

    /* ======================================================================
     *  GET
     * ====================================================================*/
    public function get(int $id): ?Product
    {
        // Input validation
        if ($id <= 0) {
            return null;
        }

        // Get and validate post
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductPostType::POST_TYPE) {
            return null;
        }

        try {
            // Safe price retrieval
            $regular_price = $this->getRegularPriceSafely($id);
            $discounted_price = $this->getDiscountedPriceSafely($id);

            // Safe taxonomy retrieval
            $category = $this->getCategorySafely($id);
            $tags = $this->getTagsSafely($id);

            // Safe product groups retrieval
            $group_ids = $this->getProductGroupIdsSafely($id);

            // Safe product construction
            return new Product([
                'id'               => $id,
                'name'             => $post->post_title ?? '',
                'description'      => $post->post_content ?? '',
                'price'            => $regular_price,
                'discounted_price' => $discounted_price,
                'category'         => $category,
                'tags'             => $tags,
                'product_group_ids'=> $group_ids,
                'image_id'         => $this->getImageIdSafely($id),
                'image_url'        => $this->getImageUrlSafely($id),
            ]);

        } catch (Exception $e) {
            error_log("Failed to create Product object for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    /* ======================================================================
     *  GETALL
     * ====================================================================*/
    public function getAll(array $filters = []): array
    {
        try {
            $query_args = [
                'post_type'      => ProductPostType::POST_TYPE,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
                'fields'         => 'ids',
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

            // Filter by category
            if (!empty($filters['category'])) {
                $meta_query[] = [
                    'key' => '_category',
                    'value' => sanitize_text_field($filters['category']),
                    'compare' => '='
                ];
            }

            if (!empty($meta_query)) {
                $query_args['meta_query'] = $meta_query;
            }

            // Search by name and description
            if (!empty($filters['search'])) {
                $query_args['s'] = sanitize_text_field($filters['search']);
            }

            $query = new WP_Query($query_args);

            if (is_wp_error($query)) {
                error_log('Failed to query products: ' . $query->get_error_message());
                return [];
            }

            $products = [];
            foreach ($query->posts as $post_id) {
                if (is_numeric($post_id) && $post_id > 0) {
                    $product = $this->get((int) $post_id);
                    if ($product) {
                        $products[] = $product;
                    }
                }
            }

            return $products;

        } catch (Exception $e) {
            error_log('Exception in getAll(): ' . $e->getMessage());
            return [];
        }
    }

    /* ======================================================================
     *  UPDATE
     * ====================================================================*/
    public function update(int $id, array $data): bool
    {
        // Input validation
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== ProductPostType::POST_TYPE) {
            return false;
        }

        // Validate update data
        $this->validateUpdateData($data);

        try {
            // Update post fields if provided
            $this->updatePostFields($id, $post, $data);

            // Update prices if provided
            $this->updatePrices($id, $data);

            // Update taxonomy if provided
            $this->updateTaxonomy($id, $data);

            // Update product groups if provided
            $this->updateProductGroups($id, $data);

            // Update product image if provided
            if (array_key_exists('image_id', $data)) {
                $this->setProductImage($id, $data['image_id']);
            }

            // Update branch availability if provided
            if (array_key_exists('availability', $data)) {
                $this->updateAvailability($id, $data['availability']);
            }

            return true;

        } catch (Exception $e) {
            error_log("Failed to update product {$id}: " . $e->getMessage());
            return false;
        }
    }

    /* ======================================================================
     *  DELETE
     * ====================================================================*/
    public function delete(int $id, bool $force = false): bool
    {
        // Input validation
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== ProductPostType::POST_TYPE) {
            return false;
        }

        // Check dependencies
        try {
            $dependants = $this->findProductDependants($id);
            if (!empty($dependants)) {
                throw new ResourceInUseException($dependants);
            }
        } catch (ResourceInUseException $e) {
            throw $e; // Re-throw dependency exceptions
        } catch (Exception $e) {
            error_log("Error checking dependencies for product {$id}: " . $e->getMessage());
            // Continue with deletion if dependency check fails
        }

        // Perform deletion
        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException('Failed to delete product: ' . $result->get_error_message());
        }

        return (bool) $result;
    }

    /* ======================================================================
     *  PRIVATE HELPER METHODS - CREATE
     * ====================================================================*/
    private function validateDiscountedPrice($price): ?float
    {
        if ($price === null || $price === '') {
            return null;
        }

        if (!is_numeric($price) || $price < 0) {
            throw new InvalidArgumentException('Discounted price must be a non-negative number or null.');
        }

        return (float) $price;
    }

    private function setPricesMeta(int $post_id, float $regular_price, ?float $discounted_price): void
    {
        $result1 = update_post_meta($post_id, '_regular_price', $regular_price);
        $result2 = update_post_meta($post_id, '_price', $discounted_price ?? $regular_price);

        if ($discounted_price !== null) {
            $result3 = update_post_meta($post_id, '_sale_price', $discounted_price);
            if ($result3 === false) {
                throw new RuntimeException('Failed to set sale price meta.');
            }
        }

        if ($result1 === false || $result2 === false) {
            throw new RuntimeException('Failed to set price meta fields.');
        }
    }

    private function setCategorySafely(int $post_id, ?string $category): void
    {
        if (empty($category)) {
            return;
        }

        if (!$this->ensureTaxonomyExists('product_cat')) {
            error_log('Taxonomy product_cat does not exist, skipping category assignment.');
            return;
        }

        $result = wp_set_object_terms($post_id, sanitize_text_field($category), 'product_cat');
        if (is_wp_error($result)) {
            error_log('Failed to set product category: ' . $result->get_error_message());
        }
    }

    private function setTagsSafely(int $post_id, array $tags): void
    {
        if (empty($tags) || !is_array($tags)) {
            return;
        }

        if (!$this->ensureTaxonomyExists('product_tag')) {
            error_log('Taxonomy product_tag does not exist, skipping tags assignment.');
            return;
        }

        $sanitized_tags = array_map('sanitize_text_field', array_filter($tags, 'is_string'));
        if (!empty($sanitized_tags)) {
            $result = wp_set_object_terms($post_id, $sanitized_tags, 'product_tag');
            if (is_wp_error($result)) {
                error_log('Failed to set product tags: ' . $result->get_error_message());
            }
        }
    }

    private function setProductGroupsSafely(int $post_id, array $group_ids): void
    {
        if (!is_array($group_ids)) {
            $group_ids = [];
        }

        // Validate and sanitize group IDs
        $valid_group_ids = array_filter(
            array_map('intval', $group_ids),
            function($id) { return $id > 0; }
        );

        // Delete the meta first, then add it (to avoid update_post_meta issues)
        delete_post_meta($post_id, '_product_group_ids');
        $result = add_post_meta($post_id, '_product_group_ids', $valid_group_ids, true);

        if ($result === false) {
            throw new RuntimeException('Failed to set product groups meta.');
        }
    }

    /* ======================================================================
     *  PRIVATE HELPER METHODS - GET
     * ====================================================================*/
    private function getRegularPriceSafely(int $id): float
    {
        $price = get_post_meta($id, '_regular_price', true);
        return is_numeric($price) ? (float) $price : 0.0;
    }

    private function getDiscountedPriceSafely(int $id): ?float
    {
        $price = get_post_meta($id, '_sale_price', true);
        return ($price === '' || $price === false || !is_numeric($price)) ? null : (float) $price;
    }

    private function getCategorySafely(int $id): ?string
    {
        if (!taxonomy_exists('product_cat')) {
            return null;
        }

        $terms = wp_get_object_terms($id, 'product_cat', ['fields' => 'names']);
        
        if (is_wp_error($terms)) {
            error_log('Error getting product category: ' . $terms->get_error_message());
            return null;
        }

        if (!is_array($terms) || empty($terms)) {
            return null;
        }

        return is_string($terms[0]) ? $terms[0] : null;
    }

    private function getTagsSafely(int $id): array
    {
        if (!taxonomy_exists('product_tag')) {
            return [];
        }

        $terms = wp_get_object_terms($id, 'product_tag', ['fields' => 'names']);
        
        if (is_wp_error($terms)) {
            error_log('Error getting product tags: ' . $terms->get_error_message());
            return [];
        }

        if (!is_array($terms)) {
            return [];
        }

        return array_filter($terms, 'is_string');
    }

    private function getProductGroupIdsSafely(int $id): array
    {
        $group_ids = get_post_meta($id, '_product_group_ids', true);
        
        if (!is_array($group_ids)) {
            return [];
        }

        return array_filter(
            array_map('intval', $group_ids),
            function($id) { return $id > 0; }
        );
    }

    /* ======================================================================
     *  PRIVATE HELPER METHODS - UPDATE
     * ====================================================================*/
    private function validateUpdateData(array $data): void
    {
        if (isset($data['name']) && (empty($data['name']) || !is_string($data['name']))) {
            throw new InvalidArgumentException('Product name cannot be empty and must be a string.');
        }

        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            throw new InvalidArgumentException('Product price must be a non-negative number.');
        }

        if (isset($data['discounted_price']) && $data['discounted_price'] !== null && 
            (!is_numeric($data['discounted_price']) || $data['discounted_price'] < 0)) {
            throw new InvalidArgumentException('Discounted price must be a non-negative number or null.');
        }
    }

    private function updatePostFields(int $id, object $post, array $data): void
    {
        if (!isset($data['name']) && !isset($data['description'])) {
            return;
        }

        $update_data = ['ID' => $id];

        if (isset($data['name'])) {
            $update_data['post_title'] = sanitize_text_field($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $update_data['post_content'] = wp_kses_post($data['description'] ?? '');
        }

        $result = wp_update_post($update_data);
        if (is_wp_error($result)) {
            throw new RuntimeException('Failed to update post fields: ' . $result->get_error_message());
        }
    }

    private function updatePrices(int $id, array $data): void
    {
        if (array_key_exists('price', $data)) {
            $regular_price = (float) $data['price'];
            update_post_meta($id, '_regular_price', $regular_price);
            
            // Update _price if no discounted price is being set
            if (!array_key_exists('discounted_price', $data)) {
                update_post_meta($id, '_price', $regular_price);
            }
        }

        if (array_key_exists('discounted_price', $data)) {
            $discounted_price = $data['discounted_price'];
            
            if ($discounted_price === null) {
                update_post_meta($id, '_sale_price', '');
                $regular_price = (float) get_post_meta($id, '_regular_price', true);
                update_post_meta($id, '_price', $regular_price);
            } else {
                $discounted_price = (float) $discounted_price;
                update_post_meta($id, '_sale_price', $discounted_price);
                update_post_meta($id, '_price', $discounted_price);
            }
        }
    }

    private function updateTaxonomy(int $id, array $data): void
    {
        if (array_key_exists('category', $data)) {
            $this->setCategorySafely($id, $data['category']);
        }

        if (array_key_exists('tags', $data)) {
            $this->setTagsSafely($id, $data['tags'] ?? []);
        }
    }

    private function updateProductGroups(int $id, array $data): void
    {
        if (array_key_exists('product_group_ids', $data)) {
            $this->setProductGroupsSafely($id, $data['product_group_ids'] ?? []);
        }
    }

    /* ======================================================================
     *  PRIVATE HELPER METHODS - UTILITIES
     * ====================================================================*/
    private function ensureTaxonomyExists(string $taxonomy): bool
    {
        if (!taxonomy_exists($taxonomy)) {
            // Attempt to register basic taxonomy if it doesn't exist
            register_taxonomy($taxonomy, ProductPostType::POST_TYPE, [
                'public' => true,
                'hierarchical' => ($taxonomy === 'product_cat'),
            ]);
        }
        
        return taxonomy_exists($taxonomy);
    }

    /* ======================================================================
     *  PRIVATE HELPER METHODS - DELETE
     * ====================================================================*/
    private function findProductDependants(int $productId): array
    {
        $names = [];

        try {
            // Find GroupItems that reference this product
            $giIds = $this->findGroupItemsReferencingProduct($productId);

            if (empty($giIds)) {
                return []; // Product not referenced anywhere
            }

            // Cache repository instance to avoid creating one per iteration
            $pgRepo = new ProductGroupRepository();

            // Find ProductGroups containing those GroupItems
            foreach ($giIds as $giId) {
                $pgIds = $this->findProductGroupsContainingGroupItem($giId);

                foreach ($pgIds as $pgId) {
                    // Get ProductGroup name using cached repository
                    try {
                        $pg = $pgRepo->get($pgId);
                        if ($pg) {
                            $names[] = $pg->name;
                        }
                    } catch (Exception $e) {
                        error_log("Failed to get ProductGroup name for ID {$pgId}: " . $e->getMessage());
                    }

                    // Find other products that include this ProductGroup
                    $siblingIds = $this->findProductsUsingProductGroup($pgId);
                    // Filter out the current product
                    $siblingIds = array_filter($siblingIds, function ($sid) use ($productId) {
                        return $sid != $productId;
                    });

                    // Batch-fetch product names instead of individual get_post_field() calls
                    if (!empty($siblingIds)) {
                        $siblingPosts = get_posts([
                            'post_type'   => ProductPostType::POST_TYPE,
                            'post__in'    => array_values($siblingIds),
                            'fields'      => 'ids',
                            'numberposts' => -1,
                            'post_status' => 'publish',
                        ]);
                        foreach ($siblingPosts as $siblingPost) {
                            $names[] = get_the_title($siblingPost);
                        }
                    }
                }
            }

        } catch (Exception $e) {
            error_log("Error finding product dependants for {$productId}: " . $e->getMessage());
            // Return empty array to allow deletion if dependency check fails
            return [];
        }

        return array_unique(array_filter($names));
    }

    private function findGroupItemsReferencingProduct(int $productId): array
    {
        $posts = get_posts([
            'post_type'   => GroupItemPostType::POST_TYPE,
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_query'  => [
                'relation' => 'AND',
                [
                    'key'     => '_item_id',
                    'value'   => $productId,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => '_item_type',
                    'value'   => 'product',
                    'compare' => '=',
                ],
            ],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    private function findProductGroupsContainingGroupItem(int $giId): array
    {
        $posts = get_posts([
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_group_item_ids',
                'value'   => 'i:' . $giId . ';',
                'compare' => 'LIKE',
            ]],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    private function findProductsUsingProductGroup(int $pgId): array
    {
        $posts = get_posts([
            'post_type'   => ProductPostType::POST_TYPE,
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_product_group_ids',
                'value'   => 'i:' . $pgId . ';',
                'compare' => 'LIKE',
            ]],
        ]);

        return is_array($posts) ? array_map('intval', $posts) : [];
    }

    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $meta_query = ['relation' => 'AND'];
        $search_query = [];
        $tax_query = [];

        // Build meta query from criteria
        foreach ($criteria as $key => $value) {
            switch ($key) {
                case 'name':
                    // Search in post title
                    $search_query['s'] = $value;
                    break;
                    
                case 'category':
                    if (!empty($value)) {
                        $tax_query[] = [
                            'taxonomy' => 'product_cat',
                            'field' => 'name',
                            'terms' => $value,
                        ];
                    }
                    break;
                    
                case 'min_price':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_regular_price',
                            'value' => (float) $value,
                            'compare' => '>='
                        ];
                    }
                    break;
                    
                case 'max_price':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_regular_price',
                            'value' => (float) $value,
                            'compare' => '<='
                        ];
                    }
                    break;
                    
                case 'on_sale':
                    if ($value) {
                        $meta_query[] = [
                            'key' => '_sale_price',
                            'value' => '',
                            'compare' => '!='
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
                    
                case 'is_featured':
                    $meta_query[] = [
                        'key' => '_is_featured',
                        'value' => (bool) $value,
                        'compare' => '='
                    ];
                    break;
                    
                case 'has_product_group':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_product_group_ids',
                            'value' => 'i:' . (int) $value . ';',
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'tag':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_tags',
                            'value' => $value,
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
            }
        }

        $query_args = [
            'post_type' => ProductPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit ?? -1,
            'offset' => $offset,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        // Custom orderby for sort_order
        if (isset($criteria['orderby']) && $criteria['orderby'] === 'sort_order') {
            $query_args['meta_key'] = '_sort_order';
            $query_args['orderby'] = 'meta_value_num';
            $query_args['order'] = $criteria['order'] ?? 'ASC';
        }

        if (!empty($meta_query) && count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        if (!empty($search_query)) {
            $query_args = array_merge($query_args, $search_query);
        }

        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($query_args);

        $products = [];
        foreach ($query->posts as $post_id) {
            $product = $this->get((int) $post_id);
            if ($product) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Count products by criteria
     */
    public function countBy(array $criteria): int
    {
        $products = $this->findBy($criteria);
        return count($products);
    }

    /**
     * Check if product exists
     */
    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        return $post && $post->post_type === ProductPostType::POST_TYPE;
    }

    /**
     * Find products by category
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category]);
    }

    /**
     * Find available products
     */
    public function findAvailable(): array
    {
        return $this->findBy(['is_available' => true]);
    }

    /**
     * Find featured products
     */
    public function findFeatured(): array
    {
        return $this->findBy(['is_featured' => true]);
    }

    /**
     * Find products on sale
     */
    public function findOnSale(): array
    {
        return $this->findBy(['on_sale' => true]);
    }

    /**
     * Find products in price range
     */
    public function findInPriceRange(float $min_price, float $max_price): array
    {
        return $this->findBy([
            'min_price' => $min_price,
            'max_price' => $max_price
        ]);
    }

    /**
     * Find products with specific product group
     */
    public function findWithProductGroup(int $group_id): array
    {
        return $this->findBy(['has_product_group' => $group_id]);
    }

    /**
     * Find products ordered by sort order
     */
    public function findOrderedBySort(string $order = 'ASC'): array
    {
        return $this->findBy(['orderby' => 'sort_order', 'order' => $order]);
    }

    /**
     * Update branch availability for a product
     *
     * @param int $id Product ID
     * @param array $availability Array of branch_id => boolean availability
     * @return bool Success
     */
    public function updateAvailability(int $id, array $availability): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductPostType::POST_TYPE) {
            return false;
        }

        foreach ($availability as $branch_id => $is_available) {
            $branch_id = (int) $branch_id;
            $is_available = (bool) $is_available;
            $meta_key = '_branch_availability_' . $branch_id;
            $meta_value = $is_available ? '1' : '0';

            update_post_meta($id, $meta_key, $meta_value);
        }

        return true;
    }

    /**
     * Get branch availability for a product
     *
     * @param int $id Product ID
     * @return array Array of branch_id => boolean availability
     */
    public function getAvailability(int $id): array
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductPostType::POST_TYPE) {
            return [];
        }

        // Get actual branch IDs from the database
        $branch_repository = new StoreBranchRepository();
        $branches = $branch_repository->getAll();

        $availability = [];

        foreach ($branches as $branch) {
            $availability[$branch->id] = (bool) get_post_meta($id, '_branch_availability_' . $branch->id, true);
        }

        return $availability;
    }

    /**
     * Calculate final availability for a product (considering group dependencies)
     *
     * @param int $id Product ID
     * @return array Array of branch_id => boolean final availability
     */
    public function calculateFinalAvailability(int $id): array
    {
        $product = $this->get($id);
        if (!$product) {
            return [];
        }

        return $product->calculateFinalAvailability($this, new ProductGroupRepository());
    }

    /**
     * Get comprehensive availability info for a product
     *
     * @param int $id Product ID
     * @return array Availability info including direct, final, and group restrictions
     */
    public function getAvailabilityInfo(int $id): array
    {
        $product = $this->get($id);
        if (!$product) {
            return [
                'direct_availability' => [],
                'final_availability' => [],
                'group_restrictions' => [],
            ];
        }

        return $product->getAvailabilityInfo($this, new ProductGroupRepository());
    }

    /* ======================================================================
     *  IMAGE HANDLING
     * ====================================================================*/

    /**
     * Get product image ID
     *
     * @param int $post_id Product post ID
     * @return int|null Image attachment ID or null if no image
     */
    private function getImageIdSafely(int $post_id): ?int
    {
        $image_id = get_post_thumbnail_id($post_id);

        return $image_id ? (int) $image_id : null;
    }

    /**
     * Get product image URL
     *
     * @param int $post_id Product post ID
     * @return string|null Image URL or null if no image
     */
    private function getImageUrlSafely(int $post_id): ?string
    {
        if (!has_post_thumbnail($post_id)) {
            return null;
        }

        $image_url = get_the_post_thumbnail_url($post_id, 'large');

        return $image_url ?: null;
    }

    /**
     * Set product image from attachment ID
     *
     * @param int $post_id Product post ID
     * @param int|null $attachment_id WordPress attachment ID
     * @return bool Success
     */
    private function setProductImage(int $post_id, ?int $attachment_id): bool
    {
        if ($attachment_id === null) {
            // Remove featured image
            return delete_post_thumbnail($post_id);
        }

        // Validate attachment exists and is an image
        if (!wp_attachment_is_image($attachment_id)) {
            throw new InvalidArgumentException("Attachment ID {$attachment_id} is not a valid image");
        }

        return (bool) set_post_thumbnail($post_id, $attachment_id);
    }

}