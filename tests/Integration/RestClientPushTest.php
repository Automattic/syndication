<?php
/**
 * Tests what the WordPress.com REST client sends, and how it reads the responses.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use WP_Error;
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
	 * @var array|WP_Error
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
	 * An HTML error page, as a proxy or load balancer would return.
	 *
	 * @return array
	 */
	private static function html_error(): array {
		return self::response( 500, '<html><body>Internal Server Error</body></html>' );
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

	/**
	 * Categories and tags are sent without a trailing comma.
	 */
	public function test_new_post_sends_terms_without_trailing_comma(): void {
		$post_id = self::factory()->post->create( array( 'tags_input' => array( 'News', 'Sport' ) ) );

		$this->client->new_post( $post_id );

		$this->assertSame( 'News,Sport', $this->requests[0]['args']['body']['tags'] );
	}

	/**
	 * A created post returns its remote ID.
	 */
	public function test_new_post_returns_remote_id_on_success(): void {
		$this->assertSame( 999, $this->client->new_post( self::factory()->post->create() ) );
	}

	/**
	 * Responses that are not a created post.
	 *
	 * @return array<string, array{0: array, 1: string}>
	 */
	public function data_failed_responses(): array {
		return array(
			'HTML error page'         => array( self::html_error(), 'Unexpected response from WordPress.com (HTTP status 500).' ),
			'JSON error'              => array(
				self::json_response(
					403,
					array(
						'error'   => 'unauthorized',
						'message' => 'User cannot edit posts',
					)
				),
				'User cannot edit posts',
			),
			'error status, JSON body' => array( self::json_response( 503, array( 'ID' => 999 ) ), 'Unexpected response from WordPress.com (HTTP status 503).' ),
			'success, not JSON'       => array( self::response( 200, 'OK' ), 'Unexpected response from WordPress.com (HTTP status 200).' ),
		);
	}

	/**
	 * Creating a post reports any failed response as a WP_Error.
	 *
	 * @dataProvider data_failed_responses
	 *
	 * @param array  $response Intercepted response.
	 * @param string $message  Expected error message.
	 */
	public function test_new_post_returns_wp_error_on_failed_response( array $response, string $message ): void {
		$this->response = $response;

		$result = $this->client->new_post( self::factory()->post->create() );

		$this->assert_wp_error( $result, 'rest-push-new-fail', $message );
	}

	/**
	 * A successful response with no post ID is not a created post.
	 */
	public function test_new_post_returns_wp_error_when_response_has_no_id(): void {
		$this->response = self::json_response( 200, array( 'found' => 0 ) );

		$result = $this->client->new_post( self::factory()->post->create() );

		$this->assert_wp_error( $result, 'rest-push-new-fail', 'WordPress.com did not return the ID of the new post.' );
	}

	/**
	 * Editing a post reports any failed response as a WP_Error.
	 *
	 * @dataProvider data_failed_responses
	 *
	 * @param array  $response Intercepted response.
	 * @param string $message  Expected error message.
	 */
	public function test_edit_post_returns_wp_error_on_failed_response( array $response, string $message ): void {
		$this->response = $response;

		$result = $this->client->edit_post( self::factory()->post->create(), 999 );

		$this->assert_wp_error( $result, 'rest-push-edit-fail', $message );
	}

	/**
	 * Deleting a post reports any failed response as a WP_Error.
	 *
	 * @dataProvider data_failed_responses
	 *
	 * @param array  $response Intercepted response.
	 * @param string $message  Expected error message.
	 */
	public function test_delete_post_returns_wp_error_on_failed_response( array $response, string $message ): void {
		$this->response = $response;

		$this->assert_wp_error( $this->client->delete_post( 999 ), 'rest-push-delete-fail', $message );
	}

	/**
	 * Edit and delete succeed on a successful JSON response.
	 */
	public function test_edit_and_delete_succeed_on_success(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame( $post_id, $this->client->edit_post( $post_id, 999 ) );
		$this->assertTrue( $this->client->delete_post( 999 ) );
	}

	/**
	 * Transport errors pass through unchanged.
	 */
	public function test_transport_error_is_returned(): void {
		$this->response = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );

		$this->assertSame( $this->response, $this->client->new_post( self::factory()->post->create() ) );
		$this->assertSame( $this->response, $this->client->delete_post( 999 ) );
	}

	/**
	 * The connection test fails on any failed response.
	 *
	 * @dataProvider data_failed_responses
	 *
	 * @param array $response Intercepted response.
	 */
	public function test_connection_fails_on_failed_response( array $response ): void {
		$this->response = $response;

		$this->assertFalse( $this->client->test_connection() );
	}

	/**
	 * The connection test passes on a successful JSON response.
	 */
	public function test_connection_succeeds_on_success(): void {
		$this->response = self::json_response( 200, array( 'ID' => 1 ) );

		$this->assertTrue( $this->client->test_connection() );
	}

	/**
	 * A post WordPress.com says is not there does not exist.
	 */
	public function test_is_post_exists_is_false_when_post_not_found(): void {
		$this->response = self::json_response(
			404,
			array(
				'error'   => 'unknown_post',
				'message' => 'Unknown post',
			)
		);

		$this->assertFalse( $this->client->is_post_exists( 999 ) );
	}

	/**
	 * A post WordPress.com returns exists.
	 */
	public function test_is_post_exists_is_true_when_post_found(): void {
		$this->assertTrue( $this->client->is_post_exists( 999 ) );
	}

	/**
	 * When the answer is unknown, assume the post exists.
	 *
	 * The caller treats a missing post as new and creates it again, so a
	 * timeout or outage must not read as "not found": that would duplicate the
	 * post on the remote site. The edit or delete that follows reports the error.
	 *
	 * @dataProvider data_indeterminate_responses
	 *
	 * @param array|WP_Error $response Intercepted response.
	 */
	public function test_is_post_exists_is_true_when_answer_is_unknown( $response ): void {
		$this->response = $response;

		$this->assertTrue( $this->client->is_post_exists( 999 ) );
	}

	/**
	 * Responses that do not say whether the post exists.
	 *
	 * @return array<string, array{0: array|WP_Error}>
	 */
	public function data_indeterminate_responses(): array {
		return array(
			'transport error' => array( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ),
			'HTML error page' => array( self::html_error() ),
			'JSON 503'        => array(
				self::json_response(
					503,
					array(
						'error'   => 'unavailable',
						'message' => 'Service unavailable',
					)
				),
			),
		);
	}

	/**
	 * Assert a WP_Error with the given code and message.
	 *
	 * @param mixed  $result  The value to check.
	 * @param string $code    Expected error code.
	 * @param string $message Expected message.
	 */
	private function assert_wp_error( $result, string $code, string $message ): void {
		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertSame( $message, $result->get_error_message() );
	}
}
