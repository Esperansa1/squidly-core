<?php
/**
 * Role Manager
 *
 * Manages custom WordPress roles for restaurant staff.
 *
 * @package SquidlyCore
 * @since 1.0.0
 */

namespace SquidlyCore\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class RoleManager {
    /**
     * Initialize role manager
     */
    public static function init() {
        add_action('init', [self::class, 'register_roles']);
    }

    /**
     * Register custom WordPress roles
     */
    public static function register_roles() {
        // Restaurant Manager role
        if (!get_role('restaurant_manager')) {
            add_role('restaurant_manager', 'Restaurant Manager', [
                'read' => true,
                'manage_restaurant' => true,
                'view_orders' => true,
                'manage_orders' => true,
                'view_customers' => true,
                'manage_customers' => true,
                'view_products' => true,
                'manage_products' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ]);
        }

        // Restaurant Staff role
        if (!get_role('restaurant_staff')) {
            add_role('restaurant_staff', 'Restaurant Staff', [
                'read' => true,
                'view_orders' => true,
                'manage_orders' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ]);
        }

        // Add restaurant capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_restaurant');
            $admin_role->add_cap('view_orders');
            $admin_role->add_cap('manage_orders');
            $admin_role->add_cap('view_customers');
            $admin_role->add_cap('manage_customers');
            $admin_role->add_cap('view_products');
            $admin_role->add_cap('manage_products');
        }
    }

    /**
     * Get allowed restaurant roles
     *
     * @return array
     */
    public static function get_allowed_roles() {
        return [
            'administrator' => 'Administrator',
            'restaurant_manager' => 'Restaurant Manager',
            'restaurant_staff' => 'Restaurant Staff',
        ];
    }

    /**
     * Check if role is a restaurant role
     *
     * @param string $role
     * @return bool
     */
    public static function is_restaurant_role($role) {
        return array_key_exists($role, self::get_allowed_roles());
    }
}
