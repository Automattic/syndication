<?php
/**
 * Tests for the RSS client.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RssClientTest
 *
 * Feeds are handed to SimplePie as raw data, so the pull tests exercise the
 * real parsing and mapping without making an HTTP request.
 *
 * @covers Syndication_WP_RSS_Client
 */
class RssClientTest extends WPIntegrationTestCase {

	/**
	 * Load the client.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rss-client.php';
	}

	/**
	 * Build a client that parses the given items instead of fetching a feed.
	 *
	 * @param string $items The <item> elements of the feed.
	 * @return \Syndication_WP_RSS_Client
	 */
	private function make_client( string $items ) {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_default_post_type', 'post' );
		update_post_meta( $site_id, 'syn_default_post_status', 'publish' );
		update_post_meta( $site_id, 'syn_default_cat_status', 'yes' );

		$client           = new \Syndication_WP_RSS_Client( $site_id );
		$client->feed_url = null;
		$client->set_raw_data( '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>' . $items . '</channel></rss>' );

		return $client;
	}

	/**
	 * An item with no categories is pulled without a warning.
	 */
	public function test_item_without_categories_has_none(): void {
		$posts = $this->make_client( '<item><guid>a</guid><title>A</title><pubDate>Mon, 15 Jan 2024 12:34:56 +0000</pubDate></item>' )->get_posts();

		$this->assertSame( array(), $posts[0]['post_category'] );
		$this->assertSame( array(), $posts[0]['tags_input'] );
	}
}
