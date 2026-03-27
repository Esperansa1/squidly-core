# Testing Patterns

**Analysis Date:** 2026-03-27

## Test Framework

**Runner:**
- PHPUnit 9.6
- Config: `phpunit.xml`
- Bootstrap: `tests/bootstrap.php`

**Assertion Library:**
- PHPUnit assertions (`assertEquals`, `assertSame`, `assertNull`, `assertTrue`, `assertFalse`, `assertIsArray`, `assertIsInt`)
- Yoast PHPUnit Polyfills `^4.0` for WordPress test compatibility

**Mocking:**
- PHPUnit built-in `createMock()` — used for repository mocks in unit/REST controller tests
- Mockery `^1.6` — available as dev dependency, not heavily used in existing tests
- WordPress filters used as mock injection point for payment provider: `add_filter('squidly/payments/provider', fn() => $mockProvider)`

**Run Commands:**
```bash
composer test              # PHP lint + all suites
composer test:unit         # Unit suite only
composer test:int          # Integration suite only
composer test:coverage     # With coverage text report
vendor/bin/phpunit --testsuite e2e        # E2E suite
vendor/bin/phpunit --testsuite domains   # Domains suite (payments)
vendor/bin/phpunit <path/to/TestFile.php> # Single file
```

## Test File Organization

**Location:**
- Separate `tests/` directory from source — not co-located with source files

**Directory Structure:**
```
tests/
├── bootstrap.php                    # WP test env setup + class loading
├── E2ETest.php                      # (legacy root e2e test)
├── unit/
│   ├── customers/                   # CustomerTest.php, CustomerRepositoryTest.php
│   ├── orders/                      # OrderTest.php, OrderItemTest.php, OrderRepositoryTest.php
│   ├── products/                    # ProductTest.php, ProductRepositoryTest.php, etc.
│   ├── rest/                        # ProductRestControllerTest.php, OrderRestControllerTest.php, etc.
│   ├── services/                    # DeliveryFeeServiceTest.php
│   ├── shared/                      # AddressTest.php
│   └── stores/                      # StoreBranchTest.php, StoreBranchRepositoryTest.php
├── integration/
│   ├── admin/                       # PostTypeRegistryTest.php, AdminMenuManagerTest.php, etc.
│   ├── customers/                   # CustomerRepositoryIntegrationTest.php, etc.
│   ├── orders/                      # OrderRepositoryIntegrationTest.php, CartCheckoutIntegrationTest.php
│   ├── products/                    # ProductRepositoryIntegrationTest.php, etc.
│   └── rest/                        # *RestControllerIntegrationTest.php (one per controller)
├── e2e/
│   └── *RestControllerE2ETest.php   # One per REST controller (security/permissions)
├── domains/
│   └── payments/                    # PaymentServiceTest.php, WooProviderTest.php, etc.
├── api/
│   └── Order*Test.php               # OrderApiWorkflowTest.php, OrderApiLoadTest.php, etc.
├── performance/
│   └── *PerformanceTest.php         # Timing-based performance assertions
└── traits/
    └── AuthenticationTestTrait.php  # Shared auth helpers for E2E tests
```

**Naming:**
- Unit tests: `{ClassName}Test.php`
- Integration tests: `{ClassName}IntegrationTest.php`
- E2E tests: `{ClassName}E2ETest.php`
- Performance tests: `{ClassName}PerformanceTest.php`
- Test methods: `test_{description_snake_case}()` (unit/integration) or `test{PascalCase}()` (domain/e2e)

## Test Structure

**Unit Test Suite (`tests/unit/`):**
- Extends `PHPUnit\Framework\TestCase`
- Uses WordPress stub environment (no full WP bootstrap needed for model tests)
- Repository unit tests use stub `wp_insert_post()`, `get_post_meta()` etc. from WP test library
- Namespace: `SquidlyCore\Tests\Unit`

```php
declare(strict_types=1);
namespace SquidlyCore\Tests\Unit;

use Product;
use PHPUnit\Framework\TestCase;

/** @covers \Product */
class ProductTest extends TestCase
{
    public function test_constructor_and_toArray_full_payload(): void
    {
        $data = ['id' => 10, 'name' => 'Burger', 'price' => 25.9];
        $p = new Product($data);
        $this->assertSame($data['name'], $p->name);
    }
}
```

