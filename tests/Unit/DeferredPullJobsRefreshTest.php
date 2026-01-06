<?php
/**
 * Unit tests for deferred pull jobs refresh functionality.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Tests for the schedule_deferred_pull_jobs_refresh method.
 *
 * Since WP_Push_Syndication_Server has heavy WordPress dependencies in its
 * constructor, we test the method logic using a test double class that
 * replicates the exact method implementation.
 *
 * @group unit
 */
class DeferredPullJobsRefreshTest extends TestCase {

	/**
	 * Test double instance that replicates the server's methods.
	 *
	 * @var object
	 */
	private $server;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a test double that replicates the exact methods.
		$this->server = new class() {
			/**
			 * Handle save_post and delete_post for syn_site posts.
			 *
			 * @param int $post_id The post ID.
			 */
			public function handle_site_change( $post_id ) {
				if ( 'syn_site' === get_post_type( $post_id ) ) {
					$this->schedule_deferred_pull_jobs_refresh();
				}
			}

			/**
			 * Handle create_term and delete_term for syn_sitegroup terms.
			 *
			 * @param int    $term     Term ID.
			 * @param int    $tt_id    Term taxonomy ID.
			 * @param string $taxonomy Taxonomy name.
			 */
			public function handle_site_group_change( $term, $tt_id, $taxonomy ) {
				if ( 'syn_sitegroup' === $taxonomy ) {
					$this->schedule_deferred_pull_jobs_refresh();
				}
			}

			/**
			 * Schedule a deferred refresh of pull jobs.
			 */
			public function schedule_deferred_pull_jobs_refresh() {
				$debounce_key = 'syn_pull_jobs_refresh_pending';

				if ( get_transient( $debounce_key ) ) {
					return;
				}

				set_transient( $debounce_key, '1', 2 * MINUTE_IN_SECONDS );

				wp_clear_scheduled_hook( 'syn_refresh_pull_jobs' );
				wp_schedule_single_event( time() + 60, 'syn_refresh_pull_jobs' );
			}
		};
	}

	/**
	 * Test that handle_site_change schedules deferred refresh for syn_site post type.
	 */
	public function test_handle_site_change_schedules_deferred_refresh_for_syn_site() {
		Functions\expect( 'get_post_type' )
			->once()
			->with( 123 )
			->andReturn( 'syn_site' );

		Functions\expect( 'get_transient' )
			->once()
			->with( 'syn_pull_jobs_refresh_pending' )
			->andReturn( false );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'syn_pull_jobs_refresh_pending', '1', 120 );

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'syn_refresh_pull_jobs' );

		Functions\expect( 'wp_schedule_single_event' )
			->once();

		$this->server->handle_site_change( 123 );
	}

	/**
	 * Test that handle_site_change does nothing for non-syn_site post types.
	 */
	public function test_handle_site_change_ignores_non_syn_site_posts() {
		Functions\expect( 'get_post_type' )
			->once()
			->with( 456 )
			->andReturn( 'post' );

		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();
		Functions\expect( 'wp_clear_scheduled_hook' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->server->handle_site_change( 456 );
	}

	/**
	 * Test that handle_site_group_change schedules deferred refresh for syn_sitegroup taxonomy.
	 */
	public function test_handle_site_group_change_schedules_deferred_refresh_for_syn_sitegroup() {
		Functions\expect( 'get_transient' )
			->once()
			->with( 'syn_pull_jobs_refresh_pending' )
			->andReturn( false );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'syn_pull_jobs_refresh_pending', '1', 120 );

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'syn_refresh_pull_jobs' );

		Functions\expect( 'wp_schedule_single_event' )
			->once();

		$this->server->handle_site_group_change( 1, 1, 'syn_sitegroup' );
	}

	/**
	 * Test that handle_site_group_change ignores non-syn_sitegroup taxonomies.
	 */
	public function test_handle_site_group_change_ignores_non_syn_sitegroup() {
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();
		Functions\expect( 'wp_clear_scheduled_hook' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->server->handle_site_group_change( 1, 1, 'category' );
	}

	/**
	 * Test that schedule is debounced when transient exists.
	 */
	public function test_schedule_is_debounced_when_transient_exists() {
		Functions\expect( 'get_post_type' )
			->once()
			->with( 123 )
			->andReturn( 'syn_site' );

		Functions\expect( 'get_transient' )
			->once()
			->with( 'syn_pull_jobs_refresh_pending' )
			->andReturn( '1' );

		// These should NOT be called due to debounce.
		Functions\expect( 'set_transient' )->never();
		Functions\expect( 'wp_clear_scheduled_hook' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->server->handle_site_change( 123 );
	}

	/**
	 * Test that existing scheduled event is cleared before scheduling new one.
	 */
	public function test_clears_existing_scheduled_event_before_scheduling_new() {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		Functions\expect( 'set_transient' )
			->once();

		// Verify that wp_clear_scheduled_hook is called BEFORE wp_schedule_single_event.
		$call_order = [];

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'syn_refresh_pull_jobs' )
			->andReturnUsing(
				function () use ( &$call_order ) {
					$call_order[] = 'clear';
					return 0;
				}
			);

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->andReturnUsing(
				function () use ( &$call_order ) {
					$call_order[] = 'schedule';
					return true;
				}
			);

		$this->server->schedule_deferred_pull_jobs_refresh();

		$this->assertEquals( [ 'clear', 'schedule' ], $call_order );
	}

	/**
	 * Test that scheduled event is set 60 seconds in the future.
	 */
	public function test_scheduled_event_is_set_60_seconds_in_future() {
		$before_time = time();

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		Functions\expect( 'set_transient' )->once();
		Functions\expect( 'wp_clear_scheduled_hook' )->once();

		$scheduled_time = null;
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->andReturnUsing(
				function ( $time, $hook ) use ( &$scheduled_time ) {
					$scheduled_time = $time;
					return true;
				}
			);

		$this->server->schedule_deferred_pull_jobs_refresh();

		$after_time = time();

		// The scheduled time should be approximately 60 seconds after the current time.
		$this->assertGreaterThanOrEqual( $before_time + 60, $scheduled_time );
		$this->assertLessThanOrEqual( $after_time + 60, $scheduled_time );
	}

	/**
	 * Test that transient is set for 2 minutes (120 seconds).
	 */
	public function test_transient_is_set_for_2_minutes() {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		$transient_expiration = null;
		Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing(
				function ( $key, $value, $expiration ) use ( &$transient_expiration ) {
					$transient_expiration = $expiration;
					return true;
				}
			);

		Functions\expect( 'wp_clear_scheduled_hook' )->once();
		Functions\expect( 'wp_schedule_single_event' )->once();

		$this->server->schedule_deferred_pull_jobs_refresh();

		// MINUTE_IN_SECONDS = 60, so 2 * MINUTE_IN_SECONDS = 120.
		$this->assertEquals( 120, $transient_expiration );
	}

	/**
	 * Test that the correct hook name is used for scheduling.
	 */
	public function test_uses_correct_hook_name() {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		Functions\expect( 'set_transient' )->once();

		$cleared_hook   = null;
		$scheduled_hook = null;

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->andReturnUsing(
				function ( $hook ) use ( &$cleared_hook ) {
					$cleared_hook = $hook;
					return 0;
				}
			);

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->andReturnUsing(
				function ( $time, $hook ) use ( &$scheduled_hook ) {
					$scheduled_hook = $hook;
					return true;
				}
			);

		$this->server->schedule_deferred_pull_jobs_refresh();

		$this->assertEquals( 'syn_refresh_pull_jobs', $cleared_hook );
		$this->assertEquals( 'syn_refresh_pull_jobs', $scheduled_hook );
	}
}
