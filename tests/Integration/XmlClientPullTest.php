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

	/**
	 * Invalid XPath expressions, at item and feed level.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function data_invalid_xpath(): array {
		return array(
			'item field' => array( array( 'title[@' => $this->node( 'post_title', array( 'is_item' ) ) ) ),
			'feed field' => array( array( '/rss/channel/title[@' => $this->node( 'post_excerpt', array() ) ) ),
		);
	}

	/**
	 * An invalid XPath aborts the pull with a logged error, instead of producing empty posts.
	 *
	 * @dataProvider data_invalid_xpath
	 *
	 * @param array<string, mixed> $nodes XPath-to-storage mappings.
	 */
	public function test_invalid_xpath_aborts_with_a_logged_error( array $nodes ): void {
		$site_id = $this->make_site( $nodes );

		$posts = ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();

		$this->assertSame( array(), $posts );
		$entry = $this->last_log_entry( $site_id );
		$this->assertSame( 'error', $entry['msg_type'] );
		$this->assertStringContainsString( 'Invalid XPath', $entry['message'] );
	}

	/**
	 * A valid XPath that matches nothing leaves the field unset, rather than blank.
	 */
	public function test_xpath_without_a_match_leaves_the_field_unset(): void {
		$site_id = $this->make_site(
			array(
				'title'       => $this->node( 'post_title', array( 'is_item' ) ),
				'description' => $this->node( 'post_content', array( 'is_item' ) ),
				'/rss/channel/subtitle' => $this->node( 'subtitle', array( 'is_meta' ) ),
			)
		);

		$posts = ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();

		$this->assertCount( 1, $posts );
		$this->assertSame( 'First', $posts[0]['post_title'] );
		$this->assertArrayNotHasKey( 'post_content', $posts[0] );
		$this->assertArrayNotHasKey( 'subtitle', $posts[0]['postmeta'] );
	}

	/**
	 * Enclosures are still collected, and an invalid enclosure XPath aborts the pull.
	 */
	public function test_enclosures_are_parsed_and_invalid_enclosure_xpath_aborts(): void {
		$this->feed_body = '<?xml version="1.0"?><rss><channel><item><title>First</title>'
			. '<media><url>https://example.com/a.jpg</url></media>'
			. '<media><url>https://example.com/b.jpg</url><caption>B</caption></media>'
			. '</item></channel></rss>';

		$nodes   = array(
			'title'   => $this->node( 'post_title', array( 'is_item' ) ),
			'url'     => $this->node( 'url', array( 'is_photo' ) ),
			'caption' => $this->node( 'caption', array( 'is_photo' ) ),
		);
		$site_id = $this->make_site( $nodes );
		$config  = get_post_meta( $site_id, 'syn_node_config', true );
		update_post_meta( $site_id, 'syn_node_config', array_merge( $config, array( 'enc_parent' => 'media' ) ) );

		$posts = ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();

		$this->assertSame(
			array(
				array(
					'url'      => 'https://example.com/a.jpg',
					'caption'  => '',
					'position' => 0,
				),
				array(
					'url'      => 'https://example.com/b.jpg',
					'caption'  => 'B',
					'position' => 1,
				),
			),
			$posts[0]['postmeta']['enclosures']
		);

		$nodes['caption[@'] = $this->node( 'caption', array( 'is_photo' ) );
		$config['nodes']    = $nodes;
		update_post_meta( $site_id, 'syn_node_config', array_merge( $config, array( 'enc_parent' => 'media' ) ) );

		$this->assertSame( array(), ( new \Syndication_WP_XML_Client( $site_id ) )->get_posts() );
		$this->assertStringContainsString( 'Invalid XPath', $this->last_log_entry( $site_id )['message'] );
	}

	/**
	 * Every value of a multi-valued taxonomy node is kept and assigned.
	 */
	public function test_multiple_taxonomy_values_are_all_kept(): void {
		$site_id = $this->make_site(
			array(
				'title'    => $this->node( 'post_title', array( 'is_item' ) ),
				'category' => $this->node( 'post_tag', array( 'is_item', 'is_tax' ) ),
			)
		);
		$client  = new \Syndication_WP_XML_Client( $site_id );

		$posts = $client->get_posts();

		$this->assertSame( array( 'news', 'sport' ), $posts[0]['tax']['post_tag'] );

		$post_id = self::factory()->post->create();
		\Syndication_WP_XML_Client::save_tax( $post_id, $posts[0], get_post( $site_id ), 'WP_XML', $client );
		$this->assertEqualsCanonicalizing( array( 'news', 'sport' ), wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ) );

		\Syndication_WP_XML_Client::update_tax( $post_id, array( 'tax' => array( 'post_tag' => array( 'politics', 'sport' ) ) ), get_post( $site_id ), 'WP_XML', $client );
		$this->assertEqualsCanonicalizing( array( 'politics', 'sport' ), wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ) );
	}

	/**
	 * The settings form renders for a site with no saved node configuration.
	 */
	public function test_settings_render_without_a_saved_node_config(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		ob_start();
		\Syndication_WP_XML_Client::display_settings( get_post( $site_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="post_root"', $html );
	}
}
