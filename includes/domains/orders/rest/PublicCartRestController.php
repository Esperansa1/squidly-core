<?php
declare(strict_types=1);

/**
 * Public Cart REST API Controller
 *
 * Handles shopping cart operations for customer app (no authentication required)
 * Provides cart creation, item management, and retrieval
 *
 * Security:
 * - Rate limiting
 * - Server-side price calculation
 * - Customization validation
 * - Cart token-based access
 */
class PublicCartRestController extends WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'public/cart';

    private CartService $cartService;
    private OrderRepository $orderRepo;
    private CustomerRepository $customerRepo;

    // Rate limiting
    private const RATE_LIMIT_REQUESTS = 30;
    private const RATE_LIMIT_WINDOW = 60; // 1 minute

    public function __construct()
    {
        $this->cartService = new CartService();
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // POST /squidly/v1/public/cart - Create cart or add item
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_or_add_to_cart'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_create_cart_args(),
            ],
        ]);

        // GET /squidly/v1/public/cart/{token} - Get cart
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<token>[a-zA-Z0-9_]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_cart'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'token' => [
                        'description' => 'Cart token',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // PUT /squidly/v1/public/cart/{token}/item/{item_id} - Update item
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<token>[a-zA-Z0-9_]+)/item/(?P<item_id>[a-zA-Z0-9_]+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_cart_item'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_update_item_args(),
            ],
        ]);

        // DELETE /squidly/v1/public/cart/{token}/item/{item_id} - Remove item
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<token>[a-zA-Z0-9_]+)/item/(?P<item_id>[a-zA-Z0-9_]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'remove_cart_item'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'token' => [
                        'description' => 'Cart token',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'item_id' => [
                        'description' => 'Item ID',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // DELETE /squidly/v1/public/cart/{token} - Clear cart
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<token>[a-zA-Z0-9_]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'clear_cart'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'token' => [
                        'description' => 'Cart token',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // POST /squidly/v1/public/cart/{token}/checkout - Convert cart to order
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<token>[a-zA-Z0-9_]+)/checkout', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'checkout'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_checkout_args(),
            ],
        ]);
    }

    /**
     * Create cart or add item to existing cart
     */
    public function create_or_add_to_cart($request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again in a moment.'
            ], 429);
        }

        try {
            $data = $request->get_json_params();

            $token = $data['token'] ?? null;
            $branch_id = $data['branch_id'];
            $product_id = $data['product_id'];
            $quantity = $data['quantity'] ?? 1;
            $customizations = $data['customizations'] ?? [];
            $notes = $data['notes'] ?? null;
            $customer_id = $data['customer_id'] ?? null;

            // Debug logging
            error_log('📥 PublicCartRestController - Raw JSON params: ' . json_encode($data));
            error_log('📥 PublicCartRestController - Received customizations: ' . json_encode($customizations));
            error_log('📥 PublicCartRestController - Customizations type: ' . gettype($customizations));
            if (is_array($customizations)) {
                error_log('📥 PublicCartRestController - Customizations keys: ' . json_encode(array_keys($customizations)));
                error_log('📥 PublicCartRestController - First key type: ' . gettype(array_key_first($customizations)));
                error_log('📥 PublicCartRestController - First key value: ' . var_export(array_key_first($customizations), true));
            }

            // Track if we're creating a new cart
            $is_new_cart = !$token;

            // If no token provided, create new cart
            if (!$token) {
                $cart = $this->cartService->createCart($branch_id, $customer_id);
                $token = $cart->token;
            }

            // Add item to cart
            $cart = $this->cartService->addItemToCart(
                $token,
                $product_id,
                $quantity,
                $customizations,
                $notes
            );

            if (!$cart) {
                return new WP_REST_Response([
                    'error' => 'Cart not found or expired'
                ], 404);
            }

            return new WP_REST_Response([
                'cart' => $cart->toArray(),
                'message' => 'Item added to cart successfully',
            ], $is_new_cart ? 201 : 200);

        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            error_log("Cart operation error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to add item to cart',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Get cart by token
     */
    public function get_cart($request)
    {
        $token = sanitize_text_field($request->get_param('token'));

        $cart = $this->cartService->getCart($token);

        if (!$cart) {
            return new WP_REST_Response([
                'error' => 'Cart not found or expired'
            ], 404);
        }

        return new WP_REST_Response([
            'cart' => $cart->toArray(),
        ], 200);
    }

    /**
     * Update cart item
     */
    public function update_cart_item($request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again in a moment.'
            ], 429);
        }

        try {
            $token = sanitize_text_field($request->get_param('token'));
            $item_id = sanitize_text_field($request->get_param('item_id'));
            $data = $request->get_json_params();

            $updates = [];
            if (isset($data['quantity'])) {
                $updates['quantity'] = (int) $data['quantity'];
            }
            if (isset($data['notes'])) {
                $updates['notes'] = sanitize_textarea_field($data['notes']);
            }

            $cart = $this->cartService->updateCartItem($token, $item_id, $updates);

            if (!$cart) {
                return new WP_REST_Response([
                    'error' => 'Cart not found or expired'
                ], 404);
            }

            return new WP_REST_Response([
                'cart' => $cart->toArray(),
                'message' => 'Cart item updated successfully',
            ], 200);

        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            error_log("Cart update error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to update cart item',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove cart item
     */
    public function remove_cart_item($request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again in a moment.'
            ], 429);
        }

        try {
            $token = sanitize_text_field($request->get_param('token'));
            $item_id = sanitize_text_field($request->get_param('item_id'));

            $cart = $this->cartService->removeCartItem($token, $item_id);

            if (!$cart) {
                return new WP_REST_Response([
                    'error' => 'Cart not found or expired'
                ], 404);
            }

            return new WP_REST_Response([
                'cart' => $cart->toArray(),
                'message' => 'Item removed from cart successfully',
            ], 200);

        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            error_log("Cart remove error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to remove cart item',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Clear cart (remove all items)
     */
    public function clear_cart($request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again in a moment.'
            ], 429);
        }

        try {
            $token = sanitize_text_field($request->get_param('token'));

            $cart = $this->cartService->clearCart($token);

            if (!$cart) {
                return new WP_REST_Response([
                    'error' => 'Cart not found or expired'
                ], 404);
            }

            return new WP_REST_Response([
                'cart' => $cart->toArray(),
                'message' => 'Cart cleared successfully',
            ], 200);

        } catch (Exception $e) {
            error_log("Cart clear error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to clear cart',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    /**
     * Simple rate limiting check
     */
    private function check_rate_limit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'squidly_cart_rate_' . md5($ip);

        $requests = get_transient($transient_key);
        if ($requests === false) {
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }

        if ($requests >= self::RATE_LIMIT_REQUESTS) {
            return false;
        }

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
     * Get arguments for create/add cart endpoint
     */
    private function get_create_cart_args(): array
    {
        return [
            'token' => [
                'description' => 'Existing cart token (optional for new cart)',
                'type'        => 'string',
                'required'    => false,
            ],
            'branch_id' => [
                'description'       => 'Branch ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'product_id' => [
                'description'       => 'Product ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'quantity' => [
                'description'       => 'Quantity',
                'type'              => 'integer',
                'default'           => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'customizations' => [
                'description' => 'Product customizations (object with group IDs as keys)',
                'type'        => 'object',
                'default'     => [],
            ],
            'notes' => [
                'description' => 'Item notes',
                'type'        => 'string',
            ],
            'customer_id' => [
                'description' => 'Customer ID (optional)',
                'type'        => 'integer',
            ],
        ];
    }

    /**
     * Get arguments for update item endpoint
     */
    private function get_update_item_args(): array
    {
        return [
            'token' => [
                'description' => 'Cart token',
                'type'        => 'string',
                'required'    => true,
            ],
            'item_id' => [
                'description' => 'Item ID',
                'type'        => 'string',
                'required'    => true,
            ],
            'quantity' => [
                'description'       => 'New quantity',
                'type'              => 'integer',
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'notes' => [
                'description' => 'Item notes',
                'type'        => 'string',
            ],
        ];
    }

    /**
     * Checkout - Convert cart to order
     */
    public function checkout($request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again in a moment.'
            ], 429);
        }

        try {
            $token = sanitize_text_field($request->get_param('token'));
            $data = $request->get_json_params();

            // Debug logging
            error_log('🛒 Checkout - Received data: ' . json_encode($data));

            // Step 1: Validate customer exists
            $customer_id = $data['customer_id'] ?? null;
            if (!$customer_id) {
                return new WP_REST_Response([
                    'error' => 'customer_id is required'
                ], 400);
            }

            $customer = $this->customerRepo->get($customer_id);
            if (!$customer) {
                return new WP_REST_Response([
                    'error' => 'Customer not found'
                ], 400);
            }

            // Step 2: Convert cart to order data
            $order_data = $this->cartService->convertToOrderData($token, $data);

            // Step 3: Create order
            $order_id = $this->orderRepo->create($order_data);

            // Step 4: Get created order
            $order = $this->orderRepo->get($order_id);

            // Step 5: Prepare payment URL (if online payment)
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

            // Step 6: Clear cart after successful order creation
            $this->cartService->clearCart($token);

            // Step 7: Return response
            return new WP_REST_Response([
                'order_id'        => $order_id,
                'tracking_token'  => $order->tracking_token,
                'total_price'     => $order->total_amount,
                'subtotal'        => $order->subtotal,
                'tax_amount'      => $order->tax_amount,
                'delivery_fee'    => $order->delivery_fee,
                'status'          => $order->status,
                'payment_status'  => $order->payment_status,
                'payment_url'     => $payment_url,
                'message'         => 'Order created successfully',
            ], 201);

        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            error_log("Checkout error: " . $e->getMessage());

            return new WP_REST_Response([
                'error' => 'Failed to complete checkout',
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
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

        // Set payment method to Cash on Delivery
        $wc_order->set_payment_method('cod');
        $wc_order->set_payment_method_title('Cash on delivery');

        error_log('🛍️ WC Order created with ID: ' . $wc_order->get_id());
        error_log('🛍️ Order items count: ' . count($order->order_items));

        // Add line items
        foreach ($order->order_items as $item) {
            error_log('🛍️ Adding product ' . $item->product_id . ' (qty: ' . $item->quantity . ', unit_price: ' . $item->unit_price . ')');
            $wc_order->add_product(
                wc_get_product($item->product_id),
                $item->quantity,
                [
                    'subtotal' => $item->unit_price * $item->quantity,
                    'total' => $item->total_price,
                ]
            );
        }

        // Set totals using correct WooCommerce 3.0+ methods
        $wc_order->set_cart_tax($order->tax_amount);
        $wc_order->set_shipping_total($order->delivery_fee);
        $wc_order->calculate_totals();

        error_log('🛍️ WC Order totals - Subtotal: ' . $wc_order->get_subtotal() . ', Total: ' . $wc_order->get_total());
        error_log('🛍️ WC Order status: ' . $wc_order->get_status());

        $wc_order->save();

        return $wc_order->get_id();
    }

    /**
     * Get arguments for checkout endpoint
     */
    private function get_checkout_args(): array
    {
        return [
            'token' => [
                'description' => 'Cart token',
                'type'        => 'string',
                'required'    => true,
            ],
            'customer_id' => [
                'description'       => 'Customer ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
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
            'delivery_fee' => [
                'description' => 'Delivery fee (calculated by frontend)',
                'type'        => 'number',
                'default'     => 0.0,
            ],
            'notes' => [
                'description' => 'Order notes',
                'type'        => 'string',
            ],
        ];
    }
}
