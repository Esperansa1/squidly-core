<?php
declare(strict_types=1);

/**
 * Order REST Controller
 *
 * Comprehensive REST API for order management with efficient lookups and analytics.
 * Provides CRUD operations, advanced filtering, statistics, and real-time order tracking.
 */
class OrderRestController extends \WP_REST_Controller
{
    private OrderRepository $repository;

    public function __construct()
    {
        $this->repository = new OrderRepository();
        $this->namespace = 'squidly/v1';
        $this->rest_base = 'orders';
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_orders'],
                'permission_callback' => [$this, 'get_orders_permissions_check'],
                'args'                => $this->get_collection_params(),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_order'],
                'permission_callback' => [$this, 'create_order_permissions_check'],
                'args'                => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::CREATABLE),
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => [
                'id' => [
                    'description' => __('Unique identifier for the order.'),
                    'type'        => 'integer',
                ],
            ],
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_order'],
                'permission_callback' => [$this, 'get_order_permissions_check'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_order'],
                'permission_callback' => [$this, 'update_order_permissions_check'],
                'args'                => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_order'],
                'permission_callback' => [$this, 'delete_order_permissions_check'],
            ],
        ]);

        // Analytics and statistics endpoints
        register_rest_route($this->namespace, '/' . $this->rest_base . '/statistics', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_order_statistics'],
                'permission_callback' => [$this, 'get_statistics_permissions_check'],
                'args'                => $this->get_statistics_params(),
            ],
        ]);

        // Revenue analytics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/revenue', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_revenue_analytics'],
                'permission_callback' => [$this, 'get_statistics_permissions_check'],
                'args'                => $this->get_revenue_params(),
            ],
        ]);

        // Customer order history
        register_rest_route($this->namespace, '/' . $this->rest_base . '/customer/(?P<customer_id>[\d]+)', [
            'args' => [
                'customer_id' => [
                    'description' => __('Customer ID to get orders for.'),
                    'type'        => 'integer',
                ],
            ],
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_customer_orders'],
                'permission_callback' => [$this, 'get_orders_permissions_check'],
                'args'                => $this->get_customer_orders_params(),
            ],
        ]);

        // Order status management
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', [
            'args' => [
                'id' => [
                    'description' => __('Unique identifier for the order.'),
                    'type'        => 'integer',
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_order_status'],
                'permission_callback' => [$this, 'update_order_permissions_check'],
                'args'                => [
                    'status' => [
                        'description' => __('New order status.'),
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => Order::getValidStatuses(),
                    ],
                ],
            ],
        ]);

        // Payment status management
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/payment', [
            'args' => [
                'id' => [
                    'description' => __('Unique identifier for the order.'),
                    'type'        => 'integer',
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_payment_status'],
                'permission_callback' => [$this, 'update_order_permissions_check'],
                'args'                => [
                    'payment_status' => [
                        'description' => __('New payment status.'),
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => Order::getValidPaymentStatuses(),
                    ],
                ],
            ],
        ]);

        // Order items management
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/items', [
            'args' => [
                'id' => [
                    'description' => __('Unique identifier for the order.'),
                    'type'        => 'integer',
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'add_order_item'],
                'permission_callback' => [$this, 'update_order_permissions_check'],
                'args'                => $this->get_order_item_params(),
            ],
        ]);

        // Popular items and trends
        register_rest_route($this->namespace, '/' . $this->rest_base . '/popular-items', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_popular_items'],
                'permission_callback' => [$this, 'get_statistics_permissions_check'],
                'args'                => $this->get_popular_items_params(),
            ],
        ]);

        // Real-time order queue for kitchen display
        register_rest_route($this->namespace, '/' . $this->rest_base . '/queue', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_order_queue'],
                'permission_callback' => [$this, 'get_orders_permissions_check'],
                'args'                => $this->get_queue_params(),
            ],
        ]);

        // Export orders to CSV/Excel
        register_rest_route($this->namespace, '/' . $this->rest_base . '/export', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'export_orders'],
                'permission_callback' => [$this, 'get_orders_permissions_check'],
                'args'                => $this->get_export_params(),
            ],
        ]);
    }

    /* ==========================================
     * CRUD Operations
     * ========================================== */

    /**
     * Get a collection of orders with advanced filtering
     */
    public function get_orders(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $filters = $this->sanitize_collection_filters($params);

            // Get orders with efficient lookup
            $orders = $this->repository->findBy($filters, $params['per_page'] ?? null, $params['offset'] ?? 0);

            // Prepare response data
            $data = [];
            foreach ($orders as $order) {
                $data[] = $this->prepare_order_for_response($order, $request);
            }

            // Add pagination headers
            $total_orders = $this->repository->countBy($filters);
            $response = rest_ensure_response($data);
            $response->header('X-WP-Total', (string) $total_orders);
            $response->header('X-WP-TotalPages', (string) ceil($total_orders / ($params['per_page'] ?? 10)));

            return $response;

        } catch (Exception $e) {
            return new \WP_Error(
                'order_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get a single order
     */
    public function get_order(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        $order = $this->repository->get($id);

        if (!$order) {
            return new \WP_Error(
                'order_not_found',
                __('Order not found.'),
                ['status' => 404]
            );
        }

        return rest_ensure_response($this->prepare_order_for_response($order, $request));
    }

    /**
     * Create a new order
     */
    public function create_order(\WP_REST_Request $request)
    {
        try {
            $data = $this->sanitize_order_data($request->get_params());

            // Calculate totals if not provided
            if (!isset($data['subtotal']) || !isset($data['total_amount'])) {
                $this->calculate_order_totals($data);
            }

            $order_id = $this->repository->create($data);
            $order = $this->repository->get($order_id);

            $response = rest_ensure_response($this->prepare_order_for_response($order, $request));
            $response->set_status(201);
            $response->header('Location', rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $order_id)));

            return $response;

        } catch (InvalidArgumentException $e) {
            return new \WP_Error(
                'order_validation_error',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            return new \WP_Error(
                'order_creation_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update an existing order
     */
    public function update_order(\WP_REST_Request $request)
    {
        try {
            $id = (int) $request['id'];
            $data = $this->sanitize_order_data($request->get_params(), true);

            // Recalculate totals if items changed
            if (isset($data['order_items'])) {
                $this->calculate_order_totals($data);
            }

            $success = $this->repository->update($id, $data);

            if (!$success) {
                return new \WP_Error(
                    'order_not_found',
                    __('Order not found.'),
                    ['status' => 404]
                );
            }

            $order = $this->repository->get($id);
            return rest_ensure_response($this->prepare_order_for_response($order, $request));

        } catch (InvalidArgumentException $e) {
            return new \WP_Error(
                'order_validation_error',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            return new \WP_Error(
                'order_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete an order
     */
    public function delete_order(\WP_REST_Request $request)
    {
        try {
            $id = (int) $request['id'];
            $force = $request->get_param('force') === true;

            $order = $this->repository->get($id);
            if (!$order) {
                return new \WP_Error(
                    'order_not_found',
                    __('Order not found.'),
                    ['status' => 404]
                );
            }

            $success = $this->repository->delete($id, $force);

            if (!$success) {
                return new \WP_Error(
                    'order_delete_error',
                    __('Could not delete order.'),
                    ['status' => 500]
                );
            }

            return rest_ensure_response(['deleted' => true]);

        } catch (ResourceInUseException $e) {
            return new \WP_Error(
                'order_delete_restricted',
                $e->getMessage(),
                ['status' => 409]
            );
        } catch (Exception $e) {
            return new \WP_Error(
                'order_delete_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /* ==========================================
     * Analytics and Statistics
     * ========================================== */

    /**
     * Get comprehensive order statistics
     */
    public function get_order_statistics(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $filters = $this->sanitize_statistics_filters($params);

            $stats = $this->repository->getStatistics($filters);

            // Enhanced statistics with additional calculations
            $enhanced_stats = $this->enhance_statistics($stats, $filters);

            return rest_ensure_response($enhanced_stats);

        } catch (Exception $e) {
            return new \WP_Error(
                'statistics_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get detailed revenue analytics
     */
    public function get_revenue_analytics(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $period = $params['period'] ?? 'daily';
            $date_from = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $params['date_to'] ?? date('Y-m-d');

            $revenue_data = $this->calculate_revenue_by_period($period, $date_from, $date_to);

            return rest_ensure_response($revenue_data);

        } catch (Exception $e) {
            return new \WP_Error(
                'revenue_analytics_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get customer order history
     */
    public function get_customer_orders(\WP_REST_Request $request)
    {
        try {
            $customer_id = (int) $request['customer_id'];
            $params = $request->get_params();

            $filters = ['customer_id' => $customer_id];
            if (isset($params['status'])) {
                $filters['status'] = $params['status'];
            }
            if (isset($params['date_from'])) {
                $filters['date_from'] = $params['date_from'];
            }
            if (isset($params['date_to'])) {
                $filters['date_to'] = $params['date_to'];
            }

            $orders = $this->repository->findBy($filters, $params['per_page'] ?? 10);

            $data = [];
            foreach ($orders as $order) {
                $data[] = $this->prepare_order_for_response($order, $request);
            }

            return rest_ensure_response($data);

        } catch (Exception $e) {
            return new \WP_Error(
                'customer_orders_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /* ==========================================
     * Order Management
     * ========================================== */

    /**
     * Update order status
     */
    public function update_order_status(\WP_REST_Request $request)
    {
        try {
            $id = (int) $request['id'];
            $status = $request['status'];

            $success = $this->repository->updateStatus($id, $status);

            if (!$success) {
                return new \WP_Error(
                    'order_not_found',
                    __('Order not found.'),
                    ['status' => 404]
                );
            }

            $order = $this->repository->get($id);
            return rest_ensure_response($this->prepare_order_for_response($order, $request));

        } catch (InvalidArgumentException $e) {
            return new \WP_Error(
                'invalid_status',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            return new \WP_Error(
                'status_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update payment status
     */
    public function update_payment_status(\WP_REST_Request $request)
    {
        try {
            $id = (int) $request['id'];
            $payment_status = $request['payment_status'];

            $success = $this->repository->updatePaymentStatus($id, $payment_status);

            if (!$success) {
                return new \WP_Error(
                    'order_not_found',
                    __('Order not found.'),
                    ['status' => 404]
                );
            }

            $order = $this->repository->get($id);
            return rest_ensure_response($this->prepare_order_for_response($order, $request));

        } catch (InvalidArgumentException $e) {
            return new \WP_Error(
                'invalid_payment_status',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            return new \WP_Error(
                'payment_status_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Add item to order
     */
    public function add_order_item(\WP_REST_Request $request)
    {
        try {
            $id = (int) $request['id'];
            $item_data = $request->get_params();

            $item = new OrderItem(
                $item_data['product_id'],
                $item_data['product_name'],
                $item_data['quantity'],
                $item_data['unit_price'],
                $item_data['modifications'] ?? [],
                $item_data['notes'] ?? null
            );

            $success = $this->repository->addItem($id, $item);

            if (!$success) {
                return new \WP_Error(
                    'order_not_found',
                    __('Order not found.'),
                    ['status' => 404]
                );
            }

            $order = $this->repository->get($id);
            return rest_ensure_response($this->prepare_order_for_response($order, $request));

        } catch (InvalidArgumentException $e) {
            return new \WP_Error(
                'invalid_item_data',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            return new \WP_Error(
                'add_item_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /* ==========================================
     * Analytics Endpoints
     * ========================================== */

    /**
     * Get popular items analytics
     */
    public function get_popular_items(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $date_from = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $params['date_to'] ?? date('Y-m-d');
            $limit = $params['limit'] ?? 10;

            $popular_items = $this->calculate_popular_items($date_from, $date_to, $limit);

            return rest_ensure_response($popular_items);

        } catch (Exception $e) {
            return new \WP_Error(
                'popular_items_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get order queue for kitchen display
     */
    public function get_order_queue(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $statuses = $params['statuses'] ?? [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING];

            $queue_orders = [];
            foreach ($statuses as $status) {
                $orders = $this->repository->getByStatus($status);
                $queue_orders = array_merge($queue_orders, $orders);
            }

            // Sort by order date
            usort($queue_orders, function($a, $b) {
                return strtotime($a->order_date) - strtotime($b->order_date);
            });

            $data = [];
            foreach ($queue_orders as $order) {
                $data[] = $this->prepare_queue_order($order, $request);
            }

            return rest_ensure_response($data);

        } catch (Exception $e) {
            return new \WP_Error(
                'queue_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Export orders to CSV format
     */
    public function export_orders(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $filters = $this->sanitize_collection_filters($params);

            // Get orders with sorting
            $orders = $this->repository->findBy($filters);

            // Determine format (csv or excel)
            $format = $params['format'] ?? 'csv';

            if ($format === 'csv') {
                return $this->export_csv($orders);
            } else {
                // For now, we'll just do CSV. Excel can be added later with a library
                return $this->export_csv($orders);
            }

        } catch (Exception $e) {
            return new \WP_Error(
                'export_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Export orders to CSV
     */
    private function export_csv(array $orders): \WP_REST_Response
    {
        // Create CSV content
        $csv_data = [];

        // CSV Header
        $csv_data[] = [
            'מספר הזמנה', // Order Number
            'תאריך', // Date
            'שעה', // Time
            'לקוח', // Customer
            'סוג', // Type
            'סכום', // Amount
            'סטטוס', // Status
            'אמצעי תשלום', // Payment Method
            'סטטוס תשלום', // Payment Status
        ];

        // CSV Rows
        foreach ($orders as $order) {
            // Get customer name if exists
            $customer_name = '';
            if ($order->customer_id) {
                $customer_post = get_post($order->customer_id);
                if ($customer_post) {
                    $customer_name = get_post_meta($order->customer_id, '_name', true) ?: $customer_post->post_title;
                }
            }

            // Determine order type
            $order_type = 'איסוף'; // Pickup
            if ($order->delivery_address || $order->payment_method === Order::PAYMENT_ONLINE) {
                $order_type = 'משלוח'; // Delivery
            }

            // Format date and time
            $date = date('Y-m-d', strtotime($order->order_date));
            $time = date('H:i', strtotime($order->order_date));

            // Translate status
            $status_labels = [
                Order::STATUS_PENDING => 'ממתין',
                Order::STATUS_CONFIRMED => 'אושר',
                Order::STATUS_PREPARING => 'בהכנה',
                Order::STATUS_READY => 'מוכן',
                Order::STATUS_COMPLETED => 'הושלם',
                Order::STATUS_CANCELLED => 'בוטל',
            ];

            // Translate payment method
            $payment_method_labels = [
                Order::PAYMENT_CASH => 'מזומן',
                Order::PAYMENT_CARD => 'אשראי',
                Order::PAYMENT_ONLINE => 'אונליין',
            ];

            // Translate payment status
            $payment_status_labels = [
                Order::PAYMENT_PENDING => 'ממתין',
                Order::PAYMENT_PAID => 'שולם',
                Order::PAYMENT_FAILED => 'נכשל',
                Order::PAYMENT_REFUNDED => 'הוחזר',
            ];

            $csv_data[] = [
                $order->id,
                $date,
                $time,
                $customer_name,
                $order_type,
                number_format($order->total_amount, 2),
                $status_labels[$order->status] ?? $order->status,
                $payment_method_labels[$order->payment_method] ?? $order->payment_method,
                $payment_status_labels[$order->payment_status] ?? $order->payment_status,
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_string = stream_get_contents($output);
        fclose($output);

        // Create response with proper headers for download
        $response = new \WP_REST_Response($csv_string, 200);
        $response->set_headers([
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="orders-export-' . date('Y-m-d-His') . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        return $response;
    }

    /* ==========================================
     * Permission Checks
     * ========================================== */

    /**
     * Check if a given request has access to read orders.
     */
    public function get_orders_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('edit_posts'));
    }

    /**
     * Check if a given request has access to create orders.
     */
    public function create_order_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('edit_posts'));
    }

    /**
     * Check if a given request has access to read a specific order.
     */
    public function get_order_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('edit_posts'));
    }

    /**
     * Check if a given request has access to update orders.
     */
    public function update_order_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('edit_posts'));
    }

    /**
     * Check if a given request has access to delete orders.
     */
    public function delete_order_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    /**
     * Check if a given request has access to view statistics.
     */
    public function get_statistics_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('view_shop_reports'));
    }

    /* ==========================================
     * Data Sanitization and Validation
     * ========================================== */

    /**
     * Sanitize order data for create/update operations
     */
    private function sanitize_order_data(array $data, bool $is_update = false): array
    {
        $sanitized = [];

        if (isset($data['customer_id'])) {
            $sanitized['customer_id'] = (int) $data['customer_id'];
        }

        if (isset($data['status'])) {
            $sanitized['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['payment_status'])) {
            $sanitized['payment_status'] = sanitize_text_field($data['payment_status']);
        }

        if (isset($data['payment_method'])) {
            $sanitized['payment_method'] = sanitize_text_field($data['payment_method']);
        }

        if (isset($data['subtotal'])) {
            $sanitized['subtotal'] = (float) $data['subtotal'];
        }

        if (isset($data['tax_amount'])) {
            $sanitized['tax_amount'] = (float) $data['tax_amount'];
        }

        if (isset($data['delivery_fee'])) {
            $sanitized['delivery_fee'] = (float) $data['delivery_fee'];
        }

        if (isset($data['total_amount'])) {
            $sanitized['total_amount'] = (float) $data['total_amount'];
        }

        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }

        if (isset($data['delivery_address'])) {
            $sanitized['delivery_address'] = sanitize_textarea_field($data['delivery_address']);
        }

        if (isset($data['pickup_time'])) {
            $sanitized['pickup_time'] = sanitize_text_field($data['pickup_time']);
        }

        if (isset($data['special_instructions'])) {
            $sanitized['special_instructions'] = sanitize_textarea_field($data['special_instructions']);
        }

        if (isset($data['order_items']) && is_array($data['order_items'])) {
            $sanitized['order_items'] = $this->sanitize_order_items($data['order_items']);
        }

        return $sanitized;
    }

    /**
     * Sanitize order items data
     */
    private function sanitize_order_items(array $items): array
    {
        $sanitized_items = [];

        foreach ($items as $item) {
            $sanitized_item = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'product_name' => sanitize_text_field($item['product_name'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'modifications' => is_array($item['modifications'] ?? null) ?
                    array_map('sanitize_text_field', $item['modifications']) : [],
                'notes' => isset($item['notes']) ? sanitize_textarea_field($item['notes']) : null,
            ];

            // Calculate total price
            $sanitized_item['total_price'] = $sanitized_item['quantity'] * $sanitized_item['unit_price'];

            $sanitized_items[] = $sanitized_item;
        }

        return $sanitized_items;
    }

    /**
     * Sanitize collection filters
     */
    private function sanitize_collection_filters(array $params): array
    {
        $filters = [];

        if (isset($params['customer_id'])) {
            $filters['customer_id'] = (int) $params['customer_id'];
        }

        if (isset($params['branch_id'])) {
            $filters['branch_id'] = (int) $params['branch_id'];
        }

        if (isset($params['status'])) {
            // Handle comma-separated status values
            $status_param = $params['status'];
            if (is_string($status_param) && strpos($status_param, ',') !== false) {
                // Multiple statuses as comma-separated string
                $statuses = array_map('trim', explode(',', $status_param));
                $filters['status__in'] = array_map('sanitize_text_field', $statuses);
            } else {
                // Single status
                $filters['status'] = sanitize_text_field($status_param);
            }
        }

        if (isset($params['payment_status'])) {
            $filters['payment_status'] = sanitize_text_field($params['payment_status']);
        }

        if (isset($params['payment_method'])) {
            $filters['payment_method'] = sanitize_text_field($params['payment_method']);
        }

        if (isset($params['date_from'])) {
            $filters['date_from'] = sanitize_text_field($params['date_from']);
        }

        if (isset($params['date_to'])) {
            $filters['date_to'] = sanitize_text_field($params['date_to']);
        }

        if (isset($params['total_min'])) {
            $filters['total_min'] = (float) $params['total_min'];
        }

        if (isset($params['total_max'])) {
            $filters['total_max'] = (float) $params['total_max'];
        }

        if (isset($params['has_delivery'])) {
            // Convert boolean to delivery_type filter for repository
            $filters['delivery_type'] = rest_sanitize_boolean($params['has_delivery']) ? 'delivery' : 'pickup';
        }

        return $filters;
    }

    /**
     * Sanitize statistics filters
     */
    private function sanitize_statistics_filters(array $params): array
    {
        return $this->sanitize_collection_filters($params);
    }

    /* ==========================================
     * Response Preparation
     * ========================================== */

    /**
     * Prepare order for REST response
     */
    private function prepare_order_for_response(Order $order, \WP_REST_Request $request): array
    {
        $data = [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'branch_id' => $order->branch_id,
            'status' => $order->status,
            'order_date' => $order->order_date,
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'delivery_fee' => $order->delivery_fee,
            'total_amount' => $order->total_amount,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'notes' => $order->notes,
            'delivery_address' => $order->delivery_address,
            'pickup_time' => $order->pickup_time,
            'special_instructions' => $order->special_instructions,
            'order_items' => array_map([$this, 'prepare_order_item'], $order->order_items),
        ];

        // Include customer data inline to avoid separate API calls
        $customer_repo = new \CustomerRepository();
        $customer = $customer_repo->get($order->customer_id);
        if ($customer) {
            $data['customer'] = [
                'id' => $customer->id,
                'name' => trim($customer->first_name . ' ' . $customer->last_name),
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
            ];
        } else {
            // Fallback for missing customer
            $data['customer'] = [
                'id' => $order->customer_id,
                'name' => 'לקוח לא ידוע',
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
            ];
        }

        // Add calculated fields
        $data['can_be_cancelled'] = $order->canBeCancelled();
        $data['is_completed'] = $order->isCompleted();
        $data['display_name'] = $order->getDisplayName();
        $data['item_count'] = count($order->order_items);
        $data['estimated_ready_time'] = $this->calculate_estimated_ready_time($order);

        return $data;
    }

    /**
     * Prepare order item for response
     */
    private function prepare_order_item(OrderItem $item): array
    {
        return [
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
            'modifications' => $item->modifications,
            'notes' => $item->notes,
            'display_string' => $item->getDisplayString(),
        ];
    }

    /**
     * Prepare order for queue display (simplified)
     */
    private function prepare_queue_order(Order $order, \WP_REST_Request $request): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'order_date' => $order->order_date,
            'total_amount' => $order->total_amount,
            'payment_status' => $order->payment_status,
            'order_items' => array_map(function($item) {
                return [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'modifications' => $item->modifications,
                    'display_string' => $item->getDisplayString(),
                ];
            }, $order->order_items),
            'special_instructions' => $order->special_instructions,
            'estimated_ready_time' => $this->calculate_estimated_ready_time($order),
        ];
    }

    /* ==========================================
     * Calculation Helpers
     * ========================================== */

    /**
     * Calculate order totals from items
     */
    private function calculate_order_totals(array &$data): void
    {
        if (!isset($data['order_items'])) {
            return;
        }

        $subtotal = 0.0;
        foreach ($data['order_items'] as &$item) {
            $item_total = (float)$item['unit_price'] * (int)$item['quantity'];
            $item['total_price'] = $item_total;
            $subtotal += $item_total;
        }

        $delivery_fee = $data['delivery_fee'] ?? 0.0;
        $tax_rate = (float)get_option('squidly_tax_rate', 0.17);
        $tax_amount = round($subtotal * $tax_rate, 2);

        $data['subtotal'] = round($subtotal, 2);
        $data['tax_amount'] = $tax_amount;
        $data['total_amount'] = round($subtotal + $tax_amount + $delivery_fee, 2);
    }

    /**
     * Enhance statistics with additional calculations
     */
    private function enhance_statistics(array $stats, array $filters): array
    {
        $enhanced = $stats;

        // Add growth rates if date range is provided
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $enhanced['growth_metrics'] = $this->calculate_growth_metrics($filters);
        }

        // Add peak hours analysis
        $enhanced['peak_hours'] = $this->calculate_peak_hours($filters);

        // Add customer retention metrics
        $enhanced['customer_metrics'] = $this->calculate_customer_metrics($filters);

        return $enhanced;
    }

    /**
     * Calculate revenue by period (daily, weekly, monthly)
     */
    private function calculate_revenue_by_period(string $period, string $date_from, string $date_to): array
    {
        $orders = $this->repository->findBy([
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);

        $revenue_data = [];

        foreach ($orders as $order) {
            $date_key = $this->get_period_key($order->order_date, $period);

            if (!isset($revenue_data[$date_key])) {
                $revenue_data[$date_key] = [
                    'period' => $date_key,
                    'revenue' => 0.0,
                    'orders' => 0,
                    'average_order_value' => 0.0,
                ];
            }

            $revenue_data[$date_key]['revenue'] += $order->total_amount;
            $revenue_data[$date_key]['orders']++;
        }

        // Calculate average order values
        foreach ($revenue_data as &$data) {
            $data['average_order_value'] = $data['orders'] > 0 ?
                $data['revenue'] / $data['orders'] : 0.0;
        }

        return array_values($revenue_data);
    }

    /**
     * Calculate popular items
     */
    private function calculate_popular_items(string $date_from, string $date_to, int $limit): array
    {
        $orders = $this->repository->findBy([
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);

        $item_stats = [];

        foreach ($orders as $order) {
            foreach ($order->order_items as $item) {
                $key = $item->product_id;

                if (!isset($item_stats[$key])) {
                    $item_stats[$key] = [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'total_quantity' => 0,
                        'total_revenue' => 0.0,
                        'order_count' => 0,
                    ];
                }

                $item_stats[$key]['total_quantity'] += $item->quantity;
                $item_stats[$key]['total_revenue'] += $item->total_price;
                $item_stats[$key]['order_count']++;
            }
        }

        // Sort by quantity and limit
        uasort($item_stats, function($a, $b) {
            return $b['total_quantity'] - $a['total_quantity'];
        });

        return array_slice(array_values($item_stats), 0, $limit);
    }

    /**
     * Calculate growth metrics
     */
    private function calculate_growth_metrics(array $filters): array
    {
        // Implementation would compare current period with previous period
        return [
            'revenue_growth' => 0.0,
            'order_growth' => 0.0,
            'customer_growth' => 0.0,
        ];
    }

    /**
     * Calculate peak hours
     */
    private function calculate_peak_hours(array $filters): array
    {
        $orders = $this->repository->findBy($filters);
        $hour_stats = array_fill(0, 24, 0);

        foreach ($orders as $order) {
            $hour = (int)date('H', strtotime($order->order_date));
            $hour_stats[$hour]++;
        }

        $peak_data = [];
        for ($i = 0; $i < 24; $i++) {
            $peak_data[] = [
                'hour' => $i,
                'order_count' => $hour_stats[$i],
            ];
        }

        return $peak_data;
    }

    /**
     * Calculate customer metrics
     */
    private function calculate_customer_metrics(array $filters): array
    {
        $orders = $this->repository->findBy($filters);
        $customer_stats = [];

        foreach ($orders as $order) {
            if (!isset($customer_stats[$order->customer_id])) {
                $customer_stats[$order->customer_id] = [
                    'order_count' => 0,
                    'total_spent' => 0.0,
                ];
            }

            $customer_stats[$order->customer_id]['order_count']++;
            $customer_stats[$order->customer_id]['total_spent'] += $order->total_amount;
        }

        return [
            'unique_customers' => count($customer_stats),
            'repeat_customers' => count(array_filter($customer_stats, fn($c) => $c['order_count'] > 1)),
            'average_orders_per_customer' => count($customer_stats) > 0 ?
                array_sum(array_column($customer_stats, 'order_count')) / count($customer_stats) : 0,
        ];
    }

    /**
     * Calculate estimated ready time for order
     */
    private function calculate_estimated_ready_time(Order $order): ?string
    {
        // Simple estimation based on item count
        $item_count = count($order->order_items);
        $base_time = 15; // minutes
        $per_item_time = 3; // minutes per item

        $estimated_minutes = $base_time + ($item_count * $per_item_time);
        $ready_time = strtotime($order->order_date) + ($estimated_minutes * 60);

        return date('Y-m-d H:i:s', $ready_time);
    }

    /**
     * Get period key for revenue grouping
     */
    private function get_period_key(string $date, string $period): string
    {
        $timestamp = strtotime($date);

        switch ($period) {
            case 'daily':
                return date('Y-m-d', $timestamp);
            case 'weekly':
                return date('Y-\WW', $timestamp);
            case 'monthly':
                return date('Y-m', $timestamp);
            default:
                return date('Y-m-d', $timestamp);
        }
    }

    /* ==========================================
     * Parameter Definitions
     * ========================================== */

    /**
     * Get the query params for collections.
     */
    public function get_collection_params(): array
    {
        return array_merge(parent::get_collection_params(), [
            'customer_id' => [
                'description' => __('Filter by customer ID.'),
                'type'        => 'integer',
            ],
            'branch_id' => [
                'description' => __('Filter by branch ID.'),
                'type'        => 'integer',
            ],
            'status' => [
                'description' => __('Filter by order status. Supports single value or comma-separated list.'),
                'type'        => 'string',
                'validate_callback' => function($param, $request, $key) {
                    // Allow comma-separated values
                    $statuses = is_array($param) ? $param : explode(',', $param);
                    $valid_statuses = Order::getValidStatuses();

                    foreach ($statuses as $status) {
                        $status = trim($status);
                        if (!in_array($status, $valid_statuses, true)) {
                            return new \WP_Error(
                                'rest_invalid_param',
                                sprintf(__('Invalid status: %s'), $status),
                                ['status' => 400]
                            );
                        }
                    }
                    return true;
                },
            ],
            'payment_status' => [
                'description' => __('Filter by payment status.'),
                'type'        => 'string',
                'enum'        => Order::getValidPaymentStatuses(),
            ],
            'payment_method' => [
                'description' => __('Filter by payment method.'),
                'type'        => 'string',
                'enum'        => Order::getValidPaymentMethods(),
            ],
            'date_from' => [
                'description' => __('Filter orders from this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'date_to' => [
                'description' => __('Filter orders to this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'total_min' => [
                'description' => __('Filter orders with minimum total amount.'),
                'type'        => 'number',
            ],
            'total_max' => [
                'description' => __('Filter orders with maximum total amount.'),
                'type'        => 'number',
            ],
            'has_delivery' => [
                'description' => __('Filter by delivery type. true = delivery orders, false = pickup orders.'),
                'type'        => 'boolean',
            ],
        ]);
    }

    /**
     * Get statistics parameters
     */
    private function get_statistics_params(): array
    {
        return [
            'date_from' => [
                'description' => __('Statistics from this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'date_to' => [
                'description' => __('Statistics to this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'customer_id' => [
                'description' => __('Filter statistics by customer ID.'),
                'type'        => 'integer',
            ],
            'status' => [
                'description' => __('Filter statistics by order status.'),
                'type'        => 'string',
                'enum'        => Order::getValidStatuses(),
            ],
        ];
    }

    /**
     * Get revenue parameters
     */
    private function get_revenue_params(): array
    {
        return [
            'period' => [
                'description' => __('Revenue period grouping.'),
                'type'        => 'string',
                'enum'        => ['daily', 'weekly', 'monthly'],
                'default'     => 'daily',
            ],
            'date_from' => [
                'description' => __('Revenue analysis from this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'date_to' => [
                'description' => __('Revenue analysis to this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
        ];
    }

    /**
     * Get customer orders parameters
     */
    private function get_customer_orders_params(): array
    {
        return [
            'status' => [
                'description' => __('Filter by order status.'),
                'type'        => 'string',
                'enum'        => Order::getValidStatuses(),
            ],
            'date_from' => [
                'description' => __('Filter orders from this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'date_to' => [
                'description' => __('Filter orders to this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'per_page' => [
                'description' => __('Maximum number of items to be returned in result set.'),
                'type'        => 'integer',
                'default'     => 10,
                'minimum'     => 1,
                'maximum'     => 100,
            ],
        ];
    }

    /**
     * Get order item parameters
     */
    private function get_order_item_params(): array
    {
        return [
            'product_id' => [
                'description' => __('Product ID for the order item.'),
                'type'        => 'integer',
                'required'    => true,
            ],
            'product_name' => [
                'description' => __('Product name for the order item.'),
                'type'        => 'string',
                'required'    => true,
            ],
            'quantity' => [
                'description' => __('Quantity of the item.'),
                'type'        => 'integer',
                'required'    => true,
                'minimum'     => 1,
            ],
            'unit_price' => [
                'description' => __('Unit price of the item.'),
                'type'        => 'number',
                'required'    => true,
                'minimum'     => 0,
            ],
            'modifications' => [
                'description' => __('Item modifications or customizations.'),
                'type'        => 'array',
                'items'       => ['type' => 'string'],
            ],
            'notes' => [
                'description' => __('Special notes for the item.'),
                'type'        => 'string',
            ],
        ];
    }

    /**
     * Get popular items parameters
     */
    private function get_popular_items_params(): array
    {
        return [
            'date_from' => [
                'description' => __('Analyze popularity from this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'date_to' => [
                'description' => __('Analyze popularity to this date (Y-m-d format).'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'limit' => [
                'description' => __('Number of popular items to return.'),
                'type'        => 'integer',
                'default'     => 10,
                'minimum'     => 1,
                'maximum'     => 50,
            ],
        ];
    }

    /**
     * Get queue parameters
     */
    private function get_queue_params(): array
    {
        return [
            'statuses' => [
                'description' => __('Order statuses to include in queue.'),
                'type'        => 'array',
                'items'       => [
                    'type' => 'string',
                    'enum' => Order::getValidStatuses(),
                ],
                'default'     => [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING],
            ],
        ];
    }

    /**
     * Get export parameters
     */
    private function get_export_params(): array
    {
        return array_merge($this->get_collection_params(), [
            'format' => [
                'description' => __('Export format (csv or excel).'),
                'type'        => 'string',
                'enum'        => ['csv', 'excel'],
                'default'     => 'csv',
            ],
        ]);
    }
}