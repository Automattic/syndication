<?php
/**
 * Syndication
 *
 * @package Syndication
 *
 * Plugin Name:  Syndication
 * Plugin URI:   http://wordpress.org/extend/plugins/push-syndication/
 * Description:  Syndicate content to and from your sites
 * Version:      3.0.0-alpha
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Author:       Automattic
 * Author URI:   http://automattic.com
 * License:      GPLv2 or later
 * Text Domain:  push-syndication
 */

declare( strict_types=1 );

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'SYNDICATION_FILE', __FILE__ );
define( 'SYNDICATION_VERSION', '3.0.0-alpha' );

// Encryption key constant (define in wp-config.php for production).
if ( ! defined( 'PUSH_SYNDICATE_KEY' ) ) {
	define( 'PUSH_SYNDICATE_KEY', 'PUSH_SYNDICATE_KEY' );
}

// Load PSR-4 autoloader for namespaced classes.
require_once __DIR__ . '/includes/Autoloader.php';
Syndication_Autoloader::register( __DIR__ );

// Initialise plugin via bootstrapper.
add_action(
	'plugins_loaded',
	static function (): void {
		$container    = \Automattic\Syndication\Infrastructure\DI\Container::instance();
		$bootstrapper = new \Automattic\Syndication\Infrastructure\WordPress\PluginBootstrapper( $container );
		$bootstrapper->init();
	},
	10
);

/**
 * Get the Syndication DI container instance.
 *
 * This function is provided for third-party developers who need to access
 * plugin services. Internal plugin code should use Container::instance() directly.
 *
 * @return \Automattic\Syndication\Infrastructure\DI\Container The container.
 */
function syndication_container(): \Automattic\Syndication\Infrastructure\DI\Container {
	return \Automattic\Syndication\Infrastructure\DI\Container::instance();
}

/**
 * Load syndication logger
 */
require_once __DIR__ . '/includes/class-syndication-logger.php';
Syndication_Logger::init();

require_once __DIR__ . '/includes/class-wp-push-syndication-server.php';

$GLOBALS['push_syndication_server'] = new WP_Push_Syndication_Server();

// Create the event counter.
require __DIR__ . '/includes/class-syndication-event-counter.php';
new Syndication_Event_Counter();

// Create the site failure monitor.
require __DIR__ . '/includes/class-syndication-site-failure-monitor.php';
new Syndication_Site_Failure_Monitor();

// Create the site auto retry functionality.
require __DIR__ . '/includes/class-syndication-site-auto-retry.php';
new Failed_Syndication_Auto_Retry();

// Initialize syndication notifications.
( new \Automattic\Syndication\Infrastructure\Notification\SyndicationNotifier() )->register_hooks();

// Initialize log viewers (admin only).
if ( is_admin() ) {
	( new \Automattic\Syndication\Infrastructure\Logging\PullLogViewer() )->register();
	( new \Automattic\Syndication\Infrastructure\Logging\PushLogViewer() )->register();
}

// Load encryption helper functions (uses Container internally).
require_once __DIR__ . '/includes/push-syndicate-encryption.php';
