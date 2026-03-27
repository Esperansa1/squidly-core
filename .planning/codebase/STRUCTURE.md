# Codebase Structure

**Analysis Date:** 2026-03-27

## Directory Layout

```
squidly-core/
├── squidly-core.php          # Plugin entry point — autoloader, bootstrap calls, hooks
├── admin.php                 # Standalone admin HTML shell (legacy, loads admin-app assets)
├── composer.json             # PHP dependencies + test scripts
├── phpunit.xml               # PHPUnit test suite config
│
├── includes/                 # All PHP backend code
│   ├── core/                 # Cross-cutting PHP infrastructure
│   │   └── PostTypeRegistry.php      # Registers all 7 CPTs at once
│   ├── shared/               # Contracts and base classes used across domains
│   │   ├── interfaces/
│   │   │   ├── RepositoryInterface.php
│   │   │   └── PostTypeInterface.php
│   │   ├── abstracts/
│   │   │   └── BasePostType.php
│   │   ├── exceptions/
│   │   │   └── ResourceInUseException.php
│   │   └── models/
│   │       └── enums/        # Shared enum types
│   ├── api/                  # API bootstrap and shared controllers
│   │   ├── AdminApiBootstrap.php     # Wires all admin REST controllers
│   │   ├── PublicApiBootstrap.php    # Wires all public REST controllers
│   │   ├── PublicRestController.php  # Base for public controllers
│   │   ├── controllers/
│   │   │   └── AdminUserRestController.php
│   │   ├── middleware/
│   │   └── routes/
│   ├── admin/                # WordPress page handlers and role management
│   │   ├── AdminPageHandler.php      # Creates/serves /restaurant-admin WP page
│   │   ├── CustomerPageHandler.php   # Creates/serves /orders WP page
│   │   └── RoleManager.php           # WordPress capability definitions
│   ├── templates/            # PHP HTML templates loaded by page handlers
│   │   ├── admin-page.php            # Injects admin-app assets + window.wpConfig
│   │   └── customer-page.php         # Injects customer-app assets + window.wpConfig
│   └── domains/              # Domain-driven business logic
│       ├── customers/
│       │   ├── models/       Customer.php
│       │   ├── post-types/   CustomerPostType.php
│       │   ├── repositories/ CustomerRepository.php
│       │   ├── rest/         CustomerRestController.php, PublicCustomerRestController.php
│       │   └── services/
│       ├── orders/
│       │   ├── enums/
│       │   ├── models/       Order.php, OrderItem.php, Cart.php, CartItem.php
│       │   ├── post-types/   OrderPostType.php
│       │   ├── repositories/ OrderRepository.php
│       │   ├── rest/         OrderRestController.php, DashboardAnalyticsController.php,
│       │   │                 PublicOrderRestController.php, PublicCartRestController.php
│       │   └── services/     CartService.php, DeliveryFeeService.php
│       ├── products/
│       │   ├── models/       Product.php, Ingredient.php, ProductGroup.php, GroupItem.php
│       │   ├── post-types/   ProductPostType.php, IngredientPostType.php,
│       │   │                 ProductGroupPostType.php, GroupItemPostType.php
│       │   ├── repositories/ ProductRepository.php, IngredientRepository.php,
│       │   │                 ProductGroupRepository.php
│       │   ├── rest/         ProductRestController.php, IngredientRestController.php,
│       │   │                 IngredientGroupRestController.php, ProductGroupRestController.php,
│       │   │                 PublicProductRestController.php
│       │   └── services/     ProductCustomizationValidator.php
│       ├── stores/
│       │   ├── models/       StoreBranch.php
│       │   ├── post-types/   StoreBranchPostType.php
│       │   ├── repositories/ StoreBranchRepository.php
│       │   ├── rest/         StoreBranchRestController.php, PublicBranchRestController.php
│       │   └── services/
│       └── payments/         # WooCommerce payment integration (namespaced: Squidly\Domains\Payments\*)
│           ├── interfaces/   PaymentProvider.php
│           ├── gateways/     WooProvider.php
│           ├── services/     PaymentService.php
│           ├── hooks/        PaymentStatusSync.php, OrderItemDisplay.php
│           ├── admin/        PaymentAdminActions.php
│           ├── activation/   PaymentProductActivation.php
│           ├── rest/         PaymentRestController.php
│           └── bootstrap/    PaymentBootstrap.php
│
├── admin-app/                # React admin SPA (authenticated)
│   ├── src/
│   │   ├── main.jsx          # Entry point — mounts to #squidly-admin-root
│   │   ├── App.jsx           # RouterProvider wrapping AppLayout
│   │   ├── router.jsx        # Custom client-side router
│   │   ├── fonts.css         # LiaDiplomat font face declarations
│   │   ├── fontLoader.js     # Dynamic font loading
│   │   ├── services/
│   │   │   └── api.js        # Singleton ApiService — all REST calls
│   │   ├── config/
│   │   │   └── theme.js      # DEFAULT_THEME, generateCSSVariables()
│   │   ├── hooks/            # usePagination.js, useSorting.js, useTheme.js
│   │   ├── utils/
│   │   ├── assets/
│   │   │   ├── fonts/
│   │   │   └── images/
│   │   ├── styles/
│   │   │   └── admin.css
│   │   └── components/
│   │       ├── AppLayout.jsx          # Shell: Sidebar + content area
│   │       ├── Sidebar.jsx            # Navigation sidebar
│   │       ├── ManagementDashboard.jsx
│   │       ├── MenuManagement.jsx     # Products/ingredients/groups page
│   │       ├── BranchManagement.jsx   # Store branches page
│   │       ├── OrderManagement.jsx    # Orders page
│   │       ├── CustomerManagement.jsx # Customers page
│   │       ├── Settings.jsx
│   │       ├── settings/              # Settings sub-components
│   │       └── ui/                    # Atomic design component library
│   │           ├── atoms/             Button, Card, Input, Label, Checkbox, Select,
│   │           │                      Textarea, Badge, Divider, IconButton, Spinner,
│   │           │                      RadioButton
│   │           ├── molecules/         FormField, TabButton, ActionButton, ActionButtons,
│   │           │                      Pagination, StatusBadge, SearchInput, StatisticsCard,
│   │           │                      OrderItemsList, TimeIndicator, EmptyState,
│   │           │                      ErrorState, LoadingState, DownloadButton
│   │           └── organisms/         Modal, GroupModal, OrderDetailsModal, TabContent,
│   │                                  PreviousOrdersTable, StatisticsCards
│   └── dist/                 # Built output (committed)
│       └── assets/           main-[hash].js, main-[hash].css
│
├── customer-app/             # React customer SPA (public)
│   ├── src/
│   │   ├── main.jsx          # Entry point — mounts to #squidly-customer-app
│   │   ├── App.jsx           # Context providers wrapping view router
│   │   ├── index.css
│   │   ├── services/
│   │   │   └── publicApi.js  # HTTP client for squidly/v1/public/* endpoints
│   │   ├── contexts/
│   │   │   ├── BranchContext.jsx  # Selected branch state
│   │   │   ├── CartContext.jsx    # Cart items + operations
│   │   │   └── ToastContext.jsx   # Global toast notifications
│   │   ├── hooks/
│   │   │   ├── useMediaQuery.js
│   │   │   └── useOrderPolling.js # Polls order status every 10-15s
│   │   ├── i18n/
│   │   │   └── translations.js   # Hebrew (default), English, Arabic + RTL logic
│   │   ├── config/
│   │   │   └── theme.js
│   │   ├── assets/fonts/
│   │   ├── styles/
│   │   └── components/
│   │       ├── branches/      BranchSelectionModal.jsx, BranchCard.jsx, etc.
│   │       ├── menu/          MenuLayout.jsx (main shell), CategoryTabs.jsx
│   │       ├── products/      ProductCard.jsx, ProductGrid.jsx, ProductCustomizer.jsx
│   │       ├── cart/          CartPanel.jsx, CartItem.jsx, CartSummary.jsx,
│   │       │                  DiscountApplier.jsx
│   │       ├── checkout/      CheckoutFlow.jsx, CustomerInfoStep.jsx,
│   │       │                  DeliveryStep.jsx, PaymentStep.jsx
│   │       ├── orders/        OrderTracker.jsx, OrderHistory.jsx,
│   │       │                  OrderStatusIndicator.jsx
│   │       └── ui/            Customer-facing UI primitives
│   └── dist/                 # Built output (committed)
│
├── tests/                    # PHPUnit tests
│   ├── bootstrap.php         # WP test env + plugin loading
│   ├── traits/               # Shared test helpers
│   ├── unit/                 # Isolated tests (Mockery)
│   │   ├── customers/
│   │   ├── orders/
│   │   ├── products/
│   │   ├── rest/
│   │   ├── services/
│   │   ├── shared/
│   │   └── stores/
│   ├── integration/          # WordPress DB tests
│   │   ├── admin/
│   │   ├── customers/
│   │   ├── orders/
│   │   ├── products/
│   │   └── rest/
│   ├── e2e/                  # Full API workflow tests
│   ├── api/                  # API-level tests
│   ├── domains/              # Domain-specific tests
│   │   ├── payments/
│   │   └── products/
│   └── performance/
│
├── bin/                      # Shell scripts (e.g., install-wp-tests.sh)
├── vendor/                   # Composer dependencies (committed)
└── .planning/                # GSD planning documents
    └── codebase/
```

