<?php
declare(strict_types=1);

/**
 * Customer REST API Controller
 *
 * Handles REST API endpoints for customer management
 * Provides full CRUD operations with proper authentication and validation
 */
class CustomerRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'customers';

    private CustomerRepository $repository;

    public function __construct()
    {
        $this->repository = new CustomerRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // GET /squidly/v1/customers - List customers
        // POST /squidly/v1/customers - Create customer
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::CREATABLE),
            ],
        ]);

        // GET/PUT/DELETE /squidly/v1/customers/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Customer ID', 'type' => 'integer']],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE),
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
                'args' => ['id' => ['description' => 'Customer ID', 'type' => 'integer']],
            ],
        ]);

        // GET /squidly/v1/customers/search?q={query}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'search_customers'],
            'permission_callback' => [$this, 'get_items_permissions_check'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Search query for customer name, email, or phone',
                    'validate_callback' => function($param) {
                        return !empty(trim($param)) && strlen(trim($param)) >= 2;
                    }
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ]
            ]
        ]);

        // PUT /squidly/v1/customers/{id}/loyalty-points
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/loyalty-points', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_loyalty_points'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
            'args' => [
                'id' => ['description' => 'Customer ID', 'type' => 'integer'],
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['add', 'use'],
                    'description' => 'Action to perform: add or use points'
                ],
                'points' => [
                    'required' => true,
                    'type' => 'number',
                    'minimum' => 0.01,
                    'description' => 'Number of points to add or use'
                ]
            ]
        ]);

        // PUT /squidly/v1/customers/{id}/staff-labels
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/staff-labels', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_staff_labels'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
            'args' => [
                'id' => ['description' => 'Customer ID', 'type' => 'integer'],
                'label' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Staff label to add to customer'
                ]
            ]
        ]);

        // POST /squidly/v1/customers/{id}/convert-guest
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/convert-guest', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'convert_guest_customer'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
            'args' => [
                'id' => ['description' => 'Customer ID', 'type' => 'integer'],
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'description' => 'Email address for the registered account'
                ],
                'auth_provider' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['google', 'phone'],
                    'description' => 'Authentication provider'
                ],
                'google_id' => [
                    'type' => 'string',
                    'description' => 'Google ID if auth_provider is google'
                ]
            ]
        ]);

        // GET /squidly/v1/customers/statistics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/statistics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_statistics'],
            'permission_callback' => [$this, 'get_statistics_permissions_check'],
        ]);
    }

    /**
     * Get all customers
     */
    public function get_items($request)
    {
        try {
            $filters = [];

            // Handle filtering parameters
            if (!empty($request['auth_provider'])) {
                $filters['auth_provider'] = sanitize_text_field($request['auth_provider']);
            }

            if (isset($request['is_guest'])) {
                $filters['is_guest'] = (bool) $request['is_guest'];
            }

            if (isset($request['is_active'])) {
                $filters['is_active'] = (bool) $request['is_active'];
            }

            if (!empty($request['min_loyalty_points'])) {
                $filters['min_loyalty_points'] = (float) $request['min_loyalty_points'];
            }

            if (!empty($request['min_total_spent'])) {
                $filters['min_total_spent'] = (float) $request['min_total_spent'];
            }

            $limit = isset($request['per_page']) ? (int) $request['per_page'] : null;
            $offset = isset($request['offset']) ? (int) $request['offset'] : 0;

            // Get customers with pagination
            $customers = empty($filters)
                ? $this->repository->findBy([], $limit, $offset)  // Use findBy for pagination support
                : $this->repository->findBy($filters, $limit, $offset);

            $data = array_map(function($customer) use ($request) {
                return $this->prepare_item_for_response($customer, $request)->get_data();
            }, $customers);

            // Get total count for pagination headers
            $total_customers = $this->repository->countBy(!empty($filters) ? $filters : []);
            $response = rest_ensure_response($data);
            $response->header('X-WP-Total', (string) $total_customers);
            $response->header('X-WP-TotalPages', (string) ceil($total_customers / ($limit ?? 10)));

            return $response;

        } catch (Exception $e) {
            error_log("CustomerRestController get_items error: " . $e->getMessage());
            return new \WP_REST_Response([
                'error' => 'Failed to fetch customers',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single customer
     */
    public function get_item($request)
    {
        try {
            $id = (int)$request['id'];
            $customer = $this->repository->get($id);

            if (!$customer) {
                return new \WP_REST_Response([
                    'error' => 'Customer not found'
                ], 404);
            }

            return $this->prepare_item_for_response($customer, $request);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch customer',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new customer
     */
    public function create_item($request)
    {
        try {
            $data = [
                'first_name' => sanitize_text_field($request['first_name']),
                'last_name' => sanitize_text_field($request['last_name']),
                'phone' => sanitize_text_field($request['phone']),
                'auth_provider' => sanitize_text_field($request['auth_provider']),
                'email' => isset($request['email']) ? sanitize_email($request['email']) : '',
                'google_id' => isset($request['google_id']) ? sanitize_text_field($request['google_id']) : '',
                'addresses' => $request['addresses'] ?? [],
                'allow_sms_notifications' => isset($request['allow_sms_notifications']) ? (bool) $request['allow_sms_notifications'] : false,
                'allow_email_notifications' => isset($request['allow_email_notifications']) ? (bool) $request['allow_email_notifications'] : false,
                'is_guest' => isset($request['is_guest']) ? (bool) $request['is_guest'] : false,
                'is_active' => isset($request['is_active']) ? (bool) $request['is_active'] : true,
            ];

            $customer_id = $this->repository->create($data);
            $customer = $this->repository->get($customer_id);

            return $this->prepare_item_for_response($customer, $request);

        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to create customer',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer
     */
    public function update_item($request)
    {
        try {
            $id = (int)$request['id'];
            $data = [];

            if (isset($request['first_name'])) {
                $data['first_name'] = sanitize_text_field($request['first_name']);
            }

            if (isset($request['last_name'])) {
                $data['last_name'] = sanitize_text_field($request['last_name']);
            }

            if (isset($request['email'])) {
                $data['email'] = sanitize_email($request['email']);
            }

            if (isset($request['phone'])) {
                $data['phone'] = sanitize_text_field($request['phone']);
            }

            if (isset($request['auth_provider'])) {
                $data['auth_provider'] = sanitize_text_field($request['auth_provider']);
            }

            if (isset($request['google_id'])) {
                $data['google_id'] = sanitize_text_field($request['google_id']);
            }

            if (isset($request['addresses'])) {
                $data['addresses'] = $request['addresses'];
            }

            if (isset($request['allow_sms_notifications'])) {
                $data['allow_sms_notifications'] = (bool) $request['allow_sms_notifications'];
            }

            if (isset($request['allow_email_notifications'])) {
                $data['allow_email_notifications'] = (bool) $request['allow_email_notifications'];
            }

            if (isset($request['is_active'])) {
                $data['is_active'] = (bool) $request['is_active'];
            }

            if (isset($request['staff_labels'])) {
                $data['staff_labels'] = sanitize_textarea_field($request['staff_labels']);
            }

            $success = $this->repository->update($id, $data);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Customer not found'
                ], 404);
            }

            $customer = $this->repository->get($id);
            return $this->prepare_item_for_response($customer, $request);

        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update customer',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer
     */
    public function delete_item($request)
    {
        try {
            $id = (int)$request['id'];
            $force = isset($request['force']) ? (bool) $request['force'] : false;

            $success = $this->repository->delete($id, $force);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Customer not found'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ], 200);

        } catch (ResourceInUseException $e) {
            return new \WP_REST_Response([
                'error' => 'לא ניתן למחוק לקוח',
                'message' => 'ללקוח יש הזמנות קיימות ולא ניתן למחוק אותו. השתמש ב-force=true לעקיפת הבדיקה.',
                'dependants' => $e->dependants
            ], 409); // 409 Conflict - resource is in use
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to delete customer',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers
     */
    public function search_customers($request)
    {
        try {
            $query = sanitize_text_field($request['q']);
            $limit = (int) ($request['limit'] ?? 20);

            $customers = $this->repository->search($query, $limit);

            $data = array_map(function($customer) use ($request) {
                return $this->prepare_item_for_response($customer, $request)->get_data();
            }, $customers);

            return new \WP_REST_Response($data, 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update loyalty points
     */
    public function update_loyalty_points($request)
    {
        try {
            $id = (int)$request['id'];
            $action = sanitize_text_field($request['action']);
            $points = (float) $request['points'];

            $success = false;
            if ($action === 'add') {
                $success = $this->repository->addLoyaltyPoints($id, $points);
            } elseif ($action === 'use') {
                $success = $this->repository->useLoyaltyPoints($id, $points);
            }

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Failed to update loyalty points',
                    'message' => 'Customer not found or insufficient points'
                ], 400);
            }

            $customer = $this->repository->get($id);
            return $this->prepare_item_for_response($customer, $request);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update loyalty points',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update staff labels
     */
    public function update_staff_labels($request)
    {
        try {
            $id = (int)$request['id'];
            $label = sanitize_text_field($request['label']);

            $success = $this->repository->addStaffLabel($id, $label);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Customer not found'
                ], 404);
            }

            $customer = $this->repository->get($id);
            return $this->prepare_item_for_response($customer, $request);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update staff labels',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert guest customer to registered
     */
    public function convert_guest_customer($request)
    {
        try {
            $id = (int)$request['id'];
            $email = sanitize_email($request['email']);
            $auth_provider = sanitize_text_field($request['auth_provider']);
            $google_id = isset($request['google_id']) ? sanitize_text_field($request['google_id']) : null;

            $success = $this->repository->convertGuestToRegistered($id, $email, $auth_provider, $google_id);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Failed to convert guest customer',
                    'message' => 'Customer not found or not a guest'
                ], 400);
            }

            $customer = $this->repository->get($id);
            return $this->prepare_item_for_response($customer, $request);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to convert guest customer',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function get_statistics($request)
    {
        try {
            $all_customers = $this->repository->getAll();

            $total_customers = count($all_customers);
            $registered_customers = count(array_filter($all_customers, fn($c) => !$c->is_guest));
            $guest_customers = count(array_filter($all_customers, fn($c) => $c->is_guest));
            $active_customers = count(array_filter($all_customers, fn($c) => $c->is_active));

            $google_auth = count(array_filter($all_customers, fn($c) => $c->auth_provider === 'google'));
            $phone_auth = count(array_filter($all_customers, fn($c) => $c->auth_provider === 'phone'));

            $total_loyalty_points = array_sum(array_map(fn($c) => $c->loyalty_points_balance, $all_customers));
            $customers_with_points = count(array_filter($all_customers, fn($c) => $c->loyalty_points_balance > 0));

            $total_spent = array_sum(array_map(fn($c) => $c->total_spent, $all_customers));
            $total_orders = array_sum(array_map(fn($c) => $c->total_orders, $all_customers));

            return new \WP_REST_Response([
                'total_customers' => $total_customers,
                'registered_customers' => $registered_customers,
                'guest_customers' => $guest_customers,
                'active_customers' => $active_customers,
                'inactive_customers' => $total_customers - $active_customers,
                'authentication' => [
                    'google' => $google_auth,
                    'phone' => $phone_auth
                ],
                'loyalty_program' => [
                    'total_points_in_system' => $total_loyalty_points,
                    'customers_with_points' => $customers_with_points,
                    'average_points_per_customer' => $total_customers > 0 ? $total_loyalty_points / $total_customers : 0
                ],
                'revenue' => [
                    'total_spent' => $total_spent,
                    'total_orders' => $total_orders,
                    'average_order_value' => $total_orders > 0 ? $total_spent / $total_orders : 0,
                    'average_customer_value' => $registered_customers > 0 ? $total_spent / $registered_customers : 0
                ]
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to get statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare item for response
     */
    public function prepare_item_for_response($item, $request)
    {
        $data = [
            'id' => $item->id,
            'first_name' => $item->first_name,
            'last_name' => $item->last_name,
            'full_name' => trim($item->first_name . ' ' . $item->last_name),
            'email' => $item->email,
            'phone' => $item->phone,
            'auth_provider' => $item->auth_provider,
            'google_id' => $item->google_id,
            'phone_verified_at' => $item->phone_verified_at?->format('Y-m-d H:i:s'),
            'addresses' => $item->addresses,
            'allow_sms_notifications' => $item->allow_sms_notifications,
            'allow_email_notifications' => $item->allow_email_notifications,
            'order_ids' => $item->order_ids,
            'total_orders' => $item->total_orders,
            'total_spent' => $item->total_spent,
            'last_order_date' => $item->last_order_date?->format('Y-m-d H:i:s'),
            'loyalty_points_balance' => $item->loyalty_points_balance,
            'lifetime_points_earned' => $item->lifetime_points_earned,
            'staff_labels' => $item->staff_labels,
            'is_active' => $item->is_active,
            'registration_date' => $item->registration_date->format('Y-m-d H:i:s'),
            'is_guest' => $item->is_guest,
        ];

        return new \WP_REST_Response($data, 200);
    }

    /**
     * Permission checks
     */
    public function get_items_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function get_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function create_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function update_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function delete_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function get_statistics_permissions_check($request)
    {
        return current_user_can('manage_options') || current_user_can('view_shop_reports');
    }

    /**
     * Get collection parameters
     */
    public function get_collection_params(): array
    {
        return [
            'auth_provider' => [
                'description' => 'Filter by authentication provider',
                'type' => 'string',
                'enum' => ['google', 'phone'],
            ],
            'is_guest' => [
                'description' => 'Filter by guest status',
                'type' => 'boolean',
            ],
            'is_active' => [
                'description' => 'Filter by active status',
                'type' => 'boolean',
            ],
            'min_loyalty_points' => [
                'description' => 'Filter by minimum loyalty points',
                'type' => 'number',
                'minimum' => 0,
            ],
            'min_total_spent' => [
                'description' => 'Filter by minimum total spent',
                'type' => 'number',
                'minimum' => 0,
            ],
            'per_page' => [
                'description' => 'Maximum number of items to return',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'offset' => [
                'description' => 'Offset for pagination',
                'type' => 'integer',
                'minimum' => 0,
            ],
        ];
    }

    /**
     * Get endpoint args for item schema
     */
    public function get_endpoint_args_for_item_schema($method = \WP_REST_Server::CREATABLE)
    {
        $args = [];

        if ($method === \WP_REST_Server::CREATABLE) {
            $args['first_name'] = [
                'required' => true,
                'type' => 'string',
                'description' => 'Customer first name',
            ];
            $args['last_name'] = [
                'required' => true,
                'type' => 'string',
                'description' => 'Customer last name',
            ];
            $args['phone'] = [
                'required' => true,
                'type' => 'string',
                'description' => 'Customer phone number',
            ];
            $args['auth_provider'] = [
                'required' => true,
                'type' => 'string',
                'enum' => ['google', 'phone'],
                'description' => 'Authentication provider',
            ];
        }

        if ($method === \WP_REST_Server::EDITABLE || $method === \WP_REST_Server::CREATABLE) {
            $args['email'] = [
                'type' => 'string',
                'format' => 'email',
                'description' => 'Customer email address',
            ];
            $args['google_id'] = [
                'type' => 'string',
                'description' => 'Google account ID',
            ];
            $args['addresses'] = [
                'type' => 'array',
                'description' => 'Customer addresses',
            ];
            $args['allow_sms_notifications'] = [
                'type' => 'boolean',
                'description' => 'Allow SMS notifications',
            ];
            $args['allow_email_notifications'] = [
                'type' => 'boolean',
                'description' => 'Allow email notifications',
            ];
            $args['is_active'] = [
                'type' => 'boolean',
                'description' => 'Customer active status',
            ];
            $args['staff_labels'] = [
                'type' => 'string',
                'description' => 'Staff labels for customer',
            ];
        }

        if ($method === \WP_REST_Server::CREATABLE) {
            $args['is_guest'] = [
                'type' => 'boolean',
                'description' => 'Is guest customer',
            ];
        }

        return $args;
    }
}