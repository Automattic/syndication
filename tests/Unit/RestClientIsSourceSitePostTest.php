<?php
/**
 * Unit tests for Syndication_WP_REST_Client::is_source_site_post()
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Test case for is_source_site_post method in REST client.
 *
 * Tests the syndication loop prevention logic for the REST API client.
 *
 * Since Syndication_WP_REST_Client has heavy WordPress dependencies in its
 * include chain and constructor, we test the method logic using a test double
 * class that replicates the exact method implementation.
 *
 * @group unit
 */
class RestClientIsSourceSitePostTest extends TestCase {

	/**
	 * Test double instance that replicates the REST client's method.
	 *
	 * @var object
	 */
	private $client;

	/**
	 * Captured URL from wp_remote_get call.
	 *
	 * @var string|null
	 */
	private $captured_url;

	/**
	 * Captured args from wp_remote_get call.
	 *
	 * @var array|null
	 */
	private $captured_args;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->captured_url  = null;
		$this->captured_args = null;

		// Create a test double that replicates the exact method from Syndication_WP_REST_Client.
		// This approach allows us to unit test the logic without loading WordPress dependencies.
		$this->client = new class() {
			/**
			 * Blog ID for the target site.
			 *
			 * @var string
			 */
			public $blog_ID = '12345';

			/**
			 * Access token for API authentication.
			 *
			 * @var string
			 */
			public $access_token = 'test-token';

			/**
			 * Timeout for HTTP requests.
			 *
			 * @var int
			 */
			public $timeout = 45;

			/**
			 * User agent string for HTTP requests.
			 *
			 * @var string
			 */
			public $useragent = 'push-syndication-plugin';

			/**
			 * Check if a post with the given meta key/value exists on the target site.
			 *
			 * This is an exact copy of Syndication_WP_REST_Client::is_source_site_post()
			 * to enable unit testing without WordPress dependencies.
			 *
			 * @param string $meta_key   The meta key to search for.
			 * @param string $meta_value The meta value to match.
			 * @return bool True if post exists on target site, false otherwise.
			 */
			public function is_source_site_post( $meta_key = '', $meta_value = '' ) {

				// If meta key or value are empty.
				if ( empty( $meta_key ) || empty( $meta_value ) ) {
					return false;
				}

				// Get posts from the target website matching the meta key and value.
				$url = sprintf(
					'https://public-api.wordpress.com/rest/v1/sites/%s/posts/?meta_key=%s&meta_value=%s',
					$this->blog_ID,
					rawurlencode( $meta_key ),
					rawurlencode( $meta_value )
				);

				$response = wp_remote_get(
					$url,
					array(
						'timeout'    => $this->timeout,
						'user-agent' => $this->useragent,
						'sslverify'  => false,
						'headers'    => array(
							'authorization' => 'Bearer ' . $this->access_token,
						),
					)
				);

				if ( is_wp_error( $response ) ) {
					return false;
				}

				$response = json_decode( wp_remote_retrieve_body( $response ) );

				if ( empty( $response->error ) && ! empty( $response->found ) && $response->found > 0 ) {
					return true;
				}

				return false;
			}
		};
	}

	/**
	 * Test returns false when meta_key is empty string.
	 */
	public function test_returns_false_when_meta_key_is_empty_string(): void {
		// No need to stub wp_remote_get since it should not be called.
		$result = $this->client->is_source_site_post( '', 'some_value' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when meta_value is empty string.
	 */
	public function test_returns_false_when_meta_value_is_empty_string(): void {
		$result = $this->client->is_source_site_post( 'some_key', '' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when both meta_key and meta_value are empty.
	 */
	public function test_returns_false_when_both_params_are_empty(): void {
		$result = $this->client->is_source_site_post( '', '' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when no parameters are provided.
	 */
	public function test_returns_false_when_no_params_provided(): void {
		$result = $this->client->is_source_site_post();

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when wp_remote_get returns WP_Error.
	 */
	public function test_returns_false_on_wp_error_response(): void {
		// Create a mock object to represent the WP_Error response.
		$error_response = new \stdClass();

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( $error_response );

		Functions\expect( 'is_wp_error' )
			->once()
			->with( $error_response )
			->andReturn( true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when response has no posts found.
	 */
	public function test_returns_false_when_no_posts_found(): void {
		$response_body = (object) [
			'found' => 0,
			'posts' => [],
		];

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when response contains an error.
	 */
	public function test_returns_false_when_response_contains_error(): void {
		$response_body = (object) [
			'error'   => 'invalid_blog',
			'message' => 'Unknown blog',
		];

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns true when matching post is found.
	 */
	public function test_returns_true_when_matching_post_found(): void {
		$response_body = (object) [
			'found' => 1,
			'posts' => [
				(object) [
					'ID'    => 456,
					'title' => 'Test Post',
				],
			],
		];

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test returns true when multiple matching posts are found.
	 */
	public function test_returns_true_when_multiple_posts_found(): void {
		$response_body = (object) [
			'found' => 3,
			'posts' => [
				(object) [ 'ID' => 1 ],
				(object) [ 'ID' => 2 ],
				(object) [ 'ID' => 3 ],
			],
		];

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test URL encodes special characters in meta_key.
	 */
	public function test_url_encodes_special_characters_in_meta_key(): void {
		$response_body = (object) [ 'found' => 0 ];

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::on(
					function ( $url ) {
						// Verify the URL contains properly encoded meta_key.
						// 'meta key with spaces' should be encoded as 'meta%20key%20with%20spaces'.
						return strpos( $url, 'meta_key=meta%20key%20with%20spaces' ) !== false;
					}
				),
				\Mockery::any()
			)
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$this->client->is_source_site_post( 'meta key with spaces', 'value' );
	}

	/**
	 * Test URL encodes special characters in meta_value.
	 */
	public function test_url_encodes_special_characters_in_meta_value(): void {
		$response_body = (object) [ 'found' => 0 ];

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::on(
					function ( $url ) {
						// Verify the URL contains properly encoded meta_value.
						// 'https://example.com/?p=123&test=value' should have ? encoded as %3F and & as %26.
						return strpos( $url, 'meta_value=https%3A%2F%2Fexample.com%2F%3Fp%3D123%26test%3Dvalue' ) !== false;
					}
				),
				\Mockery::any()
			)
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123&test=value' );
	}

	/**
	 * Test constructs correct API URL with blog_ID.
	 */
	public function test_constructs_correct_api_url(): void {
		$this->client->blog_ID = '67890';
		$response_body         = (object) [ 'found' => 0 ];

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::on(
					function ( $url ) {
						return strpos( $url, 'https://public-api.wordpress.com/rest/v1/sites/67890/posts/' ) === 0;
					}
				),
				\Mockery::any()
			)
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$this->client->is_source_site_post( 'test_key', 'test_value' );
	}

	/**
	 * Test passes correct authorization header with access token.
	 */
	public function test_passes_authorization_header(): void {
		$this->client->access_token = 'my-secret-token';
		$response_body              = (object) [ 'found' => 0 ];

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on(
					function ( $args ) {
						return isset( $args['headers']['authorization'] )
							&& 'Bearer my-secret-token' === $args['headers']['authorization'];
					}
				)
			)
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$this->client->is_source_site_post( 'test_key', 'test_value' );
	}

	/**
	 * Test passes correct timeout setting.
	 */
	public function test_passes_timeout_setting(): void {
		$this->client->timeout = 60;
		$response_body         = (object) [ 'found' => 0 ];

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on(
					function ( $args ) {
						return isset( $args['timeout'] ) && 60 === $args['timeout'];
					}
				)
			)
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$this->client->is_source_site_post( 'test_key', 'test_value' );
	}

	/**
	 * Test passes user-agent setting.
	 */
	public function test_passes_user_agent_setting(): void {
		$this->client->useragent = 'custom-user-agent';
		$response_body           = (object) [ 'found' => 0 ];

		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::on(
					function ( $args ) {
						return isset( $args['user-agent'] ) && 'custom-user-agent' === $args['user-agent'];
					}
				)
			)
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$this->client->is_source_site_post( 'test_key', 'test_value' );
	}

	/**
	 * Test returns false when response body is invalid JSON.
	 */
	public function test_returns_false_on_invalid_json_response(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => 'not valid json' ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( 'not valid json' );

		$result = $this->client->is_source_site_post( 'test_key', 'test_value' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when response body is empty.
	 */
	public function test_returns_false_on_empty_response_body(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => '' ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( '' );

		$result = $this->client->is_source_site_post( 'test_key', 'test_value' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when found is null.
	 */
	public function test_returns_false_when_found_is_null(): void {
		$response_body = (object) [
			'found' => null,
			'posts' => [],
		];

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$result = $this->client->is_source_site_post( 'test_key', 'test_value' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when found property is missing.
	 */
	public function test_returns_false_when_found_is_missing(): void {
		$response_body = (object) [
			'posts' => [],
		];

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( [ 'body' => wp_json_encode( $response_body ) ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( wp_json_encode( $response_body ) );

		$result = $this->client->is_source_site_post( 'test_key', 'test_value' );

		$this->assertFalse( $result );
	}
}
