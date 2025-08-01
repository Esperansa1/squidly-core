<?php

declare(strict_types=1);

class ProductRepository implements RepositoryInterface
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

    /* ======================================================================
    *  Safe delete()
    * ====================================================================*/

    /**
     * Delete a product only if it is not referenced by any
     *   – GroupItem  (item_type = product)
     *   – ProductGroup that includes that GroupItem
     *   – Other Products that include that ProductGroup
     *
     * @throws ResourceInUseException when still referenced
     * @throws RuntimeException       on WP delete failure
     */
    public function delete(int $id, bool $force = false): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return false;
        }

        $dependants = $this->findProductDependants($id);
        if ($dependants) {
            throw new ResourceInUseException($dependants);
        }

        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Failed to delete product: ' . $result->get_error_message()
            );
        }
        return (bool) $result;
    }

    /* ======================================================================
    *  Helper: who is still using this product?
    * ====================================================================*/
    private function findProductDependants(int $productId): array
    {
        $names   = [];
        $giRepo  = new GroupItemRepository();
        $pgRepo  = new ProductGroupRepository();

        /* 1) GroupItems that reference this product -----------------------*/
        $giIds = get_posts([
            'post_type'   => GroupItemRepository::POST_TYPE,
            'fields'      => 'ids',
            'nopaging'    => true,
            'post_status' => 'publish',
            'meta_query'  => [
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

        if (!$giIds) {
            return [];              // product not referenced anywhere
        }

        /* 2) ProductGroups containing those GroupItems -------------------*/
        foreach ($giIds as $giId) {
            $pgIds = get_posts([
                'post_type'  => ProductGroupRepository::POST_TYPE,
                'fields'     => 'ids',
                'nopaging'   => true,
                'post_status'=> 'publish',
                'meta_query' => [[
                    'key'     => '_group_item_ids',
                    'value'   => 'i:' . $giId . ';',
                    'compare' => 'LIKE',
                ]],
            ]);

            foreach ($pgIds as $pgId) {
                $pg = $pgRepo->get((int)$pgId);
                $names[] = $pg->name;

                /* 3) Other products that include this ProductGroup --------*/
                $siblingIds = get_posts([
                    'post_type'  => self::POST_TYPE,
                    'fields'     => 'ids',
                    'nopaging'   => true,
                    'post_status'=> 'publish',
                    'meta_query' => [[
                        'key'     => '_product_group_ids',
                        'value'   => 'i:' . $pgId . ';',
                        'compare' => 'LIKE',
                    ]],
                ]);

                foreach ($siblingIds as $sid) {
                    if ($sid == $productId) {
                        continue;   // skip self
                    }
                    $names[] = get_post_field('post_title', $sid);
                }
            }
        }

        return array_unique($names);
    }

}
