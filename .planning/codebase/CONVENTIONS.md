# Coding Conventions

**Analysis Date:** 2026-03-27

## Naming Patterns

**PHP Files:**
- Classes: PascalCase matching file name — `ProductRepository.php`, `OrderRestController.php`
- Interfaces: PascalCase with no suffix — `RepositoryInterface.php`, `PaymentProvider.php`
- Abstracts: PascalCase with `Base` prefix — `BasePostType.php`
- Enums: PascalCase — `ItemType.php`

**PHP Classes and Methods:**
- Classes: PascalCase — `ProductRepository`, `StoreBranchRestController`
- Public methods: snake_case (WordPress convention) — `get_items()`, `create_item()`, `register_routes()`
- Private methods: snake_case — `validateDiscountedPrice()`, `setPricesMeta()`, `findProductDependants()`
- Properties: snake_case — `$repository`, `$rest_base`, `$namespace`

**PHP Variables:**
- Local variables: snake_case — `$post_id`, `$regular_price`, `$query_args`
- Boolean flags: descriptive — `$force`, `$is_available`, `$is_guest`

**PHP Constants:**
- UPPER_SNAKE_CASE — `SQUIDLY_CORE_VERSION`, `SQUIDLY_CORE_PATH`
- Post type constants on class — `ProductPostType::POST_TYPE` (never hardcoded string `'product'`)

**JavaScript/React Files:**
- Components: PascalCase matching file name — `BranchManagement.jsx`, `Button.jsx`
- Hooks: camelCase with `use` prefix — `useTheme.js`, `useSorting.js`, `usePagination.js`
- Services: camelCase — `api.js`, `publicApi.js`
- Config: camelCase — `theme.js`

**JavaScript Functions and Variables:**
- Components: PascalCase arrow functions — `const BranchManagement = () => {}`
- Event handlers: camelCase with `handle` prefix — `handleClick`, `handleSubmit`, `handleChange`
- State: camelCase — `branches`, `selectedBranchId`, `searchTerm`, `isSaving`
- Constants: UPPER_SNAKE_CASE — `DEFAULT_THEME`, `API_BASE_URL`
- Hooks: camelCase with `use` prefix — `useTheme`, `useSorting`

**Meta Field Names:**
- Always `_` prefix — `_regular_price`, `_branch_id`, `_is_guest`, `_product_group_ids`
- snake_case after prefix — `_branch_availability_{id}` for dynamic keys

## PHP Code Style

**Strict Types:**
- All PHP files declare `declare(strict_types=1);` at top
- Example: `includes/domains/products/models/Product.php`, `includes/shared/interfaces/RepositoryInterface.php`

**Direct Access Prevention:**
- All PHP files include: `if (!defined('ABSPATH')) exit;` or rely on plugin bootstrap
- Main plugin file: `squidly-core.php` uses this guard

**Namespacing:**
- Payment domain uses PSR-4 namespaces: `Squidly\Domains\Payments\Services\PaymentService`
- All other domains are class-per-file without namespaces (autoloaded by classmap)
- Tests use namespaces: `SquidlyCore\Tests\Unit`, `SquidlyCore\Tests\Integration`, `SquidlyCore\Tests\E2E`

**Formatting:**
- No dedicated formatter config detected (no `.php-cs-fixer.php`, no `phpcs.xml`)
- Linting: `composer lint` runs `php -l` syntax check only
- WordPress PHPCS standards referenced in inline comments (`// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`)

## JavaScript Code Style

**Formatting:**
- No `.prettierrc` or `.eslintrc` at root; ESLint config embedded in `admin-app/package.json` scripts
- ESLint: `eslint . --ext js,jsx --report-unused-disable-directives --max-warnings 0`
- Plugins: `eslint-plugin-react`, `eslint-plugin-react-hooks`, `eslint-plugin-react-refresh`

**Component Structure:**
- Functional components only, no class components
- `const` arrow functions: `const Button = React.forwardRef((...) => { ... })`
- Props destructured in function signature with defaults
- `displayName` set on forwarded ref components: `Button.displayName = 'Button'`

**Styling:**
- TailwindCSS utility classes always preferred over inline styles
- Exception: theme colors use inline `style` objects because they are runtime values from `DEFAULT_THEME`
- Pattern: `const theme = DEFAULT_THEME;` at top of component, then `style={{ backgroundColor: theme.primary_color }}`
- RTL: use logical properties `ms-*` over `ml-*`, `me-*` over `mr-*`
- `className` with template literals for conditional classes: `` className={`btn ${isActive ? 'active' : ''}`} ``

