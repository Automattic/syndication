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

define( 'SYNDICATION_VERSION', '3.0.0' );

// Load and register the autoloader.
require __DIR__ . '/includes-new/class-autoloader.php';
Autoloader::register_namespace( 'Automattic\Syndication', __DIR__ . '/includes-new' );

// Initialize the bootstrapper.
new Bootstrap();
