<?php

namespace Automattic\Syndication;

/**
 * Client Manager
 *
 * The role of the client manager is to manage our syndication clients.
 * This entails the registration, fetching, and updating of clients.
 * Individual clients are used to pull or push content to/from your WordPress install.
 *
 * All 'sites' are paired with a client/transport type, e.g.
 * You could have an ESPN site setup to pull content via the XML_Pull client
 *
 * @package Automattic\Syndication
 */
class Client_Manager {

	protected $_push_clients = array();

	protected $_pull_clients = array();

	public function __construct() {

		add_action( 'syndication/init', [ $this, 'init' ] );
	}

	public function init() {

		/**
		 * Fires when the client manager initializes.
		 *
		 * Clients hook into this to register themselves with the client manager.
		 *
		 * @param Site_Manager $site_manager The Site_Manager.
		 */
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

		if ( ! empty( $client_slug ) ) {
			if ( isset( $this->_pull_clients[ $client_slug ] ) ) {
				return $this->_pull_clients[ $client_slug ];
			}
		}

		return false;
	}

	/**
	 * @param string $client_slug
	 * @return Pusher Push_Client
	 */
	public function get_push_client( $client_slug ) {

		if ( ! empty( $client_slug ) ) {
			if ( isset( $this->_push_clients[ $client_slug ] ) ) {
				return $this->_push_clients[ $client_slug ];
			}
		}

		return false;
	}

	/**
	 * Get all push and pull clients
	 *
	 * @return array
	 */
	public function get_clients() {
		return $this->_push_clients + $this->_pull_clients;
	}

	/**
	 * Get a push or pull client by slug, trying pull first.
	 *
	 * @param string $client_slug
	 * @return Pusher|Puller Push or Pull Client
	 */
	public function get_pull_or_push_client( $client_slug ) {
		$client = $this->get_pull_client( $client_slug );

		if ( ! $client ) {
			$client = $this->get_push_client( $client_slug );
		}

		return $client;
	}

	/**
	 * Test a client connection, used to validate feed.
	 *
	 * @return bool
	 * @todo verify fires on save, compare to v2
	 */
	public function test_connection( $site_ID ) {

		// Get the required client.
		$client_transport_type = get_post_meta( $site_ID, 'syn_transport_type', true );
		if ( ! $client_transport_type ) {
			return false;
		}

		// Fetch the client so we may pull it's posts
		$client_details = $this->get_push_client( $client_transport_type );

		if ( ! $client_details ) {
			return false;
		}

		// Run the client's process_site method
		$client = new $client_details['class'];

		//@todo verify this show the user an error message.
		if ( $client->test_connection( $site_ID ) ) {
			add_filter( 'redirect_post_location', create_function( '$location', 'return add_query_arg( "message", 251, $location);' ) );
		} else {
			add_filter( 'redirect_post_location', create_function( '$location', 'return add_query_arg( "message", 252, $location);' ) );
		}
	}
}
