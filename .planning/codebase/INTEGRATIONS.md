# External Integrations

**Analysis Date:** 2026-03-27

## APIs & External Services

**Payment Processing:**
- WooCommerce — sole payment gateway integration
  - SDK/Client: WordPress plugin dependency; accessed via WooCommerce PHP functions (`wc_create_order()`, `wc_get_product()`, `wc_create_refund()`)
  - Implementation: `includes/domains/payments/gateways/WooProvider.php`
  - Interface: `includes/domains/payments/interfaces/PaymentProvider.php` (allows swapping providers via `squidly/payments/provider` filter)
  - Auth: No API keys — runs as WordPress plugin, uses WooCommerce internal APIs
  - Flow: Squidly creates a WooCommerce order with a dedicated payment product, redirects customer to `$wc_order->get_checkout_payment_url()`
  - Refunds: `wc_create_refund()` called from `WooProvider::refund()`
  - Status sync: `includes/domains/payments/hooks/PaymentStatusSync.php` hooks into WooCommerce order status transitions (`woocommerce_order_status_processing`, `completed`, `failed`, `refunded`, `cancelled`, `on-hold`)

**Internal REST API (self-hosted):**
- Admin API — `wp-json/squidly/v1/` namespace
  - Bootstrap: `includes/api/AdminApiBootstrap.php`
  - Auth: WordPress nonce (`X-WP-Nonce` header) + session cookies + `manage_options` capability
- Public API — `wp-json/squidly/v1/public/` namespace
  - Bootstrap: `includes/api/PublicApiBootstrap.php`
  - Auth: None (public endpoints for customer app)

## Data Storage

**Databases:**
- WordPress MySQL/MariaDB (via WordPress itself)
  - Connection: Managed by WordPress `wp-config.php` (outside plugin scope)
  - Client: WordPress `wpdb` (accessed through WordPress functions — no direct SQL in plugin code)
  - Schema: All domain entities stored as WordPress Custom Post Types (CPTs):
    - `product`, `ingredient`, `product_group`, `group_item` — in `includes/domains/products/`
    - `store_branch` — in `includes/domains/stores/`
    - `customer` — in `includes/domains/customers/`
    - `order` — in `includes/domains/orders/`
  - Meta storage: All entity attributes in `wp_postmeta` with `_` prefixed keys

**Transient Storage:**
- WordPress Transients API — cart session storage
  - Key prefix: `squidly_cart_`
  - TTL: 7200 seconds (2 hours default)
  - Implementation: `includes/domains/orders/services/CartService.php`
  - Backed by WordPress object cache (database fallback)

**File Storage:**
- WordPress Media Library — banner images (`_banner_image_url` meta), admin user avatars
- Local filesystem only (no cloud storage integration detected)

**Caching:**
- WordPress Transients API (see cart above)
- No dedicated Redis/Memcached configuration detected at plugin level

## Authentication & Identity

**Auth Provider:**
- WordPress native authentication
  - Admin app: WordPress login required (`manage_options` capability check in `AdminPageHandler.php`, redirects to `wp_login_url()` if unauthenticated)
  - REST auth: WordPress nonce + cookie-based session (`credentials: 'include'` in fetch calls)
  - Nonce: `wp_create_nonce('wp_rest')` injected into page as `window.wpConfig.nonce`, sent as `X-WP-Nonce` header
- Custom roles registered in `includes/admin/RoleManager.php`:
  - `administrator` — full access
  - `restaurant_manager` — manage orders, customers, products, branches
  - `restaurant_staff` — view and manage orders only
- Guest checkout: No account required; guest customer records created via `POST /squidly/v1/public/guest-customer`; tracked by `_is_guest` meta on Customer CPT

**Order Tracking (public):**
- Token-based: guest customers receive a `tracking_token` for polling `GET /squidly/v1/public/orders/{id}/status?token={token}` — no authentication required

## Monitoring & Observability

**Error Tracking:**
- None detected — no Sentry, Rollbar, or similar service integrated

**Logs:**
- PHP: `console.error()` in JS, WordPress default error logging
- Frontend: `console.error()` calls in API service error handlers (`admin-app/src/services/api.js`, `customer-app/src/services/publicApi.js`)
- No structured logging library detected

## CI/CD & Deployment

**Hosting:**
- Local Sites (LocalWP) for development — detected from filesystem path `C:/Users/oresp/Local Sites/squidly/`
- Production hosting: Not specified in codebase

**CI Pipeline:**
- No CI config files detected (no `.github/workflows/`, `.gitlab-ci.yml`, etc.)
- Composer `ci` script defined in `composer.json`: runs lint + PHPUnit with coverage output to `build/coverage.xml`

**Build Process:**
- Frontend must be built manually before deployment: `cd admin-app && npm run build` and `cd customer-app && npm run build`
- Built assets land in `admin-app/dist/assets/` and `customer-app/dist/assets/` with content-hashed filenames
- WordPress PHP templates enqueue these hashed files

## Environment Configuration

**Required WordPress Options (set on activation):**
- `squidly_currency` — default `ILS`
- `squidly_currency_symbol` — default `₪`
- `squidly_loyalty_rate` — default `2.0`
- `squidly_allow_guest_checkout` — default `true`
- `squidly_guest_cleanup_days` — default `30`
- `squidly_default_order_status` — default `pending`
- `squidly_enable_online_ordering` — default `true`
- `squidly_wc_payment_product_id` — WooCommerce product ID for payment; created on activation via `PaymentProductActivation::createPaymentProduct()`

**Frontend Config (injected by PHP template into page):**
- `window.wpConfig.apiUrl` — admin REST base URL
- `window.wpConfig.publicApiUrl` — public REST base URL
- `window.wpConfig.nonce` — WP REST nonce
- `window.wpConfig.assetsUrl` / `window.wpConfig.pluginUrl` — plugin asset URL

**Secrets location:**
- No `.env` files in plugin directory
- WooCommerce payment gateway credentials managed via WooCommerce settings UI (outside plugin scope)

## Webhooks & Callbacks

**Incoming (WooCommerce payment callbacks):**
- `woocommerce_order_status_processing` — maps to Squidly `paid` status
- `woocommerce_order_status_completed` — maps to Squidly `paid` status
- `woocommerce_order_status_failed` — maps to Squidly `failed` status
- `woocommerce_order_status_refunded` — maps to Squidly `refunded` status
- `woocommerce_order_status_cancelled` — maps to Squidly `failed` status
- `woocommerce_order_status_on-hold` — maps to Squidly `pending` status
- Handler: `includes/domains/payments/hooks/PaymentStatusSync.php`
- Link field: `_squidly_order_id` meta on WC order; `_wc_order_id` meta on Squidly order

**Outgoing:**
- None detected — no external webhook dispatching

## Cron Jobs

**Guest Cleanup:**
- WordPress cron: `squidly_cleanup_guests` — daily schedule
- Registered in `squidly-core.php` via `wp_schedule_event()`
- Calls `CustomerRepository::cleanupOldGuests($days)` where `$days` comes from `squidly_guest_cleanup_days` option

---

*Integration audit: 2026-03-27*
