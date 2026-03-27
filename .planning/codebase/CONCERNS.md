# Codebase Concerns

**Analysis Date:** 2026-03-27

---

## Tech Debt

**Manual require_once for Payment Classes:**
- Issue: `squidly-core.php` (line 135) has a comment "Manual require of payment classes (temporary fix)" and lists 8 explicit `require_once` calls for payment domain files, even though the autoloader loop already processes the `payments/` subdirectories.
- Files: `squidly-core.php` lines 135–144
- Impact: Risk of double-loading; makes adding new payment files require two edits (the folder and the explicit list). The word "temporary" has persisted into production.
- Fix approach: Remove the manual requires and ensure the autoloader loop covers payment folders consistently, or eliminate the loop in favor of Composer PSR-4 autoloading.

**Inconsistent Namespace Adoption:**
- Issue: Only the `payments/` domain and `RoleManager` use PHP namespaces. All other domains (`orders`, `products`, `customers`, `stores`) declare classes at global scope with no namespace.
- Files: All files in `includes/domains/customers/`, `includes/domains/orders/`, `includes/domains/products/`, `includes/domains/stores/`
- Impact: High risk of class name collisions with other plugins; complicates future Composer PSR-4 migration; makes IDE static analysis and type inference unreliable.
- Fix approach: Progressively add namespaces per domain starting with the most-changed domain. Update the custom autoloader to support namespaced loading.

**`countBy()` Loads Full Result Sets:**
- Issue: Every repository's `countBy()` implementation calls `findBy()` (which hydrates full objects) then wraps in `count()`. No SQL `COUNT` query is used.
- Files: `includes/domains/orders/repositories/OrderRepository.php:539`, `includes/domains/customers/repositories/CustomerRepository.php:277`, `includes/domains/products/repositories/ProductRepository.php:824`, `includes/domains/products/repositories/ProductGroupRepository.php:359`, `includes/domains/products/repositories/IngredientRepository.php:471`, `includes/domains/products/repositories/GroupItemRepository.php:313`, `includes/domains/stores/repositories/StoreBranchRepository.php:604`
- Impact: Counting 1000 orders loads 1000 full order objects with all their meta. Called by analytics and dashboard endpoints.
- Fix approach: Override `countBy()` in each repository to use `WP_Query` with `'fields' => 'ids'` and read `found_posts` directly, or use `$wpdb->get_var()` with `COUNT(*)`.

**Hardcoded Post Type Strings (Violates Convention):**
- Issue: Three locations use string literals `'customer'` and `'product'` instead of `CustomerPostType::POST_TYPE` and `ProductPostType::POST_TYPE`, contradicting the documented convention.
- Files: `includes/domains/orders/post-types/OrderPostType.php:129`, `includes/domains/orders/rest/DashboardAnalyticsController.php:241`, `includes/domains/products/post-types/GroupItemPostType.php:424`
- Impact: If post type slugs are ever changed, these are silent bugs that break queries.
- Fix approach: Replace all three instances with the appropriate `::POST_TYPE` constant.

**Settings Under-Registered:**
- Issue: Only 3 of the ~9 documented options are registered via `register_setting()` in `squidly-core.php:117–119`. Options like `squidly_tax_rate`, `squidly_loyalty_rate`, `squidly_default_order_status`, `squidly_enable_online_ordering`, `squidly_wc_payment_product_id`, and `squidly_strict_availability_mode` are read with `get_option()` but never formally registered.
- Files: `squidly-core.php:116–120`, `includes/domains/orders/repositories/OrderRepository.php:806`, `includes/domains/orders/rest/PublicOrderRestController.php:210`, `includes/domains/orders/services/CartService.php:288`
- Impact: Unregistered options cannot be managed via the Settings API; no sanitization callbacks on save; cannot appear in options export. `squidly_tax_rate` has a hardcoded 17% default (`0.17`) with no UI to change it.
- Fix approach: Register all options in the `admin_init` callback with appropriate sanitize callbacks.

