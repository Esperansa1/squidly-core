# WooCommerce Payment Integration

## Overview

Squidly integrates with WooCommerce for payment processing while maintaining its own order management system. This document explains how the integration works and key implementation details.

## Architecture

### Two-System Design

Squidly maintains two parallel order systems:

1. **Squidly Orders** - Primary order management system
   - Custom post type: `order`
   - Manages: products, customizations, delivery, customer data
   - Full restaurant order lifecycle

2. **WooCommerce Orders** - Payment processing only
   - Used solely for checkout and payment
   - Linked to Squidly orders via `_wc_order_id` meta field
   - Customer sees WooCommerce checkout page

### Why This Approach?

- Squidly products (ingredients, customizations, restaurant-specific features) don't map to WooCommerce products
- WooCommerce provides robust payment gateway integrations (COD, Stripe, PayPal, etc.)
- Separates concerns: Squidly handles orders, WooCommerce handles payments

## Payment Product System

### The Payment Product

Instead of creating WooCommerce products for every Squidly product, we use a **single virtual payment product**:

- **Name:** "Squidly Payment"
- **Type:** Virtual Simple Product
- **Status:** `publish` ⚠️ **CRITICAL** - Must be `publish`, not `private`
- **Visibility:** `hidden` - Hidden from catalog but purchasable
- **Price:** 0 (price set dynamically per order)
- **Created:** During plugin activation
- **Stored:** Option `squidly_wc_payment_product_id`

### Why Status Must Be 'publish'

WooCommerce's `WC_Order::needs_payment()` and checkout validation checks require products in the order to have `publish` status. Orders containing `private` products will show:

> "This order cannot be paid for. Please contact us if you need assistance."

Even if all other conditions are met (order total > 0, payment gateway enabled, etc.).

## Order Creation Flow

### Customer App Checkout Process

1. **Cart → Checkout** (`PublicCartRestController::checkout()`)
   ```
   POST /squidly/v1/public/cart/{token}/checkout
   ```

2. **Create Squidly Order**
   - Order repository creates custom post type
   - Stores: customer, items, customizations, delivery info, totals
   - Status: `pending`

3. **Validate Payment Product** (lines 516-531)
   ```php
   $payment_product_id = get_option('squidly_wc_payment_product_id');
   if (!$payment_product_id || !wc_get_product($payment_product_id)) {
       throw new RuntimeException('WooCommerce payment system not configured');
   }
   ```

4. **Create WooCommerce Order** (`create_woocommerce_order()`)
   - Guest order: `customer_id = 0`
   - Payment method: COD (or configured gateway)
   - Billing address from Squidly customer
   - Single line item: payment product with order total

5. **Link Orders**
   ```php
   $this->orderRepo->linkWooCommerceOrder($order_id, $wc_order_id);
   ```

6. **Return Payment URL**
   ```php
   $payment_url = $wc_order->get_checkout_payment_url();
   // Returns: /checkout/order-pay/{order_id}/?pay_for_order=true&key={order_key}
   ```

7. **Customer Redirected to WooCommerce**
   - Customer completes payment on WooCommerce checkout page
   - Payment gateway processes payment
   - WooCommerce order status updated

### WooCommerce Order Structure

```php
WC_Order {
    customer_id: 0,                    // Guest order
    payment_method: 'cod',
    status: 'pending',
    billing_address: [...],            // From Squidly customer
    line_items: [
        {
            product_id: 2051,          // Payment product ID
            product_name: 'Squidly Payment',
            quantity: 1,
            subtotal: 42.00,           // Squidly order total
            total: 42.00,
            meta: {
                '_squidly_order_id': 2054,
                '_squidly_order_items_count': 1,
                '_squidly_items_summary': 'Falafel x2, Hummus x1'
            }
        }
    ]
}
```

## Code Implementation

### Files

- **`includes/domains/payments/activation/PaymentProductActivation.php`**
  - Creates/updates/deletes payment product
  - Called during plugin activation/deactivation

- **`includes/domains/orders/rest/PublicCartRestController.php`**
  - `checkout()` - Main checkout endpoint
  - `create_woocommerce_order()` - Creates WC order with payment product

- **`squidly-core.php`**
  - Activation hooks
  - One-time migration for existing payment products

### Key Code Sections

#### Creating Payment Product (PaymentProductActivation.php:17-31)

```php
$product = new \WC_Product_Simple();
$product->set_name('Squidly Payment');
$product->set_status('publish');  // MUST be 'publish'
$product->set_virtual(true);
$product->set_sold_individually(true);
$product->set_price(0);
$product->set_catalog_visibility('hidden');
$product_id = $product->save();
update_option('squidly_wc_payment_product_id', $product_id);
```

#### Creating WooCommerce Order (PublicCartRestController.php:632-667)

