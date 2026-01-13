<?php
/**
 * Unit tests for PullService.
 *
 * @package Automattic\Syndication\Tests\Unit\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application\Services;

use Automattic\Syndication\Application\DTO\PullResult;
use Automattic\Syndication\Application\Services\PullService;
use Automattic\Syndication\Domain\Contracts\PullTransportInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Mockery;
use WP_Post;

/**
 * Test case for PullService.
 *
 * @group unit
 * @covers \Automattic\Syndication\Application\Services\PullService
 */
class PullServiceTest extends TestCase {

	/**
	 * PullService instance.
	 *
	 * @var PullService
	 */
	private PullService $service;

	/**
	 * Mock transport factory.
	 *
	 * @var TransportFactoryInterface&\Mockery\MockInterface
	 */
	private TransportFactoryInterface $factory;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->factory = Mockery::mock( TransportFactoryInterface::class );
		$this->service = new PullService( $this->factory );
	}

	/**
	 * Test pull_from_site returns failure when site not found.
	 */
	public function test_pull_from_site_returns_failure_when_site_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'invalid_site', $result->error_code );
	}

	/**
	 * Test pull_from_site returns failure when wrong post type.
	 */
	public function test_pull_from_site_returns_failure_when_wrong_post_type(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $post );

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'invalid_site', $result->error_code );
	}

	/**
	 * Test pull_from_site returns skipped when site disabled.
	 */
	public function test_pull_from_site_returns_skipped_when_site_disabled(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'off',
					default            => '',
				};
			}
		);

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_skipped() );
		$this->assertStringContainsString( 'disabled', $result->message );
	}

	/**
	 * Test pull_from_site returns failure when transport creation fails.
	 */
	public function test_pull_from_site_returns_failure_when_no_transport(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'on',
					default            => '',
				};
			}
		);

		$this->factory
			->shouldReceive( 'create_pull_transport' )
			->with( 123 )
			->andReturn( null );

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'invalid_transport', $result->error_code );
	}

	/**
	 * Test pull_from_site returns success with zero posts.
	 */
	public function test_pull_from_site_returns_success_with_no_posts(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		$transport = Mockery::mock( PullTransportInterface::class );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'on',
					default            => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );

		$this->factory
			->shouldReceive( 'create_pull_transport' )
			->with( 123 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'pull' )
			->andReturn( array() );

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 0, $result->created );
		$this->assertEquals( 0, $result->updated );
	}

	/**
	 * Test pull_from_site creates new posts.
	 *
	 * Note: This test requires global $wpdb, which is only available
	 * in integration tests. This test verifies the service setup and
	 * transport interaction, with actual post creation tested in integration.
	 */
	public function test_pull_from_site_creates_new_posts(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		$transport = Mockery::mock( PullTransportInterface::class );

		$pulled_posts = array(
			array(
				'post_guid'    => 'guid-123',
				'post_title'   => 'Test Post',
				'post_content' => 'Test content.',
				'post_status'  => 'publish',
			),
		);

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'on',
					default            => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_insert_post' )->justReturn( 456 );
		Functions\when( 'do_action' )->justReturn( null );

		Functions\when( 'wp_defer_term_counting' )->justReturn( null );
		Functions\when( 'wp_defer_comment_counting' )->justReturn( null );
		Functions\when( 'wp_suspend_cache_invalidation' )->justReturn( true );

		$this->factory
			->shouldReceive( 'create_pull_transport' )
			->with( 123 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'pull' )
			->andReturn( $pulled_posts );

		// Mock global $wpdb for the find_post_by_guid call.
		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Mocking for unit test.
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->postmeta  = 'wp_postmeta';
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( "SELECT post_id FROM wp_postmeta WHERE meta_key = 'syn_post_guid' AND meta_value = 'guid-123' LIMIT 1" );
		$wpdb->shouldReceive( 'get_var' )
			->andReturn( null ); // No existing post.

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 1, $result->created );
	}

	/**
	 * Test pull_from_site handles posts without GUID.
	 */
	public function test_pull_from_site_handles_posts_without_guid(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		$transport = Mockery::mock( PullTransportInterface::class );

		$pulled_posts = array(
			array(
				'post_title'   => 'No GUID Post',
				'post_content' => 'Content without GUID.',
			),
		);

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'on',
					default            => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_defer_term_counting' )->justReturn( null );
		Functions\when( 'wp_defer_comment_counting' )->justReturn( null );
		Functions\when( 'wp_suspend_cache_invalidation' )->justReturn( true );

		$this->factory
			->shouldReceive( 'create_pull_transport' )
			->with( 123 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'pull' )
			->andReturn( $pulled_posts );

		$result = $this->service->pull_from_site( 123 );

		// Should have partial success with error for missing GUID.
		$this->assertTrue( $result->is_partial() );
		$this->assertNotEmpty( $result->errors );
	}

	/**
	 * Test set_update_existing returns self for chaining.
	 */
	public function test_set_update_existing_returns_self(): void {
		$result = $this->service->set_update_existing( false );

		$this->assertSame( $this->service, $result );
	}

	/**
	 * Test pull_from_sites calls pull_from_site for each site.
	 */
	public function test_pull_from_sites_processes_multiple_sites(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		$transport = Mockery::mock( PullTransportInterface::class );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'on',
					default            => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_defer_term_counting' )->justReturn( null );
		Functions\when( 'wp_defer_comment_counting' )->justReturn( null );
		Functions\when( 'wp_suspend_cache_invalidation' )->justReturn( true );

		$this->factory
			->shouldReceive( 'create_pull_transport' )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'pull' )
			->andReturn( array() );

		$results = $this->service->pull_from_sites( array( 1, 2, 3 ) );

		$this->assertCount( 3, $results );
		$this->assertArrayHasKey( 1, $results );
		$this->assertArrayHasKey( 2, $results );
		$this->assertArrayHasKey( 3, $results );
	}

	/**
	 * Test pull_from_site handles insert error.
	 */
	public function test_pull_from_site_handles_insert_error(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		$transport = Mockery::mock( PullTransportInterface::class );

		$pulled_posts = array(
			array(
				'post_guid'    => 'guid-123',
				'post_title'   => 'Test Post',
				'post_content' => 'Test content.',
			),
		);

		$wp_error = new \WP_Error( 'insert_failed', 'Could not insert post.' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			function ( $post_id, $key, $single ) {
				return match ( $key ) {
					'syn_site_enabled' => 'on',
					default            => '',
				};
			}
		);
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_insert_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->alias(
			function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_defer_term_counting' )->justReturn( null );
		Functions\when( 'wp_defer_comment_counting' )->justReturn( null );
		Functions\when( 'wp_suspend_cache_invalidation' )->justReturn( true );

		$this->factory
			->shouldReceive( 'create_pull_transport' )
			->with( 123 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'pull' )
			->andReturn( $pulled_posts );

		// Mock global $wpdb for the find_post_by_guid call.
		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Mocking for unit test.
		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->postmeta  = 'wp_postmeta';
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( "SELECT post_id FROM wp_postmeta WHERE meta_key = 'syn_post_guid' AND meta_value = 'guid-123' LIMIT 1" );
		$wpdb->shouldReceive( 'get_var' )
			->andReturn( null ); // No existing post.

		$result = $this->service->pull_from_site( 123 );

		$this->assertTrue( $result->is_partial() );
		$this->assertNotEmpty( $result->errors );
	}
}
