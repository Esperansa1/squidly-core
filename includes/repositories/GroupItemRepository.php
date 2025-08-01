<?php

declare(strict_types=1);

class GroupItemRepository implements RepositoryInterface
{
    const POST_TYPE = 'group_item';

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
            'post_type'   => self::POST_TYPE,
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
        if (!$post || $post->post_type !== self::POST_TYPE) {
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
        if (!$post || $post->post_type !== self::POST_TYPE) {
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
            'post_type'      => self::POST_TYPE,
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
        if (!$post || $post->post_type !== self::POST_TYPE) {
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
            'post_type'   => ProductGroupRepository::POST_TYPE,
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
                'post_type'   => ProductRepository::POST_TYPE,
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
    
}
