<?php

declare(strict_types=1);

class GroupItem
{
    public int $item_id;
    public ProductGroupType $item_type;
    public ?float $override_price;

    public function __construct(array $data)
    {
        if (!isset($data['item_id'], $data['item_type'])) {
            throw new InvalidArgumentException("GroupItem requires item_id and item_type.");
        }

        $this->item_id = (int) $data['item_id'];

        if (!ProductGroupType::tryFrom($data['item_type'])) {
            throw new InvalidArgumentException("Invalid item_type; must be 'product' or 'ingredient'.");
        }

        $this->item_type = ProductGroupType::from($data['item_type']);
        $this->override_price = isset($data['override_price']) ? (float) $data['override_price'] : null;
    }

    public function toArray(): array
    {
        return [
            'item_id'        => $this->item_id,
            'item_type'      => $this->item_type->value,
            'override_price' => $this->override_price,
        ];
    }
}
