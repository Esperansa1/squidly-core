# Recreate Payment Product Instructions

## Option 1: Reactivate Plugin (Easiest)

1. Go to WordPress Admin → Plugins
2. Find "Squidly Core"
3. Click "Deactivate"
4. Click "Activate"

The activation hook will automatically recreate the payment product.

## Option 2: Create via WooCommerce Admin

1. Go to **WordPress Admin** → **WooCommerce** → **Products**
2. Click **Add New**
3. Set the following:
   - **Product Name:** `[System] Payment Processor - Do Not Delete`
   - **Product Type:** Simple product
   - **Description:** `Internal product used for payment processing. Required for Squidly orders. Do not modify or delete.`
   - **Regular Price:** 0
   - **Virtual:** ✓ (checked)
   - **Sold individually:** ✓ (checked)
   - **Catalog visibility:** Hidden
   - **Status:** Published
4. After saving, note the Product ID from the URL (e.g., `post=123`)
5. Add custom fields using a plugin like "Advanced Custom Fields" or directly in the database:
   - `_squidly_system_product` = `yes`
   - `_squidly_product_type` = `payment_processor`
6. Update the option in database:
   ```sql
   UPDATE wp_options
   SET option_value = 'YOUR_PRODUCT_ID'
   WHERE option_name = 'squidly_wc_payment_product_id';
   ```

## Why You See It in Orders

**Important:** Even after recreation, you'll still see the payment product in **old orders** because:

- Orders store product data at the time of creation
- The payment product was part of those orders
- This is normal and expected behavior
- Old orders will always show the products they were created with

**You will NOT see it in:**
- ✅ Admin app product lists
- ✅ Customer app menus
- ✅ New product searches
- ✅ REST API product endpoints

**You WILL see it in:**
- Orders where it was used (this is correct)
- WordPress admin if you specifically search for it
- Direct product queries by ID

## Verification

After recreation, verify the filtering works:

```bash
# Check Admin API
GET /wp-json/squidly/v1/products
# Payment product should NOT appear

# Check Public API
GET /wp-json/squidly/v1/public/products
# Payment product should NOT appear
```

In admin-app and customer-app, the payment product should be completely hidden from all product listings.
