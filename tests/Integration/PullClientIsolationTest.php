<?php
/**
 * Tests that pull clients only post-process their own posts, and apply their site's settings.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullClientIsolationTest
 *
 * The XML and RSS clients register static callbacks on the shared
 * syn_post_pull_new_post and syn_post_pull_edit_post actions. Once a pull run
 * has constructed both clients, each callback fires for every pulled post, so
 * they have to check the post came from their own client.
 *
 * @covers Syndication_WP_XML_Client
 * @covers Syndication_WP_RSS_Client
 */
class PullClientIsolationTest extends WPIntegrationTestCase {

	/**
	 * Local file holding the RSS feed. SimplePie reads local paths directly.
	 *
	 * @var string
	 */
	private $rss_file;

	/**
	 * Write the RSS fixture and serve the XML feed over a mocked request.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-xml-client.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rss-client.php';

		$this->rss_file = wp_tempnam( 'syn-rss' );
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->rss_file,
			'<?xml version="1.0"?><rss version="2.0"><channel><title>RSS</title><link>https://rss.example.com/</link><description>RSS</description>'
				. '<item><title>From RSS</title><link>https://rss.example.com/1</link><guid>https://rss.example.com/1</guid>'
				. '<description>RSS body</description><pubDate>Mon, 16 Nov 2026 10:00:00 +0000</pubDate></item>'
				. '</channel></rss>'
		);

		add_filter(
			'pre_http_request',
			function () {
				return array(
					'headers'  => array(),
					'body'     => '<?xml version="1.0"?><feed><entry><id>xml-1</id><title>From XML</title><body>XML body</body></entry></feed>',
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
	 * Remove the RSS fixture.
	 */
	public function tear_down(): void {
		unlink( $this->rss_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		parent::tear_down();
	}

	/**
	 * Create an enabled pull site.
	 *
	 * @param string $transport Transport type.
	 * @param array  $meta      Site settings.
	 * @return \WP_Post The site.
	 */
	private function make_site( string $transport, array $meta ): \WP_Post {
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		$meta += array(
			'syn_site_enabled'      => 'on',
			'syn_transport_type'    => $transport,
			'syn_default_post_type' => 'post',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $site_id, $key, $value );
		}

		return get_post( $site_id );
	}

	/**
	 * Create an XML site pulling one entry.
	 *
	 * @param array $meta Extra site settings.
	 * @return \WP_Post The site.
	 */
	private function make_xml_site( array $meta = array() ): \WP_Post {
		$item_node = function ( $field, $is_meta = 0 ) {
			return array(
				array(
					'field'    => $field,
					'is_item'  => 1,
					'is_meta'  => $is_meta,
					'is_tax'   => 0,
					'is_photo' => 0,
				),
			);
		};

		return $this->make_site(
			'WP_XML',
			$meta + array(
				'syn_feed_url'    => 'http://xml.example.com/feed.xml',
				'syn_id_field'    => 'xml_id',
				'syn_node_config' => array(
					'namespace'  => '',
					'post_root'  => '/feed/entry',
					'enc_parent' => '',
					'categories' => array(),
					'nodes'      => array(
						'id'    => $item_node( 'xml_id', 1 ),
						'title' => $item_node( 'post_title' ),
						'body'  => $item_node( 'post_content' ),
					),
				),
			)
		);
	}

	/**
	 * Find the local post pulled with a given GUID.
	 *
	 * @param string $guid The source GUID.
	 * @return \WP_Post The pulled post.
	 */
	private function pulled_post( string $guid ): \WP_Post {
		$posts = get_posts(
			array(
				'post_status' => 'any',
				'meta_key'    => 'syn_post_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'  => $guid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);
		$this->assertCount( 1, $posts, "Expected one post pulled with GUID $guid." );
		return $posts[0];
	}

	/**
	 * A post pulled from an RSS site keeps its draft status when an XML site is pulled in the same run.
	 */
	public function test_rss_post_stays_draft_when_pulled_alongside_an_xml_site(): void {
		global $push_syndication_server;

		$xml_site = $this->make_xml_site( array( 'syn_default_post_status' => 'publish' ) );
		$rss_site = $this->make_site(
			'WP_RSS',
			array(
				'syn_feed_url'            => $this->rss_file,
				'syn_default_post_status' => 'draft',
				'syn_default_cat_status'  => 'no',
			)
		);

		// XML first, so its callbacks are registered before the RSS posts are inserted.
		$push_syndication_server->pull_content( array( $xml_site, $rss_site ) );

		$this->assertSame( 'draft', $this->pulled_post( 'https://rss.example.com/1' )->post_status );
		$this->assertSame( 'publish', $this->pulled_post( 'xml-1' )->post_status );
	}

	/**
	 * XML pulls apply the site's post, comment and ping status settings.
	 */
	public function test_xml_pull_applies_the_site_status_settings(): void {
		global $push_syndication_server;

		$xml_site = $this->make_xml_site(
			array(
				'syn_default_post_status'    => 'pending',
				'syn_default_comment_status' => 'closed',
				'syn_default_ping_status'    => 'closed',
			)
		);
		update_option( 'default_comment_status', 'open' );
		update_option( 'default_ping_status', 'open' );

		$push_syndication_server->pull_content( array( $xml_site ) );

		$post = $this->pulled_post( 'xml-1' );
		$this->assertSame( 'pending', $post->post_status );
		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( 'closed', $post->ping_status );
	}

	/**
	 * An XML site saved without a post status still publishes, as it did before the setting was honoured.
	 */
	public function test_xml_pull_without_a_saved_status_publishes(): void {
		global $push_syndication_server;

		$push_syndication_server->pull_content( array( $this->make_xml_site() ) );

		$this->assertSame( 'publish', $this->pulled_post( 'xml-1' )->post_status );
	}
}
