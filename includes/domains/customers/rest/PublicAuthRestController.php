<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public Auth REST Controller
 *
 * Handles customer authentication via Google Sign-In and phone OTP.
 * All endpoints are public (no WordPress authentication required).
 */
class PublicAuthRestController extends PublicRestController
{
    private CustomerRepository $customer_repository;

    public function __construct()
    {
        $this->customer_repository = new CustomerRepository();
    }

    /**
     * Register auth routes
     */
    public function register_routes(): void
    {
        // Google login
        register_rest_route($this->namespace, '/auth/google', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'google_login'],
            'permission_callback' => function () {
                return $this->auth_rate_limit('google', 20, 60);
            },
            'args' => [
                'id_token' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Phone: send OTP code
        register_rest_route($this->namespace, '/auth/phone/send-code', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'send_phone_code'],
            'permission_callback' => function () {
                return $this->auth_rate_limit('otp_send', 5, 300);
            },
            'args' => [
                'phone' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Phone: verify OTP code
        register_rest_route($this->namespace, '/auth/phone/verify', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'verify_phone_code'],
            'permission_callback' => function () {
                return $this->auth_rate_limit('otp_verify', 10, 60);
            },
            'args' => [
                'phone' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'code' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'first_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'last_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        // Get current authenticated customer
        register_rest_route($this->namespace, '/auth/me', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_me'],
            'permission_callback' => [$this, 'public_permission_callback'],
        ]);

        // Logout
        register_rest_route($this->namespace, '/auth/logout', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'logout'],
            'permission_callback' => [$this, 'public_permission_callback'],
        ]);
    }

    /**
     * Per-endpoint rate limiting (avoids shared key collision)
     */
    private function auth_rate_limit(string $endpoint, int $max_requests, int $time_window)
    {
        $ip = $this->get_client_ip();
        $transient_key = 'squidly_rl_' . $endpoint . '_' . md5($ip);
        $current_count = get_transient($transient_key);

        if ($current_count === false) {
            set_transient($transient_key, 1, $time_window);
            return true;
        }

        if ($current_count >= $max_requests) {
            return new \WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                ['status' => 429]
            );
        }

