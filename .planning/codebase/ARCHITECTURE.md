# Architecture

**Analysis Date:** 2026-03-27

## Pattern Overview

**Overall:** Domain-Driven Design (DDD) on top of WordPress as a persistence/runtime layer

**Key Characteristics:**
- All data stored as WordPress Custom Post Types (CPTs) with meta fields, not custom DB tables
- Domains are self-contained vertical slices: models, repositories, post-types, REST controllers, services
- Two decoupled React SPAs (admin-app, customer-app) communicate exclusively via REST API — they have no direct PHP dependency
- Public API (unauthenticated) is separate from the admin API (WordPress nonce + capability checks)
- Payment integration is isolated in its own domain with a `PaymentProvider` interface allowing gateway swaps

## Layers

**Models (DTOs):**
- Purpose: Plain data transfer objects representing domain entities
- Location: `includes/domains/{domain}/models/`
- Contains: Typed public properties, constructor accepting array, `toArray()` method, domain constants
- Depends on: Nothing (pure PHP, no WP dependencies)
- Used by: Repositories (instantiate), REST controllers (serialize to response)
- Example: `includes/domains/orders/models/Order.php`, `includes/domains/products/models/Product.php`

**Post Types:**
- Purpose: Register WordPress CPTs and define their WordPress admin meta boxes
- Location: `includes/domains/{domain}/post-types/`
- Contains: CPT registration args, meta box definitions, save hooks
- Depends on: `BasePostType` (abstract), `PostTypeInterface`
- Used by: `PostTypeRegistry`, repositories (via `POST_TYPE` constant)
- Example: `includes/domains/orders/post-types/OrderPostType.php`

**Repositories:**
- Purpose: All data access — abstract WP_Query, wp_insert_post, get_post_meta, etc.
- Location: `includes/domains/{domain}/repositories/`
- Contains: CRUD + query methods, input sanitization, business rule enforcement, validation
- Depends on: WordPress functions, Models, Post Type constants
- Used by: REST controllers
- All implement: `RepositoryInterface` (create, get, update, delete, getAll, findBy, countBy, exists)
- Example: `includes/domains/orders/repositories/OrderRepository.php`

**REST Controllers (Admin API):**
- Purpose: HTTP endpoints for the authenticated admin React app
- Location: `includes/domains/{domain}/rest/` (non-Public* files)
- Contains: Route registration, request handling, permission callbacks, response formatting
- Depends on: Repositories, `WP_REST_Controller`
- Namespace: `squidly/v1`
- Auth: WordPress nonce via `X-WP-Nonce` header + `current_user_can()` checks
- Example: `includes/domains/orders/rest/OrderRestController.php`

**REST Controllers (Public API):**
- Purpose: HTTP endpoints for the unauthenticated customer React app
- Location: `includes/domains/{domain}/rest/Public*RestController.php`
- Contains: Public route registration, `__return_true` permission callbacks
- Depends on: Repositories
- Namespace: `squidly/v1/public`
- Auth: None — rate limiting intended but not fully implemented
- Example: `includes/domains/stores/rest/PublicBranchRestController.php`

**Services:**
- Purpose: Business logic that spans multiple repositories or requires external integration
- Location: `includes/domains/{domain}/services/`
- Contains: Complex workflows, third-party calls, calculations
- Examples:
  - `includes/domains/payments/services/PaymentService.php` — delegates to PaymentProvider
  - `includes/domains/orders/services/CartService.php` — session-based cart management
  - `includes/domains/orders/services/DeliveryFeeService.php` — fee calculation
  - `includes/domains/products/services/ProductCustomizationValidator.php` — ingredient validation

**Shared Infrastructure:**
- Purpose: Cross-domain contracts and base classes
- Location: `includes/shared/`
- Key files:
  - `includes/shared/interfaces/RepositoryInterface.php` — CRUD contract all repositories implement
  - `includes/shared/interfaces/PostTypeInterface.php` — CPT registration contract
  - `includes/shared/abstracts/BasePostType.php` — shared CPT registration logic
  - `includes/shared/exceptions/ResourceInUseException.php` — thrown when deleting in-use entities

## Data Flow

**Admin CRUD Request (e.g., update a product):**

1. React admin-app calls `api.updateProduct(id, data)` via `ApiService.fetch()`
2. HTTP PUT sent to `wp-json/squidly/v1/products/{id}` with `X-WP-Nonce` header
3. `ProductRestController::update_item()` fires, runs `admin_permissions_check()`
4. Controller calls `ProductRepository::update(id, data)`
5. Repository validates data, calls `wp_update_post()` + `update_post_meta()`
6. Repository returns `bool`, controller returns `WP_REST_Response`
7. React app receives JSON, updates local state

**Customer Order Flow:**

1. Customer selects branch via `BranchSelectionModal` → stored in `BranchContext`
2. Customer adds items to cart → `CartContext` manages local state + calls `PublicCartRestController`
3. Cart persisted server-side via `CartService` using session tokens
4. Customer submits checkout → `PublicOrderRestController::create_order()` fires
5. `OrderRepository::create()` stores order as WP post with meta
6. If online payment: `PaymentRestController` calls `PaymentService::startPayment()`
7. `PaymentService` delegates to `WooProvider::startPayment()` → creates WC order, returns redirect URL
8. Customer redirected to WooCommerce checkout, pays, returns to `/orders` page
9. `PaymentStatusSync` hook listens for WC order status changes, syncs back to Squidly order

