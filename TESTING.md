# Customer App Testing Guide

## Overview
This guide helps test the customer app implementation (branch selection, product catalog, cart functionality).

## Prerequisites
- WordPress site running at `squidly.local`
- Plugin activated
- At least one store branch created in admin
- At least one product created in admin
- Customer app accessible at `squidly.local/orders`

## Backend API Status

### ✅ Implemented & Available

**Public Branches API:**
- `GET /wp-json/squidly/v1/public/branches` - List all branches
- `GET /wp-json/squidly/v1/public/branches/{id}` - Get single branch
- Filters: `open_now`, `city`, `per_page`, `offset`

**Public Products API:**
- `GET /wp-json/squidly/v1/public/products` - List all products
- `GET /wp-json/squidly/v1/public/products/{id}` - Get single product
- `GET /wp-json/squidly/v1/public/products/categories` - Get categories
- Filters: `branch_id`, `category`, `search`, `per_page`, `offset`

**Public Config API:**
- `GET /wp-json/squidly/v1/public/config` - Get app configuration

### ❌ Not Yet Implemented

These endpoints are planned but not built yet:
- `POST /wp-json/squidly/v1/public/guest-customer` - Create guest customer
- `POST /wp-json/squidly/v1/public/orders` - Create order
- `GET /wp-json/squidly/v1/public/orders/{id}/status` - Track order
- `POST/GET/PUT/DELETE /wp-json/squidly/v1/public/cart` - Cart session management
- `GET /wp-json/squidly/v1/public/delivery-fee` - Calculate delivery fee
- `GET /wp-json/squidly/v1/public/availability` - Check product availability

## Frontend Status

### ✅ Implemented Components

**Branch Selection:**
- BranchContext - Branch state management
- BranchCard - Display branch info with open/closed status
- BranchSelector - Grid of branches with selection

**Product Catalog:**
- ProductCard - Display product with add to cart
- ProductGrid - Responsive product grid
- CategoryTabs - Filter products by category

**Cart:**
- CartContext - Cart state with sessionStorage persistence
- Add to cart functionality
- Cart count in header

**i18n:**
- Hebrew as default language
- English and Arabic translations available
- RTL support for Hebrew/Arabic

### ❌ Not Yet Implemented

- Cart view/modal (to see cart contents)
- Quantity adjustment UI
- Remove from cart UI
- Checkout flow (3 steps)
- Order submission
- Order tracking
- Product customization modal

## Testing Instructions

### 1. Test API Endpoints Directly

**Test Branches Endpoint:**
```bash
curl http://squidly.local/wp-json/squidly/v1/public/branches
```

Expected response:
```json
[
  {
    "id": 1,
    "name": "Main Branch",
    "address": "123 Main St",
    "city": "Tel Aviv",
    "phone": "03-1234567",
    "is_currently_open": true,
    "next_opening_time": null,
    "activity_times": [...],
    "kosher_type": "Kosher",
    "accessibility_list": [...]
  }
]
```

**Test Products Endpoint:**
```bash
curl http://squidly.local/wp-json/squidly/v1/public/products
```

Expected response:
```json
[
  {
    "id": 1,
    "name": "Hamburger",
    "description": "Classic burger",
    "price": 45.00,
    "discounted_price": null,
    "product_group_ids": [1, 2],
    "ingredient_ids": [1, 2, 3],
    "image_url": ""
  }
]
```

**Test Categories Endpoint:**
```bash
curl http://squidly.local/wp-json/squidly/v1/public/products/categories
```

Expected response:
```json
[
  {
    "id": 1,
    "name": "Burgers"
  },
  {
    "id": 2,
    "name": "Drinks"
  }
]
```

**Test Config Endpoint:**
```bash
curl http://squidly.local/wp-json/squidly/v1/public/config
```

### 2. Test Customer App UI

#### Step 1: Access Customer App
1. Navigate to `http://squidly.local/orders`
2. Page should load with Hebrew text (RTL)
3. Should see "Squidly Orders" header with cart count (0)

#### Step 2: Test Branch Selection
1. Should see branch selection screen
2. If branches exist:
   - See branch cards with:
     - Branch name and city
     - Address and phone
     - Open/Closed badge (green/red)
     - "Select Branch" button
