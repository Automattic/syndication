<?php
/**
 * Unit tests for PushService.
 *
 * @package Automattic\Syndication\Tests\Unit\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application\Services;

use Automattic\Syndication\Application\DTO\PushResult;
use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Domain\Contracts\PushTransportInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Mockery;
use WP_Error;
use WP_Post;

/**
 * Test case for PushService.
 *
 * @group unit
 * @covers \Automattic\Syndication\Application\Services\PushService
 */
class PushServiceTest extends TestCase {

	/**
	 * PushService instance.
	 *
	 * @var PushService
	 */
	private PushService $service;

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
		$this->service = new PushService( $this->factory );
	}

	/**
	 * Test push_to_sites returns empty when post not found.
	 */
	public function test_push_to_sites_returns_empty_when_post_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$results = $this->service->push_to_sites( 123, array( 1, 2 ) );

		$this->assertEmpty( $results );
	}

	/**
	 * Test push_to_sites returns empty when lock cannot be acquired.
	 */
	public function test_push_to_sites_returns_empty_when_locked(): void {
		$post = Mockery::mock( WP_Post::class );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_transient' )->justReturn( 'locked' );

		$results = $this->service->push_to_sites( 123, array( 1, 2 ) );

		$this->assertEmpty( $results );
	}

	/**
	 * Test push_to_site returns failure when transport creation fails.
	 */
	public function test_push_to_site_returns_failure_when_no_transport(): void {
		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( null );

		Functions\when( 'get_post_meta' )->justReturn( array() );

		$result = $this->service->push_to_site( 123, 456 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'invalid_transport', $result->error_code );
	}

	/**
	 * Test push_to_site creates new post successfully.
	 */
	public function test_push_to_site_creates_new_post_successfully(): void {
		$transport = Mockery::mock( PushTransportInterface::class );
		$site      = Mockery::mock( WP_Post::class );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'push' )
			->with( 123 )
			->andReturn( 789 );

		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( $site );
		Functions\when( 'do_action' )->justReturn( null );

		$result = $this->service->push_to_site( 123, 456 );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 789, $result->remote_id );
		$this->assertEquals( 'created', $result->action );
	}

	/**
	 * Test push_to_site updates existing post successfully.
	 */
	public function test_push_to_site_updates_existing_post_successfully(): void {
		$transport = Mockery::mock( PushTransportInterface::class );
		$site      = Mockery::mock( WP_Post::class );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'update' )
			->with( 123, 789 )
			->andReturn( 789 );

		// Existing slave state with success.
		$slave_states = array(
			'success' => array( 456 => 789 ),
		);

		Functions\when( 'get_post_meta' )->justReturn( $slave_states );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( $site );
		Functions\when( 'do_action' )->justReturn( null );

		$result = $this->service->push_to_site( 123, 456 );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 789, $result->remote_id );
		$this->assertEquals( 'updated', $result->action );
	}

	/**
	 * Test push_to_site handles skipped (filtered) push.
	 */
	public function test_push_to_site_handles_skipped_push(): void {
		$transport = Mockery::mock( PushTransportInterface::class );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'push' )
			->with( 123 )
			->andReturn( true );

		Functions\when( 'get_post_meta' )->justReturn( array() );

		$result = $this->service->push_to_site( 123, 456 );

		$this->assertTrue( $result->is_skipped() );
		$this->assertStringContainsString( 'Filtered', $result->message );
	}

	/**
	 * Test push_to_site handles push error.
	 */
	public function test_push_to_site_handles_push_error(): void {
		$transport = Mockery::mock( PushTransportInterface::class );
		$error     = new WP_Error( 'connection_failed', 'Could not connect.' );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'push' )
			->with( 123 )
			->andReturn( $error );

		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$result = $this->service->push_to_site( 123, 456 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'connection_failed', $result->error_code );
		$this->assertEquals( 'Could not connect.', $result->message );
	}

	/**
	 * Test delete_from_site returns failure when no transport.
	 */
	public function test_delete_from_site_returns_failure_when_no_transport(): void {
		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( null );

		Functions\when( 'get_post_meta' )->justReturn( array() );

		$result = $this->service->delete_from_site( 123, 456 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'invalid_transport', $result->error_code );
	}

	/**
	 * Test delete_from_site returns failure when no remote post.
	 */
	public function test_delete_from_site_returns_failure_when_no_remote_post(): void {
		$transport = Mockery::mock( PushTransportInterface::class );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		Functions\when( 'get_post_meta' )->justReturn( array() );

		$result = $this->service->delete_from_site( 123, 456 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'no_remote_post', $result->error_code );
	}

	/**
	 * Test delete_from_site succeeds.
	 */
	public function test_delete_from_site_succeeds(): void {
		$transport = Mockery::mock( PushTransportInterface::class );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'delete' )
			->with( 789 )
			->andReturn( true );

		$slave_states = array(
			'success' => array( 456 => 789 ),
		);

		Functions\when( 'get_post_meta' )->justReturn( $slave_states );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$result = $this->service->delete_from_site( 123, 456 );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 789, $result->remote_id );
		$this->assertEquals( 'deleted', $result->action );
	}

	/**
	 * Test delete_from_site handles error.
	 */
	public function test_delete_from_site_handles_error(): void {
		$transport = Mockery::mock( PushTransportInterface::class );
		$error     = new WP_Error( 'delete_failed', 'Could not delete.' );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		$transport
			->shouldReceive( 'delete' )
			->with( 789 )
			->andReturn( $error );

		$slave_states = array(
			'success' => array( 456 => 789 ),
		);

		Functions\when( 'get_post_meta' )->justReturn( $slave_states );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$result = $this->service->delete_from_site( 123, 456 );

		$this->assertTrue( $result->is_failure() );
		$this->assertEquals( 'delete_failed', $result->error_code );
	}

	/**
	 * Test push_to_site handles retry after new-error state.
	 */
	public function test_push_to_site_retries_after_new_error(): void {
		$transport = Mockery::mock( PushTransportInterface::class );
		$site      = Mockery::mock( WP_Post::class );

		$this->factory
			->shouldReceive( 'create_push_transport' )
			->with( 456 )
			->andReturn( $transport );

		// Previous push failed with new-error state.
		$slave_states = array(
			'new-error' => array( 456 => new WP_Error( 'previous_error', 'Previous failure' ) ),
		);

		$transport
			->shouldReceive( 'push' )
			->with( 123 )
			->andReturn( 789 );

		Functions\when( 'get_post_meta' )->justReturn( $slave_states );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( $site );
		Functions\when( 'do_action' )->justReturn( null );

		$result = $this->service->push_to_site( 123, 456 );

		// Should retry as new push and succeed.
		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 'created', $result->action );
	}
}
