<?php

namespace Automattic\Syndication\Clients\RSS_Pull;

use Automattic\Syndication\Client_Manager;

/**
 * Syndication Client: RSS Pull
 *
 * Create 'syndication sites' to pull external content into your
 * WordPress install via RSS.
 *
 * @package Automattic\Syndication\Clients\RSS_Pull
 * @internal Called via instantiation in includes/class-bootstrap.php
 */
class Bootstrap {

	public function __construct() {

		// Register our syndication client
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );

		add_action( 'syndication/pre_load_client/rss_pull', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	/**
	 * Register our new Syndication client
	 *
	 * @param Client_Manager $client_man
	 */
	public function register_clients( Client_Manager $client_man ) {
		$client_man->register_pull_client(
			'rss_pull',
			array(
				'label' => 'RSS Pull Client',
				'class' => __NAMESPACE__ . '\Pull_Client',
			)
		);
	}

	public function pre_load() {
		// Clients could use this hook to make sure the class is included.
	}

	/**
	 * Render client options on the Settings->Syndication screen.
	 */
	public function render_client_options() {
	}

	/**
	 * Save client settings from the Settings->Syndication screen.
	 */
	public function save_client_options() {

	}
}
