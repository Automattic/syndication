<?php
/**
 * Tests what the WordPress.com REST client sends, and how it reads the responses.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RestClientPushTest
 *
 * Outbound requests are intercepted at 'pre_http_request', which answers with
 * whatever $this->response holds and records the request that was sent.
 *
 * @covers Syndication_WP_REST_Client
 */
class RestClientPushTest extends WPIntegrationTestCase {

	/**
	 * Requests captured at 'pre_http_request'.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private $requests = array();

	/**
	 * The response, or WP_Error, the intercepted request returns.
	 *
	 * @var array
	 */
	private $response;

	/**
	 * The client under test.
	 *
	 * @var \Syndication_WP_REST_Client
	 */
	private $client;

	/**
	 * Set up the client and intercept outbound HTTP.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rest-client.php';

		$this->requests = array();
		$this->response = self::json_response( 200, array( 'ID' => 999 ) );

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $parsed_args,
				);

				return $this->response;
			},
			10,
			3
		);

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_site_id', '12345' );
		update_post_meta( $site_id, 'syn_site_token', push_syndicate_encrypt( 'test-token' ) );

		$this->client = new \Syndication_WP_REST_Client( $site_id );
	}

	/**
	 * Build an HTTP response array.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Raw response body.
	 * @return array
	 */
	private static function response( int $code, string $body ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Build an HTTP response with a JSON body.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $data Data to encode.
	 * @return array
	 */
	private static function json_response( int $code, array $data ): array {
		return self::response( $code, wp_json_encode( $data ) );
	}

	/**
	 * A scheduled post on a site east of UTC keeps its moment in time.
	 */
	public function test_new_post_sends_gmt_date_as_utc_on_non_utc_site(): void {
		update_option( 'timezone_string', 'Europe/Berlin' );

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'future',
				'post_date'     => '2030-06-15 12:00:00',
				'post_date_gmt' => '2030-06-15 10:00:00',
			)
		);

		$this->client->new_post( $post_id );

		$this->assertSame( '2030-06-15T10:00:00+00:00', $this->requests[0]['args']['body']['date'] );
	}

	/**
	 * Edits send the same UTC date.
	 */
	public function test_edit_post_sends_gmt_date_as_utc_on_non_utc_site(): void {
		update_option( 'timezone_string', 'America/New_York' );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2024-01-15 05:00:00',
				'post_date_gmt' => '2024-01-15 10:00:00',
			)
		);

		$this->client->edit_post( $post_id, 999 );

		$this->assertSame( '2024-01-15T10:00:00+00:00', $this->requests[0]['args']['body']['date'] );
	}
}
