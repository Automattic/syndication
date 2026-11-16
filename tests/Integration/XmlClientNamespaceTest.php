<?php
/**
 * Tests that the XML client's namespace setting applies to XPath.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlClientNamespaceTest
 *
 * In XPath 1.0 an unprefixed name only matches elements in no namespace, so
 * the elements of a feed with a default namespace (xmlns="...") can only be
 * reached through a prefix registered for that namespace. The client registers
 * the site's namespace setting under the "syn" prefix.
 *
 * @covers Syndication_WP_XML_Client
 */
class XmlClientNamespaceTest extends WPIntegrationTestCase {

	/**
	 * An Atom feed: default namespace, plus a prefixed Media RSS namespace.
	 */
	const FEED = '<?xml version="1.0"?>'
		. '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">'
		. '<title>Channel</title>'
		. '<entry><id>atom-1</id><title>First</title>'
		. '<link rel="enclosure" href="https://example.com/a.jpg"><media:title>Photo</media:title></link>'
		. '<media:keywords>news</media:keywords></entry>'
		. '</feed>';

	/**
	 * Serve the Atom feed.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-xml-client.php';

		add_filter(
			'pre_http_request',
			function () {
				return array(
					'headers'  => array(),
					'body'     => self::FEED,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			}
		);
	}

	/**
	 * Build a node mapping row.
	 *
	 * @param string $field Field the value is stored in.
	 * @param array  $flags Flags to set, e.g. array( 'is_item' ).
	 * @return array<int, array<string, int|string>>
	 */
	private function node( string $field, array $flags ): array {
		$row = array(
			'field'    => $field,
			'is_item'  => 0,
			'is_meta'  => 0,
			'is_tax'   => 0,
			'is_photo' => 0,
		);
		foreach ( $flags as $flag ) {
			$row[ $flag ] = 1;
		}
		return array( $row );
	}

	/**
	 * Pull the feed with the given mapping.
	 *
	 * @param string $space      The site's namespace setting.
	 * @param string $post_root  XPath to the post root.
	 * @param string $enc_parent XPath to the enclosure parent.
	 * @param array  $nodes      XPath-to-storage mappings.
	 * @return array The pulled post data.
	 */
	private function pull( string $space, string $post_root, string $enc_parent, array $nodes ): array {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_feed_url', 'http://example.com/atom.xml' );
		update_post_meta( $site_id, 'syn_default_post_type', 'post' );
		update_post_meta( $site_id, 'syn_id_field', 'atom_id' );
		update_post_meta(
			$site_id,
			'syn_node_config',
			array(
				'namespace'  => $space,
				'post_root'  => $post_root,
				'enc_parent' => $enc_parent,
				'categories' => array(),
				'nodes'      => $nodes,
			)
		);

		return ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();
	}

	/**
	 * The namespace setting is available as the "syn" prefix on the feed, each item and each enclosure.
	 */
	public function test_namespace_setting_is_registered_as_the_syn_prefix(): void {
		$posts = $this->pull(
			'http://www.w3.org/2005/Atom',
			'/syn:feed/syn:entry',
			'syn:link[@rel="enclosure"]',
			array(
				'syn:id'             => $this->node( 'atom_id', array( 'is_item', 'is_meta' ) ),
				'syn:title'          => $this->node( 'post_title', array( 'is_item' ) ),
				'/syn:feed/syn:title' => $this->node( 'channel', array( 'is_meta' ) ),
				'@href'              => $this->node( 'url', array( 'is_photo' ) ),
				'media:title'        => $this->node( 'caption', array( 'is_photo' ) ),
			)
		);

		$this->assertCount( 1, $posts );
		$this->assertSame( 'First', $posts[0]['post_title'] );
		$this->assertSame( 'atom-1', $posts[0]['post_guid'] );
		$this->assertSame( 'Channel', $posts[0]['postmeta']['channel'] );
		$this->assertSame( 'https://example.com/a.jpg', $posts[0]['postmeta']['enclosures'][0]['url'] );
		$this->assertSame( 'Photo', $posts[0]['postmeta']['enclosures'][0]['caption'] );
	}

	/**
	 * Mappings written without the prefix keep working: local-name() tests and the feed's own prefixes.
	 *
	 * @dataProvider data_namespace_settings
	 *
	 * @param string $space The site's namespace setting.
	 */
	public function test_existing_namespace_workarounds_still_work( string $space ): void {
		$posts = $this->pull(
			$space,
			'/*[local-name()="feed"]/*[local-name()="entry"]',
			'',
			array(
				'*[local-name()="id"]'    => $this->node( 'atom_id', array( 'is_item', 'is_meta' ) ),
				'*[local-name()="title"]' => $this->node( 'post_title', array( 'is_item' ) ),
				'media:keywords'          => $this->node( 'keywords', array( 'is_item', 'is_meta' ) ),
			)
		);

		$this->assertCount( 1, $posts );
		$this->assertSame( 'First', $posts[0]['post_title'] );
		$this->assertSame( 'atom-1', $posts[0]['post_guid'] );
		$this->assertSame( 'news', $posts[0]['postmeta']['keywords'] );
	}

	/**
	 * Namespace settings: none, and the feed's default namespace.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_namespace_settings(): array {
		return array(
			'no namespace'      => array( '' ),
			'default namespace' => array( 'http://www.w3.org/2005/Atom' ),
		);
	}
}
