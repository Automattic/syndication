<?php

namespace Automattic\Syndication;

class Client_Manager {

	protected $_push_clients = [];

	protected $_pull_clients = [];

	public function __construct() {

		add_action( 'syndication/init', [ $this, 'init' ] );
	}

	public function init() {
		do_action( 'syndication/register_clients', [ $this ] );
	}

	public function register_pull_client( $client_slug, $options ) {

		// @todo validate
		$this->_pull_clients[ $client_slug ] = $options;
	}

	public function register_push_client( $client_slug, $options ) {

		// @todo validate
		$this->_push_clients[ $client_slug ] = $options;
	}

	/**
	 * Return a client by it's slug
	 *
	 * @param  string $client_slug The slug of the client you want
	 * @return array               Information about the requested client
	 */
	public function get_pull_client( $client_slug = '' ) {
		try {
			if ( ! empty( $client_slug ) ) {
				if ( isset( $this->_pull_clients[ $client_slug ] ) ) {
					return $this->_pull_clients[ $client_slug ];
				} else {
					throw new \Exception( "No client found with slug: $client_slug" );
				}
			} else {
				throw new \Exception( 'Client slug must be specified!' );
			}
		} catch ( \Exception $e ) {

			// Send the error to the PHP error log
			error_log( $e );

			return false;
		}
	}

	/**
	 * @param string $client_slug
	 * @return Push_Client
	 */
	public function get_push_client( $client_slug ) {

	}

	public function get_clients() {

		return $this->_push_clients + $this->_pull_clients;
	}
}

/**
 * Fetch a pull client by it's slug
 *
 * Example:
 * $client = \Automattic\Syndication\get_pull_client( 'my-client-slug' );
 *
 * @param  string $client_slug The slug of the client you wish to fetch
 * @return array               The client if it's found
 */
function get_pull_client( $client_slug = '' ) {
	global $client_manager;

	$pull_client = $client_manager->get_pull_client( $client_slug );

	if ( false !== $pull_client ) {
		return $pull_client;
	}
}