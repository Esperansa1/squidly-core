<?php

declare(strict_types=1);

class StoreBranch
{
    public int $id;
    public string $name;

    /** @var Product[] */
    public array $products = [];

    /** @var Ingredient[] */
    public array $ingredients = [];

    /** @var array<int, bool> */
    public array $product_availability = []; // [product_id => true/false]

    /** @var array<int, bool> */
    public array $ingredient_availability = []; // [ingredient_id => true/false]

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];

        // Products
        $this->products = array_map(
            fn($item) => $item instanceof Product ? $item : new Product($item),
            $data['products'] ?? []
        );

        // Ingredients
        $this->ingredients = array_map(
            fn($item) => $item instanceof Ingredient ? $item : new Ingredient($item),
            $data['ingredients'] ?? []
        );

        // Availability maps
        $this->product_availability = $data['product_availability'] ?? [];
        $this->ingredient_availability = $data['ingredient_availability'] ?? [];
    }

    public function isProductAvailable(int $product_id): bool
    {
        return $this->product_availability[$product_id] ?? false;
    }

    public function isIngredientAvailable(int $ingredient_id): bool
    {
        return $this->ingredient_availability[$ingredient_id] ?? false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'products' => array_map(fn(Product $p) => $p->toArray(), $this->products),
            'ingredients' => array_map(fn(Ingredient $i) => $i->toArray(), $this->ingredients),
            'product_availability' => $this->product_availability,
            'ingredient_availability' => $this->ingredient_availability,
        ];
    }
}