**No `uninstall.php`:**
- Issue: The plugin has `register_activation_hook` and `register_deactivation_hook` but no `uninstall.php` and no `register_uninstall_hook`. Custom post type data, options, transients, and cron jobs are never cleaned up on uninstall.
- Files: `squidly-core.php:219–243`
- Impact: Orphaned data, options, and scheduled cron jobs remain after the plugin is deleted.
- Fix approach: Create `uninstall.php` that deletes all `squidly_*` options and cancels the `squidly_cleanup_guests` cron event.

---

## Known Bugs / Unimplemented Features Presented as Working UI

**Coupon/Discount UI is a Non-Functional Stub:**
- Symptoms: The CouponSection in the customer cart renders an input field and "Apply" button. Clicking it always shows "מימוש קופונים יהיה זמין בקרוב" (Coupons coming soon) after a fake 500ms timeout.
- Files: `customer-app/src/components/cart/CouponSection.jsx:13–24`
- Trigger: Any user clicking "Apply Coupon" in the cart
- Workaround: None. The feature does not exist.

**Login Button is a Non-Functional Stub:**
- Symptoms: The Login button in `BranchSelectionModal` has an empty `onClick` handler with comment "Login functionality - placeholder for now".
- Files: `customer-app/src/components/branches/BranchSelectionModal.jsx:275`
- Trigger: Any user clicking the login button on the branch selection screen
- Workaround: None. Customers cannot log in; guest checkout still works.

**Delivery Distance Validation Always Returns True:**
- Symptoms: `isAddressInRange()` returns `true` for any address as long as delivery is enabled at the branch, regardless of `delivery_max_distance` configuration. `getDistance()` always returns `0.0` and caches that value for 24 hours.
- Files: `includes/domains/orders/services/DeliveryFeeService.php:62–77` and `:109–127`
- Trigger: Any delivery order placed to an out-of-range address
- Workaround: None. All addresses are accepted. Distance-based fee zones (`delivery_zones`) are unreachable code until geocoding is implemented.

---

## Security Considerations

**Rate Limiting Bypasses X-Forwarded-For (Inconsistent IP Detection):**
- Risk: `PublicOrderRestController` and `PublicCartRestController` use `$_SERVER['REMOTE_ADDR']` directly. Behind a reverse proxy (nginx, Cloudflare), `REMOTE_ADDR` is the proxy IP, meaning all users share the same rate limit bucket — a single user can exhaust it, or it can be trivially bypassed with `X-Forwarded-For` spoofing. `PublicRestController` has the correct multi-header IP detection (`HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`, `REMOTE_ADDR`) but `PublicCartRestController` and `PublicOrderRestController` extend `WP_REST_Controller` directly, not `PublicRestController`.
- Files: `includes/domains/orders/rest/PublicOrderRestController.php:596`, `includes/domains/orders/rest/PublicCartRestController.php:348`, `includes/api/PublicRestController.php:66–79`
- Current mitigation: Rate limit exists (10 req/min for orders, 200 req/min for cart) but is ineffective behind proxies.
- Recommendations: Make `PublicCartRestController` and `PublicOrderRestController` extend `PublicRestController` and use its `get_client_ip()` method.

**Cart Rate Limit is Extremely Permissive:**
- Risk: Cart endpoint allows 200 requests per minute per IP, which provides essentially no spam protection for the cart creation/update flow.
- Files: `includes/domains/orders/rest/PublicCartRestController.php:26`
- Current mitigation: WordPress nonce not required on public endpoints.
- Recommendations: Consider 30–60 requests/minute for cart operations.

**`HTTP_X_FORWARDED_FOR` Not Sanitized Before Rate Limit Key Hashing:**
- Risk: `PublicRestController::get_client_ip()` calls `sanitize_text_field()` on the raw `HTTP_X_FORWARDED_FOR` value which may contain a comma-separated list of IPs (e.g., `"1.2.3.4, 10.0.0.1"`). Attacker can supply arbitrary strings to manipulate transient key selection.
- Files: `includes/api/PublicRestController.php:72–78`
- Current mitigation: `sanitize_text_field()` strips tags but does not extract only the first IP.
- Recommendations: Parse the first valid IP from the header before using it.

---

## Performance Bottlenecks

