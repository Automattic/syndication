<?php

namespace Automattic\Syndication\Clients\XML_Pull;

use Automattic\Syndication\Client_Manager;

/**
 * Syndication Client: XML Pull
 *
 * Create 'syndication sites' to pull external content into your
 * WordPress install via XML. Includes XPath mapping to map incoming
 * XML data to specific post data.
 *
 * @package Automattic\Syndication\Clients\XML_PULL
 * @internal Called via instantiation in includes/class-bootstrap.php
 */
class Bootstrap {

	public function __construct() {
		// Register our syndication client
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );

		add_action( 'syndication/pre_load_client/xml_pull', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	/**
	 * Register our new Syndication client
	 *
	 * @param Client_Manager $client_man
	 */
	public function register_clients( Client_Manager $client_man ) {
		$client_man->register_pull_client( 'xml_pull', [
			'label' => 'XML Pull Client',
			'class' => __NAMESPACE__ . '\Pull_Client',
		] );
	}

	public function pre_load() {
		// Clients could use this hook to make sure the class is included.
	}

}
