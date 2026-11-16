<?php
/**
 * Tests that the XML site settings form keeps every node mapping when saved again.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlClientNodeRowsTest
 *
 * @covers Syndication_WP_XML_Client::display_settings
 * @covers Syndication_WP_XML_Client::save_settings
 */
class XmlClientNodeRowsTest extends WPIntegrationTestCase {

	/**
	 * Clear the request between tests.
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Two mappings for the same XPath survive the form being rendered and saved again.
	 */
	public function test_mappings_sharing_an_xpath_survive_a_resave(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$this->save(
			$site_id,
			array(
				array(
					'xpath'   => 'title',
					'is_item' => 'true',
					'field'   => 'post_title',
				),
				array(
					'xpath'   => 'title',
					'is_item' => 'true',
					'is_meta' => 'true',
					'field'   => 'source_headline',
				),
				array(
					'xpath'   => 'link',
					'is_item' => 'true',
					'field'   => 'post_guid',
				),
			)
		);

		$expected = get_post_meta( $site_id, 'syn_node_config', true )['nodes'];
		$this->assertCount( 2, $expected['title'] );

		// Save again with whatever the rendered form would submit, as an editor changing another field would.
		$this->save( $site_id, $this->submitted_rows( $site_id ) );

		$this->assertSame( $expected, get_post_meta( $site_id, 'syn_node_config', true )['nodes'] );
	}

	/**
	 * Save the XML site settings with the given node rows.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $rows    Node rows, as the form submits them.
	 */
	private function save( int $site_id, array $rows ): void {
		$_POST = array(
			'feed_url'            => 'https://example.com/feed.xml',
			'default_post_type'   => 'post',
			'default_post_status' => 'draft',
			'post_root'           => '//item',
			'node'                => $rows,
		);

		\Syndication_WP_XML_Client::save_settings( $site_id );
	}

	/**
	 * The node rows a browser would submit from the rendered settings form.
	 *
	 * @param int $site_id Site ID.
	 * @return array Node rows keyed by row index.
	 */
	private function submitted_rows( int $site_id ): array {
		ob_start();
		\Syndication_WP_XML_Client::display_settings( get_post( $site_id ) );
		$html = ob_get_clean();

		$dom = new \DOMDocument();
		$internal_errors = libxml_use_internal_errors( true );
		$dom->loadHTML( $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		$pairs = array();
		foreach ( ( new \DOMXPath( $dom ) )->query( '//input[starts-with(@name, "node[")]' ) as $input ) {
			if ( 'checkbox' === $input->getAttribute( 'type' ) && ! $input->hasAttribute( 'checked' ) ) {
				continue;
			}
			$pairs[] = rawurlencode( $input->getAttribute( 'name' ) ) . '=' . rawurlencode( $input->getAttribute( 'value' ) );
		}

		parse_str( implode( '&', $pairs ), $submitted );

		return $submitted['node'];
	}
}
