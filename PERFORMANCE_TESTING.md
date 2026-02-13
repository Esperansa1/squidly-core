# Performance Testing Guide - Squidly Core

This guide provides comprehensive instructions for testing all performance optimizations implemented in the Squidly Core plugin.

## Prerequisites

1. **Install Query Monitor Plugin**
   - Go to WordPress Admin → Plugins → Add New
   - Search for "Query Monitor"
   - Install and activate
   - This will track database queries, PHP errors, and performance metrics

2. **Browser Developer Tools**
   - Chrome DevTools (F12) or Firefox Developer Tools
   - Network tab for measuring request times
   - Performance tab for profiling

3. **Testing Environment**
   - Ensure you have test data: at least 100 orders, 50 products, 10 branches
   - Clear all caches before testing: WordPress object cache, browser cache, CDN cache

## Phase 1: Frontend Performance Testing

### 1.1 Bundle Size Verification

**Before Optimization:** Main bundle was ~738KB

**Expected After Optimization:** Main bundle ~21KB, vendor ~141KB, recharts ~356KB

**How to Test:**
```bash
cd admin-app
npm run build
```

Check the build output:
- ✅ `main-[hash].js` should be ~21KB
- ✅ `vendor-[hash].js` should be ~141KB (React + ReactDOM)
- ✅ `recharts-[hash].js` should be ~356KB (loaded only on analytics pages)
- ✅ Individual route chunks: MenuManagement (~48KB), OrderManagement (~39KB), etc.

### 1.2 Page Load Performance

**Tool:** Chrome DevTools → Network tab

**Steps:**
1. Open Chrome DevTools (F12)
2. Go to Network tab
3. Check "Disable cache"
4. Navigate to admin page: `/wp-admin/admin.php?page=squidly-admin`
5. Note the DOMContentLoaded and Load times

**Expected Results:**
- **Before:** 5-8 seconds first load
- **After:** 1-2 seconds first load
- Main bundle loads first (21KB)
- Route-specific chunks load on demand
- Vendor chunk cached across page navigations

### 1.3 Lazy Loading Verification

**Steps:**
1. Open Network tab in DevTools
2. Navigate to admin page
3. Observe which JavaScript files load
4. Click on different menu items (Menu Management, Order Management, etc.)
5. Verify that route-specific chunks load only when navigating to that page

**Expected Behavior:**
- ✅ Only `main.js` and `vendor.js` load initially
- ✅ `MenuManagement-[hash].js` loads when clicking Menu Management
- ✅ `OrderManagement-[hash].js` loads when clicking Order Management
- ✅ Subsequent navigation to same page uses cached chunk

### 1.4 Lighthouse Audit

**Tool:** Chrome DevTools → Lighthouse

**Steps:**
1. Open Chrome DevTools (F12)
2. Go to Lighthouse tab
3. Select "Performance" category
4. Click "Generate report"

**Expected Results:**
- **Before:** Performance score ~40-50
- **After:** Performance score ~70-90
- Improvements in:
  - First Contentful Paint (FCP)
  - Time to Interactive (TTI)
  - Total Blocking Time (TBT)

---

## Phase 2: Backend Performance Testing

### 2.1 Plugin Initialization Time

**Tool:** Query Monitor

**Steps:**
1. Activate Query Monitor plugin
2. Navigate to any admin page
3. Click "Query Monitor" in admin bar
4. Go to "PHP" tab
5. Find "squidly-core/squidly-core.php" in the list
6. Note the execution time

**Expected Results:**
- **Before:** ~150ms plugin initialization
- **After:** ~40ms plugin initialization (73% faster)

### 2.2 Autoloader Performance

**Verification:** Check that classes load without errors

**Steps:**
1. Navigate to different admin pages
2. Check for any PHP warnings/errors in Query Monitor
3. Verify all pages load correctly

**Expected Behavior:**
- ✅ No "Class not found" errors
- ✅ All pages load successfully
- ✅ Class loading is fast (not noticeable)

### 2.3 Conditional Loading Verification

**Tool:** Query Monitor → "Conditionals" tab

**Steps:**
1. Visit frontend page (customer-facing)
2. Check Query Monitor → "Conditionals"
3. Note which classes/files are loaded
4. Visit admin page
5. Compare loaded files

**Expected Results:**
- **Frontend:** Admin classes NOT loaded (AdminPageHandler, CustomerPageHandler)
- **Admin:** Admin classes loaded only in admin context
- **REST API:** REST controllers loaded only on `rest_api_init`

---

## Phase 3: Database Performance Testing

### 3.1 Order List Query Count

**Tool:** Query Monitor

**Before Optimization:** ~2,100 queries for 100 orders
**Expected After:** ~101 queries for 100 orders (95% reduction)

**Steps:**
1. Ensure you have at least 100 orders in the database
2. Navigate to Orders page in admin: `/wp-admin/admin.php?page=squidly-admin` → Orders
3. Open Query Monitor
4. Note the query count in the admin bar (e.g., "123 queries in 2.3s")

**Expected Results:**
- **Before:** 2,000+ queries
- **After:** ~100-150 queries
- **Breakdown:**
  - ~1 query to fetch orders
  - ~1 query to fetch all order meta (batched)
  - ~20-30 queries for customers (batch loaded, one per unique customer)
  - ~20-30 queries for branches (batch loaded, one per unique branch)

