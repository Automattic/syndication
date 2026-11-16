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
 * The feeds are local files, which SimplePie reads without HTTP.
 *
 * @covers Syndication_WP_RSS_Client::test_connection
 */
class RssClientTestConnectionTest extends WPIntegrationTestCase {

	/**
	 * Feed file written by the test.
	 *
	 * @var string
	 */
	private $file = '';

	/**
	 * Remove the feed file.
	 */
	public function tear_down(): void {
		if ( $this->file ) {
			unlink( $this->file );
		}
		parent::tear_down();
	}

	/**
	 * Build a client for a site whose feed has the given contents.
	 *
	 * @param string $contents Feed contents.
	 * @return \Syndication_WP_RSS_Client
	 */
	private function client_for( string $contents ): \Syndication_WP_RSS_Client {
		$this->file = wp_tempnam( 'syndication-feed' );
		file_put_contents( $this->file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_feed_url', $this->file );

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
	 * The test fails for something that isn't a feed.
	 */
	public function test_fails_for_an_invalid_feed(): void {
		$client = $this->client_for( 'Not a feed.' );

		$this->assertFalse( $client->test_connection() );
	}
}