## Directory Purposes

**`includes/domains/`:**
- The core of all business logic, split into 5 domains: `customers`, `orders`, `products`, `stores`, `payments`
- Each domain is a vertical slice: model → post-type → repository → REST controller
- Adding a new entity always means adding files in all 4-5 subdirectories of one domain folder

**`includes/shared/`:**
- Framework code that domains depend on but don't own
- `interfaces/` — PHP contracts (`RepositoryInterface`, `PostTypeInterface`)
- `abstracts/` — `BasePostType` (shared CPT registration behavior)
- `exceptions/` — `ResourceInUseException` for dependency-protection during deletes

**`includes/api/`:**
- Wiring layer: `AdminApiBootstrap` and `PublicApiBootstrap` instantiate and connect all REST controllers
- `controllers/` — Controllers that don't belong to a single domain (e.g., `AdminUserRestController`)

**`includes/admin/`:**
- WordPress page management: `AdminPageHandler` (creates `/restaurant-admin` page) and `CustomerPageHandler` (creates `/orders` page)
- `RoleManager` — defines WordPress capabilities for the plugin

**`includes/templates/`:**
- PHP HTML files that inject React app assets and `window.wpConfig` / `window.SQUIDLY_CONFIG` global
- These are loaded by the page handlers as page templates

