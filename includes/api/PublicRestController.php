<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Public REST Controller Base Class
 *
 * Base class for all public-facing REST API endpoints
 * No authentication required - accessible to all users
 */
abstract class PublicRestController extends WP_REST_Controller
{
    /**
     * API namespace for public endpoints
     */
    protected $namespace = 'squidly/v1/public';

    /**
     * Register routes for this controller
     * Must be implemented by child classes
     */
    abstract public function register_routes();

    /**
     * Public permission callback - allows all requests
     *
     * @return bool Always returns true for public access
     */
    public function public_permission_callback(): bool
    {
        return true;
    }

    /**
     * Rate limiting check
     * Simple rate limiting based on IP address
     *
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     * @return bool True if within rate limit
     */
    protected function check_rate_limit(int $max_requests = 100, int $time_window = 60): bool
    {
        $ip = $this->get_client_ip();
        $transient_key = 'squidly_rate_limit_' . md5($ip);

        $current_count = get_transient($transient_key);

        if ($current_count === false) {
            // First request in time window
            set_transient($transient_key, 1, $time_window);
            return true;
        }

        if ($current_count >= $max_requests) {
            return false; // Rate limit exceeded
        }

        // Increment counter
        set_transient($transient_key, $current_count + 1, $time_window);
        return true;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    protected function get_client_ip(): string
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return sanitize_text_field($ip);
    }

    /**
     * Permission callback with rate limiting
     *
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     * @return WP_Error|bool True if allowed, WP_Error if rate limited
     */
    protected function public_permission_with_rate_limit(int $max_requests = 100, int $time_window = 60)
    {
        if (!$this->check_rate_limit($max_requests, $time_window)) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                ['status' => 429]
            );
        }

        return true;
    }

    /**
     * Prepare response for collection (list of items)
     *
     * @param array $items Array of items
     * @param int $total Total count
     * @param int $per_page Items per page
     * @param int $offset Current offset
     * @return WP_REST_Response
     */
    protected function prepare_collection_response(array $items, int $total, int $per_page, int $offset): WP_REST_Response
    {
        $response = new WP_REST_Response($items, 200);

        // Add pagination headers
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));

        return $response;
    }

    /**
     * Sanitize and validate pagination parameters
     *
     * @param WP_REST_Request $request
     * @return array [per_page, offset]
     */
    protected function get_pagination_params(WP_REST_Request $request): array
    {
        $per_page = $request->get_param('per_page') ?: 20;
        $per_page = min(max(1, (int) $per_page), 100); // Limit between 1-100

        $offset = $request->get_param('offset') ?: 0;
        $offset = max(0, (int) $offset);

        return [$per_page, $offset];
    }

    /**
     * Handle errors and return appropriate response
     *
     * @param Exception $e The exception
     * @param int $status_code HTTP status code
     * @return WP_REST_Response
     */
    protected function handle_error(Exception $e, int $status_code = 500): WP_REST_Response
    {
        return new WP_REST_Response([
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ], $status_code);
    }

    /**
     * Validate required parameters
     *
     * @param WP_REST_Request $request
     * @param array $required_params Array of required parameter names
     * @return WP_Error|bool True if valid, WP_Error if invalid
     */
    protected function validate_required_params(WP_REST_Request $request, array $required_params)
    {
        foreach ($required_params as $param) {
            if (!$request->has_param($param) || empty($request->get_param($param))) {
                return new WP_Error(
                    'missing_parameter',
                    sprintf('Missing required parameter: %s', $param),
                    ['status' => 400]
                );
            }
        }

        return true;
    }

    /**
     * Sanitize text input
     *
     * @param mixed $value
     * @return string
     */
    protected function sanitize_text($value): string
    {
        return sanitize_text_field((string) $value);
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $value
     * @return int
     */
    protected function sanitize_int($value): int
    {
        return (int) $value;
    }

    /**
     * Sanitize float input
     *
     * @param mixed $value
     * @return float
     */
    protected function sanitize_float($value): float
    {
        return (float) $value;
    }

    /**
     * Sanitize boolean input
     *
     * @param mixed $value
     * @return bool
     */
    protected function sanitize_bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
