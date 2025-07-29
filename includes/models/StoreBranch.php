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

    /* ======================================================================
    *  Array-meta helpers
    * ====================================================================*/
    private function appendUnique(int $postId, string $metaKey, int $value): void
    {
        $list = get_post_meta($postId, $metaKey, true) ?: [];
        if (!in_array($value, $list, true)) {
            $list[] = $value;
            update_post_meta($postId, $metaKey, $list);
        }
    }

    private function removeValue(int $postId, string $metaKey, int $value): void
    {
        $list = get_post_meta($postId, $metaKey, true) ?: [];
        if (($idx = array_search($value, $list, true)) !== false) {
            unset($list[$idx]);
            update_post_meta($postId, $metaKey, array_values($list));
        }
    }

    /* ======================================================================
    *  Product operations
    * ====================================================================*/
    public function addProduct(int $branchId, int $productId, bool $isActive = true): void
    {
        $this->appendUnique($branchId, '_products', $productId);

        $availability = get_post_meta($branchId, '_product_availability', true) ?: [];
        $availability[$productId] = $isActive;
        update_post_meta($branchId, '_product_availability', $availability);

        // ----- recurse into sub-items --------------------------------------
        $prodRepo  = new ProductRepository();
        $groupRepo = new ProductGroupRepository();
        $giRepo    = new GroupItemRepository();
        $ingRepo   = new IngredientRepository();

        $stack   = [$productId];
        $visited = [];

        while ($stack) {
            $pid = array_pop($stack);
            if (isset($visited[$pid])) {
                continue;
            }
            $visited[$pid] = true;

            $p = $prodRepo->get($pid);
            if (!$p) {
                continue;
            }

            foreach ($p->product_group_ids as $pgId) {
                $group = $groupRepo->get((int) $pgId);
                if (!$group) {
                    continue;
                }

                foreach ($group->getResolvedItems($giRepo,$prodRepo,$ingRepo) as $item) {
                    if ($item instanceof Product) {
                        $this->appendUnique($branchId, '_products', $item->id);
                        $availability[$item->id] = $isActive;
                        $stack[] = $item->id;          // recurse deeper
                    } else {
                        $this->appendUnique($branchId, '_ingredients', $item->id);
                        $ingAvail = get_post_meta($branchId, '_ingredient_availability', true) ?: [];
                        $ingAvail[$item->id] = $isActive;
                        update_post_meta($branchId, '_ingredient_availability', $ingAvail);
                    }
                }
            }
        }
        update_post_meta($branchId, '_product_availability', $availability);
    }

    public function removeProduct(int $branchId, int $productId): void
    {
        $this->removeValue($branchId, '_products', $productId);

        $avail = get_post_meta($branchId, '_product_availability', true) ?: [];
        unset($avail[$productId]);
        update_post_meta($branchId, '_product_availability', $avail);
    }

    /* ======================================================================
    *  Ingredient operations
    * ====================================================================*/
    public function addIngredient(int $branchId, int $ingredientId, bool $isActive = true): void
    {
        $this->appendUnique($branchId, '_ingredients', $ingredientId);

        $avail = get_post_meta($branchId, '_ingredient_availability', true) ?: [];
        $avail[$ingredientId] = $isActive;
        update_post_meta($branchId, '_ingredient_availability', $avail);
    }

    public function removeIngredient(int $branchId, int $ingredientId): void
    {
        $this->removeValue($branchId, '_ingredients', $ingredientId);

        $avail = get_post_meta($branchId, '_ingredient_availability', true) ?: [];
        unset($avail[$ingredientId]);
        update_post_meta($branchId, '_ingredient_availability', $avail);
    }

    /* ======================================================================
    *  Scalar setters
    * ====================================================================*/
    public function setName(int $branchId, string $name): void
    {
        wp_update_post(['ID'=>$branchId, 'post_title'=>sanitize_text_field($name)]);
    }
    public function setPhone(int $branchId, string $phone): void
    {
        update_post_meta($branchId, '_phone', sanitize_text_field($phone));
    }
    public function setCity(int $branchId, string $city): void
    {
        update_post_meta($branchId, '_city', sanitize_text_field($city));
    }
    public function setAddress(int $branchId, string $address): void
    {
        update_post_meta($branchId, '_address', sanitize_text_field($address));
    }
    public function setIsOpen(int $branchId, bool $open): void
    {
        update_post_meta($branchId, '_is_open', $open);
    }

    /* ======================================================================
    *  Activity times
    * ====================================================================*/
    public function addActivityTime(int $branchId, string $day, string $slot): void
    {
        $times = get_post_meta($branchId, '_activity_times', true) ?: [];
        $times[$day] = array_unique(array_merge($times[$day] ?? [], [$slot]));
        update_post_meta($branchId, '_activity_times', $times);
    }

    public function removeActivityTime(int $branchId, string $day, string $slot): void
    {
        $times = get_post_meta($branchId, '_activity_times', true) ?: [];
        if (isset($times[$day])) {
            $times[$day] = array_values(array_diff($times[$day], [$slot]));
            update_post_meta($branchId, '_activity_times', $times);
        }
    }

    /* ======================================================================
    *  Kosher & accessibility
    * ====================================================================*/
    public function setKosherType(int $branchId, string $type): void
    {
        update_post_meta($branchId, '_kosher_type', sanitize_text_field($type));
    }
    public function clearKosherType(int $branchId): void
    {
        delete_post_meta($branchId, '_kosher_type');
    }

    public function addAccessibility(int $branchId, string $feature): void
    {
        $list = get_post_meta($branchId, '_accessibility_list', true) ?: [];
        if (!in_array($feature, $list, true)) {
            $list[] = $feature;
            update_post_meta($branchId, '_accessibility_list', $list);
        }
    }
    public function removeAccessibility(int $branchId, string $feature): void
    {
        $list = get_post_meta($branchId, '_accessibility_list', true) ?: [];
        if (($idx = array_search($feature, $list, true)) !== false) {
            unset($list[$idx]);
            update_post_meta($branchId, '_accessibility_list', array_values($list));
        }
    }

    /* ======================================================================
    *  Availability setters
    * ====================================================================*/
    public function setProductAvailability(int $branchId, int $productId, bool $status): void
    {
        $avail = get_post_meta($branchId, '_product_availability', true) ?: [];
        $avail[$productId] = $status;
        update_post_meta($branchId, '_product_availability', $avail);
    }
    public function setIngredientAvailability(int $branchId, int $ingredientId, bool $status): void
    {
        $avail = get_post_meta($branchId, '_ingredient_availability', true) ?: [];
        $avail[$ingredientId] = $status;
        update_post_meta($branchId, '_ingredient_availability', $avail);
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
