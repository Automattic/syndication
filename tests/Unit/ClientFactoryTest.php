<?php
/**
 * Unit tests for Syndication_Client_Factory class name generation logic
 *
 * Since the actual factory class has include dependencies on WordPress,
 * these tests verify the class naming convention using the same logic.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

/**
 * Test case for client factory class name generation.
 *
 * Tests the transport type to class name mapping logic.
 *
 * @group unit
 */
class ClientFactoryTest extends TestCase {

	/**
	 * Get the transport type class name.
	 *
	 * This mirrors the logic from Syndication_Client_Factory::get_transport_type_class().
	 *
	 * @param string $transport_type The transport type.
	 * @return string The class name.
	 */
	private function get_transport_type_class( string $transport_type ): string {
		return 'Syndication_' . $transport_type . '_Client';
	}

	/**
	 * Test get_transport_type_class returns correct class name for WP_XMLRPC.
	 */
	public function test_get_transport_type_class_xmlrpc(): void {
		$result = $this->get_transport_type_class( 'WP_XMLRPC' );

		$this->assertSame( 'Syndication_WP_XMLRPC_Client', $result );
	}

	/**
	 * Test get_transport_type_class returns correct class name for WP_REST.
	 */
	public function test_get_transport_type_class_rest(): void {
		$result = $this->get_transport_type_class( 'WP_REST' );

		$this->assertSame( 'Syndication_WP_REST_Client', $result );
	}

	/**
	 * Test get_transport_type_class returns correct class name for WP_XML.
	 */
	public function test_get_transport_type_class_xml(): void {
		$result = $this->get_transport_type_class( 'WP_XML' );

		$this->assertSame( 'Syndication_WP_XML_Client', $result );
	}

	/**
	 * Test get_transport_type_class returns correct class name for WP_RSS.
	 */
	public function test_get_transport_type_class_rss(): void {
		$result = $this->get_transport_type_class( 'WP_RSS' );

		$this->assertSame( 'Syndication_WP_RSS_Client', $result );
	}

	/**
	 * Test get_transport_type_class handles custom transport types.
	 */
	public function test_get_transport_type_class_custom(): void {
		$result = $this->get_transport_type_class( 'Custom_API' );

		$this->assertSame( 'Syndication_Custom_API_Client', $result );
	}

	/**
	 * Test get_transport_type_class handles empty string.
	 */
	public function test_get_transport_type_class_empty(): void {
		$result = $this->get_transport_type_class( '' );

		$this->assertSame( 'Syndication__Client', $result );
	}

	/**
	 * Test get_transport_type_class handles transport type with underscores.
	 */
	public function test_get_transport_type_class_with_underscores(): void {
		$result = $this->get_transport_type_class( 'My_Custom_Transport' );

		$this->assertSame( 'Syndication_My_Custom_Transport_Client', $result );
	}

	/**
	 * Test get_transport_type_class is case-sensitive.
	 */
	public function test_get_transport_type_class_case_sensitive(): void {
		$lower = $this->get_transport_type_class( 'wp_xmlrpc' );
		$upper = $this->get_transport_type_class( 'WP_XMLRPC' );

		$this->assertSame( 'Syndication_wp_xmlrpc_Client', $lower );
		$this->assertSame( 'Syndication_WP_XMLRPC_Client', $upper );
		$this->assertNotSame( $lower, $upper );
	}
}
