# Squidly Core

Restaurant management WordPress plugin with decoupled React admin and customer interfaces, WooCommerce payment integration, and domain-driven architecture.

## Requirements

- WordPress 5.0+
- PHP 8.0+
- WooCommerce (for payment processing)
- Node.js 16+ (for building frontend apps)

## Setup

1. Install and activate the plugin
2. Install and activate WooCommerce
3. Install PHP dependencies: `composer install`
4. Build frontend apps:
   ```bash
   cd admin-app && npm install && npm run build
   cd customer-app && npm install && npm run build
   ```
5. The admin interface is available at `/restaurant-admin`
6. The customer ordering app is available at `/orders`

## Project Structure

```
squidly-core/
├── squidly-core.php          # Plugin entry point
├── includes/
│   ├── admin/                # Page handlers (AdminPageHandler, CustomerPageHandler, RoleManager)
│   ├── api/                  # REST API bootstrapping
│   ├── core/                 # PostTypeRegistry
│   ├── domains/
│   │   ├── customers/        # Customer management (regular + guest)
│   │   ├── orders/           # Orders, cart, delivery
│   │   ├── payments/         # WooCommerce payment integration
│   │   ├── products/         # Products, ingredients, groups, customization
│   │   └── stores/           # Store branches
│   └── shared/               # Interfaces, abstracts, exceptions, models
├── admin-app/                # React admin SPA (Vite + TailwindCSS)
├── customer-app/             # React customer ordering SPA
└── tests/                    # PHPUnit test suite (unit, integration, e2e)
```

## Architecture

### Domain-Driven Design

Each domain follows the same structure:
- `models/` -- DTOs with constructor and `toArray()`
- `repositories/` -- Data access implementing `RepositoryInterface`
- `post-types/` -- WordPress custom post types extending `BasePostType`
- `rest/` -- REST controllers extending `WP_REST_Controller`
- `services/` -- Business logic

### REST API

- **Namespace:** `squidly/v1`
- **Admin endpoints:** Authenticated via WordPress nonce + session cookies
- **Public endpoints:** `/squidly/v1/public/*` -- No authentication required (for customer app)

### Frontend Apps

Both apps are fully decoupled React SPAs communicating exclusively via REST API.

- **Admin App** (`admin-app/`): React 18 + Vite + TailwindCSS, RTL support, port 3000
- **Customer App** (`customer-app/`): React 18 + Vite + TailwindCSS, RTL support, port 3001

### Payment Integration

WooCommerce handles payment processing. Squidly creates WooCommerce orders with fee-based line items (no WC products needed) and redirects customers to the WooCommerce checkout page. Any WooCommerce payment gateway works out of the box.

## Development

```bash
# PHP tests
composer test              # All tests + linting
composer test:unit         # Unit tests only
composer test:int          # Integration tests only

# Frontend dev servers
cd admin-app && npm run dev      # http://localhost:3000
cd customer-app && npm run dev   # http://localhost:3001

# Production builds
cd admin-app && npm run build
cd customer-app && npm run build
```

## License

MIT
