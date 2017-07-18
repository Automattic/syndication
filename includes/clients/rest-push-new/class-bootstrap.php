<?php
/**
 * Syndication Client: REST Push New
 *
 * Create 'syndication sites' to push external content from your site
 * to a remote WordPress site via the WP REST API
 *
 * @since 2.1
 * @package Automattic\Syndication\Clients\REST_Push_New
 * @internal Called via instantiation in includes/class-bootstrap.php
 */

namespace Automattic\Syndication\Clients\REST_Push_New;

use Automattic\Syndication\Client_Manager;

/**
 * Class Bootstrap
 *
 * @since 2.1
 * @package Automattic\Syndication\Clients\REST_Push_New
 */
class Bootstrap {
	/**
	 * Bootstrap constructor.
	 *
	 * @since 2.1
	 */
	public function __construct() {
		// Register our syndication client
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );

		add_action( 'syndication/pre_load_client/rest_push_new', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	/**
	 * Register our new Syndication client
	 *
	 * @since 2.1
	 * @param Client_Manager $client_man
	 */
	public function register_clients( Client_Manager $client_man ) {
		$client_man->register_push_client(
			'rest_push_new', [
				'label' => 'REST Push Client (New)',
				'class' => __NAMESPACE__ . '\Push_Client',
			]
		);
	}

	/**
	 * Clients could use this hook to make sure the class is included.
	 *
	 * @since 2.1
	 */
	public function pre_load() { }
}
