<?php

declare(strict_types=1);

class ProductGroupRepository implements RepositoryInterface
{
    const POST_TYPE = 'product_group';

    public function create(array $data): int
    {
        if (empty($data['name']) || !ItemType::tryFrom($data['type'])) {
            throw new InvalidArgumentException('Invalid ProductGroup data.');
        }

        $post_id = wp_insert_post([
            'post_title'  => sanitize_text_field($data['name']),
            'post_type'   => self::POST_TYPE,
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
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return new ProductGroup([
            'id'              => $id,
            'name'            => $post->post_title,
            'type'            => get_post_meta($id, '_type', true),
            'group_item_ids'  => get_post_meta($id, '_group_item_ids', true) ?? [],
        ]);
    }
}
