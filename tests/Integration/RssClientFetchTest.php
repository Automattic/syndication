<?php
/**
 * Tests that the RSS client fetches feeds through the WordPress HTTP API.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RssClientFetchTest
 *
 * Going through the HTTP API is what lets proxy settings, the pull user
 * agent and the HTTP API filters apply, so each test intercepts the request
 * with pre_http_request. A fetch that bypasses the HTTP API never reaches
 * that filter, and the unresolvable host makes it fail instead.
 *
 * @covers Syndication_WP_RSS_Client
 */
class RssClientFetchTest extends WPIntegrationTestCase {

	/**
	 * The requests the HTTP API was asked to make, as URL and arguments.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private $requests = array();

	/**
	 * Intercept every HTTP request and answer with a one-item feed.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rss-client.php';

		$this->requests = array();

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				return array(
					'headers'  => array( 'content-type' => 'application/rss+xml' ),
					'body'     => '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>'
						. '<item><guid>a</guid><title>Fetched</title></item></channel></rss>',
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
	 * Create an RSS site pointed at the given feed URL.
	 *
	 * @param string $feed_url The feed URL.
	 * @return int The site post ID.
	 */
	private function make_site( string $feed_url ): int {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_feed_url', $feed_url );
		update_post_meta( $site_id, 'syn_transport_type', 'WP_RSS' );
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_default_post_type', 'post' );
		update_post_meta( $site_id, 'syn_default_post_status', 'publish' );

		return $site_id;
	}

	/**
	 * The feed is requested through the HTTP API.
	 */
	public function test_feed_is_fetched_through_the_http_api(): void {
		$client = new \Syndication_WP_RSS_Client( $this->make_site( 'http://feed.example.invalid/feed/' ) );

		$posts = $client->get_posts();

		$this->assertSame( 'http://feed.example.invalid/feed/', $this->requests[0]['url'] );
		$this->assertCount( 1, $posts );
		$this->assertSame( 'Fetched', $posts[0]['post_title'] );
	}

	/**
	 * A pull sends the user agent from the syn_pull_user_agent filter.
	 */
	public function test_pull_sends_the_filtered_user_agent(): void {
		global $push_syndication_server;

		add_filter(
			'syn_pull_user_agent',
			static function () {
				return 'Syndication Test Agent';
			}
		);

		$site_id = $this->make_site( 'http://agent.example.invalid/feed/' );

		$push_syndication_server->pull_content( array( get_post( $site_id ) ) );

		$this->assertNotEmpty( $this->requests, 'The pull should have made a request.' );
		$this->assertSame( 'Syndication Test Agent', $this->requests[0]['args']['user-agent'] );
	}

	/**
	 * Feeds on private addresses are still fetched, as the XML client's are.
	 */
	public function test_feed_on_a_private_address_is_not_rejected(): void {
		$client = new \Syndication_WP_RSS_Client( $this->make_site( 'http://10.0.0.1/feed/' ) );

		$client->get_posts();

		$this->assertFalse( $this->requests[0]['args']['reject_unsafe_urls'] );
	}
}
