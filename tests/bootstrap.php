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
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Load payment classes before tests run
_load_payment_classes();

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

