<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Public API Bootstrap
 *
 * Registers all public REST API controllers (no authentication required).
 * These endpoints are intended for the customer-facing frontend application.
 */
class PublicApiBootstrap
{
    /**
     * Initialize public API registration.
     */
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes'], 5);
        add_action('rest_api_init', [self::class, 'setup_cors'], 5);
    }

    /**
     * Register all public API routes.
     */
    public static function register_routes(): void
    {
        // Public Branches API
        $branches_controller = new PublicBranchRestController();
        $branches_controller->register_routes();

        // Public Products API
        $products_controller = new PublicProductRestController();
        $products_controller->register_routes();

        // Public config endpoint for customer app
        register_rest_route('squidly/v1/public', '/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_public_config'],
            'permission_callback' => '__return_true', // Public access
        ]);
    }

    /**
     * Setup CORS headers for public API.
     */
    public static function setup_cors(): void
    {
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            // Only apply to public API endpoints
            if (strpos($request->get_route(), '/squidly/v1/public') !== 0) {
                return $served;
            }

            $origin = get_http_origin();

            // In development, allow localhost origins
            if (WP_DEBUG && $origin && preg_match('/^https?:\/\/localhost(:\d+)?$/', $origin)) {
                $result->header('Access-Control-Allow-Origin', $origin);
                $result->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
                $result->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                $result->header('Access-Control-Allow-Credentials', 'true');
            }

            return $served;
        }, 10, 4);
    }

    /**
     * Get public configuration for customer app.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response with public configuration
     */
    public static function get_public_config($request): WP_REST_Response
    {
        $config = [
            'api' => [
                'base_url' => rest_url('squidly/v1/public/'),
                'admin_base_url' => rest_url('squidly/v1/'),
            ],
            'currency' => [
                'code' => get_option('squidly_currency', 'ILS'),
                'symbol' => get_option('squidly_currency_symbol', '₪'),
            ],
            'features' => [
                'guest_checkout' => (bool) get_option('squidly_allow_guest_checkout', true),
                'online_ordering' => (bool) get_option('squidly_enable_online_ordering', true),
            ],
            'theme' => [
                'primary_color' => '#D12525',
                'secondary_color' => '#F2F2F2',
                'success_color' => '#10B981',
                'warning_color' => '#F59E0B',
                'danger_color' => '#EF4444',
            ],
            'strings' => [
                // English strings (can be extended for i18n)
                'branches' => 'Branches',
                'products' => 'Products',
                'cart' => 'Cart',
                'checkout' => 'Checkout',
                'order_now' => 'Order Now',
                'loading' => 'Loading...',
                'error' => 'Error',
                'success' => 'Success',
            ],
        ];

        return new WP_REST_Response($config, 200);
    }
}
