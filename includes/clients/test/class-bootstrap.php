<?php

namespace Automattic\Syndication\Clients\Test;

use Automattic\Syndication\Client_Manager;

class Bootstrap {

	public function __construct()
	{
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );
		add_action( 'syndication/pre_load_client/test_pull', [ $this, 'pre_load' ] );
		add_action( 'syndication/pre_load_client/test_push', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	public function register_clients( Client_Manager $client_man )
	{
		$client_man->register_pull_client(
			'test_pull',
			array(
				'label' => 'Test Pull Client',
				'class' => __NAMESPACE__ . '\Pull_Client',
			)
		);

		$client_man->register_push_client(
			'test_push',
			array(
				'label' => 'Test Push Client',
				'class' => __NAMESPACE__ . '\Push_Client',
			)
		);
	}

	public function pre_load() {

		// Clients could use this hook to make sure the class is included.
	}
}