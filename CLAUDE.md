# CLAUDE.md

Guidance for Claude Code when working with this WordPress restaurant management plugin.

## Squidly Core WordPress Plugin

Domain-driven WordPress plugin for restaurant management with decoupled React interfaces. Manages products, ingredients, orders, customers, branches, and WooCommerce payment integration.

## Development Commands

### PHP/Backend
```bash
composer install               # Install dependencies
composer test                  # All tests + linting
composer test:unit|int         # Unit or integration tests
composer test:coverage         # With coverage report
composer lint                  # PHP syntax check
vendor/bin/phpunit <path>      # Specific test
vendor/bin/phpunit --testsuite e2e  # E2E tests
```

### Frontend (Admin & Customer Apps)
```bash
cd admin-app/      # or customer-app/
npm install
npm run dev        # Dev server (port 3000 for admin, 3001 for customer)
npm run build      # Production build
npm run preview    # Preview build
npm run lint       # ESLint
```

## Architecture

### Domain-Driven Design
```
includes/domains/
├── customers/    # Customer management (regular + guests)
├── orders/       # Order lifecycle + payment integration
├── products/     # Products, ingredients, groups
├── stores/       # Store branches
└── payments/     # WooCommerce integration

Each domain:
├── models/       # DTOs (Order, Product, Customer)
├── repositories/ # Data access (implements RepositoryInterface)
├── post-types/   # WordPress CPTs (extends BasePostType)
├── rest/         # REST controllers (extends WP_REST_Controller)
└── services/     # Business logic
```

### Shared Infrastructure
```
includes/shared/
├── interfaces/   # RepositoryInterface, PostTypeInterface
├── abstracts/    # BasePostType
├── exceptions/   # ResourceInUseException
└── models/       # Address, enums

includes/core/    # PostTypeRegistry
includes/admin/   # AdminMenuManager, AdminPageHandler
includes/api/     # AdminApiBootstrap
```

### Repository Pattern
All repositories implement `RepositoryInterface`:
- **CRUD:** `create()`, `get()`, `update()`, `delete()`, `getAll()`
- **Query:** `findBy($criteria, $limit, $offset)`, `countBy()`, `exists()`
- **Validation:** `validateCreateData()`, `validateUpdateData()`
- **Responsibilities:** Abstract WP operations, sanitize input, enforce business rules, manage relationships

### Post Types
- Extend `BasePostType`, implement `PostTypeInterface`
- Registered via `PostTypeRegistry::register_all()`
- Not public (REST API/admin access only)
- Meta fields use `_` prefix: `_regular_price`, `_branch_id`, `_is_guest`
- **Available:** `product`, `ingredient`, `product_group`, `group_item`, `store_branch`, `customer`, `order`

### REST API
**Namespace:** `squidly/v1` | **Base:** `wp-json/squidly/v1/`

**Auth:** WordPress nonce (`X-WP-Nonce` header) + session cookies + capability checks

**Standard Endpoints:**
```
GET/POST    /squidly/v1/{resource}      # List/Create
GET/PUT/DEL /squidly/v1/{resource}/{id} # Get/Update/Delete
```

**Pagination:** Query params `per_page`, `offset` | Response headers `X-WP-Total`, `X-WP-TotalPages`

**Controllers:** ProductGroup, Ingredient, IngredientGroup, Product, StoreBranch, Customer, Order, Payment

**Special:**
- `GET /orders/statistics` - Analytics
- `GET /orders/customer/{id}` - Order history
- `GET /auth/check` - Auth verification
- `GET /admin/config` - Frontend config

### Models
Simple DTOs with constructor accepting array, typed public properties, and `toArray()` method.

## Key Patterns

### Exception Handling
- **Custom:** `ResourceInUseException` (dependency deletion)
- **Integration:** Catch in REST controllers, return `WP_REST_Response` with status codes

```php
try {
    $this->repository->delete($id);
} catch (ResourceInUseException $e) {
    return new WP_REST_Response(['error' => $e->getMessage()], 409);
}
```

### Validation
- Repository methods: `validateCreateData()`, `validateUpdateData()` throw `InvalidArgumentException`
- Sanitization: `sanitize_text_field()`, `wp_kses_post()`, type casting
- Business rules: Check dependencies, validate transitions, enforce integrity

