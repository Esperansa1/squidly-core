<?php
declare(strict_types=1);

/**
 * Persistence layer for StoreBranch.
 *
 * All WordPress DB I/O is encapsulated here; the DTO (StoreBranch) remains
 * framework-agnostic.
 */
class StoreBranchRepository
{
    public const POST_TYPE = 'store_branch';

    private const VALID_DAYS = [
        'SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY',
        'THURSDAY', 'FRIDAY', 'SATURDAY',
    ];

    /* ---------------------------------------------------------------------
     *  create()
     * -------------------------------------------------------------------*/
    /**
     * Persist a new StoreBranch and return its post-ID.
     *
     * Required keys: name, phone, city, address, is_open (bool),
     *                activity_times (array), kosher_type (string),
     *                accessibility_list (array)
     *
     * Optional: products, ingredients, product_availability,
     *           ingredient_availability
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function create(array $data): int
    {
        foreach (['name','phone','city','address','is_open',
                  'activity_times','kosher_type','accessibility_list'] as $key) {
            if ( ! array_key_exists($key, $data) ) {
                throw new InvalidArgumentException("Missing required key: $key");
            }
        }

        $post_id = wp_insert_post([
            'post_title'  => sanitize_text_field($data['name']),
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException(
                'Failed to create StoreBranch: ' . $post_id->get_error_message()
            );
        }

        /* meta fields ----------------------------------------------------*/
        update_post_meta($post_id, '_phone',            sanitize_text_field($data['phone']));
        update_post_meta($post_id, '_city',             sanitize_text_field($data['city']));
        update_post_meta($post_id, '_address',          sanitize_text_field($data['address']));
        update_post_meta($post_id, '_is_open',          (bool) $data['is_open']);
        update_post_meta($post_id, '_activity_times',   $data['activity_times']);
        update_post_meta($post_id, '_kosher_type',      sanitize_text_field($data['kosher_type']));
        update_post_meta($post_id, '_accessibility_list',$data['accessibility_list']);

        update_post_meta($post_id, '_products',                $data['products']                ?? []);
        update_post_meta($post_id, '_ingredients',             $data['ingredients']             ?? []);
        update_post_meta($post_id, '_product_availability',    $data['product_availability']    ?? []);
        update_post_meta($post_id, '_ingredient_availability', $data['ingredient_availability'] ?? []);

        return $post_id;
    }

    /* ---------------------------------------------------------------------
     *  get()
     * -------------------------------------------------------------------*/
    public function get(int $id): ?StoreBranch
    {
        $post = get_post($id);

        if ( ! $post || $post->post_type !== self::POST_TYPE) {
            return null;
        }
        
        $prodRepo = new ProductRepository();
        $ingRepo  = new IngredientRepository();

        $productIds = get_post_meta($id, '_products', true) ?: [];
        $ingredientIds = get_post_meta($id, '_ingredients', true) ?: [];


        return new StoreBranch([
            'id'                     => $id,
            'name'                   => $post->post_title,
            'phone'                  => (string) get_post_meta($id, '_phone', true),
            'city'                   => (string) get_post_meta($id, '_city', true),
            'address'                => (string) get_post_meta($id, '_address', true),
            'is_open'                => (bool)   get_post_meta($id, '_is_open', true),
            'activity_times'         => get_post_meta($id, '_activity_times', true)      ?: [],
            'kosher_type'            => (string) get_post_meta($id, '_kosher_type', true),
            'accessibility_list'     => get_post_meta($id, '_accessibility_list', true)  ?: [],
            'products'               => array_map(fn($pid)=> $prodRepo->get((int)$pid), $productIds),            
            'ingredients'            => array_map(fn($iid)=> $ingRepo->get((int)$iid),  $ingredientIds),
            'product_availability'   => get_post_meta($id, '_product_availability', true)    ?: [],
            'ingredient_availability'=> get_post_meta($id, '_ingredient_availability', true) ?: [],
        ]);
    }

    /* ---------------------------------------------------------------------
     *  getAll() – all published branches
     * -------------------------------------------------------------------*/
    /**
     * @return StoreBranch[]
     */
    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        $out = [];
        foreach ($query->posts as $id) {
            $branch = $this->get((int) $id);
            if ($branch) {
                $out[] = $branch;
            }
        }
        return $out;
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


    /**
     * Add a time-slot (e.g. “08:00-14:00”) to a specific day.
     *
     * @throws InvalidArgumentException When $day is not a real week-day.
     */
    public function addActivityTime(int $branchId, string $day, string $slot): void
    {
        $dayUC = strtoupper($day);

        if (! in_array($dayUC, self::VALID_DAYS, true)) {
            throw new InvalidArgumentException(
                "Invalid week-day '{$day}'. Allowed: " . implode(', ', self::VALID_DAYS)
            );
        }

        $times = get_post_meta($branchId, '_activity_times', true) ?: [];
        $times[$dayUC] = array_unique(array_merge($times[$dayUC] ?? [], [$slot]));

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


    /* ======================================================================
    *  delete()
    * ====================================================================*/
    /**
     * Permanently trash or force-delete a Store Branch.
     *
     * @param int  $id     Post-ID of the branch
     * @param bool $force  True = bypass trash and delete permanently
     *
     * @return bool  True on success, false when the post doesn’t exist / wrong type
     * @throws RuntimeException When WP returns a WP_Error.
     */
    public function delete(int $id, bool $force = false): bool
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return false;                              // not a branch
        }

        // Future: place dependency checks here
        // -------------------------------------

        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Failed to delete store-branch: ' . $result->get_error_message()
            );
        }
        return (bool) $result;                         // true when trashed/deleted
    }



}
