<?php
declare(strict_types=1);

/**
 * Post Type Interface
 * 
 * Defines the contract that all post type classes must implement.
 * Ensures consistency across all post type registrations.
 */
interface PostTypeInterface
{
    /**
     * Get the post type slug/identifier
     */
    public static function getPostType(): string;

    /**
     * Register the post type with WordPress
     */
    public static function register(): void;

    /**
     * Get the post type labels configuration
     */
    public static function getLabels(): array;

    /**
     * Get the post type arguments for registration
     */
    public static function getArgs(): array;

    /**
     * Add custom meta boxes for this post type
     */
    public static function addMetaBoxes(): void;

    /**
     * Save custom fields when post is saved
     */
    public static function saveCustomFields(int $post_id): void;

    /**
     * Get supported features for this post type
     */
    public static function getSupports(): array;
}