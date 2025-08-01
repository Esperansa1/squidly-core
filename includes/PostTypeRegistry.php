<?php

require_once __DIR__ . '/post-types/StoreBranchPostType.php';
require_once __DIR__ . '/post-types/ProductPostType.php';
require_once __DIR__ . '/post-types/ProductGroupPostType.php';
require_once __DIR__ . '/post-types/IngredientPostType.php';
require_once __DIR__ . '/post-types/GroupItemPostType.php';

class PostTypeRegistry {
    public static function register_all(): void {
        StoreBranchPostType::register();
        ProductPostType::register();
        ProductGroupPostType::register();
        IngredientPostType::register();
        GroupItemPostType::register();
    }
}
