<?php
/*

**************************************************************************

Plugin Name:  Push Syndication XMLRPC Client
Plugin URI:
Description:  XMLRPC client for push syndication server
Version:      1.0
Author:       Automattic
Author URI:   http://automattic.com/wordpress-plugins/
License:      GPLv2 or later

**************************************************************************/

class Push_Syndication_XMLRPC_Client {

	function __construct() {
		add_filter( 'xmlrpc_methods' , array( &$this, 'push_syndicate_methods' ) );
	}

	public function push_syndicate_methods() {

	}

}

$Push_Syndication_XMLRPC_Client = new Push_Syndication_XMLRPC_Client();