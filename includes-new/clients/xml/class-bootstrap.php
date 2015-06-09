<?php

namespace Automattic\Syndication\Clients\XML;

use Automattic\Syndication\Client_Manager;

class Bootstrap {

	public function __construct()
	{
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );
		add_action( 'syndication/pre_load_client/xml_pull', [ $this, 'pre_load' ] );

		new Site_Options();
		new Client_Options();
	}

	public function register_clients( Client_Manager $client_man )
	{
		$client_man->register_pull_client( 'xml_pull', [
			'label' => 'XML Pull Client',
			'class' => __NAMESPACE__ . '\Pull_Client',
		] );
	}

	public function pre_load() {

		// Clients could use this hook to make sure the class is included.
	}
}
