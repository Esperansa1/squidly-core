<?php

declare(strict_types=1);

/**
 * ProductCustomizationValidator - Validates customer customizations against constraints
 *
 * Prevents security issues where customers manipulate frontend to bypass constraints:
 * - Validates min/max selection constraints
 * - Verifies selected items belong to the group
 * - Prevents price manipulation
 * - Ensures groups belong to the product
 */
class ProductCustomizationValidator
{
    private ProductRepository $productRepo;
    private ProductGroupRepository $groupRepo;
    private GroupItemRepository $groupItemRepo;
    private IngredientRepository $ingredientRepo;

    public function __construct(
        ?ProductRepository $productRepo = null,
        ?ProductGroupRepository $groupRepo = null,
        ?GroupItemRepository $groupItemRepo = null,
        ?IngredientRepository $ingredientRepo = null
    ) {
        $this->productRepo = $productRepo ?? new ProductRepository();
        $this->groupRepo = $groupRepo ?? new ProductGroupRepository();
        $this->groupItemRepo = $groupItemRepo ?? new GroupItemRepository();
        $this->ingredientRepo = $ingredientRepo ?? new IngredientRepository();
    }

    /**
     * Validate all customizations for a product
     *
     * @param int $product_id Product being customized
     * @param array $customizations Array of [group_id => [selected_items]]
     * @throws InvalidArgumentException If validation fails
     * @return bool True if valid
     */
    public function validateProductCustomizations(int $product_id, array $customizations): bool
    {
        // Get the product
        $product = $this->productRepo->get($product_id);
        if (!$product) {
            throw new InvalidArgumentException("Product not found: {$product_id}");
        }

        // Validate each group's customizations
        foreach ($customizations as $group_id => $selected_items) {
            $this->validateGroupCustomization($product, (int) $group_id, $selected_items);
        }

        return true;
    }

    /**
     * Validate customizations for a single group
     *
     * @param Product $product Product being customized
     * @param int $group_id ProductGroup ID
     * @param array $selected_items Array of selected item objects
     * @throws InvalidArgumentException If validation fails
     */
    private function validateGroupCustomization(Product $product, int $group_id, array $selected_items): void
    {
        // 1. Verify group belongs to this product
        if (!in_array($group_id, $product->product_group_ids)) {
            throw new InvalidArgumentException(
                "ProductGroup {$group_id} does not belong to Product {$product->id}"
            );
        }

        // 2. Get the group and its constraints
        $group = $this->groupRepo->get($group_id);
        if (!$group) {
            throw new InvalidArgumentException("ProductGroup not found: {$group_id}");
        }

        // 3. Validate selection count against min/max constraints
        $this->validateSelectionCount($group, count($selected_items));

        // 4. Validate each selected item
        $validItems = $group->getResolvedItems(
            $this->groupItemRepo,
            $this->productRepo,
            $this->ingredientRepo
        );

        foreach ($selected_items as $selectedItem) {
            $this->validateSelectedItem($selectedItem, $validItems, $group);
        }
    }

    /**
     * Validate selection count against min/max constraints
     *
     * @param ProductGroup $group The product group
     * @param int $count Number of selections
     * @throws InvalidArgumentException If count violates constraints
     */
    private function validateSelectionCount(ProductGroup $group, int $count): void
    {
        $min = $group->min_selections;
        $max = $group->max_selections;

        // Check minimum constraint
        if ($count < $min) {
            throw new InvalidArgumentException(
                "Group '{$group->name}' requires at least {$min} selection(s), but received {$count}"
            );
        }

        // Check maximum constraint (0 = unlimited)
        if ($max > 0 && $count > $max) {
            throw new InvalidArgumentException(
                "Group '{$group->name}' allows maximum {$max} selection(s), but received {$count}"
            );
        }
    }

    /**
     * Validate a selected item exists in the group and price is correct
     *
     * @param array $selectedItem Selected item from customer
     * @param array $validItems Valid items from the group
     * @param ProductGroup $group The product group
     * @throws InvalidArgumentException If item is invalid or price manipulated
     */
    private function validateSelectedItem(array $selectedItem, array $validItems, ProductGroup $group): void
    {
        // Ensure selected item has required fields
        if (!isset($selectedItem['id']) || !isset($selectedItem['price'])) {
            throw new InvalidArgumentException(
                "Invalid item format in group '{$group->name}'"
            );
        }

        $itemId = (int) $selectedItem['id'];
        $itemPrice = (float) $selectedItem['price'];

        // Find the item in valid items
        $found = false;
        foreach ($validItems as $validItem) {
            if ($validItem->id === $itemId) {
                $found = true;

                // Validate price hasn't been manipulated
                // Use small epsilon for floating point comparison
                if (abs($validItem->price - $itemPrice) > 0.01) {
                    throw new InvalidArgumentException(
                        "Price manipulation detected for item '{$validItem->name}' in group '{$group->name}'. " .
                        "Expected: {$validItem->price}, Received: {$itemPrice}"
                    );
                }

                break;
            }
        }

        if (!$found) {
            throw new InvalidArgumentException(
                "Item {$itemId} does not belong to group '{$group->name}'"
            );
        }
    }

    /**
     * Calculate total price including customizations
     * Use this instead of trusting frontend price calculations
     *
     * @param int $product_id Product ID
     * @param array $customizations Customizations array
     * @return float Total price
     */
    public function calculateTotalPrice(int $product_id, array $customizations): float
    {
        // Validate first to ensure no manipulation
        $this->validateProductCustomizations($product_id, $customizations);

        // Get base product price
        $product = $this->productRepo->get($product_id);
        $basePrice = $product->discounted_price ?? $product->price;

        // Calculate customizations price
        $customizationsPrice = 0.0;
        foreach ($customizations as $group_id => $selected_items) {
            foreach ($selected_items as $item) {
                $customizationsPrice += (float) $item['price'];
            }
        }

        return $basePrice + $customizationsPrice;
    }
}
