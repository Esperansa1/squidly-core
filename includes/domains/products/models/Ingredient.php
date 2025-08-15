<?php
declare(strict_types=1);

class Ingredient
{
    public int $id;
    public string $name;
    public float $price;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->price = (float) $data['price'];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
        ];
    }
}
