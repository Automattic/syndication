<?php

namespace Automattic\Syndication\Clients\XML_Push;

use Automattic\Syndication\Client_Manager;

/**
 * Syndication Client: XML Push
 *
 * Create 'syndication sites' to push external content from your site
 * to a remote WordPress site via XML-RPC
 *
 * @package Automattic\Syndication\Clients\XML_PUSH
 * @internal Called via instantiation in includes/class-bootstrap.php
 */
class Bootstrap {

	public function __construct() {
		// Register our syndication client
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );

		add_action( 'syndication/pre_load_client/xml_push', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	/**
	 * Register our new Syndication client
	 *
	 * @param Client_Manager $client_man
	 */
	public function register_clients( Client_Manager $client_man ) {
		$client_man->register_push_client(
			'xml_push', [
				'label' => 'XML Push Client',
				'class' => __NAMESPACE__ . '\Push_Client',
			]
		);
	}

	public function pre_load() {
		// Clients could use this hook to make sure the class is included.
	}

}