        set_transient($transient_key, $current_count + 1, $time_window);
        return true;
    }

    /**
     * Google login endpoint
     */
    public function google_login(\WP_REST_Request $request): \WP_REST_Response
    {
        $id_token = $request->get_param('id_token');

        // Verify Google ID token
        $google_data = $this->verify_google_token($id_token);
        if (is_wp_error($google_data)) {
            return new \WP_REST_Response([
                'error' => $google_data->get_error_message(),
            ], 401);
        }

        $google_id = $google_data['sub'];
        $email = $google_data['email'] ?? '';
        $first_name = $google_data['given_name'] ?? '';
        $last_name = $google_data['family_name'] ?? '';

        try {
            // 1. Try to find by Google ID
            $customer = $this->customer_repository->findByGoogleId($google_id);

            if (!$customer && !empty($email)) {
                // 2. Try to find by email (may be a guest)
                $customer = $this->customer_repository->findByEmail($email);

                if ($customer && $customer->is_guest) {
                    // Convert guest to registered with Google
                    $this->customer_repository->convertGuestToRegistered(
                        $customer->id,
                        $email,
                        'google',
                        $google_id
                    );
                    $customer = $this->customer_repository->get($customer->id);
                } elseif ($customer && !$customer->is_guest) {
                    // Existing registered customer found by email — link Google ID
                    $this->customer_repository->update($customer->id, [
                        'google_id' => $google_id,
                    ]);
                    $customer = $this->customer_repository->get($customer->id);
                }
            }

            if (!$customer) {
                // 3. Create new customer
                $customer_id = $this->customer_repository->create([
                    'first_name' => $first_name ?: 'Google',
                    'last_name' => $last_name ?: 'User',
                    'phone' => $this->generate_placeholder_phone(),
                    'email' => $email,
                    'auth_provider' => 'google',
                    'google_id' => $google_id,
                    'is_guest' => false,
                ]);
                $customer = $this->customer_repository->get($customer_id);
            }

            if (!$customer) {
                return new \WP_REST_Response([
                    'error' => 'Failed to create or retrieve customer',
                ], 500);
            }

            $token = $this->generate_auth_token($customer->id);

            return new \WP_REST_Response([
                'token' => $token,
                'customer' => $this->prepare_customer_response($customer),
            ], 200);
        } catch (\Exception $e) {
            return $this->handle_error($e, 500);
        }
    }

    /**
     * Send phone verification code
     */
    public function send_phone_code(\WP_REST_Request $request): \WP_REST_Response
    {
        $phone = $request->get_param('phone');

        try {
            $normalized = $this->normalize_phone($phone);
        } catch (\InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => $e->getMessage(),
            ], 400);
        }

        // Generate 6-digit code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Store code as transient (5 min expiry)
        $transient_key = 'squidly_otp_' . md5($normalized);
        set_transient($transient_key, $code, 5 * MINUTE_IN_SECONDS);

        // Send code via configured provider
        $provider = get_option('squidly_sms_provider', 'mock');

        if ($provider === 'mock') {
            error_log("Squidly OTP for {$normalized}: {$code}");
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Verification code sent',
        ], 200);
    }

    /**
     * Verify phone OTP code
     */
    public function verify_phone_code(\WP_REST_Request $request): \WP_REST_Response
    {
        $phone = $request->get_param('phone');
        $code = $request->get_param('code');
        $first_name = $request->get_param('first_name');
        $last_name = $request->get_param('last_name');
        $email = $request->get_param('email');

        try {
            $normalized = $this->normalize_phone($phone);
        } catch (\InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => $e->getMessage(),
            ], 400);
        }

        // Validate code against transient
        $transient_key = 'squidly_otp_' . md5($normalized);
        $stored_code = get_transient($transient_key);

        if ($stored_code === false || $stored_code !== $code) {
            return new \WP_REST_Response([
                'error' => 'Invalid or expired verification code',
            ], 401);
        }

        // DO NOT delete the OTP yet — only delete after successful auth/creation

        try {
            $is_new_customer = false;

            // Look up customer by phone
            $customer = $this->customer_repository->findByPhone($normalized);

            if ($customer && !$customer->is_guest) {
                // Existing registered customer — just log them in
                $this->customer_repository->update($customer->id, [
                    'phone_verified_at' => date('Y-m-d H:i:s'),
                ]);
                $customer = $this->customer_repository->get($customer->id);

            } elseif ($customer && $customer->is_guest) {
                // Guest customer — need info to convert
                if (empty($first_name) || empty($last_name)) {
                    // Keep OTP alive, ask frontend for info
                    return new \WP_REST_Response([
                        'is_new_customer' => true,
                        'needs_info' => true,
                        'message' => 'Please provide your details to complete registration',
                    ], 200);
                }

                // Update name
                $this->customer_repository->update($customer->id, [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                ]);

                // Convert guest to registered
                $this->customer_repository->convertGuestToRegistered(
                    $customer->id,
                    $email ?: $customer->email ?: '',
                    'phone'
                );
                $customer = $this->customer_repository->get($customer->id);
                $is_new_customer = true;

            } else {
                // No customer found — need info to create
                if (empty($first_name) || empty($last_name)) {
                    // Keep OTP alive, ask frontend for info
                    return new \WP_REST_Response([
                        'is_new_customer' => true,
                        'needs_info' => true,
                        'message' => 'Please provide your details to complete registration',
                    ], 200);
                }

                // Create new customer
                $customer_id = $this->customer_repository->create([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => $normalized,
                    'email' => $email ?: '',
                    'auth_provider' => 'phone',
                    'phone_verified_at' => date('Y-m-d H:i:s'),
                    'is_guest' => false,
                ]);
                $customer = $this->customer_repository->get($customer_id);
                $is_new_customer = true;
            }

            if (!$customer) {
                return new \WP_REST_Response([
                    'error' => 'Failed to create or retrieve customer',
                ], 500);
            }

            // NOW delete the OTP — auth succeeded
            delete_transient($transient_key);

            $token = $this->generate_auth_token($customer->id);

            return new \WP_REST_Response([
                'token' => $token,
                'customer' => $this->prepare_customer_response($customer),
                'is_new_customer' => $is_new_customer,
            ], 200);
        } catch (\Exception $e) {
            return $this->handle_error($e, 500);
        }
    }

    /**
     * Get current authenticated customer
     */
    public function get_me(\WP_REST_Request $request): \WP_REST_Response
    {
        $customer_id = $this->authenticate_request($request);

        if (!$customer_id) {
            return new \WP_REST_Response([
                'error' => 'Not authenticated',
            ], 401);
        }

        $customer = $this->customer_repository->get($customer_id);

        if (!$customer) {
            return new \WP_REST_Response([
                'error' => 'Customer not found',
            ], 404);
        }

        return new \WP_REST_Response([
            'customer' => $this->prepare_customer_response($customer),
        ], 200);
    }

    /**
     * Logout — delete auth token
     */
    public function logout(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $this->extract_bearer_token($request);

        if ($token) {
            $transient_key = 'squidly_auth_' . hash('sha256', $token);
            delete_transient($transient_key);
        }

        return new \WP_REST_Response([
            'success' => true,
        ], 200);
    }

    // ===== Helper Methods =====

    /**
     * Authenticate request from Authorization header
     */
    private function authenticate_request(\WP_REST_Request $request): ?int
    {
        $token = $this->extract_bearer_token($request);

        if (!$token) {
            return null;
        }

        $transient_key = 'squidly_auth_' . hash('sha256', $token);
        $customer_id = get_transient($transient_key);

        if ($customer_id === false) {
            return null;
        }

        return (int) $customer_id;
    }

    /**
     * Extract Bearer token from Authorization header
     */
    private function extract_bearer_token(\WP_REST_Request $request): ?string
    {
        $auth_header = $request->get_header('Authorization');

        if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
            return null;
        }

        return trim(substr($auth_header, 7));
    }

    /**
     * Generate and store auth token
     */
    private function generate_auth_token(int $customer_id): string
    {
        $token = wp_generate_password(64, false);
        $transient_key = 'squidly_auth_' . hash('sha256', $token);
        set_transient($transient_key, $customer_id, 7 * DAY_IN_SECONDS);

        return $token;
    }

    /**
     * Verify Google ID token via Google's tokeninfo endpoint
     */
    private function verify_google_token(string $id_token)
    {
        $google_client_id = get_option('squidly_google_client_id', '');

        $response = wp_remote_get(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token),
            ['timeout' => 10]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('google_verify_failed', 'Failed to verify Google token: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || isset($body['error'])) {
            return new \WP_Error('google_token_invalid', 'Invalid Google token');
        }

        if (!empty($google_client_id) && ($body['aud'] ?? '') !== $google_client_id) {
            return new \WP_Error('google_audience_mismatch', 'Google token audience does not match');
        }

        if (isset($body['exp']) && (int) $body['exp'] < time()) {
            return new \WP_Error('google_token_expired', 'Google token has expired');
        }

        return $body;
    }

    /**
     * Normalize an Israeli phone number to +972 format
     */
    private function normalize_phone(string $phone): string
    {
        $phone = trim($phone);

        if (empty($phone)) {
            throw new \InvalidArgumentException('Phone number cannot be empty');
        }

        $clean = preg_replace('/[^\d+]/', '', $phone);

        if (empty($clean)) {
            throw new \InvalidArgumentException('Phone number must contain digits');
        }

        if (str_starts_with($clean, '+972')) {
            if (strlen($clean) !== 13) {
                throw new \InvalidArgumentException('Israeli phone number with +972 must be 13 digits total');
            }
        } elseif (str_starts_with($clean, '0')) {
            if (strlen($clean) !== 10) {
                throw new \InvalidArgumentException('Israeli phone number starting with 0 must be 10 digits');
            }
            $clean = '+972' . substr($clean, 1);
        } else {
            throw new \InvalidArgumentException('Phone number must start with +972 or 0 for Israeli numbers');
        }

        return $clean;
    }

    /**
     * Prepare customer data for API response (safe fields only)
     */
    private function prepare_customer_response(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'auth_provider' => $customer->auth_provider,
            'is_guest' => $customer->is_guest,
            'addresses' => array_map(fn(Address $addr) => $addr->toArray(), $customer->addresses),
            'total_orders' => $customer->total_orders,
            'loyalty_points_balance' => $customer->loyalty_points_balance,
        ];
    }

    /**
     * Generate a placeholder phone for Google-only auth
     */
    private function generate_placeholder_phone(): string
    {
        $random = str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        return '+97200' . $random;
    }
}
