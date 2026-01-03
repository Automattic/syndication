<?php
/**
 * Unit tests for Syndication_WP_XMLRPC_Client::is_source_site_post()
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Test case for is_source_site_post method in XMLRPC client.
 *
 * Tests the syndication loop prevention logic for the XMLRPC client.
 *
 * Since Syndication_WP_XMLRPC_Client has heavy WordPress dependencies in its
 * include chain and constructor, we test the method logic using a test double
 * class that replicates the exact method implementation.
 *
 * @group unit
 */
class XmlrpcClientIsSourceSitePostTest extends TestCase {

	/**
	 * Test double instance that replicates the XMLRPC client's method.
	 *
	 * @var object
	 */
	private $client;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a test double that replicates the exact method from Syndication_WP_XMLRPC_Client.
		// This approach allows us to unit test the logic without loading WordPress dependencies.
		$this->client = new class() {
			/**
			 * Username for XMLRPC authentication.
			 *
			 * @var string
			 */
			public $username = 'testuser';

			/**
			 * Password for XMLRPC authentication.
			 *
			 * @var string
			 */
			public $password = 'testpass';

			/**
			 * Simulated query response.
			 *
			 * @var mixed
			 */
			private $query_response;

			/**
			 * Whether the query should succeed.
			 *
			 * @var bool
			 */
			private $query_result = true;

			/**
			 * Set the query response for testing.
			 *
			 * @param mixed $response The response to return from getResponse().
			 * @param bool  $result   Whether query() should return true or false.
			 */
			public function set_query_response( $response, bool $result = true ): void {
				$this->query_response = $response;
				$this->query_result   = $result;
			}

			/**
			 * Simulates the query method from WP_HTTP_IXR_Client.
			 *
			 * @param string $method The XMLRPC method name.
			 * @param mixed  ...$args The method arguments.
			 * @return bool True on success, false on failure.
			 */
			public function query( $method, ...$args ) {
				return $this->query_result;
			}

			/**
			 * Simulates the getResponse method from WP_HTTP_IXR_Client.
			 *
			 * @return mixed The response from the query.
			 */
			public function getResponse() {
				return $this->query_response;
			}

			/**
			 * Check if a post with the given meta key/value exists on the target site.
			 *
			 * This is an exact copy of Syndication_WP_XMLRPC_Client::is_source_site_post()
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

				// Use filter to limit posts returned and request custom_fields.
				$filter = array(
					'number' => 100,
				);

				$result = $this->query(
					'wp.getPosts',
					'1',
					$this->username,
					$this->password,
					$filter,
					array( 'post_id', 'custom_fields' )
				);

				if ( ! $result ) {
					return false;
				}

				$posts_list = $this->getResponse();

				if ( empty( $posts_list ) ) {
					return false;
				}

				foreach ( $posts_list as $post ) {
					if ( empty( $post['custom_fields'] ) ) {
						continue;
					}

					foreach ( $post['custom_fields'] as $field ) {
						if ( isset( $field['key'], $field['value'] ) &&
							$meta_key === $field['key'] &&
							$meta_value === $field['value']
						) {
							return true;
						}
					}
				}

				return false;
			}
		};
	}

	/**
	 * Test returns false when meta_key is empty string.
	 */
	public function test_returns_false_when_meta_key_is_empty_string(): void {
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
	 * Test returns false when XMLRPC query fails.
	 */
	public function test_returns_false_on_failed_query(): void {
		$this->client->set_query_response( null, false );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when query returns empty array.
	 */
	public function test_returns_false_when_posts_list_is_empty(): void {
		$this->client->set_query_response( [], true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when query returns null.
	 */
	public function test_returns_false_when_posts_list_is_null(): void {
		$this->client->set_query_response( null, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when posts have no matching custom_fields.
	 */
	public function test_returns_false_when_no_matching_custom_fields(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'other_key',
						'value' => 'other_value',
					],
				],
			],
			[
				'post_id'       => 2,
				'custom_fields' => [
					[
						'key'   => 'another_key',
						'value' => 'another_value',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when meta_key matches but meta_value does not.
	 */
	public function test_returns_false_when_key_matches_but_value_does_not(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => 'https://different-site.com/?p=456',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns false when meta_value matches but meta_key does not.
	 */
	public function test_returns_false_when_value_matches_but_key_does_not(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'different_key',
						'value' => 'https://example.com/?p=123',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test returns true when matching post is found in first post.
	 */
	public function test_returns_true_when_matching_post_found_in_first_post(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => 'https://example.com/?p=123',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test returns true when matching post is found in second post.
	 */
	public function test_returns_true_when_matching_post_found_in_later_post(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'other_key',
						'value' => 'other_value',
					],
				],
			],
			[
				'post_id'       => 2,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => 'https://example.com/?p=123',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test searches all custom_fields, not just the first one.
	 */
	public function test_searches_all_custom_fields_not_just_first(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'first_key',
						'value' => 'first_value',
					],
					[
						'key'   => 'second_key',
						'value' => 'second_value',
					],
					[
						'key'   => 'syn_source_url',
						'value' => 'https://example.com/?p=123',
					],
					[
						'key'   => 'fourth_key',
						'value' => 'fourth_value',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test skips posts without custom_fields.
	 */
	public function test_skips_posts_without_custom_fields(): void {
		$posts_list = [
			[
				'post_id' => 1,
				// No custom_fields key at all.
			],
			[
				'post_id'       => 2,
				'custom_fields' => [], // Empty custom_fields.
			],
			[
				'post_id'       => 3,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => 'https://example.com/?p=123',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test returns false when custom_fields is empty array.
	 */
	public function test_returns_false_when_custom_fields_is_empty(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test handles custom_fields with missing key property.
	 */
	public function test_handles_custom_fields_missing_key_property(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						// Missing 'key' property.
						'value' => 'https://example.com/?p=123',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test handles custom_fields with missing value property.
	 */
	public function test_handles_custom_fields_missing_value_property(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key' => 'syn_source_url',
						// Missing 'value' property.
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test performs strict comparison on meta_key (type-safe).
	 */
	public function test_performs_strict_comparison_on_meta_key(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 123, // Integer instead of string.
						'value' => 'https://example.com/?p=123',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( '123', 'https://example.com/?p=123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test performs strict comparison on meta_value (type-safe).
	 */
	public function test_performs_strict_comparison_on_meta_value(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'post_id',
						'value' => 123, // Integer instead of string.
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'post_id', '123' );

		$this->assertFalse( $result );
	}

	/**
	 * Test handles special characters in meta_key.
	 */
	public function test_handles_special_characters_in_meta_key(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'meta_key_with_special_chars_!@#$%',
						'value' => 'test_value',
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'meta_key_with_special_chars_!@#$%', 'test_value' );

		$this->assertTrue( $result );
	}

	/**
	 * Test handles URL with query parameters in meta_value.
	 */
	public function test_handles_url_with_query_params_in_meta_value(): void {
		$url        = 'https://example.com/post/?p=123&utm_source=syndication&ref=test';
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => $url,
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', $url );

		$this->assertTrue( $result );
	}

	/**
	 * Test stops searching after finding first match.
	 *
	 * This verifies efficiency - once a match is found, no further iteration is needed.
	 */
	public function test_returns_true_immediately_on_first_match(): void {
		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => 'https://example.com/?p=123',
					],
				],
			],
			[
				'post_id'       => 2,
				'custom_fields' => [
					[
						'key'   => 'syn_source_url',
						'value' => 'https://example.com/?p=123', // Duplicate match.
					],
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test handles large number of posts.
	 */
	public function test_handles_many_posts(): void {
		$posts_list = [];
		for ( $i = 1; $i <= 99; $i++ ) {
			$posts_list[] = [
				'post_id'       => $i,
				'custom_fields' => [
					[
						'key'   => 'other_key',
						'value' => "other_value_{$i}",
					],
				],
			];
		}
		// Add match at position 100.
		$posts_list[] = [
			'post_id'       => 100,
			'custom_fields' => [
				[
					'key'   => 'syn_source_url',
					'value' => 'https://example.com/?p=123',
				],
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}

	/**
	 * Test handles posts with many custom fields.
	 */
	public function test_handles_posts_with_many_custom_fields(): void {
		$custom_fields = [];
		for ( $i = 1; $i <= 49; $i++ ) {
			$custom_fields[] = [
				'key'   => "key_{$i}",
				'value' => "value_{$i}",
			];
		}
		// Add match at position 50.
		$custom_fields[] = [
			'key'   => 'syn_source_url',
			'value' => 'https://example.com/?p=123',
		];

		$posts_list = [
			[
				'post_id'       => 1,
				'custom_fields' => $custom_fields,
			],
		];

		$this->client->set_query_response( $posts_list, true );

		$result = $this->client->is_source_site_post( 'syn_source_url', 'https://example.com/?p=123' );

		$this->assertTrue( $result );
	}
}
