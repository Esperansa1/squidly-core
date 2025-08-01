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

        return new ProductGroup([
            'id'              => $id,
            'name'            => $post->post_title,
            'type'            => get_post_meta($id, '_type', true),
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

}