**Integration Test Suite (`tests/integration/`):**
- Extends `WP_UnitTestCase`
- Uses real WordPress database via WP test library
- Setup/teardown uses `set_up()` / `tear_down()` (WP naming, not setUp/tearDown)
- Tracks created IDs in `array $test_*_ids = []` for cleanup
- Namespace: `SquidlyCore\Tests\Integration`

```php
class ProductRepositoryIntegrationTest extends WP_UnitTestCase
{
    private ProductRepository $repo;

    public function set_up(): void
    {
        parent::set_up();
        register_taxonomy('product_cat', 'product'); // ensure taxonomies exist
        $this->repo = new ProductRepository();
    }

    public function test_full_create_get_update_delete_cycle(): void
    {
        $id = $this->repo->create(['name' => 'Hamburger', 'price' => 35.0]);
        $this->assertSame('product', get_post_type($id));
        // ...
    }
}
```

**E2E Test Suite (`tests/e2e/`):**
- Extends `WP_UnitTestCase`
- Dispatches requests through WordPress internal REST server (`$wp_rest_server->dispatch($request)`)
- Creates admin and subscriber users via `$this->factory->user->create()`
- Focuses on security boundaries (401/403/404 status codes)
- Large workflow tests covering full request lifecycle in a single test method
- Uses `AuthenticationTestTrait` for shared helpers

```php
class ProductRestControllerE2ETest extends WP_UnitTestCase
{
    private int $admin_user_id;
    private int $regular_user_id;
    private array $test_product_ids = [];

    public function setUp(): void
    {
        parent::setUp();
        // Register REST routes
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');
        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        $this->regular_user_id = $this->factory->user->create(['role' => 'subscriber']);
    }
}
```

**Domain Test Suite (`tests/domains/`):**
- Extends `PHPUnit\Framework\TestCase`
- Tests payment domain classes with Mockery/PHPUnit mocks
- Uses WordPress filter injection for mock provider:
  ```php
  add_filter('squidly/payments/provider', fn() => $this->mockProvider);
  ```
- Cleanup: `remove_all_filters('squidly/payments/provider')` in `tearDown()`

## Mocking

**PHPUnit createMock() — REST Controller Unit Tests:**
```php
$this->mockRepository = $this->createMock(ProductRepository::class);
// Inject via reflection (private property)
$reflection = new \ReflectionClass($this->controller);
$property = $reflection->getProperty('repository');
$property->setAccessible(true);
$property->setValue($this->controller, $this->mockRepository);
```

**Configuring expectations:**
```php
$this->mockRepository
    ->expects($this->once())
    ->method('get')
    ->with(123)
    ->willReturn($product);

$this->mockRepository
    ->expects($this->once())
    ->method('create')
    ->willThrowException(new InvalidArgumentException('Name is required'));
```

**What to Mock:**
- Repositories in REST controller unit tests (isolate controller logic)
- Payment provider interface in payment service tests (via WP filter)
- WordPress functions are stubbed by WP test library in unit/integration tests

**What NOT to Mock:**
- Real DB operations in integration tests — use actual WordPress DB
- Post type and taxonomy registrations — call `register_taxonomy()` explicitly in `set_up()`

## Fixtures and Factories

**WordPress Factory:**
- `$this->factory->user->create(['role' => 'administrator'])` — create test users
- Used in integration and E2E tests only

**Inline Test Data:**
- Data defined inline in each test method
- Helper methods in test class: `createMockProduct(int $id, string $name): Product`
- `AuthenticationTestTrait::getTestData(string $resource_type)` — centralized valid test data map
- `AuthenticationTestTrait::getInvalidTestData(string $resource_type)` — centralized invalid data map

**Cleanup Pattern:**
```php
private array $test_product_ids = [];

public function tearDown(): void
{
    foreach ($this->test_product_ids as $id) {
        $this->repository->delete($id, true);
    }
    parent::tearDown();
}
```
Track IDs as items are created: `$this->test_product_ids[] = $id;`

## Coverage

**Requirements:** No minimum coverage enforced in CI

**View Coverage:**
```bash
composer test:coverage     # Text output to terminal
composer ci                # XML report at build/coverage.xml (requires XDEBUG_MODE=coverage)
```

