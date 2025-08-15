<?php
declare(strict_types=1);

/**
 * OrderItem Model
 * 
 * Represents a single line item within an order.
 * Contains product reference, quantity, pricing, and modifications.
 */
class OrderItem
{
    public int $product_id;
    public string $product_name; // Stored for historical purposes
    public int $quantity;
    public float $unit_price; // Price at time of order
    public float $total_price; // quantity × unit_price
    public array $modifications; // Special requests/customizations
    public ?string $notes; // Item-specific notes

    public function __construct(
        int $product_id,
        string $product_name,
        int $quantity,
        float $unit_price,
        array $modifications = [],
        ?string $notes = null
    ) {
        $this->product_id = $product_id;
        $this->product_name = $product_name;
        $this->quantity = $quantity;
        $this->unit_price = $unit_price;
        $this->modifications = $modifications;
        $this->notes = $notes;
        $this->calculateTotal();
    }

    /**
     * Calculate total price for this item
     */
    public function calculateTotal(): void
    {
        $this->total_price = $this->quantity * $this->unit_price;
    }

    /**
     * Create OrderItem from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['product_id'],
            $data['product_name'],
            $data['quantity'],
            $data['unit_price'],
            $data['modifications'] ?? [],
            $data['notes'] ?? null
        );
    }

    /**
     * Convert OrderItem to array for storage
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'modifications' => $this->modifications,
            'notes' => $this->notes,
        ];
    }

    /**
     * Get display string for this item
     */
    public function getDisplayString(): string
    {
        $display = "{$this->quantity}x {$this->product_name}";
        
        if (!empty($this->modifications)) {
            $mods = implode(', ', $this->modifications);
            $display .= " ({$mods})";
        }
        
        return $display;
    }

    /**
     * Update quantity and recalculate total
     */
    public function updateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }
        
        $this->quantity = $quantity;
        $this->calculateTotal();
    }

    /**
     * Update unit price and recalculate total
     */
    public function updateUnitPrice(float $unit_price): void
    {
        if ($unit_price < 0) {
            throw new InvalidArgumentException('Unit price cannot be negative');
        }
        
        $this->unit_price = $unit_price;
        $this->calculateTotal();
    }
}