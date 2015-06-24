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
	 * @see Called via functions-template-tags.php:syn_get_pull_client
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
	 * @return Push_Client
	 */
	public function get_push_client( $client_slug ) {

	}

	public function get_clients() {
		return $this->_push_clients + $this->_pull_clients;
	}
}