3. Click "Select Branch" button
4. Should navigate to menu view

#### Step 3: Test Product Catalog
1. After selecting branch, should see menu page
2. Header shows selected branch name
3. If products exist:
   - See product grid (1/2/3 columns responsive)
   - Each product shows: name, price, description, image (if available)
   - "Add to Cart" button on each product
4. If categories exist:
   - See tabs at top for filtering
   - Click tab to filter products

#### Step 4: Test Cart Functionality
1. Click "Add to Cart" on a product
2. Cart count in header should increment
3. Console should log: "Added to cart: [Product Name]"
4. Add same product again - should increase quantity
5. Add different product - should add as new item
6. Refresh page - cart count should persist (sessionStorage)

#### Step 5: Test Category Filtering
1. Click on category tabs
2. Products should filter to show only that category
3. Click "All" tab to show all products again

#### Step 6: Test Navigation
1. Click "Back" button on menu page
2. Should return to branch selection
3. Cart count should remain unchanged
4. Select branch again - should remember selected branch

### 3. Test Browser Console

Open browser DevTools (F12) and check Console tab for:

**Expected Logs:**
- `✅ Public API initialized: {...}` - API config loaded
- `✅ Branches:` - When branch selection loads
- `✅ Products loaded:` - When menu view loads
- `Added to cart: [Product Name]` - When adding to cart

**Check for Errors:**
- No 404 or 500 errors
- No JavaScript errors
- No CORS errors

### 4. Test sessionStorage

Open DevTools → Application tab → Storage → Session Storage:
- Should see `squidly_cart` key
- Value should contain cart items array
- Refresh page - data should persist

### 5. Test Responsive Design

Test at different screen widths:
- **Mobile (< 768px):** Product grid should be 1 column
- **Tablet (768px - 1024px):** Product grid should be 2 columns
- **Desktop (> 1024px):** Product grid should be 3 columns

## Expected Behavior Summary

### ✅ Should Work
- Branch selection display and selection
- Product catalog display
- Category filtering
- Adding products to cart
- Cart count updates
- Session persistence
- Back navigation
- Hebrew/RTL display
- API data fetching

### ❌ Won't Work Yet (Not Implemented)
- Viewing cart contents (no modal/page)
- Removing items from cart (no UI)
- Changing quantities (no UI)
- Proceeding to checkout (no checkout flow)
- Submitting orders (no backend endpoint)
- Order tracking (no backend endpoint)
- Product customization (no modal)

## Common Issues & Solutions

**Issue:** "Failed to load branches/products"
- **Solution:** Check admin app - create at least one branch and product

**Issue:** Cart count not updating
- **Solution:** Check browser console for errors. Verify CartContext is wrapped around AppContent

**Issue:** Categories not showing
- **Solution:** Products must be assigned to product groups in admin

**Issue:** Products not filtering by branch
- **Solution:** Branch must have product availability configured

**Issue:** Hebrew text not showing
- **Solution:** Check `customer-app/src/i18n/translations.js` - should have `currentLanguage = 'he'`

**Issue:** sessionStorage not persisting
- **Solution:** Check if browser allows sessionStorage (private browsing may block)

## Next Steps After Testing

Based on testing results, prioritize:

1. **If APIs work but UI has issues:** Fix frontend bugs
2. **If APIs don't return data:** Create test data in admin
3. **If everything works:** Continue with cart view/checkout implementation
4. **If rate limiting triggers:** Adjust rate limits in PublicRestController

## Test Data Requirements

For comprehensive testing, create in WordPress admin:

**Minimum:**
- 1 branch with activity_times configured
- 3-5 products with prices
- 2-3 product groups (categories)
- Assign products to groups

**Ideal:**
- 2-3 branches (test branch selection)
- 10+ products (test grid layout, pagination)
- 3-5 categories (test filtering)
- Mix of products with/without discounts
- Mix of products with/without images

## Report Issues

Document any issues found with:
1. What you were testing
2. Expected behavior
3. Actual behavior
4. Browser console errors
5. Network tab (API responses)
