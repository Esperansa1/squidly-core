<?php

declare(strict_types=1);

class ProductGroup
{
    public int $id;
    public string $name;
    public ItemType $type;
    public array $group_item_ids; // int[]

    public function __construct(array $data)
    {
        $this->id              = (int) $data['id'];
        $this->name            = (string) $data['name'];
        $this->type            = ItemType::from($data['type']);
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
}
