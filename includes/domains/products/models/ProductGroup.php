<?php

declare(strict_types=1);

class ProductGroup
{
    public int $id;
    public string $name;
    public string $description;
    public ItemType $type;
    public array $group_item_ids; // int[]
    public array $availability; // array [branch_id => boolean]
    public int $min_selections; // Minimum items customer must select (0 = optional)
    public int $max_selections; // Maximum items customer can select (0 = unlimited)

    public function __construct(array $data)
    {
        $this->id              = (int) $data['id'];
        $this->name            = (string) $data['name'];
        $this->description     = (string) ($data['description'] ?? '');
        $this->type            = ItemType::from($data['type']);
        $this->group_item_ids  = $data['group_item_ids'] ?? [];
        $this->availability    = $data['availability'] ?? [];
        $this->min_selections  = isset($data['min_selections']) ? (int) $data['min_selections'] : 0;
        $this->max_selections  = isset($data['max_selections']) ? (int) $data['max_selections'] : 0;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'type'            => $this->type->value,
            'group_item_ids'  => $this->group_item_ids,
            'availability'    => $this->availability,
            'min_selections'  => $this->min_selections,
            'max_selections'  => $this->max_selections,
        ];
    }

    public function getGroupItems(): array
    {
        $repo  = new GroupItemRepository();
        $items = [];

        foreach ($this->group_item_ids as $gid) {
            $item = $repo->get((int) $gid);
            if ($item !== null) {          // skip IDs that no longer exist
                $items[] = $item;
            }
        }

        return $items;
    }

    
    /**
     * Get the *final* Product / Ingredient objects referenced by this group.
     *
     * @param GroupItemRepository|null    $groupRepo  allow injection for tests
     * @param ProductRepository|null      $prodRepo   optional injection
     * @param IngredientRepository|null   $ingRepo    optional injection
     *
     * @return array  Product[] | Ingredient[] — order mirrors $group_item_ids
     */
    public function getResolvedItems(
        ?GroupItemRepository $groupRepo = null,
        ?ProductRepository   $prodRepo  = null,
        ?IngredientRepository $ingRepo  = null
    ): array {
        $groupRepo ??= new GroupItemRepository();

        $out = [];
        foreach ($this->group_item_ids as $gid) {
            $groupItem = $groupRepo->get((int) $gid);
            if ( ! $groupItem ) {
                continue;                           // skip missing rows
            }

            $item = $groupItem->getItem($prodRepo, $ingRepo);
            if ($item !== null) {
                $out[] = $item;                    // honour override price
            }
        }

        return $out;
    }

    /**
     * Calculate availability based on constituent items
     * A group is available in a branch only if ALL its items are available in that branch
     *
     * @param ProductRepository|null $prodRepo
     * @param IngredientRepository|null $ingRepo
     * @return array [branch_id => boolean]
     */
    public function calculateAvailability(
        ?ProductRepository $prodRepo = null,
        ?IngredientRepository $ingRepo = null
    ): array {
        $prodRepo ??= new ProductRepository();
        $ingRepo ??= new IngredientRepository();

        // Get all branch IDs from the database
        $branch_repository = new StoreBranchRepository();
        $branches = $branch_repository->getAll();

        $calculated_availability = [];

        foreach ($branches as $branch) {
            $branch_id = $branch->id;
            $all_items_available = true;

            // Resolve GroupItem IDs to actual items
            $resolvedItems = $this->getResolvedItems(null, $prodRepo, $ingRepo);

            foreach ($resolvedItems as $item) {
                $item_availability = [];

                // Get availability based on the actual item type and ID
                if ($item instanceof Ingredient) {
                    $item_availability = $ingRepo->getAvailability($item->id);
                } elseif ($item instanceof Product) {
                    $item_availability = $prodRepo->getAvailability($item->id);
                } else {
                    // Skip unknown item types
                    continue;
                }

                // If this item is not available in this branch, group is not available
                if (!($item_availability[$branch_id] ?? false)) {
                    $all_items_available = false;
                    break;
                }
            }

            $calculated_availability[$branch_id] = $all_items_available;
        }

        return $calculated_availability;
    }

    /**
     * Get final availability combining manual settings with calculated availability
     * Manual availability can override calculated availability (for business decisions)
     *
     * @param ProductRepository|null $prodRepo
     * @param IngredientRepository|null $ingRepo
     * @return array [branch_id => boolean]
     */
    public function getFinalAvailability(
        ?ProductRepository $prodRepo = null,
        ?IngredientRepository $ingRepo = null
    ): array {
        $calculated = $this->calculateAvailability($prodRepo, $ingRepo);

        // If no manual availability is set, use calculated
        if (empty($this->availability)) {
            return $calculated;
        }

        // Merge manual and calculated availability
        // Manual availability can only restrict (false), not enable when calculated is false
        $final_availability = [];
        foreach ($calculated as $branch_id => $is_calculated_available) {
            $is_manually_set = $this->availability[$branch_id] ?? null;

            if ($is_manually_set === null) {
                // No manual override, use calculated
                $final_availability[$branch_id] = $is_calculated_available;
            } else {
                // Manual override exists, but can only restrict availability
                $final_availability[$branch_id] = $is_calculated_available && $is_manually_set;
            }
        }

        return $final_availability;
    }
}
