<?php
/**
 * Plugin Name:  Syndication
 * Plugin URI:   http://wordpress.org/extend/plugins/push-syndication/
 * Description:  Syndicate content to and from your sites
 * Version:      2.0
 * Author:       Automattic
 * Author URI:   http://automattic.com
 * License:      GPLv2 or later
 */

define( 'SYNDICATION_VERSION', 2.0 );

if ( ! defined( 'PUSH_SYNDICATE_KEY' ) )
	define( 'PUSH_SYNDICATE_KEY', 'PUSH_SYNDICATE_KEY' );

require_once ( dirname( __FILE__ ) . '/includes/class-wp-push-syndication-server.php' );
if( apply_filters( 'syn_use_async_jobs', false ) )
    require_once ( dirname( __FILE__ ) . '/includes/class-wpcom-push-syndication-server.php' );

if ( !defined( 'PUSH_SYNDICATION_ENVIRONMENT' ) )
    define( 'PUSH_SYNDICATION_ENVIRONMENT', 'WP' );

if ( defined( 'WP_CLI' ) && WP_CLI )
	require_once( dirname( __FILE__ ) . '/includes/class-wp-cli.php' );

$push_syndication_server_class = PUSH_SYNDICATION_ENVIRONMENT . '_Push_Syndication_Server';
$push_syndication_server = new $push_syndication_server_class();
