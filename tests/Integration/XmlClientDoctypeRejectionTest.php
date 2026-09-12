<?php
/**
 * Tests that the XML client refuses feeds carrying a document type declaration.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlClientDoctypeRejectionTest
 *
 * Feed bodies are remote and the feed URL field accepts plain http://, so an
 * on-path attacker can choose the bytes that reach the parser. On libxml builds
 * that still resolve external entities that means local file disclosure or SSRF,
 * so fetch_feed() refuses a body declaring a doctype rather than trusting the
 * library's defaults. Rejecting at the fetch covers test_connection() too, which
 * means a feed the plugin will refuse to pull reports as broken when it is
 * configured rather than silently at pull time.
 *
 * @covers Syndication_WP_XML_Client
 */
class XmlClientDoctypeRejectionTest extends WPIntegrationTestCase {

	/**
	 * The body returned to the client in place of a real HTTP response.
	 *
	 * @var string
	 */
	private $feed_body = '';

	/**
	 * Set up a configured site and intercept the feed fetch.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-xml-client.php';

		add_filter(
			'pre_http_request',
			function () {
				return array(
					'headers'  => array(),
					'body'     => $this->feed_body,
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
	 * Build a client pointed at an http:// feed with a minimal node config.
	 *
	 * @return \Syndication_WP_XML_Client
	 */
	private function make_client() {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_feed_url', 'http://example.com/feed.xml' );
		update_post_meta(
			$site_id,
			'syn_node_config',
			array(
				'namespace'  => '',
				'post_root'  => '/rss/channel/item',
				'enc_parent' => '',
				'categories' => array(),
				'nodes'      => array(
					'title' => array(
						array(
							'field'    => 'post_title',
							'is_item'  => 1,
							'is_meta'  => 0,
							'is_tax'   => 0,
							'is_photo' => 0,
						),
					),
				),
			)
		);

		return new \Syndication_WP_XML_Client( $site_id );
	}

	/**
	 * Feeds declaring a doctype, external entity or not.
	 *
	 * @return array<string, array{0: string}> Test cases keyed by feed shape.
	 */
	public function data_doctype_feeds(): array {
		return array(
			'external entity'    => array(
				'<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
					. '<rss><channel><item><title>&xxe;</title></item></channel></rss>',
			),
			'network entity'     => array(
				'<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY xxe SYSTEM "http://169.254.169.254/">]>'
					. '<rss><channel><item><title>&xxe;</title></item></channel></rss>',
			),
			'whitespace padding' => array(
				'<?xml version="1.0"?><! DOCTYPE rss SYSTEM "http://example.com/evil.dtd">'
					. '<rss><channel><item><title>Hello</title></item></channel></rss>',
			),
			'harmless doctype'   => array(
				'<?xml version="1.0"?><!DOCTYPE rss><rss><channel><item><title>Hello</title></item></channel></rss>',
			),
		);
	}

	/**
	 * A feed with a doctype is never handed to the parser.
	 *
	 * @dataProvider data_doctype_feeds
	 *
	 * @param string $feed The feed body served to the client.
	 */
	public function test_feed_with_a_doctype_is_rejected( string $feed ): void {
		$this->feed_body = $feed;

		$this->assertSame(
			array(),
			$this->make_client()->get_posts(),
			'A feed declaring a doctype should yield no posts.'
		);
	}

	/**
	 * Rejecting at the fetch means a doctype feed also fails its connection test.
	 *
	 * @dataProvider data_doctype_feeds
	 *
	 * @param string $feed The feed body served to the client.
	 */
	public function test_feed_with_a_doctype_fails_the_connection_test( string $feed ): void {
		$this->feed_body = $feed;

		$this->assertFalse(
			$this->make_client()->test_connection(),
			'A feed declaring a doctype should not report a working connection.'
		);
	}

	/**
	 * A feed without a doctype still parses, so the guard is not just rejecting everything.
	 */
	public function test_feed_without_a_doctype_still_parses(): void {
		$this->feed_body = '<?xml version="1.0"?><rss><channel><item><title>Hello</title></item></channel></rss>';

		$posts = $this->make_client()->get_posts();

		$this->assertCount( 1, $posts );
		$this->assertSame( 'Hello', $posts[0]['post_title'] );
		$this->assertTrue( $this->make_client()->test_connection() );
	}
}
