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

    public function __construct()
    {
        $this->productRepo = new ProductRepository();
        $this->validator = new ProductCustomizationValidator();
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
