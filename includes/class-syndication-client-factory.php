<?php

require_once dirname( __FILE__ ) . '/class-syndication-wp-xmlrpc-client.php';
require_once dirname( __FILE__ ) . '/class-syndication-wp-xml-client.php';
require_once dirname( __FILE__ ) . '/class-syndication-wp-rest-client.php';
require_once dirname( __FILE__ ) . '/class-syndication-wp-rss-client.php';

/**
 * Class Syndication_Client_Factory
 */
class Syndication_Client_Factory {

	/**
	 * @param $transport_type
	 * @param $site_id
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public static function get_client( $transport_type, $site_id ) {
		$class = self::get_transport_type_class( $transport_type );
		if ( class_exists( $class ) ) {
			return new $class( $site_id );
		}

		throw new Exception( ' transport class not found' );
	}

	/**
	 * @param $site
	 * @param $transport_type
	 *
	 * @return false|mixed
	 * @throws Exception
	 */
	public static function display_client_settings( $site, $transport_type ) {
		$class = self::get_transport_type_class( $transport_type );
		if ( class_exists( $class ) ) {
			return call_user_func( array( $class, 'display_settings' ), $site );
		}

		throw new Exception( 'transport class not found: ' . $class );
	}

	/**
	 * @param $site_id
	 * @param $transport_type
	 *
	 * @return false|mixed
	 * @throws Exception
	 */
	public static function save_client_settings( $site_id, $transport_type ) {
		$class = self::get_transport_type_class( $transport_type );
		if ( class_exists( $class ) ) {
			return call_user_func( array( $class, 'save_settings' ), $site_id );
		}

		throw new Exception( 'transport class not found' );
	}

	/**
	 * @param $transport_type
	 *
	 * @return string
	 */
	public static function get_transport_type_class( $transport_type ) {
		return 'Syndication_' . $transport_type . '_Client';
	}

}
