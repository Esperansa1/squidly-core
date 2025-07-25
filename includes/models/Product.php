<?php

declare(strict_types=1);

class Product
{
    public int $id;
    public string $name;
    public string $description;
    public float $price;
    public ?float $discounted_price;
    public ?string $category;
    public array $tags;              // string[]
    public array $product_group_ids; // int[]

    public function __construct(array $data)
    {
        $this->id                = (int) $data['id'];
        $this->name              = (string) $data['name'];
        $this->description       = (string) ($data['description'] ?? '');
        $this->price             = (float) $data['price'];
        $this->discounted_price  = isset($data['discounted_price']) ? (float) $data['discounted_price'] : null;
        $this->category          = isset($data['category']) ? (string) $data['category'] : null;
        $this->tags              = $data['tags'] ?? [];
        $this->product_group_ids = $data['product_group_ids'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'description'        => $this->description,
            'price'              => $this->price,
            'discounted_price'   => $this->discounted_price,
            'category'           => $this->category,
            'tags'               => $this->tags,
            'product_group_ids'  => $this->product_group_ids,
        ];
    }
}
