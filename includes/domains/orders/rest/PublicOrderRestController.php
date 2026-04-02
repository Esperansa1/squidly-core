<?php
declare(strict_types=1);

use Squidly\Domains\Payments\Bootstrap\PaymentBootstrap;

/**
 * Public Order REST API Controller
 *
 * Handles public order creation for customer app (no authentication required)
 * Validates customizations, calculates prices server-side, generates tracking tokens
 *
 * Security:
 * - Rate limiting
 * - Server-side price calculation (don't trust frontend)
 * - Customization validation against manipulation
 * - Input sanitization
 */
class PublicOrderRestController extends WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'public/orders';

    private OrderRepository $orderRepo;
    private CustomerRepository $customerRepo;
    private StoreBranchRepository $branchRepo;
    private ProductRepository $productRepo;
    private ProductCustomizationValidator $validator;
    private DeliveryFeeService $deliveryFeeService;

    // Rate limiting (simple IP-based)
    private const RATE_LIMIT_REQUESTS = 10;
    private const RATE_LIMIT_WINDOW = 60; // seconds

    public function __construct()
    {
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
        $this->branchRepo = new StoreBranchRepository();
        $this->productRepo = new ProductRepository();
        $this->validator = new ProductCustomizationValidator();
        $this->deliveryFeeService = new DeliveryFeeService();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // POST /squidly/v1/public/orders
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_order'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_create_order_args(),
            ],
        ]);

        // GET /squidly/v1/public/orders/{id}/status?token={tracking_token}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/status', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_order_status'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'id' => [
                        'description' => 'Order ID',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'token' => [
                        'description' => 'Tracking token',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // DELETE /squidly/v1/public/orders/{id}?token={tracking_token}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'cancel_order'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'id' => [
                        'description' => 'Order ID',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'token' => [
                        'description' => 'Tracking token',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // GET /squidly/v1/public/delivery-fee?branch_id={id}&address={address}&subtotal={amount}
        register_rest_route($this->namespace, '/public/delivery-fee', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_delivery_fee'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'branch_id' => [
                        'description'       => 'Branch ID',
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ],
                    'address' => [
                        'description' => 'Delivery address',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'subtotal' => [
                        'description' => 'Order subtotal for free delivery threshold check',
                        'type'        => 'number',
                        'required'    => false,
                        'default'     => 0,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a new order (public endpoint)
     */
    public function create_order($request)
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

            // Step 1: Validate basic data
            $this->validate_basic_data($data);

            // Step 2: Validate customer exists
            $customer = $this->customerRepo->get($data['customer_id']);
            if (!$customer) {
                return new WP_REST_Response([
                    'error' => 'Customer not found'
                ], 400);
            }

            // Step 3: Validate branch exists
            $branch = $this->branchRepo->get($data['branch_id']);
            if (!$branch) {
                return new WP_REST_Response([
                    'error' => 'Branch not found'
                ], 400);
            }

            // Step 4: Validate and calculate order items with server-side prices
            $validated_items = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $item_data) {
                $validated_item = $this->validate_and_calculate_item($item_data, $branch);
                $validated_items[] = $validated_item;
                $subtotal += $validated_item['total_price'];
            }

            // Validate minimum order amount
            if ($branch->min_order_amount > 0 && $subtotal < $branch->min_order_amount) {
                return new WP_REST_Response([
                    'error' => 'Minimum order amount not met',
                    'message' => sprintf(
                        'Order subtotal (%.2f) is below minimum order amount (%.2f) for this branch',
                        $subtotal,
                        $branch->min_order_amount
                    ),
                    'min_order_amount' => $branch->min_order_amount,
                    'current_subtotal' => $subtotal
                ], 400);
            }

            // Step 5: Calculate delivery fee (if delivery)
            $delivery_fee = 0.0;
            $delivery_address = $data['delivery_address'] ?? null;
            if (($data['delivery_type'] ?? 'pickup') === 'delivery') {
                try {
                    $delivery_fee = $this->deliveryFeeService->calculateFee(
                        $data['branch_id'],
                        $delivery_address,
                        $subtotal
                    );
                } catch (InvalidArgumentException $e) {
                    return new WP_REST_Response([
                        'error' => 'Delivery fee calculation failed',
                        'message' => $e->getMessage()
                    ], 400);
                }
            }

            // Step 6: Calculate totals
            $tax_rate = (float) get_option('squidly_tax_rate', 0.17);
            $tax_amount = $subtotal * $tax_rate;
            $total_amount = $subtotal + $tax_amount + $delivery_fee;

            // Step 7: Generate tracking token
            $tracking_token = $this->generate_tracking_token();

            // Step 8: Prepare order data
            $order_data = [
                'customer_id'          => $data['customer_id'],
                'branch_id'            => $data['branch_id'],
                'status'               => Order::STATUS_PENDING,
                'subtotal'             => $subtotal,
                'tax_amount'           => $tax_amount,
                'delivery_fee'         => $delivery_fee,
                'total_amount'         => $total_amount,
                'payment_status'       => Order::PAYMENT_PENDING,
                'payment_method'       => $data['payment_method'] ?? 'online',
                'delivery_type'        => $data['delivery_type'] ?? 'pickup',
                'delivery_address'     => $data['delivery_address'] ?? null,
                'pickup_time'          => $data['delivery_time'] ?? null, // Using delivery_time for both
                'notes'                => sanitize_textarea_field($data['notes'] ?? ''),
                'special_instructions' => sanitize_textarea_field($data['notes'] ?? ''),
                'tracking_token'       => $tracking_token,
                'order_items'          => $validated_items,
            ];

            // Step 9: Create order
            $order_id = $this->orderRepo->create($order_data);

            // Step 10: Get created order
            $order = $this->orderRepo->get($order_id);

            // Step 11: Prepare payment URL (if online payment)
            $payment_url = null;
            $payment_method = $data['payment_method'] ?? 'online';
            if ($payment_method === 'woocommerce' || $payment_method === 'online') {
                // Create WooCommerce order for payment
                try {
                    $wc_order_id = $this->create_woocommerce_order($order);
                    $this->orderRepo->linkWooCommerceOrder($order_id, $wc_order_id);

                    $wc_order = wc_get_order($wc_order_id);
                    $payment_url = $wc_order->get_checkout_payment_url();
                } catch (Exception $e) {
                    error_log("Failed to create WooCommerce order: " . $e->getMessage());
                    // Continue without payment URL - can be retried later
                }
            }

            // Step 12: Return response
            return new WP_REST_Response([
                'order_id'        => $order_id,
                'tracking_token'  => $tracking_token,
                'total_price'     => $total_amount,
                'subtotal'        => $subtotal,
                'tax_amount'      => $tax_amount,
                'delivery_fee'    => $delivery_fee,
                'status'          => Order::STATUS_PENDING,
                'payment_status'  => Order::PAYMENT_PENDING,
                'payment_url'     => $payment_url,
                'message'         => 'Order created successfully',
            ], 201);

        } catch (InvalidArgumentException $e) {
            // Validation error
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            // Log error for debugging
            error_log("Order creation error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to create order',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Get order status (public endpoint with token validation)
     */
    public function get_order_status($request)
    {
        $order_id = (int) $request->get_param('id');
        $token = sanitize_text_field($request->get_param('token'));

        $order = $this->orderRepo->get($order_id);

        if (!$order) {
            return new WP_REST_Response([
                'error' => 'Order not found'
            ], 404);
        }

        // Verify tracking token
        if ($order->tracking_token !== $token) {
            return new WP_REST_Response([
                'error' => 'Invalid tracking token'
            ], 403);
        }

        // Compute estimated ready time: order_date + 30 minutes for active orders
        $estimated_time = null;
        if (in_array($order->status, ['pending', 'confirmed', 'preparing'])) {
            $estimated_time = date('Y-m-d H:i:s', strtotime($order->order_date) + 30 * 60);
        }

        // Return complete order data for tracking UI
        return new WP_REST_Response([
            'order' => [
                'id'              => $order->id,
                'status'          => $order->status,
                'payment_status'  => $order->payment_status,
                'payment_method'  => $order->payment_method,
                'total_amount'    => $order->total_amount,
                'subtotal'        => $order->subtotal,
                'tax_amount'      => $order->tax_amount,
                'delivery_fee'    => $order->delivery_fee,
                'order_date'      => $order->order_date,
                'delivery_type'   => $order->delivery_type ?? 'pickup',
                'delivery_address' => $order->delivery_address ?? '',
                'pickup_time'     => $order->pickup_time ?? '',
                'special_instructions' => $order->special_instructions ?? '',
                'estimated_time'  => $estimated_time,
                'confirmed_at'    => $order->confirmed_at,
                'preparing_at'    => $order->preparing_at,
                'ready_at'        => $order->ready_at,
                'completed_at'    => $order->completed_at,
                'cancelled_at'    => $order->cancelled_at,
                'items'           => array_map(function($item) {
                    return [
                        'product_id'   => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity'     => $item->quantity,
                        'unit_price'   => $item->unit_price,
                        'total_price'  => $item->total_price,
                        'special_instructions' => $item->notes ?? '',
                    ];
                }, $order->order_items),
            ],
        ], 200);
    }

    /**
     * Cancel an order (public endpoint - requires tracking token)
     */
    public function cancel_order($request)
    {
        $order_id = (int) $request->get_param('id');
        $token = sanitize_text_field($request->get_param('token'));

        try {
            $order = $this->orderRepo->get($order_id);

            if (!$order) {
                return new WP_REST_Response([
                    'error' => 'Order not found'
                ], 404);
            }

            // Verify tracking token
            if ($order->tracking_token !== $token) {
                return new WP_REST_Response([
                    'error' => 'Invalid tracking token'
                ], 403);
            }

            // Only allow cancellation if order is in pending or confirmed status
            if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])) {
                return new WP_REST_Response([
                    'error' => 'Order cannot be cancelled',
                    'message' => 'Orders can only be cancelled when pending or confirmed. Current status: ' . $order->status
                ], 400);
            }

            // Update order status to cancelled
            $this->orderRepo->update($order_id, [
                'status' => Order::STATUS_CANCELLED
            ]);

            return new WP_REST_Response([
                'message' => 'Order cancelled successfully',
                'order_id' => $order_id,
                'status' => Order::STATUS_CANCELLED,
            ], 200);

        } catch (Exception $e) {
            error_log("Order cancellation error: " . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'Failed to cancel order',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Get delivery fee estimate (public endpoint)
     */
    public function get_delivery_fee($request)
    {
        $branch_id = (int) $request->get_param('branch_id');
        $address = sanitize_text_field($request->get_param('address'));
        $subtotal = (float) ($request->get_param('subtotal') ?? 0);

        try {
            // Validate branch exists and has delivery enabled
            $branch = $this->branchRepo->get($branch_id);

            if (!$branch) {
                return new WP_REST_Response([
                    'error' => 'Branch not found'
                ], 404);
            }

            if (!$branch->delivery_enabled) {
                return new WP_REST_Response([
                    'error' => 'Delivery not available',
                    'message' => 'This branch does not offer delivery service'
                ], 400);
            }

            // Calculate delivery fee
            $delivery_fee = $this->deliveryFeeService->calculateFee(
                $branch_id,
                $address,
                $subtotal
            );

            // Check if delivery is available to this address
            $is_deliverable = $this->deliveryFeeService->isAddressInRange($branch_id, $address);

            return new WP_REST_Response([
                'delivery_fee' => $delivery_fee,
                'is_deliverable' => $is_deliverable,
                'branch_id' => $branch_id,
                'free_delivery_threshold' => $branch->delivery_free_threshold ?? 0,
                'is_free_delivery' => $delivery_fee === 0.0 && $subtotal > 0,
            ], 200);

        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            error_log("Delivery fee calculation error: " . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'Failed to calculate delivery fee',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Validate basic request data
     */
    private function validate_basic_data(array $data): void
    {
        $required_fields = ['customer_id', 'branch_id', 'items'];

        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!is_array($data['items']) || empty($data['items'])) {
            throw new InvalidArgumentException('Order must contain at least one item');
        }

        if (!is_numeric($data['customer_id'])) {
            throw new InvalidArgumentException('Invalid customer_id');
        }

        if (!is_numeric($data['branch_id'])) {
            throw new InvalidArgumentException('Invalid branch_id');
        }

        // Validate delivery type
        if (isset($data['delivery_type']) && !in_array($data['delivery_type'], ['pickup', 'delivery'])) {
            throw new InvalidArgumentException('delivery_type must be either "pickup" or "delivery"');
        }

        // Validate payment method
        if (isset($data['payment_method']) && !in_array($data['payment_method'], ['cash', 'card', 'online', 'woocommerce'])) {
            throw new InvalidArgumentException('Invalid payment_method');
        }
    }

    /**
     * Validate and calculate a single order item with server-side price
     *
     * CRITICAL: This prevents price manipulation by calculating prices from database
     */
    private function validate_and_calculate_item(array $item_data, $branch): array
    {
        // Validate required fields
        if (!isset($item_data['product_id']) || !isset($item_data['quantity'])) {
            throw new InvalidArgumentException('Each item must have product_id and quantity');
        }

        $product_id = (int) $item_data['product_id'];
        $quantity = (int) $item_data['quantity'];
        $customizations = $item_data['customizations'] ?? [];

        if ($quantity < 1) {
            throw new InvalidArgumentException('Item quantity must be at least 1');
        }

        // Get product from database
        $product = $this->productRepo->get($product_id);
        if (!$product) {
            throw new InvalidArgumentException("Product not found: {$product_id}");
        }

        // Check if product is available at this branch
        if (!empty($branch->product_availability) && !$branch->isProductAvailable($product_id)) {
            throw new InvalidArgumentException("Product '{$product->name}' is not available at this branch");
        }

        // Validate customizations if present
        $unit_price = 0.0;
        if (!empty($customizations)) {
            // Validate customizations against constraints and calculate price
            $this->validator->validateProductCustomizations($product_id, $customizations);
            $unit_price = $this->validator->calculateTotalPrice($product_id, $customizations);
        } else {
            // No customizations - use base product price
            $unit_price = $product->discounted_price ?? $product->price;
        }

        $item_total = $unit_price * $quantity;

        // Prepare validated item data
        return [
            'product_id'   => $product_id,
            'product_name' => $product->name,
            'quantity'     => $quantity,
            'unit_price'   => $unit_price,
            'total_price'  => $item_total,
            'modifications'=> $customizations, // Store customizations as modifications
            'notes'        => $item_data['notes'] ?? null,
        ];
    }

    /**
     * Generate secure tracking token for guest orders
     */
    private function generate_tracking_token(): string
    {
        return 'tk_' . bin2hex(random_bytes(16));
    }

    /**
     * Create WooCommerce order for payment processing
     */
    private function create_woocommerce_order(Order $order): int
    {
        if (!function_exists('wc_create_order')) {
            throw new RuntimeException('WooCommerce is not active');
        }

        $wc_order = wc_create_order([
            'customer_id' => $order->customer_id,
        ]);

        // Add line items
        foreach ($order->order_items as $item) {
            $wc_order->add_product(
                wc_get_product($item['product_id']),
                $item['quantity'],
                [
                    'subtotal' => $item['unit_price'] * $item['quantity'],
                    'total' => $item['total_price'],
                ]
            );
        }

        // Set totals
        $wc_order->set_total($order->subtotal, 'cart');
        $wc_order->set_total($order->tax_amount, 'tax');
        $wc_order->set_total($order->delivery_fee, 'shipping');
        $wc_order->calculate_totals();

        $wc_order->save();

        return $wc_order->get_id();
    }

    /**
     * Simple rate limiting check
     */
    private function check_rate_limit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'squidly_order_rate_' . md5($ip);

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
     * Public permission callback (no authentication required)
     */
    public function public_permission_callback($request): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get arguments for create order endpoint
     */
    private function get_create_order_args(): array
    {
        return [
            'customer_id' => [
                'description'       => 'Customer ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'branch_id' => [
                'description'       => 'Branch ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'items' => [
                'description' => 'Order items array',
                'type'        => 'array',
                'required'    => true,
            ],
            'delivery_type' => [
                'description' => 'Delivery type (pickup or delivery)',
                'type'        => 'string',
                'enum'        => ['pickup', 'delivery'],
                'default'     => 'pickup',
            ],
            'delivery_address' => [
                'description' => 'Delivery address (required if delivery_type is delivery)',
                'type'        => 'string',
            ],
            'delivery_time' => [
                'description' => 'Preferred delivery/pickup time',
                'type'        => 'string',
            ],
            'payment_method' => [
                'description' => 'Payment method',
                'type'        => 'string',
                'enum'        => ['cash', 'card', 'online', 'woocommerce'],
                'default'     => 'online',
            ],
            'notes' => [
                'description' => 'Order notes',
                'type'        => 'string',
            ],
        ];
    }
}
