<?php

declare(strict_types=1);

class GroupItemRepository implements RepositoryInterface
{

    public function create(array $data): int
    {
        if (!isset($data['item_id'], $data['item_type'])) {
            throw new InvalidArgumentException("GroupItem requires item_id and item_type.");
        }

        if (ItemType::tryFrom($data['item_type']) === null) {
            throw new InvalidArgumentException('Invalid item_type; must be "product" or "ingredient".');
        }
        
        $post_id = wp_insert_post([
            'post_title'  => 'Group Item',
            'post_type'   => GroupItemPostType::POST_TYPE,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create GroupItem: ' . $post_id->get_error_message());
        }

        update_post_meta($post_id, '_item_id', (int) $data['item_id']);
        update_post_meta($post_id, '_item_type', sanitize_text_field($data['item_type']));
        update_post_meta($post_id, '_override_price', isset($data['override_price']) ? (float) $data['override_price'] : '');

        return $post_id;
    }

    public function get(int $id): ?GroupItem
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== GroupItemPostType::POST_TYPE) {
            return null;
        }

        return new GroupItem([
            'id'             => $id,
            'item_id'        => (int) get_post_meta($id, '_item_id', true),
            'item_type'      => (string) get_post_meta($id, '_item_type', true),
            'override_price' => ($override = get_post_meta($id, '_override_price', true)) !== '' ? (float) $override : null,
        ]);
    }

    /* ---------------------------------------------------------------------
     *  update()
     * -------------------------------------------------------------------*/
    /**
     * Update an existing GroupItem.
     * Accepts any subset of ['item_id','item_type','override_price'].
     *
     * @return bool false when the post is missing / wrong type
     * @throws InvalidArgumentException on bad input
     */
    public function update(int $id, array $data): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== GroupItemPostType::POST_TYPE) {
            return false;
        }

        /* validations ---------------------------------------------------*/
        if (isset($data['item_type']) &&
            ItemType::tryFrom($data['item_type']) === null
        ) {
            throw new InvalidArgumentException(
                'item_type must be "product" or "ingredient".'
            );
        }

        /* meta updates --------------------------------------------------*/
        if (array_key_exists('item_id', $data)) {
            update_post_meta($id, '_item_id', (int) $data['item_id']);
        }
        if (array_key_exists('item_type', $data)) {
            update_post_meta($id, '_item_type',
                sanitize_text_field($data['item_type'])
            );
        }
        if (array_key_exists('override_price', $data)) {
            $val = $data['override_price'];
            update_post_meta($id, '_override_price',
                $val === null ? '' : (float) $val
            );
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     *  getAll()
     * -------------------------------------------------------------------*/
    /**
     * Retrieve all published GroupItems.
     *
     * @return GroupItem[]
     */
    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type'      => GroupItemPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $out = [];
        foreach ($query->posts as $gid) {
            $gi = $this->get((int) $gid);
            if ($gi) {
                $out[] = $gi;
            }
        }
        return $out;
    }


    /* ---------------------------------------------------------------------
     *  delete()
     * -------------------------------------------------------------------*/
    /**
     * Trash or force-delete a GroupItem.
     *
     * @param bool $force  true = bypass trash
     * @return bool
     */
    public function delete(int $id, bool $force = false): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== GroupItemPostType::POST_TYPE) {
            return false;
        }

        $dependants = $this->findGroupItemDependants($id);
        if ($dependants) {
            throw new ResourceInUseException($dependants);
        }


        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Failed to delete GroupItem: ' . $result->get_error_message()
            );
        }
        return (bool) $result;
    }


    /* ---------------------------------------------------------------------
    *  Helper: list ProductGroups / Products that still use this GI
    * -------------------------------------------------------------------*/
    private function findGroupItemDependants(int $giId): array
    {
        $names   = [];
        $pgRepo  = new ProductGroupRepository();
        $prodRepo= new ProductRepository();

        /* 1) Product-groups that embed this GroupItem -----------------------*/
        $pgIds = get_posts([
            'post_type'   => ProductGroupPostType::POST_TYPE,
            'fields'      => 'ids',
            'nopaging'    => true,
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_group_item_ids',
                'value'   => 'i:' . $giId . ';',      // serialized int
                'compare' => 'LIKE',
            ]],
        ]);

        if(!$pgIds){
            return [];
        }

        foreach ($pgIds as $pgId) {
            if ($pg = $pgRepo->get((int) $pgId)) {
                $names[] = $pg->name;
            }

            /* 2) Products that embed **this** product-group ----------------*/
            $prodIds = get_posts([
                'post_type'   => ProductPostType::POST_TYPE,
                'fields'      => 'ids',
                'nopaging'    => true,
                'post_status' => 'publish',
                'meta_query'  => [[
                    'key'     => '_product_group_ids',
                    'value'   => 'i:' . $pgId . ';',  // ← single string per loop
                    'compare' => 'LIKE',
                ]],
            ]);

            foreach ($prodIds as $pid) {
                $names[] = get_post_field('post_title', $pid);
            }
        }

        return array_unique($names);
    }


    /**
     * Find group items by criteria
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $meta_query = ['relation' => 'AND'];

        // Build meta query from criteria
        foreach ($criteria as $key => $value) {
            switch ($key) {
                case 'item_id':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_item_id',
                            'value' => (int) $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'item_type':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_item_type',
                            'value' => $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'has_override_price':
                    if ($value) {
                        $meta_query[] = [
                            'key' => '_override_price',
                            'value' => '',
                            'compare' => '!='
                        ];
                    } else {
                        $meta_query[] = [
                            'key' => '_override_price',
                            'value' => '',
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'min_override_price':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_override_price',
                            'value' => (float) $value,
                            'compare' => '>='
                        ];
                    }
                    break;
                    
                case 'max_override_price':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_override_price',
                            'value' => (float) $value,
                            'compare' => '<='
                        ];
                    }
                    break;
            }
        }

        $query_args = [
            'post_type' => GroupItemPostType::POST_TYPE,
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

        $query = new WP_Query($query_args);

        $group_items = [];
        foreach ($query->posts as $post_id) {
            $group_item = $this->get((int) $post_id);
            if ($group_item) {
                $group_items[] = $group_item;
            }
        }

        return $group_items;
    }

    /**
     * Count group items by criteria
     */
    public function countBy(array $criteria): int
    {
        $group_items = $this->findBy($criteria);
        return count($group_items);
    }

    /**
     * Check if group item exists
     */
    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        return $post && $post->post_type === GroupItemPostType::POST_TYPE;
    }

    /**
     * Find group items by referenced item
     */
    public function findByReferencedItem(int $item_id, string $item_type): array
    {
        return $this->findBy([
            'item_id' => $item_id,
            'item_type' => $item_type
        ]);
    }

    /**
     * Find group items with price overrides
     */
    public function findWithOverrides(): array
    {
        return $this->findBy(['has_override_price' => true]);
    }

    
}
