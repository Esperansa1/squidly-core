<?php

declare(strict_types=1);

class ProductGroup
{
    public int $id;
    public string $name;
    public ProductGroupType $type;
    public array $group_item_ids; // int[]

    public function __construct(array $data)
    {
        $this->id              = (int) $data['id'];
        $this->name            = (string) $data['name'];
        $this->type            = ProductGroupType::from($data['type']);
        $this->group_item_ids  = $data['group_item_ids'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'type'            => $this->type->value,
            'group_item_ids'  => $this->group_item_ids,
        ];
    }
}