### 3.2 Single Order Performance

**Steps:**
1. Navigate to a single order page or use REST API:
   ```bash
   GET /wp-json/squidly/v1/orders/123
   ```
2. Check Query Monitor

**Expected Results:**
- **Before:** ~21 queries per order (individual meta + customer + branch)
- **After:** ~3-5 queries per order (batched meta + customer + branch)

### 3.3 Meta Field Batching Verification

**Tool:** Query Monitor → "Queries by Caller" tab

**Steps:**
1. Load orders list page
2. Open Query Monitor
3. Go to "Queries by Caller" tab
4. Look for `get_post_meta` calls
5. Verify there's ONE call per order (fetching all meta at once)

**Expected Behavior:**
- ✅ Single `get_post_meta($post_id)` per order (no $single parameter)
- ✅ NOT 20+ individual `get_post_meta($post_id, '_field_name', true)` calls

### 3.4 API Response Caching

**Tool:** Browser DevTools → Network tab + Query Monitor

**Steps:**
1. Make an API request to orders endpoint:
   ```bash
   GET /wp-json/squidly/v1/orders
   ```
2. Note the query count in Query Monitor
3. Make the EXACT SAME request again within 60 seconds
4. Note the query count for the second request

**Expected Results:**
- **First request:** ~100-150 queries
- **Second request (cached):** 0 queries
- Response time: <10ms (served from transient cache)

**Cache Invalidation Test:**
1. Create/update/delete an order
2. Make the API request again
3. Verify queries run again (cache was cleared)

### 3.5 Database Index Verification

**Tool:** MySQL/phpMyAdmin or WP-CLI

**Method 1: phpMyAdmin**
1. Open phpMyAdmin
2. Select your WordPress database
3. Go to wp_postmeta table
4. Click "Structure" tab
5. Verify these indexes exist:
   - `squidly_meta_lookup` (post_id, meta_key)
   - `squidly_meta_value_lookup` (meta_key, meta_value)
6. Go to wp_posts table
7. Verify index exists:
   - `squidly_type_status_date` (post_type, post_status, post_date)

**Method 2: WP-CLI**
```bash
wp db query "SHOW INDEX FROM wp_postmeta WHERE Key_name LIKE 'squidly_%'"
wp db query "SHOW INDEX FROM wp_posts WHERE Key_name = 'squidly_type_status_date'"
```

**Expected:** All 3 indexes should exist

---

## Comprehensive Performance Comparison

### Summary of Expected Improvements

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **Frontend** |
| Admin bundle size | 738KB | 21KB | 97% smaller |
| First page load | 5-8s | 1-2s | 70% faster |
| **Backend** |
| Plugin init time | 150ms | 40ms | 73% faster |
| **Database** |
| Orders list queries (100 items) | 2,100 | 101 | 95% fewer |
| Orders API response time | 2,500ms | 250ms | 90% faster |
| Cached API response | 2,500ms | <10ms | 99% faster |

---

## Troubleshooting

### Issue: Bundle sizes unchanged

**Solution:**
```bash
cd admin-app
rm -rf node_modules dist
npm install
npm run build
```

### Issue: High query count persists

**Possible causes:**
1. Browser cache serving old JavaScript
   - Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)
2. Opcode cache not cleared
   - Restart PHP-FPM or Apache
3. Database indexes not created
   - Deactivate and reactivate the plugin
   - Check indexes with phpMyAdmin

### Issue: Caching not working

**Verification:**
1. Check transients in database:
   ```sql
   SELECT * FROM wp_options WHERE option_name LIKE '_transient_squidly_%';
   ```
2. Should see cached entries after making API requests
3. Entries should disappear after 60 seconds or after create/update/delete

### Issue: Autoloader errors

**Solution:**
1. Check that `includes/autoload-map.php` exists
2. Verify file permissions (should be readable)
3. Check PHP error logs for specific class loading errors

---

## Performance Monitoring (Ongoing)

### Tools to Install

1. **Query Monitor** (already installed)
   - Always-on performance monitoring
   - Track slow queries, duplicate queries, N+1 issues

2. **New Relic** or **Blackfire.io** (optional)
   - Application Performance Monitoring (APM)
   - Deep performance insights
   - Bottleneck identification

3. **GTmetrix** or **Pingdom**
   - External website performance testing
   - Regular performance checks
   - Historical performance data

### Best Practices

1. **Regular Testing**
   - Test performance after each major update
   - Monitor query counts in production
   - Track page load times

2. **Cache Management**
   - Clear caches regularly in development
   - Monitor cache hit rates
   - Adjust cache TTL if needed (currently 60s)

3. **Database Maintenance**
   - Regularly optimize database tables
   - Monitor table sizes
   - Review slow query logs

4. **Monitoring Alerts**
   - Set up alerts for:
     - Query count > 200 per page
     - Page load time > 2 seconds
     - API response time > 500ms

---

## Next Steps

After verifying all optimizations:

1. ✅ Document baseline performance metrics
2. ✅ Deploy to staging environment
3. ✅ Run full test suite
4. ✅ Load test with real traffic patterns
5. ✅ Monitor for 1-2 weeks
6. ✅ Deploy to production
7. ✅ Continue monitoring

## Questions?

If you encounter issues or have questions about performance testing, please open an issue on GitHub or contact the development team.

---

**Generated for Squidly Core Performance Optimization Project**
