<?php
/**
 * Plugin Name:  Syndication
 * Plugin URI:   http://wordpress.org/extend/plugins/push-syndication/
 * Description:  Syndicate content to and from your sites.
 * Version:      3.0
 * Author:       Automattic
 * Author URI:   http://automattic.com/
 * License:      GPLv2 or later
 */

namespace Automattic\Syndication;

/**
 * Don't load on autosave requests.
 */
if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	return;
}

define( 'SYNDICATION_VERSION', '3.0.0' );
define( 'SYNDICATION_URL', plugin_dir_url( __FILE__ ) );
define( 'SYNDICATION_PATH', dirname( __FILE__ ) . '/' );

if ( ! defined( 'PUSH_SYNDICATE_KEY' ) ) {
	define( 'PUSH_SYNDICATE_KEY', 'PUSH_SYNDICATE_KEY' );
}
// Load and register the autoloader.
require __DIR__ . '/includes/class-autoloader.php';
Autoloader::register_namespace( 'Automattic\Syndication', __DIR__ . '/includes' );

// Initialize the bootstrapper.
new Bootstrap();
