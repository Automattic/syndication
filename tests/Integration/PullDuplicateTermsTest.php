<?php
/**
 * Tests that one pull doesn't create the same term twice.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rss-client.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-xml-client.php';

/**
 * The real RSS client, parsing a feed held in memory instead of fetching one.
 */
class RawFeedRssClient extends \Syndication_WP_RSS_Client {

	/**
	 * The feed every instance parses.
	 *
	 * @var string
	 */
	public static $feed = '';

	/**
	 * Set up the client, then point it at the in-memory feed.
	 *
	 * @param int $site_id The site post ID.
	 */
	public function __construct( $site_id ) {
		parent::__construct( $site_id );

		$this->feed_url = null;
		$this->set_raw_data( self::$feed );
	}
}

// The client factory builds class names as Syndication_{transport}_Client.
if ( ! class_exists( 'Syndication_RawFeedRSS_Client', false ) ) {
	class_alias( RawFeedRssClient::class, 'Syndication_RawFeedRSS_Client' );
}

/**
 * Class PullDuplicateTermsTest
 *
 * Runs whole pulls through pull_content(), where two items share a category
 * the site doesn't have yet.
 *
 * @covers WP_Push_Syndication_Server::pull_content
 */
class PullDuplicateTermsTest extends WPIntegrationTestCase {

	/**
	 * Create an enabled site for the given transport.
	 *
	 * @param string $transport_type The site's transport type.
	 * @return \WP_Post The site post.
	 */
	private function make_site( string $transport_type ): \WP_Post {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', $transport_type );
		update_post_meta( $site_id, 'syn_feed_url', 'http://example.com/feed.xml' );
		update_post_meta( $site_id, 'syn_default_post_type', 'post' );
		update_post_meta( $site_id, 'syn_default_post_status', 'publish' );

		return get_post( $site_id );
	}

	/**
	 * Count the categories with the given name.
	 *
	 * @param string $name The category name.
	 * @return int The number of categories with that name.
	 */
	private function count_categories( string $name ): int {
		return count(
			get_terms(
				array(
					'taxonomy'   => 'category',
					'name'       => $name,
					'hide_empty' => false,
				)
			)
		);
	}

	/**
	 * Two RSS items sharing a new category create it once.
	 */
	public function test_rss_items_sharing_a_new_category_create_it_once(): void {
		global $push_syndication_server;

		RawFeedRssClient::$feed = '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>'
			. '<item><guid>rss-a</guid><title>A</title><pubDate>Mon, 15 Jan 2024 12:00:00 +0000</pubDate><category>Shared RSS Category</category></item>'
			. '<item><guid>rss-b</guid><title>B</title><pubDate>Mon, 15 Jan 2024 13:00:00 +0000</pubDate><category>Shared RSS Category</category></item>'
			. '</channel></rss>';

		$site = $this->make_site( 'RawFeedRSS' );
		update_post_meta( $site->ID, 'syn_default_cat_status', 'yes' );

		$push_syndication_server->pull_content( array( $site ) );

		$this->assertSame( 1, $this->count_categories( 'Shared RSS Category' ) );
	}

	/**
	 * Two XML items sharing a new category through a taxonomy node create it once.
	 */
	public function test_xml_items_sharing_a_new_category_create_it_once(): void {
		global $push_syndication_server;

		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => '<rss><channel>'
						. '<item><guid>xml-a</guid><title>A</title><category>Shared XML Category</category></item>'
						. '<item><guid>xml-b</guid><title>B</title><category>Shared XML Category</category></item>'
						. '</channel></rss>',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			}
		);

		$site = $this->make_site( 'WP_XML' );
		update_post_meta( $site->ID, 'syn_id_field', 'syn_guid' );
		update_post_meta(
			$site->ID,
			'syn_node_config',
			array(
				'namespace'  => '',
				'post_root'  => '/rss/channel/item',
				'enc_parent' => '',
				'categories' => array(),
				'nodes'      => array(
					'title'    => array( $this->node( 'post_title' ) ),
					'guid'     => array( $this->node( 'syn_guid', array( 'is_meta' => 1 ) ) ),
					'category' => array( $this->node( 'category', array( 'is_tax' => 1 ) ) ),
				),
			)
		);

		$push_syndication_server->pull_content( array( $site ) );

		$this->assertSame( 2, count( get_posts( array( 'category_name' => 'shared-xml-category' ) ) ), 'Both items should be pulled into the category.' );
		$this->assertSame( 1, $this->count_categories( 'Shared XML Category' ) );
	}

	/**
	 * Build an item node mapping.
	 *
	 * @param string $field Where the value is stored.
	 * @param array  $flags Flags to set, such as is_meta or is_tax.
	 * @return array The node mapping.
	 */
	private function node( string $field, array $flags = array() ): array {
		return array_merge(
			array(
				'field'    => $field,
				'is_item'  => 1,
				'is_meta'  => 0,
				'is_tax'   => 0,
				'is_photo' => 0,
			),
			$flags
		);
	}
}
