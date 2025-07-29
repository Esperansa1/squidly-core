<?php
declare(strict_types=1);

/**
 * Represents a physical branch (store / restaurant).
 *
 * – All DB I/O stays in StoreBranchRepository; this DTO is pure PHP.
 * – Activity times are normalised to 24-hour “HH:MM” strings.
 */
class StoreBranch
{
    public int    $id;
    public string $name;
    public string $phone;
    public string $city;
    public string $address;
    public bool   $is_open;                 // true = open, false = closed

    /** @var array<string, string[]>  e.g. 'Sunday' => ['08:00-13:00','16:00-21:00'] */
    public array  $activity_times = [];

    public string $kosher_type;             // single choice
    /** @var string[] */
    public array  $accessibility_list = [];

    /** @var Product[] */
    public array  $products = [];
    /** @var Ingredient[] */
    public array  $ingredients = [];

    /** @var array<int, bool> [product_id => available?] */
    public array  $product_availability = [];
    /** @var array<int, bool> [ingredient_id => available?] */
    public array  $ingredient_availability = [];

    public function __construct(array $data)
    {
        $this->id        = (int)    $data['id'];
        $this->name      = (string) $data['name'];
        $this->phone     = (string) $data['phone'];
        $this->city      = (string) $data['city'];
        $this->address   = (string) $data['address'];
        $this->is_open   = (bool)   $data['is_open'];

        $this->activity_times      = $data['activity_times']      ?? [];
        $this->kosher_type         = $data['kosher_type']         ?? '';
        $this->accessibility_list  = $data['accessibility_list']  ?? [];

        $this->products            = array_map(
            fn($p) => $p instanceof Product ? $p : new Product($p),
            $data['products'] ?? []
        );
        $this->ingredients         = array_map(
            fn($i) => $i instanceof Ingredient ? $i : new Ingredient($i),
            $data['ingredients'] ?? []
        );

        $this->product_availability    = $data['product_availability']    ?? [];
        $this->ingredient_availability = $data['ingredient_availability'] ?? [];
    }

    /* ---------- Helper look-ups ---------- */

    public function isProductAvailable(int $product_id): bool
    {
        return $this->product_availability[$product_id] ?? false;
    }

    public function isIngredientAvailable(int $ingredient_id): bool
    {
        return $this->ingredient_availability[$ingredient_id] ?? false;
    }

    /** Flatten everything to an array for JSON / API use. */
    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'phone'                  => $this->phone,
            'city'                   => $this->city,
            'address'                => $this->address,
            'is_open'                => $this->is_open,
            'activity_times'         => $this->activity_times,
            'kosher_type'            => $this->kosher_type,
            'accessibility_list'     => $this->accessibility_list,
            'products'               => array_map(fn(Product $p)    => $p->toArray(), $this->products),
            'ingredients'            => array_map(fn(Ingredient $i) => $i->toArray(), $this->ingredients),
            'product_availability'   => $this->product_availability,
            'ingredient_availability'=> $this->ingredient_availability,
        ];
    }
}