### Optimistic UI (Frontend)
All user-facing actions must update the UI immediately without waiting for the server response. If the server later rejects the request, revert the UI change and show an error.

**Pattern:**
1. Update local state / UI instantly on user action
2. Fire the API call in the background
3. On server error: revert state and notify the user (e.g., toast)

**Example — Logout:**
```javascript
const logout = useCallback(() => {
  const oldToken = token;
  // Optimistic: clear UI immediately
  localStorage.removeItem(TOKEN_KEY);
  setToken(null);
  setCustomer(null);
  // Fire server call in background — don't await
  if (oldToken) {
    authApi.logout(oldToken).catch(() => {});
  }
}, [token]);
```

**When to apply:** Any action where the expected outcome is known (logout, remove from cart, toggle, delete). Login/signup still needs to await the server since the response data (token, customer) is required.

### Testing
```
tests/
├── bootstrap.php  # WP test env + plugin loading
├── unit/          # Isolated with Mockery
├── integration/   # WordPress DB integrated
└── e2e/           # Full API workflows
```

**Suites:** `unit`, `integration`, `e2e`, `domains`
**Bootstrap:** Loads WP test library, plugin, domain classes, registers post types

## Frontend Applications

### Admin App (`admin-app/`)
**Tech:** React 18, Vite, TailwindCSS 3.3, Heroicons, Context API (no TypeScript/state library)
**Build:** Entry `src/main.jsx`, output `dist/assets/main-[hash].{js,css}`, dev port 3000
**Features:** RTL support, LiaDiplomat font, semantic colors, responsive, branch filtering, pagination

### Customer App (`customer-app/`)
**Tech:** Same as admin app
**Build:** Dev port 3001 (avoid admin conflict)
**Architecture:** SPA, tab-based categories, multi-step checkout, public API only

**Structure:**
```
customer-app/src/
├── components/
│   ├── products/  # ProductCard, ProductGrid, ProductCustomizer, CategoryTabs
│   ├── cart/      # CartPanel, CartItem, CartSummary, DiscountApplier
│   ├── checkout/  # CheckoutFlow, CustomerInfoStep, DeliveryStep, PaymentStep
│   ├── branches/  # BranchCard, BranchSelector, BranchHours, BranchMap
│   └── orders/    # OrderTracker, OrderHistory, OrderStatusIndicator
├── contexts/      # Cart, Branch, Order, Auth
├── hooks/         # useCart, useBranch, useOrder, useCustomization, usePolling
├── services/      # publicApi.js, cartSession.js
├── i18n/          # translations.js (default: Hebrew)
└── config/        # theme.js
```

### Shared UI Library (`shared-ui/`)
**Purpose:** Share components between admin & customer apps
**Structure:** `atoms/` (Button, Card, Input...), `molecules/` (FormField, StatusBadge...), `organisms/` (Modal, Toast), `hooks/` (useTheme, useMediaQuery)
**Import:** `import { Button } from '../../shared-ui/atoms'`
**Build:** No separate build, directly imported via Vite `@shared-ui` alias

## Customer App Features

### 1. Branch Selection
First step before menu. Displays: name, address, phone, hours, real-time open/closed status, kosher type, accessibility.

### 2. Product Catalog
Tab-based categories (admin-defined), branch filtering, dynamic content (no page refreshes).

### 3. Product Customization
Add/remove ingredients, grouped toppings, price adjustments, special instructions, validation (min/max).

### 4. Shopping Cart
Session-based persistence (2hr default), operations: add/update/remove, discount codes, price breakdown.

### 5. Multi-Step Checkout
Step 1: Customer info | Step 2: Delivery/pickup + timing | Step 3: Payment + confirmation
Progressive disclosure with validation.

### 6. Delivery & Pickup
Branch-level config: enable/disable, fee calculation, min order, radius, estimated times.

### 7. Real-Time Tracking
Polling (10-15s intervals), visual status, estimated times, guest token-based.

### 8. Authentication
Optional login, guest checkout (no account required), guest customer records.

### 9. Theming
Same system as admin, customizable colors/fonts/logos via WordPress options.

