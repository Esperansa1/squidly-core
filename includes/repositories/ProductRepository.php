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
            ]);

        } catch (Exception $e) {
            error_log("Failed to create Product object for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    /* ======================================================================
     *  GETALL
     * ====================================================================*/
    public function getAll(): array
    {
        try {
            $query = new WP_Query([
                'post_type'      => ProductPostType::POST_TYPE,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]);

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

        $result = update_post_meta($post_id, '_product_group_ids', $valid_group_ids);
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

            // Find ProductGroups containing those GroupItems
            foreach ($giIds as $giId) {
                $pgIds = $this->findProductGroupsContainingGroupItem($giId);
                
                foreach ($pgIds as $pgId) {
                    // Get ProductGroup name
                    $pg_name = $this->getProductGroupName($pgId);
                    if ($pg_name) {
                        $names[] = $pg_name;
                    }

                    // Find other products that include this ProductGroup
                    $siblingIds = $this->findProductsUsingProductGroup($pgId);
                    foreach ($siblingIds as $sid) {
                        if ($sid != $productId) {
                            $product_name = get_post_field('post_title', $sid);
                            if ($product_name && !is_wp_error($product_name)) {
                                $names[] = $product_name;
                            }
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

    private function getProductGroupName(int $pgId): ?string
    {
        try {
            $pgRepo = new ProductGroupRepository();
            $pg = $pgRepo->get($pgId);
            return $pg ? $pg->name : null;
        } catch (Exception $e) {
            error_log("Failed to get ProductGroup name for ID {$pgId}: " . $e->getMessage());
            return null;
        }
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


}