<?php
declare(strict_types=1);

/**
 * Admin API Bootstrap
 * 
 * Registers all REST API controllers for the admin interface
 */
class AdminApiBootstrap
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('rest_api_init', [self::class, 'setup_cors']);
    }

    public static function register_routes(): void
    {
        // Product Groups API
        $product_groups_controller = new ProductGroupRestController();
        $product_groups_controller->register_routes();

        // Ingredients API
        $ingredients_controller = new IngredientRestController();
        $ingredients_controller->register_routes();

        // Ingredient Groups API  
        $ingredient_groups_controller = new IngredientGroupRestController();
        $ingredient_groups_controller->register_routes();

        // Store Branches API
        $branches_controller = new StoreBranchRestController();
        $branches_controller->register_routes();

        // Auth check endpoint for admin
        register_rest_route('squidly/v1', '/auth/check', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'check_auth'],
            'permission_callback' => '__return_true', // Allow unauthenticated to check
        ]);

        // Admin config endpoint
        register_rest_route('squidly/v1', '/admin/config', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_admin_config'],
            'permission_callback' => [self::class, 'admin_permissions_check'],
        ]);
    }

    public static function setup_cors(): void
    {
        // Allow CORS for admin app (if needed for development)
        add_filter('rest_pre_serve_request', function($served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server) {
            $origin = get_http_origin();
            
            // In development, allow localhost origins
            if (WP_DEBUG && $origin && preg_match('/^https?:\/\/localhost(:\d+)?$/', $origin)) {
                $result->header('Access-Control-Allow-Origin', $origin);
                $result->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $result->header('Access-Control-Allow-Headers', 'X-WP-Nonce, Content-Type, Authorization');
                $result->header('Access-Control-Allow-Credentials', 'true');
            }
            
            return $served;
        }, 10, 4);
    }

    /**
     * Check if user is authenticated and has admin permissions
     */
    public static function check_auth($request)
    {
        $user = wp_get_current_user();
        
        if (!$user || !$user->exists()) {
            return new \WP_REST_Response([
                'authenticated' => false,
                'message' => 'Not authenticated'
            ], 401);
        }

        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response([
                'authenticated' => true,
                'authorized' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        return new \WP_REST_Response([
            'authenticated' => true,
            'authorized' => true,
            'user' => [
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
            ]
        ], 200);
    }

    /**
     * Get admin configuration
     */
    public static function get_admin_config($request)
    {
        return new \WP_REST_Response([
            'theme' => [
                'primary_color' => '#D12525',
                'secondary_color' => '#F2F2F2',
                'success_color' => '#10B981',
                'danger_color' => '#EF4444',
            ],
            'api' => [
                'base_url' => rest_url('squidly/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
            'strings' => [
                'all_branches' => 'כל הסניפים',
                'groups' => 'קבוצות',
                'ingredients' => 'מרכיבים',
                'products' => 'מוצרים',
                'product_groups' => 'קבוצות מוצרים',
                'ingredient_groups' => 'קבוצות מרכיבים',
                'group_name' => 'שם הקבוצה',
                'group_status' => 'סטטוס הקבוצה',
                'active' => 'פעילה',
                'inactive' => 'לא פעילה',
                'add' => 'הוסף',
                'edit' => 'ערוך',
                'delete' => 'מחק',
                'loading' => 'טוען...',
                'error' => 'שגיאה',
                'success' => 'הצלחה',
                'confirm_delete' => 'האם אתה בטוח שברצונך למחוק?',
            ],
            'features' => [
                'rtl_support' => true,
                'dark_mode' => false,
            ]
        ], 200);
    }

    /**
     * Admin permission check
     */
    public static function admin_permissions_check($request)
    {
        return current_user_can('manage_options');
    }
}