**`admin-app/src/components/ui/`:**
- Atomic design component library shared across admin feature pages
- **Do not bypass** — always use these components over native HTML elements
- `atoms/` → `molecules/` → `organisms/` hierarchy

**`customer-app/src/contexts/`:**
- Global state for the customer SPA using React Context API
- `BranchContext` — stores selected branch; required before menu loads
- `CartContext` — all cart operations and state
- `ToastContext` — notification system

## Key File Locations

**Entry Points:**
- `squidly-core.php` — PHP plugin bootstrap, autoloader, all initialization
- `admin-app/src/main.jsx` — Admin React app entry
- `customer-app/src/main.jsx` — Customer React app entry

**Configuration:**
- `admin-app/src/config/theme.js` — `DEFAULT_THEME` and `generateCSSVariables()`
- `customer-app/src/i18n/translations.js` — All UI strings in Hebrew/English/Arabic
- `phpunit.xml` — Test suite definitions (unit, integration, e2e, domains)
- `composer.json` — PHP dependencies and test commands

**Core Logic:**
- `includes/core/PostTypeRegistry.php` — Single place to register all 7 CPTs
- `includes/api/AdminApiBootstrap.php` — Wires all admin REST routes
- `includes/api/PublicApiBootstrap.php` — Wires all public REST routes
- `includes/domains/payments/bootstrap/PaymentBootstrap.php` — Payment system init

**API Clients:**
- `admin-app/src/services/api.js` — All authenticated REST calls from admin app
- `customer-app/src/services/publicApi.js` — All public REST calls from customer app

**Testing:**
- `tests/bootstrap.php` — Test environment setup
- `tests/traits/` — Shared test helper traits

