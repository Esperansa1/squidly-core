# Security Validation for Product Customizations

## Overview

The `ProductCustomizationValidator` service prevents customers from manipulating the frontend to bypass constraints or tamper with prices. **Always validate customizations server-side** before creating orders.

## Security Threats Prevented

### 1. **Constraint Bypass**
- Customer manipulates JavaScript to select more items than `max_selections`
- Customer submits fewer items than `min_selections` required
- **Protection:** Server validates against database constraints

### 2. **Price Manipulation**
- Customer changes item prices in browser DevTools
- Customer submits incorrect prices via modified requests
- **Protection:** Server recalculates prices from database

### 3. **Invalid Item Injection**
- Customer adds items that don't belong to the group
- Customer adds items from other products
- **Protection:** Server verifies items against group's resolved items

### 4. **Invalid Group Injection**
- Customer adds groups that don't belong to the product
- Customer submits fake group IDs
- **Protection:** Server verifies groups against product's group_ids

## Usage in Public Order Creation

When building the public order creation endpoint, use this pattern:

```php
<?php
// PublicOrderRestController.php

public function create_order($request) {
    $order_data = $request->get_json_params();
    $validator = new ProductCustomizationValidator();

    // Validate and calculate prices for each item
    foreach ($order_data['items'] as $item) {
        $product_id = $item['product_id'];
        $customizations = $item['customizations'] ?? [];

        try {
            // Validate customizations (throws exception if invalid)
            $validator->validateProductCustomizations($product_id, $customizations);

            // Calculate actual price (don't trust frontend)
            $actual_price = $validator->calculateTotalPrice($product_id, $customizations);

            // Use $actual_price instead of customer-provided price
            $item['validated_price'] = $actual_price;

        } catch (InvalidArgumentException $e) {
            // Reject the entire order - security violation detected
            return new WP_REST_Response([
                'error' => 'Invalid customization',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // All items validated - safe to create order
    $order_id = $this->createOrder($order_data);

    return new WP_REST_Response([
        'order_id' => $order_id,
        'message' => 'Order created successfully'
    ], 201);
}
```

## Customization Data Format

Customer sends customizations in this format:

```json
{
  "product_id": 42,
  "quantity": 1,
  "customizations": {
    "10": [
      {
        "id": 5,
        "name": "Lettuce",
        "price": 0.0
      },
      {
        "id": 7,
        "name": "Cheese",
        "price": 5.0
      }
    ],
    "11": [
      {
        "id": 12,
        "name": "Fries",
        "price": 8.0
      }
    ]
  }
}
```

**Structure:**
- Key: `group_id` (ProductGroup ID)
- Value: Array of selected items with `id`, `name`, `price`

## Validation Rules

### Selection Count
```php
// Group constraints from database
$group->min_selections = 1;
$group->max_selections = 2;

// Valid: 1 or 2 selections
$valid = [
    ['id' => 5, 'price' => 0.0]
];

// Invalid: 0 selections (below min)
$invalid_min = [];

// Invalid: 3 selections (above max)
$invalid_max = [
    ['id' => 5, 'price' => 0.0],
    ['id' => 7, 'price' => 5.0],
    ['id' => 8, 'price' => 3.0]
];
```

### Price Validation
```php
// Database price
$ingredient->price = 5.0;

// Valid: Exact match
$valid = ['id' => 7, 'price' => 5.0];

// Invalid: Price manipulation
$invalid = ['id' => 7, 'price' => 0.0];  // ❌ Changed from 5.0 to 0.0
```

### Item Membership
```php
// Group contains items: [5, 7, 8]

// Valid: Item in group
$valid = ['id' => 5, 'price' => 0.0];

// Invalid: Item not in group
$invalid = ['id' => 999, 'price' => 0.0];  // ❌ Doesn't exist in group
```

### Group Membership
```php
// Product has groups: [10, 11]

// Valid: Group belongs to product
$valid = [
    '10' => [...]
];

// Invalid: Group doesn't belong to product
$invalid = [
    '999' => [...]  // ❌ Group 999 not part of this product
];
```

## Error Messages

The validator throws descriptive errors:

```php
// Min constraint violation
"Group 'Choose Your Side' requires at least 1 selection(s), but received 0"

// Max constraint violation
"Group 'Choose Your Side' allows maximum 2 selection(s), but received 3"

// Price manipulation
"Price manipulation detected for item 'Cheese' in group 'Toppings'. Expected: 5.0, Received: 0.0"

// Invalid item
"Item 999 does not belong to group 'Toppings'"

// Invalid group
"ProductGroup 999 does not belong to Product 42"
```

## Testing

Run the test suite to verify validation:

```bash
# Access via browser
http://squidly.local/wp-content/plugins/squidly-core/test-customization-validation.php

# Should see:
✅ Passed: 8
❌ Failed: 0
🎉 ALL TESTS PASSED!
```

## Performance Considerations

- Validator makes database queries to verify items/groups
- Cache ProductGroup data if validating multiple items in same order
- Consider using transactions when creating orders with validation

## Best Practices

1. **Always validate server-side** - Never trust frontend validation
2. **Use validator's price calculation** - Don't trust customer-provided prices
3. **Fail fast** - Reject entire order on first validation error
4. **Log security violations** - Track potential attack attempts
5. **Return generic errors** - Don't expose internal structure to attackers

## Example: Complete Order Flow

```php
public function create_public_order($request) {
    $data = $request->get_json_params();
    $validator = new ProductCustomizationValidator();
    $total_order_price = 0.0;

    // Step 1: Validate all items
    foreach ($data['items'] as &$item) {
        try {
            // Validate
            $validator->validateProductCustomizations(
                $item['product_id'],
                $item['customizations']
            );

            // Calculate price
            $item_price = $validator->calculateTotalPrice(
                $item['product_id'],
                $item['customizations']
            );

            // Store validated price
            $item['unit_price'] = $item_price;
            $total_order_price += $item_price * $item['quantity'];

        } catch (InvalidArgumentException $e) {
            // Log security violation
            error_log("Security violation in order creation: " . $e->getMessage());

            // Return generic error
            return new WP_REST_Response([
                'error' => 'Invalid order data'
            ], 400);
        }
    }

    // Step 2: Create order with validated data
    $order_id = $this->orderRepository->create([
        'customer_id' => $data['customer_id'],
        'branch_id' => $data['branch_id'],
        'items' => $data['items'],  // Now contains validated prices
        'total_price' => $total_order_price,  // Server-calculated total
        'status' => 'pending'
    ]);

    return new WP_REST_Response([
        'order_id' => $order_id,
        'total_price' => $total_order_price
    ], 201);
}
```

## Security Checklist

Before deploying order creation endpoint:

- [ ] Validate customizations using `ProductCustomizationValidator`
- [ ] Calculate prices server-side using `calculateTotalPrice()`
- [ ] Never trust customer-provided prices
- [ ] Reject orders with validation errors
- [ ] Log security violations for monitoring
- [ ] Add rate limiting to prevent abuse
- [ ] Use HTTPS for all order submissions
- [ ] Implement CAPTCHA for guest orders (optional)

## Questions?

This validator is ready for use in the public order creation endpoint. When building checkout flow, ensure all customizations pass through this validation before creating orders.
