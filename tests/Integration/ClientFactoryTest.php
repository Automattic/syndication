<?php
/**
 * Tests for Syndication_Client_Factory.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Exception;
use Syndication_Client_Factory;
use Syndication_Mock_Client;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class ClientFactoryTest
 *
 * @covers Syndication_Client_Factory
 */
class ClientFactoryTest extends WPIntegrationTestCase {

	/**
	 * The transport ID each client registers resolves back to that client.
	 *
	 * Sites store the registered ID as their transport type, so a mismatch
	 * leaves every site of that type without a client.
	 *
	 * @dataProvider data_built_in_clients
	 *
	 * @param string $client_class Client class name.
	 */
	public function test_registered_transport_id_resolves_to_its_client( string $client_class ): void {
		$client_data = call_user_func( array( $client_class, 'get_client_data' ) );

		$this->assertSame( $client_class, Syndication_Client_Factory::get_transport_type_class( $client_data['id'] ) );
	}

	/**
	 * Built-in clients.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_built_in_clients(): array {
		return array(
			'XML-RPC' => array( 'Syndication_WP_XMLRPC_Client' ),
			'REST'    => array( 'Syndication_WP_REST_Client' ),
			'RSS'     => array( 'Syndication_WP_RSS_Client' ),
			'XML'     => array( 'Syndication_WP_XML_Client' ),
		);
	}

	/**
	 * A transport type with a matching class gets an instance of it.
	 */
	public function test_get_client_returns_the_transport_class(): void {
		$this->assertInstanceOf( Syndication_Mock_Client::class, Syndication_Client_Factory::get_client( 'Mock', 1 ) );
	}

	/**
	 * A transport type without a class is refused.
	 */
	public function test_get_client_throws_for_an_unknown_transport(): void {
		$this->expectException( Exception::class );

		Syndication_Client_Factory::get_client( 'No_Such_Transport', 1 );
	}
}
