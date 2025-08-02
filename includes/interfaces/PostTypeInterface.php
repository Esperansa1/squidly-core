<?php
declare(strict_types=1);

/**
 * Guarantees that every PostType class exposes a static register() hook.
 */
interface PostTypeInterface
{
    /**
     * Hook your post-type registration here.
     */
    public static function register(): void;
}