### 10. Multi-Language (i18n) ✅
**Default Language:** Hebrew (RTL)
**Supported:** Hebrew, English, Arabic
**API:** `t(key, vars)`, `setLanguage(lang)`, `getCurrentLanguage()`, `getAvailableLanguages()`
**Location:** `customer-app/src/i18n/translations.js`
**Features:** Variable interpolation, auto RTL/LTR, extensible (upgrade to react-i18next later)

**Usage:**
```javascript
import { t } from '../../i18n/translations';
const text = t('welcome');  // Returns Hebrew by default
setLanguage('en');          // Switch to English + LTR
```

**Categories:** Common, Branch Selection, Cart, Checkout, Products, Orders

**Adding Languages:** Add translation object, update `getAvailableLanguages()`, set RTL if needed.

### Customer Journey
1. **Landing** → Select branch → Verify hours
2. **Browse** → Category tabs → Product details
3. **Customize** → Modify ingredients → Notes → Add to cart
4. **Cart** → Adjust quantities → Discount → Checkout
5. **Checkout** → Customer info → Delivery/pickup → Payment
6. **Payment** → WooCommerce redirect → Confirmation
7. **Track** → Real-time status → Notifications

## Public API Endpoints

Customer app requires public endpoints (no auth):

**Products:**
- `GET /squidly/v1/public/products[?branch_id=&category=&search=]`
- `GET /squidly/v1/public/products/{id}`

**Branches:**
- `GET /squidly/v1/public/branches`
- `GET /squidly/v1/public/branches/{id}`

**Customers:**
- `POST /squidly/v1/public/guest-customer` (first_name, last_name, phone, email)

**Orders:**
- `POST /squidly/v1/public/orders` (customer_id, branch_id, order_items, delivery_type, address, timing, payment_method)
- `GET /squidly/v1/public/orders/{id}/status?token={tracking_token}`

**Cart:**
- `POST/GET/PUT/DELETE /squidly/v1/public/cart[/{token}]`

**Delivery:**
- `GET /squidly/v1/public/delivery-fee?branch_id={id}&address={address}`

**Availability:**
- `GET /squidly/v1/public/availability?branch_id={id}&product_ids={ids}`

**Security:** Rate limiting, CAPTCHA optional, input validation, token expiration, no sensitive data exposure

## Feature Roadmap

**Should Have:**
- ✅ Multi-Language (Hebrew, English, Arabic + RTL)
- Language Manager UI
- Customer accounts/auth
- Loyalty points integration
- Real-time notifications (WebSocket/push)
- Advanced search (ingredients, dietary filters)
- Guest order history
- Saved addresses
- Favorites/wishlist
- Delivery tracking map

**Nice to Have:** Social sharing, AI recommendations, group orders, scheduled orders, dietary filters, meal builder, calorie calculator, order templates

**Technical Debt:** Error boundaries, skeleton states, accessibility audit, performance optimization, PWA, E2E tests

## Important Conventions

### Class Loading
Custom autoloader in `squidly-core.php` with domain-aware paths. Manual requires for payment domain classes.

### Meta Fields
Always prefix with `_`, use snake_case, cast to correct type, handle null gracefully.

### Post Type Constants
✅ Use: `ProductPostType::POST_TYPE`
❌ Never: `'product'` (hardcoded string)

### Order Status
`pending`, `confirmed`, `preparing`, `ready`, `completed`, `cancelled`

### Payment Status
`pending`, `paid`, `failed`, `refunded`

### Payment Integration
WooCommerce via `PaymentBootstrap`, `PaymentProvider` interface, `WooProvider` implementation, `PaymentService`, `PaymentStatusSync`

**📖 See:** `docs/WOOCOMMERCE_PAYMENT_INTEGRATION.md` for complete integration details, troubleshooting, and architecture

**Key Points:**
- Uses single payment product (option: `squidly_wc_payment_product_id`)
- Payment product MUST have `publish` status (not `private`) for orders to be payable
- WooCommerce orders created as guest orders (customer_id = 0)
- Squidly order linked via `_wc_order_id` meta field
- Order metadata stores Squidly order details in WC order items

### Customer Management
Regular (accounts) vs Guest (`_is_guest` meta). Guest cleanup cron: `squidly_cleanup_guests` (default 30 days).

## Security

- Capability-based access: `current_user_can()` checks
- Input sanitization: `sanitize_text_field()`, `wp_kses_post()`, `absint()`
- Direct access prevention: `if (!defined('ABSPATH')) exit;`
- Nonce verification: WordPress nonces, `X-WP-Nonce` header
- CSRF protection: WordPress nonces + same-origin policy