## Naming Conventions

**PHP Files:**
- PascalCase matching class name: `OrderRepository.php`, `StoreBranchPostType.php`
- Public API controllers prefixed with `Public`: `PublicOrderRestController.php`
- Domain controllers named `{Entity}RestController.php`

**PHP Classes:**
- Non-namespaced (most domains): `OrderRepository`, `ProductRestController`
- Namespaced (payments domain only): `Squidly\Domains\Payments\Services\PaymentService`

**PHP Meta Keys:**
- Always prefixed with underscore: `_customer_id`, `_branch_id`, `_order_items`
- snake_case after prefix

**Post Type Constants:**
- Use `{Entity}PostType::POST_TYPE` constant — never hardcode the string (e.g., `'order'`)

**React Files:**
- Components: PascalCase, file name matches class: `BranchSelectionModal.jsx`
- Hooks: camelCase starting with `use`: `useOrderPolling.js`
- Services: camelCase: `publicApi.js`, `api.js`
- Contexts: PascalCase + `Context` suffix: `BranchContext.jsx`

**React Directories:**
- Feature groupings: lowercase: `branches/`, `checkout/`, `products/`
- Atomic design: `atoms/`, `molecules/`, `organisms/`

## Where to Add New Code

**New Domain Entity (e.g., `Promotion`):**
1. Model: `includes/domains/{domain}/models/Promotion.php`
2. Post Type: `includes/domains/{domain}/post-types/PromotionPostType.php`
3. Repository: `includes/domains/{domain}/repositories/PromotionRepository.php`
4. Admin REST Controller: `includes/domains/{domain}/rest/PromotionRestController.php`
5. Public REST Controller (if customer-facing): `includes/domains/{domain}/rest/PublicPromotionRestController.php`
6. Register CPT: Add `PromotionPostType::init()` to `includes/core/PostTypeRegistry.php`
7. Register routes: Add controller instantiation in `includes/api/AdminApiBootstrap.php` and/or `includes/api/PublicApiBootstrap.php`
8. Add to autoloader: Add path to the `spl_autoload_register` block in `squidly-core.php`

**New Admin REST Endpoint on Existing Resource:**
1. Add `register_rest_route()` call in the existing `{Entity}RestController::register_routes()`
2. Add handler method and permission callback to same controller
3. Add corresponding method to `admin-app/src/services/api.js`

**New Admin UI Page:**
- Page component: `admin-app/src/components/{FeatureName}.jsx`
- Register in router: `admin-app/src/router.jsx`
- Navigation entry: `admin-app/src/components/Sidebar.jsx`

**New Customer UI Component:**
- Feature component: `customer-app/src/components/{feature}/{ComponentName}.jsx`
- Use atoms from `customer-app/src/components/ui/`

**New Shared Admin UI Component:**
- Atom (simple): `admin-app/src/components/ui/atoms/{ComponentName}.jsx` + export from `atoms/index.js`
- Molecule (composite): `admin-app/src/components/ui/molecules/{ComponentName}.jsx` + export from `molecules/index.js`
- Organism (complex/feature): `admin-app/src/components/ui/organisms/{ComponentName}.jsx`

**PHP Tests:**
- Unit test: `tests/unit/{domain}/{ClassName}Test.php`
- Integration test: `tests/integration/{domain}/{ClassName}Test.php`

## Special Directories

**`vendor/`:**
- Purpose: Composer PHP dependencies
- Generated: Yes (via `composer install`)
- Committed: Yes (for deployment consistency)

**`admin-app/dist/` and `customer-app/dist/`:**
- Purpose: Vite production build output loaded by WordPress page handlers
- Generated: Yes (via `npm run build`)
- Committed: Yes (so WordPress can serve without build step in production)

**`admin-app/node_modules/` and `customer-app/node_modules/`:**
- Generated: Yes
- Committed: No

**`.planning/`:**
- Purpose: GSD planning documents (architecture analysis, feature plans, phases)
- Generated: No
- Committed: Yes

**`bin/`:**
- Purpose: Shell utility scripts for test environment setup
- Key file: `bin/install-wp-tests.sh` — installs WordPress test library

---

*Structure analysis: 2026-03-27*
