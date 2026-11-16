<?php
/**
 * Tests the RSS client's connection test.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RssClientTestConnectionTest
 *
 * Feeds are fetched through the WordPress HTTP API, so each test serves its
 * feed from a `pre_http_request` filter.
 *
 * @covers Syndication_WP_RSS_Client::test_connection
 */
class RssClientTestConnectionTest extends WPIntegrationTestCase {

	/**
	 * Address the test site's feed is served from.
	 */
	const FEED_URL = 'http://feed.example.invalid/feed/';

	/**
	 * Body served for the feed.
	 *
	 * @var string
	 */
	private $body = '';

	/**
	 * Serve the feed from memory, and refuse every other request.
	 */
	public function set_up(): void {
		parent::set_up();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( self::FEED_URL !== $url ) {
					return new \WP_Error( 'http_request_failed', 'Unexpected request.' );
				}

				return array(
					'headers'  => array( 'content-type' => 'application/rss+xml' ),
					'body'     => $this->body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);
	}

	/**
	 * Build a client for a site whose feed has the given contents.
	 *
	 * @param string $contents Feed contents.
	 * @return \Syndication_WP_RSS_Client
	 */
	private function client_for( string $contents ): \Syndication_WP_RSS_Client {
		$this->body = $contents;

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_feed_url', self::FEED_URL );

		return new \Syndication_WP_RSS_Client( $site_id );
	}

	/**
	 * The test passes for a feed that parses.
	 */
	public function test_succeeds_for_a_valid_feed(): void {
		$client = $this->client_for( '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title><link>http://feed.example/</link><item><title>Item</title></item></channel></rss>' );

		$this->assertTrue( $client->test_connection() );
	}

	/**
	 * The test fails for a response that isn't a feed.
	 */
	public function test_fails_for_an_invalid_feed(): void {
		$client = $this->client_for( 'Not a feed.' );

		$this->assertFalse( $client->test_connection() );
	}
}
