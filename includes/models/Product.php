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

    /**
     * Return a ready-to-render associative array:
     *
     * [
     *     'id'          => 42,
     *     'name'        => 'Hamburger',
     *     'base_price'  => 29.0,
     *     'description' => 'Our signature burger …',
     *     'groups'      => [
     *         [
     *             'group_name' => 'Hamburger Free Ingredients',
     *             'type'       => 'ingredient',
     *             'items'      => [
     *                 ['name'=>'Lettuce', 'price'=>0.0],
     *                 ...
     *             ],
     *         ],
     *         ...
     *     ]
     * ]
     *
     * @param ProductGroupRepository|null    $pgRepo
     * @param GroupItemRepository|null       $giRepo
     * @param ProductRepository|null         $prodRepo
     * @param IngredientRepository|null      $ingRepo
     *
     * @return array
     */
    public function buildProduct(
        ?ProductGroupRepository $pgRepo = null,
        ?GroupItemRepository    $giRepo = null,
        ?ProductRepository      $prodRepo = null,
        ?IngredientRepository   $ingRepo = null
    ): array {
        $pgRepo   ??= new ProductGroupRepository();
        $giRepo   ??= new GroupItemRepository();
        $prodRepo ??= new ProductRepository();
        $ingRepo  ??= new IngredientRepository();

        $groupsOut = [];

        foreach ($this->product_group_ids as $pgId) {
            $group = $pgRepo->get((int) $pgId);
            if (! $group) {
                continue;            // skip deleted groups
            }

            $resolvedItems = $group->getResolvedItems($giRepo, $prodRepo, $ingRepo);

            $groupsOut[] = [
                'group_name' => $group->name,
                'type'       => $group->type->value,
                'items'      => array_map(
                    fn ($i) => ['name' => $i->name, 'price' => $i->price],
                    $resolvedItems
                ),
            ];
        }

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'base_price'  => $this->price,
            'description' => $this->description,
            'groups'      => $groupsOut,
        ];
    }

}