**State Management (Frontend):**
- Admin app: No global state library; component-level `useState` + props
- Customer app: React Context API — `BranchContext`, `CartContext`, `ToastContext`
- Customer app polling: `useOrderPolling.js` polls `GET /public/orders/{id}/status` every 10-15s

## Key Abstractions

**RepositoryInterface:**
- Purpose: Standard contract for all data access
- Location: `includes/shared/interfaces/RepositoryInterface.php`
- Methods: `create()`, `get()`, `getAll()`, `update()`, `delete()`, `findBy()`, `countBy()`, `exists()`
- All repositories implement this; controllers depend on the interface behavior

**BasePostType:**
- Purpose: Shared WordPress CPT registration and meta box logic
- Location: `includes/shared/abstracts/BasePostType.php`
- Pattern: Abstract class; child classes implement `getPostType()`, `getLabels()`, `getSupports()`; `init()` hooks WP `init` action

**PaymentProvider Interface:**
- Purpose: Swappable payment gateway contract
- Location: `includes/domains/payments/interfaces/PaymentProvider.php`
- Methods: `startPayment()`, `refund()`, `label()`
- Current implementation: `includes/domains/payments/gateways/WooProvider.php`
- Swap mechanism: `apply_filters('squidly/payments/provider', new WooProvider())`

**ApiService (admin-app):**
- Purpose: Singleton HTTP client for all admin-app → PHP communication
- Location: `admin-app/src/services/api.js`
- Pattern: Instantiated once, `api.init()` called on app mount to verify auth and fetch config
- All fetch calls attach `X-WP-Nonce` header automatically

**publicApi (customer-app):**
- Purpose: HTTP client for customer-app → public PHP API
- Location: `customer-app/src/services/publicApi.js`
- Pattern: No auth headers; calls `squidly/v1/public/*` endpoints only

## Entry Points

**PHP Plugin Bootstrap:**
- Location: `squidly-core.php`
- Triggers: WordPress `plugins_loaded` lifecycle
- Responsibilities: Define constants, register autoloader, call `PostTypeRegistry::register_all()`, require payment files (manual), initialize `AdminApiBootstrap`, `PublicApiBootstrap`, `AdminPageHandler`, `CustomerPageHandler`, schedule guest cleanup cron

**Admin API Bootstrap:**
- Location: `includes/api/AdminApiBootstrap.php`
- Triggers: `rest_api_init` WP action
- Responsibilities: Instantiate and register all admin REST controllers, setup CORS for dev, register `/auth/check` and `/admin/config` endpoints

**Public API Bootstrap:**
- Location: `includes/api/PublicApiBootstrap.php`
- Triggers: `rest_api_init` WP action
- Responsibilities: Instantiate and register all public REST controllers, register `/public/config` endpoint

**Payment Bootstrap:**
- Location: `includes/domains/payments/bootstrap/PaymentBootstrap.php`
- Triggers: `init` WP action
- Responsibilities: Initialize `PaymentStatusSync` (WC hooks), `PaymentAdminActions`, register payment REST routes

**Admin React App:**
- Location: `admin-app/src/main.jsx`
- Triggers: Browser loads `admin-app/dist/assets/main-[hash].js` served by `AdminPageHandler` or `includes/templates/admin-page.php`
- Responsibilities: Apply theme CSS variables, mount `<App />` to `#squidly-admin-root`

**Customer React App:**
- Location: `customer-app/src/main.jsx`
- Triggers: Browser loads `customer-app/dist/assets/main-[hash].js` served by `CustomerPageHandler` on the `/orders` WordPress page
- Responsibilities: Set background image from `window.wpConfig`, mount `<App />` to `#squidly-customer-app`

## Error Handling

**Strategy:** Exception-based in PHP; try/catch in REST controllers converts exceptions to `WP_REST_Response` with appropriate HTTP status codes

**Patterns:**
- `InvalidArgumentException` — thrown by repository `validateCreateData()` / `validateUpdateData()`, caught in REST controllers, returned as 400
- `ResourceInUseException` — thrown by repository `delete()` when entity has dependants (e.g., ingredient used in products), caught in REST controllers, returned as 409
- `RuntimeException` — thrown when WP operations fail (e.g., `wp_insert_post` returns `WP_Error`), returned as 500
- Frontend: API errors propagate as thrown JS errors; components display error states via `ErrorState` molecule or toast notifications

## Cross-Cutting Concerns

**Logging:** `console.error()` in frontend; no server-side structured logging — PHP errors go to WordPress error log

**Validation:**
- PHP: Repository-level validation in `validateCreateData()` / `validateUpdateData()` before any DB write
- PHP: Input sanitization via `sanitize_text_field()`, `absint()`, `wp_kses_post()`, `esc_url_raw()` in repositories
- Frontend: Form-level validation in modal components before API calls

**Authentication:**
- Admin: WordPress nonce (`X-WP-Nonce` header) + session cookies + `current_user_can('manage_options')` on all admin endpoints
- Public: No authentication; guest orders tracked by `_tracking_token` meta field
- Admin app page: `admin.php` and `AdminPageHandler` redirect unauthenticated users to WP login

**CORS:**
- Dev only: Both `AdminApiBootstrap` and `PublicApiBootstrap` allow `localhost:*` origins when `WP_DEBUG` is true
- Production: Same-origin only (no CORS headers)

---

*Architecture analysis: 2026-03-27*
