<?php
/**
 * Tests the XML client's connection test.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlClientTestConnectionTest
 *
 * @covers Syndication_WP_XML_Client::test_connection
 */
class XmlClientTestConnectionTest extends WPIntegrationTestCase {

	/**
	 * Build a client for a site whose feed URL answers with the given body.
	 *
	 * @param string $body Response body.
	 * @return \Syndication_WP_XML_Client
	 */
	private function client_for( string $body ): \Syndication_WP_XML_Client {
		add_filter(
			'pre_http_request',
			static function () use ( $body ) {
				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}
		);

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_feed_url', 'http://feed.example/feed.xml' );

		return new \Syndication_WP_XML_Client( $site_id );
	}

	/**
	 * The test passes for a body that parses as XML.
	 */
	public function test_succeeds_for_valid_xml(): void {
		$client = $this->client_for( '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Item</title></item></channel></rss>' );

		$this->assertTrue( $client->test_connection() );
	}

	/**
	 * The test fails for a body that doesn't parse as XML.
	 */
	public function test_fails_for_invalid_xml(): void {
		$client = $this->client_for( '<html><body>Not XML<br></body>' );

		$this->assertFalse( $client->test_connection() );
	}
}
