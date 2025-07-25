<?php

declare(strict_types=1);

class IngredientRepository
{
    const POST_TYPE = 'ingredient';

    /**
     * Create a new Ingredient and save it to the database.
     *
     * @param array $data Should contain:
     *  - name (string)
     *  - price (float)
     * @return int The post ID of the created ingredient
     */
    public function create(array $data): int
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Ingredient name is required.');
        }

        $name = sanitize_text_field($data['name']);
        $price = (float) ($data['price'] ?? 0);

        $post_id = wp_insert_post([
            'post_title'  => $name,
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create ingredient: ' . $post_id->get_error_message());
        }

        update_post_meta($post_id, '_price', $price);

        return $post_id;
    }

    /**
     * Get an Ingredient by ID and return it as an Ingredient object.
     *
     * @param int $id
     * @return Ingredient|null
     */
    public function get(int $id): ?Ingredient
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return new Ingredient([
            'id'    => $post->ID,
            'name'  => $post->post_title,
            'price' => (float) get_post_meta($post->ID, '_price', true),
        ]);
    }

    /**
     * Get all published Ingredients.
     *
     * @return Ingredient[]
     */
    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $ingredients = [];

        foreach ($query->posts as $post) {
            $ingredient = $this->get((int) $post->ID);
            if ($ingredient) {
                $ingredients[] = $ingredient;
            }
        }

        return $ingredients;
    }
}
