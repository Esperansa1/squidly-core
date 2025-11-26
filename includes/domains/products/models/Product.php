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
    public ?int $image_id;           // WordPress attachment ID
    public ?string $image_url;       // Product image URL

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
        $this->image_id          = isset($data['image_id']) ? (int) $data['image_id'] : null;
        $this->image_url         = $data['image_url'] ?? null;
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
            'image_id'           => $this->image_id,
            'image_url'          => $this->image_url,
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
                continue;
            }

            $resolved = $group->getResolvedItems($giRepo, $prodRepo, $ingRepo);

            $groupsOut[] = [
                'group_id'       => $group->id,
                'group_name'     => $group->name,
                'type'           => $group->type->value,
                'description'    => $group->description,
                'min_selections' => $group->min_selections,
                'max_selections' => $group->max_selections,
                'items'          => array_map(
                    fn ($i) => ['id' => $i->id, 'name' => $i->name, 'price' => $i->price],
                    $resolved
                ),
            ];
        }

        // start with full DTO, drop the raw IDs, add hydrated data
        $dto = $this->toArray();
        unset($dto['product_group_ids']);
        $dto['groups_product_data'] = $groupsOut;

        return $dto;
    }

    /**
     * Calculate final availability based on product availability and group dependencies
     * A product is only available if:
     * 1. The product itself is marked as available for the branch
     * 2. ALL associated groups are available for the branch
     *
     * @param ProductRepository|null $prodRepo
     * @param ProductGroupRepository|null $pgRepo
     * @return array [branch_id => boolean]
     */
    public function calculateFinalAvailability(
        ?ProductRepository $prodRepo = null,
        ?ProductGroupRepository $pgRepo = null
    ): array {
        $prodRepo ??= new ProductRepository();
        $pgRepo ??= new ProductGroupRepository();

        // Get product's direct availability
        $product_availability = $prodRepo->getAvailability($this->id);

        // Get all branch IDs from the database
        $branch_repository = new StoreBranchRepository();
        $branches = $branch_repository->getAll();

        $final_availability = [];

        foreach ($branches as $branch) {
            $branch_id = $branch->id;
            $product_available = $product_availability[$branch_id] ?? false;

            // If product itself is not available, final availability is false
            if (!$product_available) {
                $final_availability[$branch_id] = false;
                continue;
            }

            // Check if all associated groups are available
            $all_groups_available = true;
            foreach ($this->product_group_ids as $group_id) {
                $group_final_availability = $pgRepo->getFinalAvailability($group_id);
                if (!($group_final_availability[$branch_id] ?? false)) {
                    $all_groups_available = false;
                    break;
                }
            }

            // Product is available only if both product and all groups are available
            $final_availability[$branch_id] = $product_available && $all_groups_available;
        }

        return $final_availability;
    }

    /**
     * Get availability info including direct, group-dependent, and final availability
     *
     * @param ProductRepository|null $prodRepo
     * @param ProductGroupRepository|null $pgRepo
     * @return array
     */
    public function getAvailabilityInfo(
        ?ProductRepository $prodRepo = null,
        ?ProductGroupRepository $pgRepo = null
    ): array {
        $prodRepo ??= new ProductRepository();
        $pgRepo ??= new ProductGroupRepository();

        $direct_availability = $prodRepo->getAvailability($this->id);
        $final_availability = $this->calculateFinalAvailability($prodRepo, $pgRepo);

        // Calculate which branches are affected by group dependencies
        $group_restrictions = [];
        foreach ($direct_availability as $branch_id => $is_direct_available) {
            $is_final_available = $final_availability[$branch_id] ?? false;
            $group_restrictions[$branch_id] = $is_direct_available && !$is_final_available;
        }

        return [
            'direct_availability' => $direct_availability,
            'final_availability' => $final_availability,
            'group_restrictions' => $group_restrictions, // branches where groups prevent availability
        ];
    }

}