**Coverage annotation:**
- `/** @covers \Product */` on test classes
- `@covers ClassName` used in E2E and integration tests

## Test Types

**Unit Tests (`tests/unit/`):**
- Scope: single class in isolation
- Model DTOs: test constructor mapping, `toArray()`, default values for optional fields
- Repositories: test CRUD with WP function stubs, validate exception throwing for bad data
- REST controllers: mock repository, verify correct HTTP status codes returned

**Integration Tests (`tests/integration/`):**
- Scope: class + real WordPress database
- Repositories: full CRUD cycle, dependency enforcement, query accuracy
- REST controllers: full request dispatch through WP REST server with real DB

**E2E Tests (`tests/e2e/`):**
- Scope: full request lifecycle through WP REST server
- Focus: security and permission enforcement
- Patterns: unauthenticated → 401, subscriber → 403, admin → 200
- One large workflow method per scenario: `testSecurityAndPermissionsWorkflow()`, `testProductDataIntegrityAndValidation()`

**Domain Tests (`tests/domains/`):**
- Scope: payment domain subsystem with mocked provider
- Tests service delegation to provider interface
- Tests bootstrap, activation, and admin action classes

**Performance Tests (`tests/performance/`):**
- Scope: timing assertions under load scenarios
- Uses `WP_UnitTestCase` with real DB
- Tests bulk operations and multi-entity queries

**API Tests (`tests/api/`):**
- Scope: order API edge cases, validation, permissions, workflow scenarios
- Organized by concern: `OrderApiEdgeCaseTest`, `OrderApiLoadTest`, `OrderApiPermissionTest`, `OrderApiValidationTest`, `OrderApiWorkflowTest`

## Common Patterns

**Data Provider (PHP):**
```php
/** @dataProvider provideBadCreateData */
public function test_create_invalid_payload_throws(array $payload): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->repo->create($payload);
}

public function provideBadCreateData(): array
{
    return [
        'missing name'   => [['price' => 5]],
        'negative price' => [['name' => 'X', 'price' => -1]],
    ];
}
```

**Exception Testing:**
```php
$this->expectException(ResourceInUseException::class);
$this->repo->delete($productId, true);
```

**REST Request in Tests:**
```php
$request = new WP_REST_Request('POST', '/squidly/v1/products');
$request->set_param('name', 'Test Product');
$request->set_url_params(['id' => '123']);
$response = $this->controller->create_item($request);
$this->assertEquals(200, $response->get_status());
```

**User Context Switching (E2E):**
```php
wp_set_current_user(0);                     // Unauthenticated
wp_set_current_user($this->regular_user_id); // Subscriber
wp_set_current_user($this->admin_user_id);   // Admin
```

**REST Server Dispatch (E2E/Integration):**
```php
global $wp_rest_server;
$wp_rest_server = new \WP_REST_Server();
do_action('rest_api_init');
$response = $wp_rest_server->dispatch($request);
$status = $response->get_status();
$data = $response->get_data();
```

## Shared Test Infrastructure

**`tests/traits/AuthenticationTestTrait.php`:**
- `setupAuthenticationTesting()` — creates admin + subscriber users
- `makeRestRequest(method, namespace, route, data, auto_authenticate)` — dispatch via WP REST server
- `getResponseStatus($response)` — extract HTTP status code
- `getResponseData($response)` — extract response body as array
- `assertSecurityResponse($response, $expected_status, $message)` — assert status with message
- `runAuthenticationWorkflow(namespace, route, data, methods)` — standard 401/403/200 test sequence
- `runNonExistentResourceSecurity(namespace, base_route, fake_id)` — test 401/403/404 for missing resource
- `assertNoSensitiveData(array $data, array $sensitive_fields)` — verify no secrets in response
- `getTestData(resource_type)` — valid test payloads for each resource type
- `getInvalidTestData(resource_type)` — invalid test payloads for validation testing

**Bootstrap (`tests/bootstrap.php`):**
- Loads WordPress test library from `C:\Users\oresp\AppData\Local\Temp\wordpress-tests-lib`
- Registers plugin via `tests_add_filter('muplugins_loaded', '_manually_load_plugin')`
- `_load_payment_classes()` — manual requires for payment domain (not autoloaded)
- `_load_core_classes()` — iterates known class names searching domain paths, registers post types

---

*Testing analysis: 2026-03-27*
