<?php
/**
 * Tests that each RSS pull reads the feed afresh.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RssClientFeedCacheTest
 *
 * @covers Syndication_WP_RSS_Client
 */
class RssClientFeedCacheTest extends WPIntegrationTestCase {

	/**
	 * The items the feed currently serves.
	 *
	 * @var string
	 */
	private $items = '';

	/**
	 * Serve the feed from memory through the HTTP API.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rss-client.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';

		add_filter(
			'pre_http_request',
			function () {
				return array(
					'headers'  => array( 'content-type' => 'application/rss+xml' ),
					'body'     => '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>' . $this->items . '</channel></rss>',
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
	 * Pull the site's feed with a new client, as each scheduled or manual pull does.
	 *
	 * @param int $site_id The site post ID.
	 * @return array The pulled posts.
	 */
	private function pull( int $site_id ): array {
		$client = new \Syndication_WP_RSS_Client( $site_id );

		// Fetch through the HTTP API, so the filter above can serve the feed.
		$client->registry->register( 'File', 'WP_SimplePie_File', true );

		return $client->get_posts();
	}

	/**
	 * An item published between two pulls is seen by the second.
	 */
	public function test_second_pull_sees_an_item_added_since_the_first(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_feed_url', 'http://cache.example.invalid/feed/' );

		$this->items = '<item><guid>a</guid><title>First</title></item>';
		$this->assertCount( 1, $this->pull( $site_id ) );

		$this->items .= '<item><guid>b</guid><title>Second</title></item>';
		$this->assertCount( 2, $this->pull( $site_id ), 'The second pull should not be served the cached feed.' );
	}
}
