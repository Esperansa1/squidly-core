<?php
declare(strict_types=1);

/**
 * Cart Model
 *
 * Represents a shopping cart for guest/customer ordering.
 * Carts are session-based with automatic expiration.
 */
class Cart
{
    public string $token;
    public ?int $customer_id;
    public int $branch_id;
    public array $items; // Array of CartItem objects
    public string $created_at;
    public string $expires_at;
    public string $last_updated;

    public function __construct(array $data)
    {
        $this->token = $data['token'];
        $this->customer_id = $data['customer_id'] ?? null;
        $this->branch_id = $data['branch_id'];

        // Convert array items to CartItem objects
        $this->items = array_map(function($item) {
            return $item instanceof CartItem ? $item : new CartItem($item);
        }, $data['items'] ?? []);

        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->expires_at = $data['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+2 hours'));
        $this->last_updated = $data['last_updated'] ?? current_time('mysql');
    }

    /**
     * Convert cart to array for storage/response
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'items' => array_map(function($item) {
                return $item instanceof CartItem ? $item->toArray() : $item;
            }, $this->items),
            'item_count' => $this->getItemCount(),
            'subtotal' => $this->getSubtotal(),
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'last_updated' => $this->last_updated,
        ];
    }

    /**
     * Get total number of items in cart
     */
    public function getItemCount(): int
    {
        return array_reduce($this->items, function($carry, $item) {
            $quantity = $item instanceof CartItem ? $item->quantity : $item['quantity'];
            return $carry + $quantity;
        }, 0);
    }

    /**
     * Get cart subtotal
     */
    public function getSubtotal(): float
    {
        return array_reduce($this->items, function($carry, $item) {
            $total = $item instanceof CartItem ? $item->total_price : $item['total_price'];
            return $carry + $total;
        }, 0.0);
    }

    /**
     * Check if cart has expired
     */
    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * Add item to cart
     */
    public function addItem(CartItem $item): void
    {
        $this->items[] = $item;
        $this->last_updated = current_time('mysql');
    }

    /**
     * Update item in cart
     */
    public function updateItem(string $item_id, array $updates): bool
    {
        foreach ($this->items as $index => $item) {
            $id = $item instanceof CartItem ? $item->id : $item['id'];
            if ($id === $item_id) {
                if (isset($updates['quantity'])) {
                    if ($item instanceof CartItem) {
                        $item->quantity = $updates['quantity'];
                        $item->total_price = $item->unit_price * $item->quantity;
                    } else {
                        $this->items[$index]['quantity'] = $updates['quantity'];
                        $this->items[$index]['total_price'] = $this->items[$index]['unit_price'] * $updates['quantity'];
                    }
                }
                if (isset($updates['notes'])) {
                    if ($item instanceof CartItem) {
                        $item->notes = $updates['notes'];
                    } else {
                        $this->items[$index]['notes'] = $updates['notes'];
                    }
                }
                $this->last_updated = current_time('mysql');
                return true;
            }
        }
        return false;
    }

    /**
     * Remove item from cart
     */
    public function removeItem(string $item_id): bool
    {
        foreach ($this->items as $index => $item) {
            $id = $item instanceof CartItem ? $item->id : $item['id'];
            if ($id === $item_id) {
                array_splice($this->items, $index, 1);
                $this->last_updated = current_time('mysql');
                return true;
            }
        }
        return false;
    }

    /**
     * Clear all items from cart
     */
    public function clear(): void
    {
        $this->items = [];
        $this->last_updated = current_time('mysql');
    }

    /**
     * Find item by ID
     */
    public function findItem(string $item_id): ?array
    {
        foreach ($this->items as $item) {
            $id = $item instanceof CartItem ? $item->id : $item['id'];
            if ($id === $item_id) {
                return $item instanceof CartItem ? $item->toArray() : $item;
            }
        }
        return null;
    }
}