## Configuration

**Constants:** `SQUIDLY_CORE_VERSION`, `SQUIDLY_CORE_PATH`, `SQUIDLY_CORE_URL`

**Options:** `squidly_currency` (ILS), `squidly_currency_symbol` (₪), `squidly_loyalty_rate` (2.0), `squidly_allow_guest_checkout` (true), `squidly_guest_cleanup_days` (30), `squidly_default_order_status` (pending), `squidly_enable_online_ordering` (true), `squidly_wc_payment_product_id`

## Common Workflows

### Add Domain Entity
1. Create model (`models/{Entity}.php`) with constructor + `toArray()`
2. Create post type (`post-types/{Entity}PostType.php`) extending `BasePostType`
3. Create repository (`repositories/{Entity}Repository.php`) implementing `RepositoryInterface`
4. Create REST controller (`rest/{Entity}RestController.php`) extending `WP_REST_Controller`
5. Register: Add to `PostTypeRegistry`, require controller in `squidly-core.php`

### Add REST Endpoint
1. Add route in controller: `register_rest_route($namespace, '/endpoint', [...])`
2. Add method to API service: `async getEndpoint(id) { return await this.fetch(\`endpoint/${id}\`); }`
3. Use in component: `const data = await api.getEndpoint(id);`

### Run Tests
```bash
composer test                # All tests
composer test:unit|int       # Specific suite
vendor/bin/phpunit <path>    # Specific file/method
composer test:coverage       # With coverage
```

## Git Workflow (Autonomous)

Claude MUST manage git automatically without waiting for the user to ask. This is not optional.

### When to Commit
- After any working change: code compiles, builds succeed, tests pass (if applicable)
- Do NOT batch unrelated changes into one commit — commit each logical unit separately
- Do NOT ask "should I commit?" — just do it

### Commit Rules
- Write concise commit messages in English, imperative mood ("Add branch modal", not "Added branch modal")
- Always include `Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>`
- Stage only relevant files (no `git add -A` unless everything is relevant)
- Never commit `.env`, credentials, or `node_modules`

### When to Push
- Push to remote immediately after every commit
- If on a feature branch: `git push -u origin <branch>`
- If on `dev` or `main`: `git push origin <branch>`

### Branch Strategy
- **`main`** — production-stable code. Only merge here when a feature is fully complete and tested.
- **`dev`** — active development integration branch. Merge feature branches here when done.
- **Feature branches** — create from `dev` for any non-trivial work: `feature/<short-name>`

### Feature Workflow
1. **Start feature:** `git checkout dev && git pull origin dev && git checkout -b feature/<name>`
2. **Work:** commit and push as you go
3. **Feature done:** merge into `dev`:
   ```
   git checkout dev && git pull origin dev && git merge feature/<name> && git push origin dev
   ```
4. **Milestone / release-ready:** merge `dev` into `main`:
   ```
   git checkout main && git pull origin main && git merge dev && git push origin main
   ```
5. **Cleanup:** delete merged feature branch locally and remotely

### Decision Guide
| Situation | Action |
|---|---|
| Small fix on `dev` or `main` | Commit + push directly |
| New feature or multi-step work | Create `feature/` branch from `dev` |
| Feature branch work is done | Merge to `dev`, push, delete branch |
| User says "merge to main" or feature is release-ready | Merge `dev` → `main`, push |
| Build fails | Fix first, then commit the fix |

### What NOT to Do
- Never force-push (`--force`) without explicit user request
- Never amend commits that are already pushed
- Never rebase shared branches without user approval
- Never leave working changes uncommitted at the end of a task

## Troubleshooting

**Class not found:** Check autoloader paths, file naming, manual requires for payment classes

**Post type constants undefined:** Ensure `PostTypeRegistry::register_all()` called, check test bootstrap

**REST API 401/403:** Verify nonce header, check capabilities, ensure cookies sent

**Frontend not loading:** Check `wpConfig` in page source, API base URL, nonce, browser console, `api.init()` called

**Tests failing:** Verify WP test library installed (`bin/install-wp-tests.sh`), check DB credentials, ensure bootstrap loads dependencies
