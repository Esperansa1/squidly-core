<?php

declare(strict_types=1);

class ProductGroupRepository implements RepositoryInterface
{

    public function create(array $data): int
    {
        if (empty($data['name']) || !ItemType::tryFrom($data['type'])) {
            throw new InvalidArgumentException('Invalid ProductGroup data.');
        }

        $post_id = wp_insert_post([
            'post_title'  => sanitize_text_field($data['name']),
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create ProductGroup: ' . $post_id->get_error_message());
        }

        update_post_meta($post_id, '_type', $data['type']);
        update_post_meta($post_id, '_group_item_ids', array_map('intval', $data['group_item_ids'] ?? []));

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
            'type'            => $type,
            'group_item_ids'  => get_post_meta($id, '_group_item_ids', true) ?? [],
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

        if (isset($data['name'])) {
            wp_update_post([
                'ID'         => $id,
                'post_title' => sanitize_text_field($data['name']),
            ]);
        }
        if (array_key_exists('type', $data)) {
            update_post_meta($id, '_type', $data['type']);
        }
        if (array_key_exists('group_item_ids', $data)) {
            update_post_meta($id, '_group_item_ids', array_map('intval', $data['group_item_ids']));
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

}
