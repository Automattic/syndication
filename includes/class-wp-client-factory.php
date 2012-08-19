<?php

// @TODO load all the classes dynamically
include_once( dirname( __FILE__ ) . '/class-wp-xmlrpc-client.php' );
include_once( dirname( __FILE__ ) . '/class-wp-rest-client.php' );

class wp_client_factory {

	public static function get_client( $transport_type, $site_ID ) {

		$class = $transport_type . '_client';
		if( class_exists($class) ) {
			return new $class( $site_ID );
		}

		throw new Exception('transport class not found');

	}

	public static function display_client_settings( $site, $class ) {

		if( class_exists($class) ) {
			return call_user_func( array( $class, 'display_settings' ), $site );
		}

		throw new Exception('transport class not found');

	}

	public static function save_client_settings( $site_ID, $class ) {

		$class = $_POST['transport_type'] . '_client';
		if( class_exists($class) ) {
			return call_user_func( array( $class, 'save_settings' ), $site_ID );
		}

		throw new Exception('transport class not found');

	}

}