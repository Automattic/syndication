<?php
/**
 * PHPUnit bootstrap file for Syndication plugin tests.
 *
 * @package Automattic\Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration;

require_once dirname( __DIR__ ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

// Check for a `--testsuite` arg when calling phpunit.
$argv_local     = $GLOBALS['argv'] ?? [];
$key            = (int) array_search( '--testsuite', $argv_local, true );
$testsuite      = '';

// Check for --testsuite <name> (two separate args).
if ( $key && isset( $argv_local[ $key + 1 ] ) ) {
	$testsuite = $argv_local[ $key + 1 ];
}

// Check for --testsuite=<name> (single arg with equals).
foreach ( $argv_local as $arg ) {
	if ( 0 === strpos( $arg, '--testsuite=' ) ) {
		$testsuite = substr( $arg, strlen( '--testsuite=' ) );
		break;
	}
}

$is_unit        = 'Unit' === $testsuite;
$is_integration = 'integration' === $testsuite;

// Unit tests - load Brain Monkey and classes without WordPress.
if ( $is_unit ) {
	// Load WordPress stubs first (before anything else).
	require_once __DIR__ . '/Unit/Stubs/WP_Error.php';

	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

	// Load PSR-4 autoloader for namespaced classes.
	require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
	\Syndication_Autoloader::register( dirname( __DIR__ ) );

	// Define WordPress time constants used in unit tests.
	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}

	// Load classes needed for unit tests (those without WordPress dependencies).
	require_once dirname( __DIR__ ) . '/includes/class-syndication-event-counter.php';
	require_once __DIR__ . '/Unit/TestCase.php';

	return;
}

if ( $is_integration ) {
	$_tests_dir = WPIntegration\get_path_to_wp_test_dir();

	// Give access to tests_add_filter() function.
	require_once $_tests_dir . '/includes/functions.php';

	// Manually load the plugin being tested.
	\tests_add_filter(
		'muplugins_loaded',
		function (): void {
			require dirname( __DIR__ ) . '/push-syndication.php';
		}
	);

	/*
	 * Bootstrap WordPress. This will also load the Composer autoload file, the PHPUnit Polyfills
	 * and the custom autoloader for the TestCase and the mock object classes.
	 */
	WPIntegration\bootstrap_it();

	/*
	 * Load test dependencies.
	 */
	require_once __DIR__ . '/Integration/EncryptorTestCase.php';
	require_once __DIR__ . '/Integration/Syndication_Mock_Client.php';
}
