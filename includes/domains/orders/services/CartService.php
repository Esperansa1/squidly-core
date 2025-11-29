<?php
declare(strict_types=1);

/**
 * Cart Service
 *
 * Handles cart storage, retrieval, and expiration using WordPress transients.
 * Carts are stored with automatic expiration (default 2 hours).
 */
class CartService
{
    private const TRANSIENT_PREFIX = 'squidly_cart_';
    private const DEFAULT_EXPIRATION = 7200; // 2 hours in seconds

    private ProductRepository $productRepo;
    private ProductCustomizationValidator $validator;
    private DeliveryFeeService $deliveryFeeService;

    public function __construct()
    {
        $this->productRepo = new ProductRepository();
        $this->validator = new ProductCustomizationValidator();
        $this->deliveryFeeService = new DeliveryFeeService();
    }

    /**
     * Create a new cart
     */
    public function createCart(int $branch_id, ?int $customer_id = null): Cart
    {
        $token = $this->generateToken();

        $cart = new Cart([
            'token' => $token,
            'customer_id' => $customer_id,
            'branch_id' => $branch_id,
            'items' => [],
        ]);

        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Get cart by token
     */
    public function getCart(string $token): ?Cart
    {
        $data = get_transient($this->getTransientKey($token));

        if ($data === false) {
            return null; // Cart not found or expired
        }

        $cart = new Cart($data);

        // Check if cart has expired
        if ($cart->isExpired()) {
            $this->deleteCart($token);
            return null;
        }

        return $cart;
    }

    /**
     * Save cart to transients
     */
    public function saveCart(Cart $cart): bool
    {
        $expiration_seconds = strtotime($cart->expires_at) - time();

        if ($expiration_seconds <= 0) {
            return false; // Cart already expired
        }

        return set_transient(
            $this->getTransientKey($cart->token),
            $cart->toArray(),
            $expiration_seconds
        );
    }

    /**
     * Delete cart
     */
    public function deleteCart(string $token): bool
    {
        return delete_transient($this->getTransientKey($token));
    }

    /**
     * Add item to cart with validation and price calculation
     */
    public function addItemToCart(
        string $token,
        int $product_id,
        int $quantity,
        array $customizations = [],
        ?string $notes = null
    ): ?Cart {
        $cart = $this->getCart($token);

        if (!$cart) {
            return null; // Cart not found
        }

        // Get product from database
        $product = $this->productRepo->get($product_id);
        if (!$product) {
            throw new InvalidArgumentException("Product not found: {$product_id}");
        }

        // Validate quantity
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        // Validate and calculate price with customizations
        $unit_price = 0.0;
        if (!empty($customizations)) {
            $this->validator->validateProductCustomizations($product_id, $customizations);
            $unit_price = $this->validator->calculateTotalPrice($product_id, $customizations);
        } else {
            $unit_price = $product->discounted_price ?? $product->price;
        }

        // Create cart item
        $cart_item = new CartItem([
            'product_id' => $product_id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'customizations' => $customizations,
            'notes' => $notes,
        ]);

        $cart->addItem($cart_item);
        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Update cart item
     */
    public function updateCartItem(
        string $token,
        string $item_id,
        array $updates
    ): ?Cart {
        $cart = $this->getCart($token);

        if (!$cart) {
            return null;
        }

        // Validate quantity if provided
        if (isset($updates['quantity']) && $updates['quantity'] < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        $updated = $cart->updateItem($item_id, $updates);

        if (!$updated) {
            throw new InvalidArgumentException("Cart item not found: {$item_id}");
        }

        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Remove item from cart
     */
    public function removeCartItem(string $token, string $item_id): ?Cart
    {
        $cart = $this->getCart($token);

        if (!$cart) {
            return null;
        }

        $removed = $cart->removeItem($item_id);

        if (!$removed) {
            throw new InvalidArgumentException("Cart item not found: {$item_id}");
        }

        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(string $token): ?Cart
    {
        $cart = $this->getCart($token);

        if (!$cart) {
            return null;
        }

        $cart->clear();
        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Associate cart with customer
     */
    public function associateCustomer(string $token, int $customer_id): ?Cart
    {
        $cart = $this->getCart($token);

        if (!$cart) {
            return null;
        }

        $cart->customer_id = $customer_id;
        $cart->last_updated = current_time('mysql');
        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Convert cart to order data
     * Returns order data array ready for OrderRepository->create()
     * Does NOT delete the cart - that's done after successful order creation
     */
    public function convertToOrderData(
        string $token,
        array $checkout_data
    ): array {
        $cart = $this->getCart($token);

        if (!$cart) {
            throw new InvalidArgumentException('Cart not found or expired');
        }

        if (empty($cart->items)) {
            throw new InvalidArgumentException('Cannot checkout with empty cart');
        }

        // Validate required checkout data
        $this->validateCheckoutData($checkout_data);

        // Convert cart items to order items format
        $order_items = [];
        $subtotal = 0.0;

        foreach ($cart->items as $cart_item) {
            $order_items[] = [
                'product_id'    => $cart_item->product_id,
                'product_name'  => $cart_item->product_name,
                'quantity'      => $cart_item->quantity,
                'unit_price'    => $cart_item->unit_price,
                'total_price'   => $cart_item->total_price,
                'modifications' => $cart_item->customizations,
                'notes'         => $cart_item->notes,
            ];
            $subtotal += $cart_item->total_price;
        }

        // Calculate delivery fee (if delivery)
        $delivery_fee = 0.0;
        $delivery_address = $checkout_data['delivery_address'] ?? null;
        if (($checkout_data['delivery_type'] ?? 'pickup') === 'delivery') {
            try {
                $delivery_fee = $this->deliveryFeeService->calculateFee(
                    $cart->branch_id,
                    $delivery_address,
                    $subtotal
                );
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException('Delivery fee calculation failed: ' . $e->getMessage());
            }
        }

        // Calculate tax and total
        $tax_rate = (float) get_option('squidly_tax_rate', 0.17);
        $tax_amount = $subtotal * $tax_rate;
        $total_amount = $subtotal + $tax_amount + $delivery_fee;

        // Generate tracking token
        $tracking_token = 'tk_' . bin2hex(random_bytes(16));

        // Prepare order data
        return [
            'customer_id'          => $cart->customer_id ?? $checkout_data['customer_id'],
            'branch_id'            => $cart->branch_id,
            'status'               => Order::STATUS_PENDING,
            'subtotal'             => $subtotal,
            'tax_amount'           => $tax_amount,
            'delivery_fee'         => $delivery_fee,
            'total_amount'         => $total_amount,
            'payment_status'       => Order::PAYMENT_PENDING,
            'payment_method'       => $checkout_data['payment_method'] ?? 'online',
            'delivery_type'        => $checkout_data['delivery_type'] ?? 'pickup',
            'delivery_address'     => $checkout_data['delivery_address'] ?? null,
            'pickup_time'          => $checkout_data['delivery_time'] ?? null,
            'notes'                => sanitize_textarea_field($checkout_data['notes'] ?? ''),
            'special_instructions' => sanitize_textarea_field($checkout_data['notes'] ?? ''),
            'tracking_token'       => $tracking_token,
            'order_items'          => $order_items,
        ];
    }

    /**
     * Validate checkout data
     */
    private function validateCheckoutData(array $data): void
    {
        // Customer ID required if cart doesn't have one
        if (empty($data['customer_id'])) {
            throw new InvalidArgumentException('customer_id is required for checkout');
        }

        // Validate delivery type
        if (isset($data['delivery_type']) && !in_array($data['delivery_type'], ['pickup', 'delivery'])) {
            throw new InvalidArgumentException('delivery_type must be either "pickup" or "delivery"');
        }

        // Validate payment method
        if (isset($data['payment_method'])) {
            error_log('💳 CartService - payment_method value: ' . var_export($data['payment_method'], true) . ' (type: ' . gettype($data['payment_method']) . ')');
            error_log('💳 CartService - valid methods: ' . json_encode(['cash', 'card', 'online', 'woocommerce']));

            if (!in_array($data['payment_method'], ['cash', 'card', 'online', 'woocommerce'], true)) {
                error_log('💳 CartService - INVALID payment_method detected!');
                throw new InvalidArgumentException('Invalid payment_method');
            }
        }

        // Require delivery address if delivery type is delivery
        if (($data['delivery_type'] ?? 'pickup') === 'delivery' && empty($data['delivery_address'])) {
            throw new InvalidArgumentException('delivery_address is required for delivery orders');
        }
    }

    /**
     * Generate unique cart token
     */
    private function generateToken(): string
    {
        return 'cart_' . bin2hex(random_bytes(16));
    }

    /**
     * Get transient key for cart token
     */
    private function getTransientKey(string $token): string
    {
        return self::TRANSIENT_PREFIX . $token;
    }
}
