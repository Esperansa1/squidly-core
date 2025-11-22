<?php
declare(strict_types=1);

/**
 * Cart Item Model
 *
 * Represents a single item in a shopping cart.
 */
class CartItem
{
    public string $id;           // Unique ID within cart (item_xxxxx)
    public int $product_id;
    public string $product_name; // Cached for display
    public int $quantity;
    public float $unit_price;    // Server-calculated price
    public float $total_price;   // quantity × unit_price
    public array $customizations;
    public ?string $notes;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? 'item_' . bin2hex(random_bytes(8));
        $this->product_id = $data['product_id'];
        $this->product_name = $data['product_name'];
        $this->quantity = $data['quantity'];
        $this->unit_price = $data['unit_price'];
        $this->total_price = $data['total_price'] ?? ($this->unit_price * $this->quantity);
        $this->customizations = $data['customizations'] ?? [];
        $this->notes = $data['notes'] ?? null;
    }

    /**
     * Convert cart item to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'customizations' => $this->customizations,
            'notes' => $this->notes,
        ];
    }
}
