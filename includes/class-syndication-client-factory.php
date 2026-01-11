<?php
/**
 * Factory for creating syndication client instances.
 *
 * @package Syndication
 */

require_once __DIR__ . '/class-syndication-wp-xmlrpc-client.php';
require_once __DIR__ . '/class-syndication-wp-xml-client.php';
require_once __DIR__ . '/class-syndication-wp-rest-client.php';
require_once __DIR__ . '/class-syndication-wp-rss-client.php';

/**
 * Class Syndication_Client_Factory
 *
 * Creates syndication client instances based on transport type.
 * Supports XML-RPC, REST API, RSS, and XML feed transports.
 */
class Syndication_Client_Factory {

	/**
	 * Get a syndication client instance for the given transport type.
	 *
	 * @param string $transport_type The transport type (e.g., 'WP_XMLRPC', 'WP_REST').
	 * @param int    $site_ID        The site post ID.
	 * @return Syndication_Client The client instance.
	 * @throws Exception If the transport class is not found.
	 */
	public static function get_client( $transport_type, $site_ID ) {

		$class = self::get_transport_type_class( $transport_type );
		if ( class_exists( $class ) ) {
			return new $class( $site_ID );
		}

		throw new Exception( ' transport class not found' );
	}

	/**
	 * Display the settings form for a client type.
	 *
	 * @param WP_Post $site           The site post object.
	 * @param string  $transport_type The transport type.
	 * @return mixed The result of the display_settings call.
	 * @throws Exception If the transport class is not found.
	 */
	public static function display_client_settings( $site, $transport_type ) {

		$class = self::get_transport_type_class( $transport_type );
		if ( class_exists( $class ) ) {
			return call_user_func( array( $class, 'display_settings' ), $site );
		}

		throw new Exception( 'transport class not found: ' . esc_html( $class ) );
	}

	/**
	 * Save the settings for a client type.
	 *
	 * @param int    $site_ID        The site post ID.
	 * @param string $transport_type The transport type.
	 * @return mixed The result of the save_settings call.
	 * @throws Exception If the transport class is not found.
	 */
	public static function save_client_settings( $site_ID, $transport_type ) {

		$class = self::get_transport_type_class( $transport_type );
		if ( class_exists( $class ) ) {
			return call_user_func( array( $class, 'save_settings' ), $site_ID );
		}

		throw new Exception( 'transport class not found' );
	}

	/**
	 * Get the class name for a transport type.
	 *
	 * @param string $transport_type The transport type.
	 * @return string The fully qualified class name.
	 */
	public static function get_transport_type_class( $transport_type ) {
		return 'Syndication_' . $transport_type . '_Client';
	}
}
