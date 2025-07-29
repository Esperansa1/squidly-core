<?php
declare(strict_types=1);

/**
 * Persistence layer for StoreBranch.
 *
 * All WordPress DB I/O is encapsulated here; the DTO (StoreBranch) remains
 * framework-agnostic.
 */
class StoreBranchRepository
{
    public const POST_TYPE = 'store_branch';

    /* ---------------------------------------------------------------------
     *  create()
     * -------------------------------------------------------------------*/
    /**
     * Persist a new StoreBranch and return its post-ID.
     *
     * Required keys: name, phone, city, address, is_open (bool),
     *                activity_times (array), kosher_type (string),
     *                accessibility_list (array)
     *
     * Optional: products, ingredients, product_availability,
     *           ingredient_availability
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function create(array $data): int
    {
        foreach (['name','phone','city','address','is_open',
                  'activity_times','kosher_type','accessibility_list'] as $key) {
            if ( ! array_key_exists($key, $data) ) {
                throw new InvalidArgumentException("Missing required key: $key");
            }
        }

        $post_id = wp_insert_post([
            'post_title'  => sanitize_text_field($data['name']),
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException(
                'Failed to create StoreBranch: ' . $post_id->get_error_message()
            );
        }

        /* meta fields ----------------------------------------------------*/
        update_post_meta($post_id, '_phone',            sanitize_text_field($data['phone']));
        update_post_meta($post_id, '_city',             sanitize_text_field($data['city']));
        update_post_meta($post_id, '_address',          sanitize_text_field($data['address']));
        update_post_meta($post_id, '_is_open',          (bool) $data['is_open']);
        update_post_meta($post_id, '_activity_times',   $data['activity_times']);
        update_post_meta($post_id, '_kosher_type',      sanitize_text_field($data['kosher_type']));
        update_post_meta($post_id, '_accessibility_list',$data['accessibility_list']);

        update_post_meta($post_id, '_products',                $data['products']                ?? []);
        update_post_meta($post_id, '_ingredients',             $data['ingredients']             ?? []);
        update_post_meta($post_id, '_product_availability',    $data['product_availability']    ?? []);
        update_post_meta($post_id, '_ingredient_availability', $data['ingredient_availability'] ?? []);

        return $post_id;
    }

    /* ---------------------------------------------------------------------
     *  get()
     * -------------------------------------------------------------------*/
    public function get(int $id): ?StoreBranch
    {
        $post = get_post($id);

        if ( ! $post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return new StoreBranch([
            'id'                     => $id,
            'name'                   => $post->post_title,
            'phone'                  => (string) get_post_meta($id, '_phone', true),
            'city'                   => (string) get_post_meta($id, '_city', true),
            'address'                => (string) get_post_meta($id, '_address', true),
            'is_open'                => (bool)   get_post_meta($id, '_is_open', true),
            'activity_times'         => get_post_meta($id, '_activity_times', true)      ?: [],
            'kosher_type'            => (string) get_post_meta($id, '_kosher_type', true),
            'accessibility_list'     => get_post_meta($id, '_accessibility_list', true)  ?: [],
            'products'               => get_post_meta($id, '_products', true)            ?: [],
            'ingredients'            => get_post_meta($id, '_ingredients', true)         ?: [],
            'product_availability'   => get_post_meta($id, '_product_availability', true)    ?: [],
            'ingredient_availability'=> get_post_meta($id, '_ingredient_availability', true) ?: [],
        ]);
    }

    /* ---------------------------------------------------------------------
     *  getAll() – all published branches
     * -------------------------------------------------------------------*/
    /**
     * @return StoreBranch[]
     */
    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        $out = [];
        foreach ($query->posts as $id) {
            $branch = $this->get((int) $id);
            if ($branch) {
                $out[] = $branch;
            }
        }
        return $out;
    }
}
