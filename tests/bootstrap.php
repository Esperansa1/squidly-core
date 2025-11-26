<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Squidly_Core
 */

define( 'EMPTY_TRASH_DAYS', 1 );

$_tests_dir = 'C:\Users\oresp\AppData\Local\Temp/wordpress-tests-lib';

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/squidly-core.php';
}

/**
 * Load payment domain classes for testing.
 */
function _load_payment_classes() {
	$plugin_dir = dirname( dirname( __FILE__ ) );

	require_once $plugin_dir . '/includes/domains/payments/interfaces/PaymentProvider.php';
	require_once $plugin_dir . '/includes/domains/payments/services/PaymentService.php';
	require_once $plugin_dir . '/includes/domains/payments/gateways/WooProvider.php';
	require_once $plugin_dir . '/includes/domains/payments/hooks/PaymentStatusSync.php';
	require_once $plugin_dir . '/includes/domains/payments/rest/PaymentRestController.php';
	require_once $plugin_dir . '/includes/domains/payments/admin/PaymentAdminActions.php';
	require_once $plugin_dir . '/includes/domains/payments/activation/PaymentProductActivation.php';
	require_once $plugin_dir . '/includes/domains/payments/bootstrap/PaymentBootstrap.php';
}

/**
 * Load core shared classes and exceptions for testing.
 */
function _load_core_classes() {
	$plugin_dir = dirname( dirname( __FILE__ ) );

	// Load shared exceptions
	require_once $plugin_dir . '/includes/shared/exceptions/ResourceInUseException.php';

	// Load shared models and enums
	require_once $plugin_dir . '/includes/shared/models/enums/ItemType.php';

	// Load domain models (commonly used in tests)
	$models = [
		'Product', 'Ingredient', 'ProductGroup', 'GroupItem', 'StoreBranch', 'Customer', 'Order',
		'OrderItem', 'Cart', 'CartItem', 'Address'
	];
	foreach ($models as $model) {
		$paths = [
			"/includes/domains/products/models/{$model}.php",
			"/includes/domains/stores/models/{$model}.php",
			"/includes/domains/customers/models/{$model}.php",
			"/includes/domains/orders/models/{$model}.php",
			"/includes/shared/models/{$model}.php"
		];
		foreach ($paths as $path) {
			$file = $plugin_dir . $path;
			if (file_exists($file)) {
				require_once $file;
				break;
			}
		}
	}

	// Load domain repositories (commonly used in tests)
	$repositories = [
		'ProductRepository', 'IngredientRepository', 'ProductGroupRepository',
		'GroupItemRepository', 'StoreBranchRepository', 'CustomerRepository', 'OrderRepository'
	];
	foreach ($repositories as $repo) {
		$paths = [
			"/includes/domains/products/repositories/{$repo}.php",
			"/includes/domains/stores/repositories/{$repo}.php",
			"/includes/domains/customers/repositories/{$repo}.php",
			"/includes/domains/orders/repositories/{$repo}.php"
		];
		foreach ($paths as $path) {
			$file = $plugin_dir . $path;
			if (file_exists($file)) {
				require_once $file;
				break;
			}
		}
	}

	// Load domain services (commonly used in tests)
	$services = [
		'DeliveryFeeService', 'CartService', 'ProductCustomizationValidator'
	];
	foreach ($services as $service) {
		$paths = [
			"/includes/domains/orders/services/{$service}.php",
			"/includes/domains/products/services/{$service}.php",
			"/includes/domains/customers/services/{$service}.php"
		];
		foreach ($paths as $path) {
			$file = $plugin_dir . $path;
			if (file_exists($file)) {
				require_once $file;
				break;
			}
		}
	}

	// Load post type classes (commonly used in tests)
	$postTypes = [
		'ProductPostType', 'IngredientPostType', 'ProductGroupPostType',
		'GroupItemPostType', 'StoreBranchPostType', 'CustomerPostType', 'OrderPostType'
	];
	foreach ($postTypes as $postType) {
		$paths = [
			"/includes/domains/products/post-types/{$postType}.php",
			"/includes/domains/stores/post-types/{$postType}.php",
			"/includes/domains/customers/post-types/{$postType}.php",
			"/includes/domains/orders/post-types/{$postType}.php"
		];
		foreach ($paths as $path) {
			$file = $plugin_dir . $path;
			if (file_exists($file)) {
				require_once $file;
				// Register the post type for tests
				if (class_exists($postType) && method_exists($postType, 'register')) {
					call_user_func([$postType, 'register']);
				}
				break;
			}
		}
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Load payment classes after WordPress is loaded
_load_payment_classes();

// Load core shared classes and repositories for tests
_load_core_classes();