## Import Organization

**PHP:**
- No import statements (autoloader handles class loading)
- Manual `require_once` only for payment domain and bootstrap files

**JavaScript:**
- Order: React core → external libraries → internal services → components → config
- Path aliases: `../../shared-ui/atoms` for shared UI library
- Barrel imports from UI library: `import { Card, Button, FormField } from './ui'`
- API singleton: `import api from '../services/api.js'`
- Theme: `import { DEFAULT_THEME } from '../config/theme.js'`

## Error Handling

**PHP — Repository Layer:**
- `InvalidArgumentException` for validation failures (missing required fields, invalid values)
- `RuntimeException` for operation failures (wp_insert_post fails, meta save fails)
- `ResourceInUseException` for dependency violations (delete blocked by references)
- Rollback on partial failure: delete created post if metadata save fails
- `error_log()` for non-fatal errors (taxonomy missing, meta read failure), return null/false/[]

**PHP — REST Controller Layer:**
- Catch `InvalidArgumentException` → return `WP_REST_Response` with status 400
- Catch `ResourceInUseException` → return `WP_REST_Response` with status 409
- Not found → return `WP_REST_Response` with status 404
- Auth failure → return `WP_REST_Response` with status 401 or 403
- Pattern:
  ```php
  try {
      $this->repository->delete($id);
  } catch (ResourceInUseException $e) {
      return new WP_REST_Response(['error' => $e->getMessage()], 409);
  }
  ```

**PHP — Guard Pattern:**
- Validate IDs: `if ($id <= 0) { return null; }` before any DB operation
- Check post type: `if (!$post || $post->post_type !== ProductPostType::POST_TYPE) { return null; }`
- Check `is_wp_error()` after every WordPress operation

**JavaScript:**
- `try/catch` blocks in async functions
- `console.error()` for failures: `console.error('API initialization failed:', error);`
- Re-throw errors from `init()` so callers can handle auth failures
- State-based error display: `setError(null)` before fetch, `setError(message)` on failure

## Logging

**PHP:**
- `error_log()` for non-fatal recoverable errors in repository methods
- Format: `error_log("Failed to create Product object for ID {$id}: " . $e->getMessage());`
- No structured logging library used

**JavaScript:**
- `console.error()` for errors in services: `console.error('API initialization failed:', error);`
- No logging library; `console.log` is not used in production code paths

## Comments

**PHP DocBlocks:**
- All public interface methods have `@param`, `@return`, `@throws` annotations
- Example from `RepositoryInterface`: `@throws InvalidArgumentException When required data is missing or invalid`
- Private helper methods often have single-line descriptions
- Section dividers used in large files:
  ```php
  /* ======================================================================
   *  CREATE
   * ====================================================================*/
  ```

**JavaScript JSDoc:**
- Service methods and hooks have JSDoc blocks: `@param`, `@returns`
- Component-level description comments at top of file
- Inline comments for non-obvious logic

## Function Design

**PHP:**
- Public methods focus on one operation (create, get, update, delete)
- Private helpers extract complex logic: `validateDiscountedPrice()`, `setPricesMeta()`, `setCategorySafely()`
- Null-safe pattern: `??=` operator for optional repository injection — `$pgRepo ??= new ProductGroupRepository();`
- Early return for invalid input: `if ($id <= 0) { return false; }`

**JavaScript:**
- Hooks return objects with state and handlers
- Components use `useMemo` for expensive computations
- `useEffect` dependencies explicitly listed
- Loading states tracked separately (`loading`, `tableLoading`) for granular UX

## Module Design

**PHP:**
- No barrel exports; autoloader resolves by class name
- Payment domain uniquely uses PSR-4 namespace with manual requires in bootstrap

**JavaScript:**
- Barrel exports from `admin-app/src/components/ui/index.js` for all UI components
- Sub-barrel exports: `atoms/index.js`, `molecules/index.js`, `organisms/index.js`
- Import from barrel: `import { Button, FormField, Modal } from './ui'`
- Avoid importing from deep paths when barrel exists

## Post Type Usage

- Always use class constants for post type strings:
  - `ProductPostType::POST_TYPE` not `'product'`
  - `GroupItemPostType::POST_TYPE` not `'group_item'`
- Post types registered via `PostTypeRegistry::register_all()` in `includes/core/PostTypeRegistry.php`

---

*Convention analysis: 2026-03-27*
