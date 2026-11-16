<?php
/**
 * Tests the REST client's syndication loop check.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RestClientIsSourceSitePostTest
 *
 * Runs the real REST client with the WordPress.com API faked at the HTTP layer.
 *
 * @covers Syndication_WP_REST_Client::is_source_site_post
 */
class RestClientIsSourceSitePostTest extends WPIntegrationTestCase {

	/**
	 * Requests made, as URL and arguments.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private $requests = array();

	/**
	 * What the fake API answers with: a response array or a WP_Error.
	 *
	 * @var array|\WP_Error
	 */
	private $response;

	/**
	 * The client under test.
	 *
	 * @var \Syndication_WP_REST_Client
	 */
	private $client;

	/**
	 * Set up a site, its client and the fake API.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->requests = array();
		$this->respond_with( array( 'found' => 0 ) );

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_site_token', push_syndicate_encrypt( 'token-123' ) );
		update_post_meta( $site_id, 'syn_site_id', '42' );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				return $this->response;
			},
			10,
			3
		);

		$this->client = new \Syndication_WP_REST_Client( $site_id );
	}

	/**
	 * Make the fake API answer with the given body.
	 *
	 * @param mixed $body Value to JSON-encode, or a raw string.
	 */
	private function respond_with( $body ): void {
		$this->response = array(
			'headers'  => array(),
			'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Without both a meta key and value there is nothing to look for, so no request is made.
	 */
	public function test_needs_key_and_value(): void {
		$this->assertFalse( $this->client->is_source_site_post( '', 'abc123' ) );
		$this->assertFalse( $this->client->is_source_site_post( 'post_uniqueid', '' ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * The request queries the site's posts by meta, encoded, with the site's token.
	 */
	public function test_queries_by_meta_with_token(): void {
		$this->client->is_source_site_post( 'post_uniqueid', 'a&b c' );

		$this->assertSame(
			'https://public-api.wordpress.com/rest/v1/sites/42/posts/?meta_key=post_uniqueid&meta_value=a%26b%20c',
			$this->requests[0]['url']
		);
		$this->assertSame( 'Bearer token-123', $this->requests[0]['args']['headers']['authorization'] );
	}

	/**
	 * A match is reported when the API finds posts.
	 */
	public function test_true_when_posts_found(): void {
		$this->respond_with( array( 'found' => 2 ) );

		$this->assertTrue( $this->client->is_source_site_post( 'post_uniqueid', 'abc123' ) );
	}

	/**
	 * No match when the API finds nothing, errors, or answers with something unreadable.
	 *
	 * @dataProvider data_not_found_responses
	 *
	 * @param mixed $body Response body.
	 */
	public function test_false_when_nothing_usable_found( $body ): void {
		$this->respond_with( $body );

		$this->assertFalse( $this->client->is_source_site_post( 'post_uniqueid', 'abc123' ) );
	}

	/**
	 * Response bodies that mean "not found".
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function data_not_found_responses(): array {
		return array(
			'none found'   => array( array( 'found' => 0 ) ),
			'API error'    => array(
				array(
					'error' => 'unauthorized',
					'found' => 1,
				),
			),
			'invalid JSON' => array( 'not json' ),
			'empty body'   => array( '' ),
		);
	}

	/**
	 * A failed request counts as not found.
	 */
	public function test_false_when_request_fails(): void {
		$this->response = new \WP_Error( 'http_request_failed', 'Failed.' );

		$this->assertFalse( $this->client->is_source_site_post( 'post_uniqueid', 'abc123' ) );
	}
}
