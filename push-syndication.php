<?php /*

**************************************************************************

Plugin Name:  Push Syndication Server
Plugin URI:   
Description:  Push content to multiple sites
Version:      1.0
Author:       Automattic
Author URI:   http://automattic.com/wordpress-plugins/
License:      GPLv2 or later

**************************************************************************/

require_once ( dirname( __FILE__ ) . '/includes/class-wpcom-push-syndication-server.php' );
require_once ( dirname( __FILE__ ) . '/includes/class-wp-push-syndication-server.php' );


if ( !defined( 'PUSH_SYNDICATION_ENVIRONMENT' ) )
    define( 'PUSH_SYNDICATION_ENVIRONMENT', 'WP' );

$push_syndication_server_class = PUSH_SYNDICATION_ENVIRONMENT . 'Push_Syndication_Server';
$push_syndication_server = new $push_syndication_server_class();

?>
