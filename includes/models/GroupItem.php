<?php

declare(strict_types=1);

class GroupItem
{
    public int $id;
    public int $item_id;
    public ItemType $item_type;
    public ?float $override_price;

    public function __construct(array $data)
    {
        if (! isset($data['id'])) {
            throw new InvalidArgumentException('GroupItem requires its own id.');
        }
        $this->id = (int) $data['id'];

        if (!isset($data['item_id'], $data['item_type'])) {
            throw new InvalidArgumentException("GroupItem requires item_id and item_type.");
        }

        $this->item_id = (int) $data['item_id'];

        if (!ItemType::tryFrom($data['item_type'])) {
            throw new InvalidArgumentException("Invalid item_type; must be 'product' or 'ingredient'.");
        }

        $this->item_type = ItemType::from($data['item_type']);
        $this->override_price = isset($data['override_price']) ? (float) $data['override_price'] : null;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'item_id'        => $this->item_id,
            'item_type'      => $this->item_type->value,
            'override_price' => $this->override_price,
        ];
    }

    
    /**
     * Return the concrete Product | Ingredient with price overridden
     * when $override_price is not null.
     *
     * @param ProductRepository|null    $prodRepo  (DI‑friendly)
     * @param IngredientRepository|null $ingRepo   (DI‑friendly)
     *
     * @return Product|Ingredient|null
     */
    public function getItem(
        ?ProductRepository    $prodRepo = null,
        ?IngredientRepository $ingRepo  = null
    ) /* no return type for PHP 7.4 */ {

        // Select repository based on enum value
        $item = $this->item_type->isProduct()
            ? ($prodRepo ?? new ProductRepository())->get($this->item_id)
            : ($ingRepo  ?? new IngredientRepository())->get($this->item_id);


        if ( ! $item ) {
            return null;                    // ID missing
        }

        $item = clone $item;

        // Override price if requested
        if ($this->override_price !== null) {
            $item->price = $this->override_price;
        }

        return $item;
    }
}
