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
	 * @param string $client_slug
	 * @return Pull_Client
	 */
	public function get_pull_client( $client_slug ) {

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