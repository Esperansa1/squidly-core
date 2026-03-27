# Technology Stack

**Analysis Date:** 2026-03-27

## Languages

**Primary:**
- PHP 8.5 - WordPress plugin backend, REST API controllers, domain logic
- JavaScript (ES2020+) - React frontend applications (no TypeScript)

**Secondary:**
- CSS - TailwindCSS utility classes, custom font declarations

## Runtime

**Environment:**
- PHP 8.5 (CLI: Visual C++ 2022 x64 NTS build)
- Node.js v22.18.0

**Package Manager:**
- Composer (PHP) — lockfile present at `composer.lock`
- npm 11.5.2 (JS) — lockfiles present at `admin-app/package-lock.json` and `customer-app/package-lock.json`

## Frameworks

**Core:**
- WordPress (host platform) — plugin hooks, CPTs, WP_REST_Controller, options API, transients
- WooCommerce (required dependency) — payment product, order creation, checkout URL, refunds
- React 18.2.0 — both admin and customer frontend apps (`admin-app/`, `customer-app/`)

**CSS:**
- TailwindCSS 3.3.3 — utility-first styling with custom brand color palette
- `@tailwindcss/forms` 0.5.4 — form element normalization
- PostCSS 8.x + Autoprefixer — CSS build pipeline

**Build/Dev:**
- Vite 4.4.5 — bundler for both frontend apps
  - Admin app: dev port 3000, output `admin-app/dist/`
  - Customer app: dev port 3001, output `customer-app/dist/`
  - Build target: `es2015`, minifier: `esbuild`, sourcemaps enabled
  - Shared UI alias: `@shared-ui` → `../shared-ui`

**Testing (PHP):**
- PHPUnit 9.6 — test runner, config at `phpunit.xml`
- Mockery 1.6.12 — mock objects for unit tests
- Yoast PHPUnit Polyfills 4.0.0 — PHPUnit compatibility shims
- WordPress Test Library — integration/e2e test environment (external, installed via `bin/install-wp-tests.sh`)

**Linting:**
- ESLint 8.45.0 — JS/JSX linting
  - `eslint-plugin-react` 7.32.2
  - `eslint-plugin-react-hooks` 4.6.0
  - `eslint-plugin-react-refresh` 0.4.3
- PHP syntax check via `php -l` (no phpcs/phpstan)

## Key Dependencies

**Critical:**
- `react` 18.2.0 + `react-dom` 18.2.0 — UI rendering (both apps)
- `@heroicons/react` 2.0.18 — icon library (both apps)
- `recharts` 3.7.0 — charts/analytics (admin app only, `admin-app/`)
- WooCommerce (WordPress plugin, not in composer) — payment processing; plugin activation checks `class_exists('WooCommerce')`

**Infrastructure:**
- `@vitejs/plugin-react` 4.0.3 — Vite React JSX transform
- WordPress Transients API — cart session storage (2-hour TTL, prefix `squidly_cart_`)
- WordPress Options API — plugin configuration (`squidly_currency`, `squidly_wc_payment_product_id`, etc.)
- WordPress Custom Post Types — all domain entities stored as CPTs

## Configuration

**Environment:**
- No `.env` files detected
- WordPress configuration via `wp-config.php` (outside plugin scope)
- Plugin constants defined in `squidly-core.php`:
  - `SQUIDLY_CORE_VERSION` = `1.0.0`
  - `SQUIDLY_CORE_PATH` — absolute filesystem path
  - `SQUIDLY_CORE_URL` — plugin URL
- Frontend config injected by WordPress template into `window.wpConfig` or `window.SQUIDLY_CONFIG`:
  - `apiUrl` — admin REST base URL
  - `publicApiUrl` — public REST base URL
  - `nonce` — WP REST nonce
  - `assetsUrl` / `pluginUrl`

**Build:**
- `admin-app/vite.config.js` — admin app Vite config
- `customer-app/vite.config.js` — customer app Vite config
- `admin-app/tailwind.config.js` — Tailwind theme (LiaDiplomat font, brand colors, RTL utilities)
- `customer-app/tailwind.config.js` — same structure
- `admin-app/postcss.config.js` — PostCSS config
- `phpunit.xml` — PHPUnit test suite config, WP_TESTS_DIR env var

## Platform Requirements

**Development:**
- PHP 8.5+
- Node.js 22+, npm 11+
- WordPress with WooCommerce active
- WordPress Test Library installed at `C:/Users/oresp/AppData/Local/Temp/wordpress-tests-lib` (Windows-specific path in `phpunit.xml`)

**Production:**
- WordPress hosting with PHP 8.x
- WooCommerce plugin installed and activated
- React apps pre-built to `admin-app/dist/` and `customer-app/dist/` — WordPress PHP templates enqueue hashed assets

---

*Stack analysis: 2026-03-27*