```php
// Get payment product
$payment_product_id = get_option('squidly_wc_payment_product_id');
$payment_product = wc_get_product($payment_product_id);

// Add to order
$item_id = $wc_order->add_product(
    $payment_product,
    1,  // Quantity always 1
    [
        'subtotal' => $order->subtotal,
        'total' => $order->subtotal,
    ]
);

// Add metadata
wc_add_order_item_meta($item_id, '_squidly_order_id', $order->id);
wc_add_order_item_meta($item_id, '_squidly_order_items_count', count($order->order_items));
wc_add_order_item_meta($item_id, '_squidly_items_summary', implode(', ', $items_summary));
```

## Troubleshooting

### "This order cannot be paid for"

**Symptoms:**
- WooCommerce order created successfully
- Logs show `needs_payment: YES`
- Payment gateway enabled
- Still shows error on checkout page

**Root Cause:**
Payment product has `private` status instead of `publish`.

**Solution:**
```php
$product = wc_get_product(get_option('squidly_wc_payment_product_id'));
$product->set_status('publish');
$product->save();
```

**Prevention:**
The plugin now includes:
1. Correct status on creation (PaymentProductActivation.php:20)
2. One-time migration to fix existing products (squidly-core.php:212-221)

### Payment Product Not Found

**Symptoms:**
- Error: "WooCommerce payment system not configured"
- Checkout fails before creating WC order

**Causes:**
1. Plugin not activated after installation
2. WooCommerce not active during plugin activation
3. Payment product manually deleted

**Solution:**
Deactivate and reactivate the Squidly plugin:
```
WP Admin → Plugins → Deactivate Squidly Core → Activate Squidly Core
```

This triggers `register_activation_hook()` which creates the payment product.

### Guest Orders vs User Orders

**Why guest orders (customer_id = 0)?**

Squidly uses its own customer system (custom post type). Squidly customer IDs (e.g., 2033) don't correspond to WordPress user IDs. Passing a non-existent WP user ID can cause issues.

**Solution:**
All WooCommerce orders are created as guest orders (`customer_id = 0`) with billing details populated from Squidly customer data.

## Payment Gateways

### Currently Configured

- **Cash on Delivery (COD)** - Default
  - Enabled in WooCommerce → Settings → Payments
  - No additional configuration required

### Adding New Gateways

To add credit card, PayPal, or other gateways:

1. Install/enable gateway in WooCommerce
2. Configure gateway settings in WooCommerce admin
3. Gateway automatically available for Squidly orders
4. No changes needed in Squidly code

The payment product system works with any WooCommerce payment gateway.

## Order Status Sync

### WooCommerce → Squidly

Payment status changes in WooCommerce can be synced to Squidly:

```php
// Future implementation
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
    $wc_order = wc_get_order($order_id);
    $squidly_order_id = $wc_order->get_meta('_squidly_order_id');

    if ($squidly_order_id) {
        // Update Squidly order payment status
    }
});
```

### Squidly → WooCommerce

Currently one-way (Squidly creates WC order). WooCommerce order status is managed by WC payment gateways.

## Testing

### Manual Testing Checklist

- [ ] Add product to cart (with/without customizations)
- [ ] Proceed to checkout
- [ ] Fill customer info
- [ ] Submit order
- [ ] Verify redirect to WooCommerce payment page
- [ ] Complete payment (COD)
- [ ] Check Squidly order created
- [ ] Check WooCommerce order created
- [ ] Verify orders linked (`_wc_order_id` meta)

### Debug Logging

Enable debug logs in checkout flow:

```php
// PublicCartRestController.php
error_log('🔍 Payment product check: ID=' . $payment_product_id);
error_log('🛍️ WC Order created with ID: ' . $wc_order_id);
error_log('🛍️ WC Order needs payment: ' . ($wc_order->needs_payment() ? 'YES' : 'NO'));
error_log('🛍️ Available payment gateways: ' . implode(', ', array_keys($payment_gateways)));
```

View logs:
```
wp-content/debug.log
```

## Migration Notes

### Upgrading from Private to Publish Status

Existing installations (pre-Dec 2025) had payment products with `private` status.

**One-time migration** (squidly-core.php:212-221):
```php
add_action('init', function() {
    if (!get_option('squidly_payment_product_status_fixed')) {
        PaymentProductActivation::updatePaymentProductStatus();
        update_option('squidly_payment_product_status_fixed', true);
    }
}, 20);
```

This runs once on any page load and updates the product to `publish` status.

## Future Enhancements

### Planned

- [ ] Support for multiple payment gateways simultaneously
- [ ] Payment status webhook sync (WC → Squidly)
- [ ] Refund handling
- [ ] Payment analytics integration
- [ ] Custom payment gateway for Squidly-specific payment methods

### Considerations

- **Tax calculations** - Currently manual, consider WC tax engine
- **Coupons/discounts** - Squidly has its own, could integrate with WC
- **Shipping** - Squidly delivery system vs WC shipping
- **Multi-currency** - WC multi-currency plugins compatibility

## References

- [WooCommerce Order API](https://woocommerce.github.io/code-reference/classes/WC-Order.html)
- [WooCommerce Product API](https://woocommerce.github.io/code-reference/classes/WC-Product.html)
- [WooCommerce Payment Gateways](https://woocommerce.com/document/payment-gateway-api/)
