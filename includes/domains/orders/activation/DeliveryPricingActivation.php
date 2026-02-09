<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Pricing Activation
 * Creates and manages database tables for delivery pricing features
 */
class DeliveryPricingActivation
{
    /**
     * Create delivery pricing tables
     */
    public static function createTables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table: delivery_tiers (Order-value based pricing)
        $table_tiers = $wpdb->prefix . 'squidly_delivery_tiers';
        $sql_tiers = "CREATE TABLE IF NOT EXISTS $table_tiers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            branch_id bigint(20) unsigned NOT NULL,
            min_order_value decimal(10,2) NOT NULL DEFAULT 0.00,
            delivery_fee decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY branch_id (branch_id),
            KEY min_order_value (min_order_value)
        ) $charset_collate;";

        // Table: delivery_time_surcharges (Time-based pricing)
        $table_time = $wpdb->prefix . 'squidly_delivery_time_surcharges';
        $sql_time = "CREATE TABLE IF NOT EXISTS $table_time (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            branch_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
            start_time time NOT NULL,
            end_time time NOT NULL,
            surcharge_amount decimal(10,2) NOT NULL,
            surcharge_type varchar(20) NOT NULL DEFAULT 'fixed' COMMENT 'fixed or percentage',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY branch_id (branch_id),
            KEY day_of_week (day_of_week),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Table: delivery_loyalty_discounts (Loyalty-based discounts)
        $table_loyalty = $wpdb->prefix . 'squidly_delivery_loyalty_discounts';
        $sql_loyalty = "CREATE TABLE IF NOT EXISTS $table_loyalty (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            min_loyalty_points int(11) NOT NULL DEFAULT 0,
            discount_amount decimal(10,2) NOT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'fixed' COMMENT 'fixed or percentage',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY min_loyalty_points (min_loyalty_points),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tiers);
        dbDelta($sql_time);
        dbDelta($sql_loyalty);

        // Store schema version
        update_option('squidly_delivery_pricing_schema_version', '1.0.0');
    }

    /**
     * Drop delivery pricing tables (cleanup on plugin removal)
     */
    public static function dropTables(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'squidly_delivery_tiers',
            $wpdb->prefix . 'squidly_delivery_time_surcharges',
            $wpdb->prefix . 'squidly_delivery_loyalty_discounts',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('squidly_delivery_pricing_schema_version');
    }

    /**
     * Seed default data for testing
     */
    public static function seedDefaultData(): void
    {
        global $wpdb;

        // Get first branch ID for default data
        $branch_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'store_branch' AND post_status = 'publish' LIMIT 1");

        if (!$branch_id) {
            return; // No branches to seed data for
        }

        $table_tiers = $wpdb->prefix . 'squidly_delivery_tiers';
        $table_time = $wpdb->prefix . 'squidly_delivery_time_surcharges';
        $table_loyalty = $wpdb->prefix . 'squidly_delivery_loyalty_discounts';

        // Seed delivery tiers (order-value based)
        $wpdb->insert($table_tiers, [
            'branch_id' => $branch_id,
            'min_order_value' => 0,
            'delivery_fee' => 25.00,
        ]);

        $wpdb->insert($table_tiers, [
            'branch_id' => $branch_id,
            'min_order_value' => 50,
            'delivery_fee' => 15.00,
        ]);

        $wpdb->insert($table_tiers, [
            'branch_id' => $branch_id,
            'min_order_value' => 100,
            'delivery_fee' => 0.00,
        ]);

        // Seed time surcharges (peak hours: Fri/Sat evenings)
        $wpdb->insert($table_time, [
            'branch_id' => $branch_id,
            'day_of_week' => 5, // Friday
            'start_time' => '18:00:00',
            'end_time' => '23:00:00',
            'surcharge_amount' => 10.00,
            'surcharge_type' => 'fixed',
            'is_active' => 1,
        ]);

        $wpdb->insert($table_time, [
            'branch_id' => $branch_id,
            'day_of_week' => 6, // Saturday
            'start_time' => '18:00:00',
            'end_time' => '23:00:00',
            'surcharge_amount' => 10.00,
            'surcharge_type' => 'fixed',
            'is_active' => 1,
        ]);

        // Seed loyalty discounts
        $wpdb->insert($table_loyalty, [
            'min_loyalty_points' => 100,
            'discount_amount' => 5.00,
            'discount_type' => 'fixed',
            'is_active' => 1,
        ]);

        $wpdb->insert($table_loyalty, [
            'min_loyalty_points' => 500,
            'discount_amount' => 15.00,
            'discount_type' => 'fixed',
            'is_active' => 1,
        ]);

        $wpdb->insert($table_loyalty, [
            'min_loyalty_points' => 1000,
            'discount_amount' => 100.00,
            'discount_type' => 'percentage',
            'is_active' => 1,
        ]);
    }
}
