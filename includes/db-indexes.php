<?php
/**
 * Database Index Management
 *
 * Ensures optimal database indexes exist for plugin performance.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if an index exists on a table
 *
 * @param string $table_name Table name
 * @param string $index_name Index name
 * @return bool True if index exists, false otherwise
 */
function squidly_index_exists(string $table_name, string $index_name): bool {
    global $wpdb;

    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = %s
        AND TABLE_NAME = %s
        AND INDEX_NAME = %s",
        DB_NAME,
        $table_name,
        $index_name
    ));

    return (bool) $result;
}

/**
 * Verify and create database indexes for optimal performance
 */
function squidly_verify_database_indexes(): void {
    global $wpdb;

    // Index for wp_postmeta lookups (post_id + meta_key)
    if (!squidly_index_exists($wpdb->postmeta, 'squidly_meta_lookup')) {
        $wpdb->query(
            "CREATE INDEX squidly_meta_lookup
            ON {$wpdb->postmeta} (post_id, meta_key(191))"
        );
    }

    // Index for wp_posts by type, status, and date
    if (!squidly_index_exists($wpdb->posts, 'squidly_type_status_date')) {
        $wpdb->query(
            "CREATE INDEX squidly_type_status_date
            ON {$wpdb->posts} (post_type, post_status, post_date)"
        );
    }

    // Index for wp_postmeta meta_value lookups (for customer phone/email searches)
    if (!squidly_index_exists($wpdb->postmeta, 'squidly_meta_value_lookup')) {
        $wpdb->query(
            "CREATE INDEX squidly_meta_value_lookup
            ON {$wpdb->postmeta} (meta_key(191), meta_value(191))"
        );
    }
}

/**
 * Check if indexes are healthy and provide recommendations
 *
 * @return array Status information about indexes
 */
function squidly_check_index_health(): array {
    global $wpdb;

    $health = [
        'postmeta_lookup' => squidly_index_exists($wpdb->postmeta, 'squidly_meta_lookup'),
        'posts_type_status' => squidly_index_exists($wpdb->posts, 'squidly_type_status_date'),
        'postmeta_value_lookup' => squidly_index_exists($wpdb->postmeta, 'squidly_meta_value_lookup'),
    ];

    $health['all_healthy'] = !in_array(false, $health, true);

    return $health;
}