**N+1 Query Pattern in `OrderRepository::findBy()` and `getAll()`:**
- Problem: `findBy()` fetches post IDs via `WP_Query`, then calls `$this->get()` for each ID (which calls `get_post()` + multiple `get_post_meta()` calls). No meta cache priming with `update_meta_cache()` before the loop.
- Files: `includes/domains/orders/repositories/OrderRepository.php:146–160` and `:519–533`
- Cause: Same N+1 pattern as the pre-optimization state. `ProductRepository::getAll()` was optimized with `update_meta_cache()` at line 187 but `OrderRepository` was not.
- Improvement path: Add `update_meta_cache('post', $post_ids)` before the `foreach` loop, identical to the pattern in `ProductRepository`.

**`CustomerRepository::getAll()` Has No Pagination and No Meta Cache Priming:**
- Problem: `getAll()` uses `posts_per_page => -1` with no `update_meta_cache()` call before hydrating each customer object.
- Files: `includes/domains/customers/repositories/CustomerRepository.php:72–91`
- Cause: Same N+1 pattern as `OrderRepository`.
- Improvement path: Add `update_meta_cache('post', $query->posts)` before the `foreach` loop.

**`DashboardAnalyticsController` Uses `posts_per_page => -1` for Revenue Calculation:**
- Problem: `get_period_stats()` fetches all orders for a given period with no limit to compute revenue and average order value. For a busy restaurant, this can mean hundreds or thousands of posts loaded into memory.
- Files: `includes/domains/orders/rest/DashboardAnalyticsController.php:251`
- Cause: No dedicated analytics table or caching layer.
- Improvement path: Add transient caching on dashboard stats (e.g., 5-minute TTL) or use `$wpdb` aggregate queries instead of loading all posts.

**Live Order Polling Every 30 Seconds:**
- Problem: The admin Order Management page polls `/orders` every 30 seconds regardless of how many admin users are viewing it. Each poll fetches up to 100 live orders.
- Files: `admin-app/src/components/OrderManagement.jsx:70`
- Cause: Simple `setInterval` approach; no WebSocket or SSE alternative.
- Improvement path: Acceptable at small scale; at high load, consider exponential backoff when no new orders arrive or a push notification approach.

**Customer App Order Tracking Polls Every 10 Seconds:**
- Problem: `useOrderPolling` polls every 10 seconds from the customer's browser. With many active orders, this adds significant REST API load.
- Files: `customer-app/src/hooks/useOrderPolling.js:70`
- Cause: No WebSocket/SSE; polling is the intended approach.
- Improvement path: Increase interval to 15–30s for orders in non-urgent states (`confirmed`, `preparing`); keep 10s only for `ready` state.

---

## Fragile Areas

**`squidly-core.php` Custom Autoloader is Load-Order Dependent:**
- Files: `squidly-core.php:25–108`
- Why fragile: The autoloader iterates a hardcoded array of directory paths in sequence. Classes can only reference classes in directories listed earlier in the array. Adding a new dependency between domains in the wrong order causes a fatal "class not found" error that is hard to diagnose.
- Safe modification: Always add new directories to the array AFTER all directories containing their dependencies. Test with `composer test:unit` after any change.
- Test coverage: No test validates that the autoloader covers all expected classes.

**`OrderRestController` Is 1590 Lines — Single Responsibility Violation:**
- Files: `includes/domains/orders/rest/OrderRestController.php`
- Why fragile: The controller handles CRUD, CSV export, analytics, revenue, and customer order history. Adding features or fixing bugs risks breaking unrelated functionality. The CSV export handler opens a `php://temp` stream at line 771 that must be manually managed.
- Safe modification: Any change requires running the full order E2E test suite (`vendor/bin/phpunit --testsuite e2e`).
- Test coverage: `tests/unit/rest/OrderRestControllerTest.php` and `tests/e2e/OrderRestControllerE2ETest.php` exist but may not cover the CSV export or revenue endpoints.

