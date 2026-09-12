<?php
/**
 * Tests that the REST client never disables TLS certificate verification.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RestClientTlsVerificationTest
 *
 * Regression guard for credentialed requests to public-api.wordpress.com being
 * sent with 'sslverify' => false. Every one of these requests carries the OAuth
 * bearer token, so disabling verification let an on-path attacker impersonate
 * the API, harvest the token and feed forged responses into the push pipeline.
 *
 * These tests assert on the arguments WP_Http has finished assembling, captured
 * at 'pre_http_request'. That is the effective value on the wire after WP merges
 * its defaults, so an absent 'sslverify' key correctly reads as true, and the
 * test fails if anything reintroduces the flag or a filter turns it off.
 *
 * @covers Syndication_WP_REST_Client
 */
class RestClientTlsVerificationTest extends WPIntegrationTestCase {

	/**
	 * Arguments captured from each intercepted outbound request.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private $requests = array();

	/**
	 * The syndication site post the client reads its credentials from.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * The client under test.
	 *
	 * @var \Syndication_WP_REST_Client
	 */
	private $client;

	/**
	 * Set up the client and intercept outbound HTTP before it leaves WP.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rest-client.php';

		$this->requests = array();

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $parsed_args,
				);

				// Short-circuit with a 200 so no request leaves the test runner. The body
				// carries an ID because new_post() reads $response->ID off a success.
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'ID' => 999 ) ),
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

		$this->site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $this->site_id, 'syn_site_id', '12345' );
		update_post_meta( $this->site_id, 'syn_site_token', push_syndicate_encrypt( 'test-token' ) );

		$this->client = new \Syndication_WP_REST_Client( $this->site_id );
	}

	/**
	 * Every public method that reaches the WordPress.com REST API.
	 *
	 * @return array<string, array{0: callable}> Test cases keyed by method name.
	 */
	public function data_api_methods(): array {
		return array(
			'is_source_site_post' => array(
				function ( $client, $post_id ) {
					return $client->is_source_site_post( 'syn_source_url', 'https://example.com/' );
				},
			),
			'new_post'            => array(
				function ( $client, $post_id ) {
					return $client->new_post( $post_id );
				},
			),
			'edit_post'           => array(
				function ( $client, $post_id ) {
					return $client->edit_post( $post_id, 999 );
				},
			),
			'delete_post'         => array(
				function ( $client, $post_id ) {
					return $client->delete_post( 999 );
				},
			),
			'test_connection'     => array(
				function ( $client, $post_id ) {
					return $client->test_connection();
				},
			),
			'is_post_exists'      => array(
				function ( $client, $post_id ) {
					return $client->is_post_exists( 999 );
				},
			),
		);
	}

	/**
	 * Each API method must send its request with certificate verification enabled.
	 *
	 * @dataProvider data_api_methods
	 *
	 * @param callable $invoke Invokes the method under test.
	 */
	public function test_request_verifies_the_certificate( callable $invoke ): void {
		$post_id = self::factory()->post->create();

		$invoke( $this->client, $post_id );

		$this->assertNotEmpty( $this->requests, 'Expected the method to make an outbound request.' );

		foreach ( $this->requests as $request ) {
			$this->assertNotFalse(
				$request['args']['sslverify'],
				sprintf(
					'Request to %s was sent with certificate verification disabled, exposing the bearer token to on-path attackers.',
					$request['url']
				)
			);
		}
	}

	/**
	 * The requests carry credentials, which is why verification matters here.
	 *
	 * @dataProvider data_api_methods
	 *
	 * @param callable $invoke Invokes the method under test.
	 */
	public function test_request_is_credentialed_and_uses_https( callable $invoke ): void {
		$post_id = self::factory()->post->create();

		$invoke( $this->client, $post_id );

		foreach ( $this->requests as $request ) {
			$this->assertStringStartsWith( 'https://', $request['url'] );
			$this->assertSame(
				'Bearer test-token',
				$request['args']['headers']['authorization'],
				'Request should carry the OAuth bearer token.'
			);
		}
	}

	/**
	 * Guard against the interception above silently passing on an unverified default.
	 *
	 * If WP ever stopped defaulting sslverify to true, the assertions above would
	 * still pass while real requests went unverified. This pins the default.
	 */
	public function test_wp_defaults_ssl_verification_to_true(): void {
		wp_remote_get( 'https://public-api.wordpress.com/rest/v1/me/' );

		$this->assertTrue(
			$this->requests[0]['args']['sslverify'],
			'WP_Http should default sslverify to true when the argument is omitted.'
		);
	}
}
