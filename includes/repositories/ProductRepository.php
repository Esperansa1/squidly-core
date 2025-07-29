<?php

declare(strict_types=1);

class ProductRepository
{

    public const POST_TYPE = 'product';

    public function create(array $data): int
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Product name is required.');
        }

        if (!isset($data['price']) || $data['price'] < 0) {
            throw new InvalidArgumentException('Product price is required.');
        }
        

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($data['name']),
            'post_content' => wp_kses_post($data['description'] ?? ''),
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create product: ' . $post_id->get_error_message());
        }

        // Prices
        $regular = (float) $data['price'];
        $sale    = isset($data['discounted_price']) ? (float) $data['discounted_price'] : '';
        update_post_meta($post_id, '_regular_price', $regular);
        update_post_meta($post_id, '_price', $sale !== '' ? $sale : $regular);
        if ($sale !== '') {
            update_post_meta($post_id, '_sale_price', $sale);
        }

        // Category
        if (!empty($data['category'])) {
            wp_set_object_terms($post_id, sanitize_text_field($data['category']), 'product_cat');
        }

        // Tags
        if (!empty($data['tags'])) {
            wp_set_object_terms($post_id, array_map('sanitize_text_field', $data['tags']), 'product_tag');
        }

        // ProductGroup IDs
        if (!empty($data['product_group_ids']) && is_array($data['product_group_ids'])) {
            $group_ids = array_map('intval', $data['product_group_ids']);
            update_post_meta($post_id, '_product_group_ids', $group_ids);
        }

        return $post_id;
    }

    public function get(int $id): ?Product
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        $regular = (float) get_post_meta($id, '_regular_price', true);
        $sale    = get_post_meta($id, '_sale_price', true);
        $sale    = ($sale === '') ? null : (float) $sale;

        $category = wp_get_object_terms($id, 'product_cat', ['fields' => 'names']);
        $tags     = wp_get_object_terms($id, 'product_tag', ['fields' => 'names']);

        $group_ids = get_post_meta($id, '_product_group_ids', true);
        $group_ids = is_array($group_ids) ? $group_ids : [];

        
        $groupRepo = new ProductGroupRepository();
        $product_groups = [];

        foreach ($group_ids as $gid) {
            $group = $groupRepo->get((int) $gid);
            if ($group) {
                $product_groups[] = $group;
            }
        }

        return new Product([
            'id'               => $id,
            'name'             => $post->post_title,
            'description'      => $post->post_content,
            'price'            => $regular,
            'discounted_price' => $sale,
            'category'         => $category[0] ?? null,
            'tags'             => is_array($tags) ? $tags : [],
            'product_group_ids'=> $group_ids,
        ]);
    }

    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $products = [];

        foreach ($query->posts as $post) {
            $product = $this->get((int) $post->ID);
            if ($product) {
                $products[] = $product;
            }
        }

        return $products;
    }
}