**`MenuLayout.jsx` Is 1123 Lines with 64 Inline Style Declarations:**
- Files: `customer-app/src/components/menu/MenuLayout.jsx`
- Why fragile: One component manages layout, category tabs, product grid, cart state display, mobile/desktop branching, and branch context reading. 64 `style={{}}` blocks use `theme` object values directly without Tailwind, making responsive changes risky.
- Safe modification: Test on both mobile and desktop viewports after any change; verify RTL layout.
- Test coverage: No frontend tests exist for this component.

**`ProductCustomizationModal.jsx` Is 679 Lines:**
- Files: `customer-app/src/components/products/ProductCustomizationModal.jsx`
- Why fragile: Single component handles multi-group ingredient selection, validation, price calculation, and form state. Ingredient group display logic is tightly coupled to the data shape returned by the API.
- Safe modification: Run a complete checkout flow test on staging after any change.
- Test coverage: `tests/integration/products/ProductCustomizationValidatorIntegrationTest.php` covers backend validation but not frontend rendering.

---

## Test Coverage Gaps

**`DashboardAnalyticsController` Has No Tests:**
- What's not tested: All analytics endpoints including revenue calculation, period comparison, percentage change, and the `posts_per_page => -1` query.
- Files: `includes/domains/orders/rest/DashboardAnalyticsController.php`
- Risk: Revenue analytics could return wrong numbers silently after any filter/date logic change.
- Priority: High

**`DeliveryFeeService` Distance Calculation is Untestable (Always Returns 0):**
- What's not tested: `getDistance()` is a private method that always returns `0.0`. The `calculateZoneFee()` private method and zone-based pricing logic are unreachable from tests because `isAddressInRange()` short-circuits to `true`.
- Files: `includes/domains/orders/services/DeliveryFeeService.php`
- Risk: When a geocoding API is eventually wired in, zone fee logic has no existing test coverage.
- Priority: Medium

**No Frontend Tests (Admin or Customer App):**
- What's not tested: All React components, context providers, custom hooks (`useOrderPolling`, `useCart`, `useBranch`), and the `publicApi.js`/`api.js` service layers.
- Files: All files under `admin-app/src/` and `customer-app/src/`
- Risk: UI regressions go undetected until manual testing or production.
- Priority: High — especially for `MenuLayout.jsx`, `CheckoutFlow.jsx`, `ProductCustomizationModal.jsx`, and `useOrderPolling.js`.

**No Tests for Public REST Endpoints: `PublicCustomerRestController`:**
- What's not tested: Guest customer creation rate limiting, phone deduplication, and error responses.
- Files: `includes/domains/customers/rest/PublicCustomerRestController.php` (integration tests exist in `tests/integration/customers/` but rate limiting behavior is not tested)
- Risk: Rate limit bypass and duplicate guest creation go undetected.
- Priority: Medium

**`CustomerRepository::cleanupOldGuests` Not Unit Tested:**
- What's not tested: The cron-triggered guest cleanup path — particularly the 30-day threshold logic and the `error_log` fallback path.
- Files: `includes/domains/customers/repositories/CustomerRepository.php:737–761`
- Risk: A regression in cleanup logic could cause uncontrolled growth of guest customer post records.
- Priority: Medium

---

## Scaling Limits

**All Data Stored as WordPress Custom Post Types:**
- Current capacity: Adequate for a single-location restaurant (hundreds of orders/day).
- Limit: CPTs store data in `wp_posts` + `wp_postmeta` (EAV model). At thousands of orders, meta queries for filtering/sorting become full table scans on `wp_postmeta`. The `DashboardAnalyticsController` running `posts_per_page => -1` will degrade first.
- Scaling path: Introduce a dedicated `squidly_orders` table with proper indexed columns for high-volume deployments. This is a significant architectural change.

**WordPress Transients Used for Rate Limiting (Not Atomic):**
- Current capacity: Works correctly on single-server deployments.
- Limit: `get_transient()` + `set_transient()` is not an atomic operation. On high-concurrency multi-server setups, two simultaneous requests can both read `requests = 9` and both set `requests = 10`, allowing more than the configured limit through.
- Scaling path: Use Redis with atomic increment (`INCR`/`EXPIRE`) if deploying with an object cache that supports Redis.

---

*Concerns audit: 2026-03-27*
