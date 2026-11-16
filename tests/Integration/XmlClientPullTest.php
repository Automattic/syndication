<?php
/**
 * Tests for parsing and settings in the XML pull client.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlClientPullTest
 *
 * @covers Syndication_WP_XML_Client
 */
class XmlClientPullTest extends WPIntegrationTestCase {

	/**
	 * The body returned to the client in place of a real HTTP response.
	 *
	 * @var string
	 */
	private $feed_body = '<?xml version="1.0"?><rss><channel><title>Channel</title>'
		. '<item><title>First</title><category>news</category><category>sport</category></item>'
		. '</channel></rss>';

	/**
	 * Intercept the feed fetch.
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
	 * Build a node mapping row.
	 *
	 * @param string $field   Field the value is stored in.
	 * @param array  $flags   Flags to set, e.g. array( 'is_item' ).
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
	 * Create an XML site with the given XPath mappings.
	 *
	 * @param array  $nodes     XPath-to-storage mappings.
	 * @param string $post_root XPath to the post root.
	 * @return int The site ID.
	 */
	private function make_site( array $nodes, string $post_root = '/rss/channel/item' ): int {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_feed_url', 'http://example.com/feed.xml' );
		update_post_meta( $site_id, 'syn_default_post_type', 'post' );
		update_post_meta(
			$site_id,
			'syn_node_config',
			array(
				'namespace'  => '',
				'post_root'  => $post_root,
				'enc_parent' => '',
				'categories' => array(),
				'nodes'      => $nodes,
			)
		);

		return $site_id;
	}

	/**
	 * Get the most recent log entry for a site.
	 *
	 * @param int $site_id The site ID.
	 * @return array<string, mixed>
	 */
	private function last_log_entry( int $site_id ): array {
		$log = get_post_meta( $site_id, 'syn_log', true );
		return end( $log );
	}

	/**
	 * A feed that fails to parse is logged, rather than crashing on the log call.
	 */
	public function test_unparseable_feed_is_logged(): void {
		$this->feed_body = '<rss><channel><item>';
		$site_id         = $this->make_site( array( 'title' => $this->node( 'post_title', array( 'is_item' ) ) ) );

		$posts = ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();

		$this->assertSame( array(), $posts );
		$entry = $this->last_log_entry( $site_id );
		$this->assertSame( 'error', $entry['msg_type'] );
		$this->assertStringContainsString( 'Failed to parse feed', $entry['message'] );
	}

	/**
	 * A post root that matches nothing is logged, rather than crashing on the log call.
	 */
	public function test_post_root_without_matches_is_logged(): void {
		$site_id = $this->make_site( array( 'title' => $this->node( 'post_title', array( 'is_item' ) ) ), '/rss/channel/entry' );

		$posts = ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();

		$this->assertSame( array(), $posts );
		$entry = $this->last_log_entry( $site_id );
		$this->assertSame( 'error', $entry['msg_type'] );
		$this->assertStringContainsString( 'No post nodes found', $entry['message'] );
	}
}
