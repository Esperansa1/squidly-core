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

    // Rate limiting
    private const RATE_LIMIT_REQUESTS = 30;
    private const RATE_LIMIT_WINDOW = 60; // 1 minute

    public function __construct()
    {
        $this->cartService = new CartService();
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
                'description' => 'Product customizations',
                'type'        => 'array',
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
}
