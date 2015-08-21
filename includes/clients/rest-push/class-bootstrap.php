<?php

namespace Automattic\Syndication\Clients\REST_Push;

use Automattic\Syndication\Client_Manager;

/**
 * Syndication Client: REST Push
 *
 * Create 'syndication sites' to push external content from your site
 * to a remote WordPress site with RESTful requests via the WordPress.com REST API.
 *
 * @package Automattic\Syndication\Clients\REST_PUSH
 * @internal Called via instantiation in includes/class-bootstrap.php
 */
class Bootstrap {

	public function __construct() {
		// Register our syndication client
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );

		add_action( 'syndication/pre_load_client/rest_push', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	/**
	 * Register our new Syndication client
	 *
	 * @param Client_Manager $client_man
	 */
	public function register_clients( Client_Manager $client_man ) {
		$client_man->register_push_client(
			'rest_push', [
				'label' => 'REST Push Client',
				'class' => __NAMESPACE__ . '\Push_Client',
			]
		);
	}

	public function pre_load() {
		// Clients could use this hook to make sure the class is included.
	}

}
