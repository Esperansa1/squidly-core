<?php

declare(strict_types=1);

class ProductGroupRepository implements RepositoryInterface
{

    public function create(array $data): int
    {
        if (empty($data['name']) || !ItemType::tryFrom($data['type'])) {
            throw new InvalidArgumentException('Invalid ProductGroup data.');
        }

        // Validate group items to prevent mixed types
        $this->validateGroupItems($data['group_item_ids'] ?? [], $data['type']);

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($data['name']),
            'post_content' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'post_type'    => ProductGroupPostType::POST_TYPE,
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create ProductGroup: ' . $post_id->get_error_message());
        }

        update_post_meta($post_id, '_type', $data['type']);
        update_post_meta($post_id, '_group_item_ids', array_map('intval', $data['group_item_ids'] ?? []));

        // Save min/max selection constraints (default to 0)
        update_post_meta($post_id, '_min_selections', isset($data['min_selections']) ? max(0, (int) $data['min_selections']) : 0);
        update_post_meta($post_id, '_max_selections', isset($data['max_selections']) ? max(0, (int) $data['max_selections']) : 0);

        // Handle branch availability if provided
        if (isset($data['availability']) && is_array($data['availability'])) {
            $this->updateAvailability($post_id, $data['availability']);
        }

        return $post_id;
    }

    public function get(int $id): ?ProductGroup
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductGroupPostType::POST_TYPE) {
            return null;
        }

        $type = get_post_meta($id, '_type', true);
        
        // Skip ProductGroups without _type meta field - they're from before the filtering was implemented
        if (empty($type) || !ItemType::tryFrom($type)) {
            return null;
        }

        return new ProductGroup([
            'id'              => $id,
            'name'            => $post->post_title,
            'description'     => $post->post_content,
            'type'            => $type,
            'group_item_ids'  => get_post_meta($id, '_group_item_ids', true) ?? [],
            'availability'    => $this->getAvailability($id),
            'min_selections'  => (int) get_post_meta($id, '_min_selections', true),
            'max_selections'  => (int) get_post_meta($id, '_max_selections', true),
        ]);
    }


    /* ---------------------------------------------------------------------
     *  update()
     * -------------------------------------------------------------------*/
    public function update(int $id, array $data): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductGroupPostType::POST_TYPE) {
            return false;
        }

        if (isset($data['name']) && $data['name'] === '') {
            throw new InvalidArgumentException('ProductGroup name cannot be empty.');
        }
        if (isset($data['type']) && ItemType::tryFrom($data['type']) === null) {
            throw new InvalidArgumentException('Invalid type for ProductGroup.');
        }

        // Validate group items to prevent mixed types if both type and group_item_ids are being updated
        if (array_key_exists('group_item_ids', $data)) {
            $type = $data['type'] ?? get_post_meta($id, '_type', true);
            $this->validateGroupItems($data['group_item_ids'], $type);
        }

        $update_data = ['ID' => $id];
        if (isset($data['name'])) {
            $update_data['post_title'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $update_data['post_content'] = sanitize_textarea_field($data['description']);
        }
        if (count($update_data) > 1) {
            wp_update_post($update_data);
        }
        if (array_key_exists('type', $data)) {
            update_post_meta($id, '_type', $data['type']);
        }
        if (array_key_exists('group_item_ids', $data)) {
            update_post_meta($id, '_group_item_ids', array_map('intval', $data['group_item_ids']));
        }

        // Update min/max selections constraints
        if (array_key_exists('min_selections', $data)) {
            update_post_meta($id, '_min_selections', max(0, (int) $data['min_selections']));
        }
        if (array_key_exists('max_selections', $data)) {
            update_post_meta($id, '_max_selections', max(0, (int) $data['max_selections']));
        }

        // Update branch availability if provided
        if (array_key_exists('availability', $data)) {
            $this->updateAvailability($id, $data['availability']);
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     *  delete()  — dependency-aware
     * -------------------------------------------------------------------*/
    public function delete(int $id, bool $force = false): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductGroupPostType::POST_TYPE) {
            return false;
        }

        $dependants = $this->findProductGroupDependants($id);
        if ($dependants) {
            throw new ResourceInUseException($dependants);
        }

        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Failed to delete ProductGroup: ' . $result->get_error_message()
            );
        }
        return (bool) $result;
    }

    /* ---------------------------------------------------------------------
     *  getAll()
     * -------------------------------------------------------------------*/
    public function getAll(): array
    {
        $ids = get_posts([
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'nopaging'    => true,
        ]);

        // Prime the meta cache for all posts at once (1 query instead of N)
        if (!empty($ids)) {
            update_meta_cache('post', $ids);
        }

        return array_values(
            array_filter(
                array_map(fn($pid) => $this->get((int)$pid), $ids)
            )
        );
    }

    /* ---------------------------------------------------------------------
     *  getAllByItemType()
     * -------------------------------------------------------------------*/
    /**
     * Get all ProductGroups filtered by ItemType
     * 
     * @param ItemType $itemType The item type to filter by (product or ingredient)
     * @return array Array of ProductGroup objects
     */
    public function getAllByItemType(ItemType $itemType): array
    {
        $ids = get_posts([
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => [
                [
                    'key'     => '_type',
                    'value'   => $itemType->value,
                    'compare' => '='
                ],
                // Ensure the meta key exists (exclude ProductGroups without _type meta)
                [
                    'key'     => '_type',
                    'compare' => 'EXISTS'
                ]
            ],
            'meta_query_relation' => 'AND'
        ]);

        // Prime the meta cache for all posts at once (1 query instead of N)
        if (!empty($ids)) {
            update_meta_cache('post', $ids);
        }

        return array_values(
            array_filter(
                array_map(fn($pid) => $this->get((int)$pid), $ids)
            )
        );
    }

    /* ---------------------------------------------------------------------
     *  getProductGroups() - Convenience method for product-type groups
     * -------------------------------------------------------------------*/
    /**
     * Get all ProductGroups that group products together (ItemType::PRODUCT)
     * These are used for organizing related products in menu categories
     * 
     * @return array Array of ProductGroup objects with type 'product'
     */
    public function getProductGroups(): array
    {
        return $this->getAllByItemType(ItemType::from('product'));
    }

    /* ---------------------------------------------------------------------
     *  getIngredientGroups() - Convenience method for ingredient-type groups
     * -------------------------------------------------------------------*/
    /**
     * Get all ProductGroups that group ingredients for customization (ItemType::INGREDIENT)
     * These are used for allowing customers to customize ingredients within a product
     * 
     * @return array Array of ProductGroup objects with type 'ingredient'
     */
    public function getIngredientGroups(): array
    {
        return $this->getAllByItemType(ItemType::from('ingredient'));
    }

    /* ---------------------------------------------------------------------
     *  Helper – list Products that still include this ProductGroup
     * -------------------------------------------------------------------*/
    private function findProductGroupDependants(int $pgId): array
    {
        $names = [];
        $serializedId = 'i:' . $pgId . ';';

        $prodIds = get_posts([
            'post_type'   => ProductPostType::POST_TYPE,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => [[
                'key'     => '_product_group_ids',
                'value'   => $serializedId,
                'compare' => 'LIKE',
            ]],
        ]);

        foreach ($prodIds as $pid) {
            $names[] = get_post_field('post_title', $pid);
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
                case 'search':
                    // Search in post title
                    $search_query['s'] = $value;
                    break;
                    
                case 'type':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_type',
                            'value' => $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'contains_group_item':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_group_item_ids',
                            'value' => 'i:' . (int) $value . ';',
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'min_items':
                    // Groups with at least X items
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_group_item_ids',
                            'value' => str_repeat('i:', (int) $value),
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
            }
        }

        $query_args = [
            'post_type' => ProductGroupPostType::POST_TYPE,
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

        // Prime the meta cache for all posts at once (1 query instead of N)
        if (!empty($query->posts)) {
            update_meta_cache('post', $query->posts);
        }

        $groups = [];
        foreach ($query->posts as $post_id) {
            $group = $this->get((int) $post_id);
            if ($group) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * Count product groups by criteria
     */
    public function countBy(array $criteria): int
    {
        $groups = $this->findBy($criteria);
        return count($groups);
    }

    /**
     * Check if product group exists
     */
    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        return $post && $post->post_type === ProductGroupPostType::POST_TYPE;
    }

    /**
     * Find product groups by type
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }

    /**
     * Find groups containing specific group item
     */
    public function findContainingGroupItem(int $group_item_id): array
    {
        return $this->findBy(['contains_group_item' => $group_item_id]);
    }

    /**
     * Validate that group items match the specified type to prevent mixed types
     *
     * @param array $group_item_ids Array of GroupItem IDs to validate
     * @param string $expected_type The expected type ('ingredient' or 'product')
     * @throws InvalidArgumentException If mixed types are found
     */
    private function validateGroupItems(array $group_item_ids, string $expected_type): void
    {
        if (empty($group_item_ids)) {
            return;
        }

        $groupItemRepo = new GroupItemRepository();

        // Check each group item ID
        foreach ($group_item_ids as $group_item_id) {
            $group_item_id = intval($group_item_id);

            // Get the GroupItem object
            $group_item = $groupItemRepo->get($group_item_id);
            if (!$group_item) {
                // Skip validation for non-existent items during cleanup/testing
                // Log warning in production but don't fail validation
                error_log("Warning: Referenced group item ID {$group_item_id} not found, skipping validation");
                continue;
            }

            // Check if the item type matches the expected type
            $actual_type = $group_item->item_type->value;

            if ($actual_type !== $expected_type) {
                throw new InvalidArgumentException("Cannot mix {$actual_type}s and {$expected_type}s in the same group. Found {$actual_type} item in {$expected_type} group.");
            }
        }
    }

    /**
     * Update branch availability for a product group
     *
     * @param int $id ProductGroup ID
     * @param array $availability Array of branch_id => boolean availability
     * @return bool Success
     */
    public function updateAvailability(int $id, array $availability): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductGroupPostType::POST_TYPE) {
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
     * Get branch availability for a product group
     *
     * @param int $id ProductGroup ID
     * @return array Array of branch_id => boolean availability
     */
    public function getAvailability(int $id): array
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== ProductGroupPostType::POST_TYPE) {
            return [];
        }

        // Get all availability meta keys for this product group
        $all_meta = get_post_meta($id);
        $availability = [];

        foreach ($all_meta as $meta_key => $meta_value) {
            if (strpos($meta_key, '_branch_availability_') === 0) {
                $branch_id = (int) str_replace('_branch_availability_', '', $meta_key);
                $is_available = (bool) ($meta_value[0] ?? false);
                $availability[$branch_id] = $is_available; // Include both true and false values
            }
        }

        return $availability;
    }

    /**
     * Calculate availability for a product group based on its constituent items
     *
     * @param int $id ProductGroup ID
     * @return array Array of branch_id => boolean calculated availability
     */
    public function calculateAvailability(int $id): array
    {
        $group = $this->get($id);
        if (!$group) {
            return [];
        }

        return $group->calculateAvailability();
    }

    /**
     * Get final availability for a product group (combining manual and calculated)
     *
     * @param int $id ProductGroup ID
     * @return array Array of branch_id => boolean final availability
     */
    public function getFinalAvailability(int $id): array
    {
        $group = $this->get($id);
        if (!$group) {
            return [];
        }

        return $group->getFinalAvailability();
    }

}
