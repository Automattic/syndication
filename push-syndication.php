<?php
/**
 * Syndication
 *
 * @package Syndication
 *
 * Plugin Name:  Syndication
 * Plugin URI:   http://wordpress.org/extend/plugins/push-syndication/
 * Description:  Syndicate content to and from your sites
 * Version:      2.2.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:       Automattic
 * Author URI:   http://automattic.com
 * License:      GPLv2 or later
 * Text Domain:  push-syndication
 */

define( 'SYNDICATION_VERSION', '2.2.0' );

if ( ! defined( 'PUSH_SYNDICATE_KEY' ) ) {
	define( 'PUSH_SYNDICATE_KEY', 'PUSH_SYNDICATE_KEY' );
}

/**
 * Load syndication logger
 */
require_once __DIR__ . '/includes/class-syndication-logger.php';
Syndication_Logger::init();

require_once __DIR__ . '/includes/class-wp-push-syndication-server.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-wp-cli.php';
}

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

// Load encryption classes.
require_once __DIR__ . '/includes/class-syndication-encryption.php';
require_once __DIR__ . '/includes/interface-syndication-encryptor.php';
require_once __DIR__ . '/includes/class-syndication-encryptor-mcrypt.php';
require_once __DIR__ . '/includes/class-syndication-encryptor-openssl.php';

// On PHP 7.1 mcrypt is available, but will throw a deprecated error if its used. Therefore, checking for the
// PHP version, instead of checking for mcrypt is a better approach.
if ( ! defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 70100 ) {
	$syndication_encryption = new Syndication_Encryption( new Syndication_Encryptor_MCrypt() );
} else {
	$syndication_encryption = new Syndication_Encryption( new Syndication_Encryptor_OpenSSL() );
}

// @TODO: instead of saving this as a global, have it as an attribute of WP_Push_Syndication_Server.
$GLOBALS['push_syndication_encryption'] = $syndication_encryption;
