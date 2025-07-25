<?php

declare(strict_types=1);

class GroupItemRepository
{
    const POST_TYPE = 'group_item';

    public function create(array $data): int
    {
        if (!isset($data['item_id'], $data['item_type'])) {
            throw new InvalidArgumentException("GroupItem requires item_id and item_type.");
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
            'item_id'        => (int) get_post_meta($id, '_item_id', true),
            'item_type'      => (string) get_post_meta($id, '_item_type', true),
            'override_price' => ($override = get_post_meta($id, '_override_price', true)) !== '' ? (float) $override : null,
        ]);
    }
}
