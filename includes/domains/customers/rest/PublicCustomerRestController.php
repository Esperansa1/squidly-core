<?php
declare(strict_types=1);

/**
 * Public Customer REST API Controller
 *
 * Handles public guest customer creation for the customer app (no authentication required)
 * Validates input, prevents duplicate guests, applies rate limiting
 *
 * Security:
 * - Rate limiting
 * - Input validation and sanitization
 * - Duplicate phone number detection
 */
class PublicCustomerRestController extends WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'public/guest-customer';

    private CustomerRepository $customerRepo;

    // Rate limiting (simple IP-based) - Higher limits for busy checkout periods
    private const RATE_LIMIT_REQUESTS = 50; // 50 guest customer creations per 5 minutes
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes

    public function __construct()
    {
        $this->customerRepo = new CustomerRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // POST /squidly/v1/public/guest-customer
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_guest_customer'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_create_customer_args(),
            ],
        ]);
    }

    /**
     * Create a new guest customer (public endpoint)
     */
    public function create_guest_customer($request)
    {
        // Rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again in a moment.'
            ], 429);
        }

        try {
            // Get request data
            $data = $request->get_json_params();

            // Validate required fields
            $this->validate_customer_data($data);

            // Sanitize input
            $first_name = sanitize_text_field($data['first_name']);
            $last_name = sanitize_text_field($data['last_name']);
            $phone = sanitize_text_field($data['phone']);

            // Sanitize email: trim, remove trailing commas/semicolons, then validate
            $email_raw = isset($data['email']) ? trim($data['email']) : '';
            $email_raw = rtrim($email_raw, ',;'); // Remove trailing commas or semicolons
            $email = !empty($email_raw) ? sanitize_email($email_raw) : null;

            // Normalize phone number for duplicate checking (0501234567 -> +972501234567)
            $normalized_phone = $this->normalize_phone($phone);

            // Check for existing guest with same phone
            $existing_customer = $this->customerRepo->findByPhone($normalized_phone);
            if ($existing_customer && $existing_customer->is_guest) {
                // Update customer info if it has changed
                $updates = [];

                if ($email && $email !== $existing_customer->email) {
                    $updates['email'] = $email;
                }

                if ($first_name !== $existing_customer->first_name) {
                    $updates['first_name'] = $first_name;
                }

                if ($last_name !== $existing_customer->last_name) {
                    $updates['last_name'] = $last_name;
                }

                // Apply updates if any
                if (!empty($updates)) {
                    $this->customerRepo->update($existing_customer->id, $updates);
                }

                // Return existing guest customer ID
                return new WP_REST_Response([
                    'customer_id' => $existing_customer->id,
                    'message' => 'Guest customer already exists',
                    'existing' => true,
                ], 200);
            }

            // If non-guest customer exists with this phone, reject
            if ($existing_customer && !$existing_customer->is_guest) {
                return new WP_REST_Response([
                    'error' => 'A registered customer already exists with this phone number. Please log in.',
                    'message' => 'A registered customer already exists with this phone number. Please log in.',
                    'code' => 'customer_exists',
                ], 400);
            }

            // Create guest customer
            $customer_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'email' => $email,
                'auth_provider' => 'phone',
                'is_guest' => true,
                'is_active' => true,
            ];

            $customer_id = $this->customerRepo->create($customer_data);

            return new WP_REST_Response([
                'customer_id' => $customer_id,
                'message' => 'Guest customer created successfully',
                'existing' => false,
            ], 201);

        } catch (InvalidArgumentException $e) {
            // Validation error
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            // Log error for debugging
            error_log("Guest customer creation error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to create guest customer',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Validate customer data
     */
    private function validate_customer_data(array $data): void
    {
        $required_fields = ['first_name', 'last_name', 'phone'];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim((string) $data[$field]))) {
                throw new InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }

        // Validate email if provided (trim and remove trailing commas first)
        if (isset($data['email']) && !empty($data['email'])) {
            $email_check = trim($data['email']);
            $email_check = rtrim($email_check, ',;'); // Remove trailing commas/semicolons
            if (!empty($email_check) && !filter_var($email_check, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email format');
            }
        }

        // Validate phone format (will be validated by CustomerRepository too, but check early)
        // Strip non-digit chars (except +) before validation to handle dashes/spaces
        $phone = preg_replace('/[^\d+]/', '', trim((string) $data['phone']));
        if (!preg_match('/^(\+972|0)[2-9]\d{7,8}$/', $phone)) {
            throw new InvalidArgumentException('Phone number must be a valid Israeli phone number');
        }

        // Validate name length
        if (strlen($data['first_name']) < 2 || strlen($data['first_name']) > 50) {
            throw new InvalidArgumentException('First name must be between 2 and 50 characters');
        }

        if (strlen($data['last_name']) < 2 || strlen($data['last_name']) > 50) {
            throw new InvalidArgumentException('Last name must be between 2 and 50 characters');
        }
    }

    /**
     * Simple rate limiting check
     */
    private function check_rate_limit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'squidly_guest_rate_' . md5($ip);

        $requests = get_transient($transient_key);
        if ($requests === false) {
            // First request in this window
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }

        if ($requests >= self::RATE_LIMIT_REQUESTS) {
            return false; // Rate limit exceeded
        }

        // Increment request count
        set_transient($transient_key, $requests + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * Normalize Israeli phone number to +972 format
     */
    private function normalize_phone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone));

        // Convert 0XX to +972XX format
        if (substr($phone, 0, 1) === '0') {
            $phone = '+972' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Public permission callback (no authentication required)
     */
    public function public_permission_callback($request): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get arguments for create customer endpoint
     */
    private function get_create_customer_args(): array
    {
        return [
            'first_name' => [
                'description'       => 'Customer first name',
                'type'              => 'string',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_string($param) && strlen(trim($param)) >= 2 && strlen(trim($param)) <= 50;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'description'       => 'Customer last name',
                'type'              => 'string',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_string($param) && strlen(trim($param)) >= 2 && strlen(trim($param)) <= 50;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone' => [
                'description'       => 'Customer phone number (Israeli format)',
                'type'              => 'string',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_string($param) && preg_match('/^(\+972|0)[2-9]\d{7,8}$/', trim($param));
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'description'       => 'Customer email address (optional)',
                'type'              => 'string',
                'required'          => false,
                'default'           => '',
                'validate_callback' => function($param, $request, $key) {
                    // Allow null, empty string, or missing param (optional field)
                    if (is_null($param) || $param === '' || !isset($param)) {
                        return true;
                    }
                    // Clean the email before validation
                    $cleaned = trim($param);
                    $cleaned = rtrim($cleaned, ',;'); // Remove trailing punctuation
                    // If empty after cleaning, it's valid (optional field)
                    if (empty($cleaned)) {
                        return true;
                    }
                    // If provided, must be valid email
                    return filter_var($cleaned, FILTER_VALIDATE_EMAIL) !== false;
                },
                'sanitize_callback' => function($param) {
                    // Return null for empty values
                    if (empty($param)) {
                        return null;
                    }
                    // Trim and remove trailing commas/semicolons before sanitizing
                    $cleaned = trim($param);
                    $cleaned = rtrim($cleaned, ',;');
                    return !empty($cleaned) ? sanitize_email($cleaned) : null;
                },
            ],
        ];
    }
}
