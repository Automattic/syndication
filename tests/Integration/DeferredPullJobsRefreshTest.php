<?php
/**
 * Tests for the deferred refresh of pull jobs when sites or sitegroups change.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class DeferredPullJobsRefreshTest
 *
 * @covers WP_Push_Syndication_Server::handle_site_change
 * @covers WP_Push_Syndication_Server::handle_site_group_change
 */
class DeferredPullJobsRefreshTest extends WPIntegrationTestCase {

	/**
	 * Start each test with no refresh pending.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_transient( 'syn_pull_jobs_refresh_pending' );
		wp_clear_scheduled_hook( 'syn_refresh_pull_jobs' );
	}

	/**
	 * Saving a site schedules a refresh a minute later instead of refreshing at once.
	 */
	public function test_saving_a_site_schedules_a_refresh(): void {
		$before = time();

		self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$scheduled = wp_next_scheduled( 'syn_refresh_pull_jobs' );
		$this->assertGreaterThanOrEqual( $before + 60, $scheduled );
		$this->assertLessThanOrEqual( time() + 60, $scheduled );
		$this->assertSame( '1', get_transient( 'syn_pull_jobs_refresh_pending' ) );
	}

	/**
	 * Deleting a site schedules a refresh.
	 */
	public function test_deleting_a_site_schedules_a_refresh(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		delete_transient( 'syn_pull_jobs_refresh_pending' );
		wp_clear_scheduled_hook( 'syn_refresh_pull_jobs' );

		wp_delete_post( $site_id, true );

		$this->assertNotFalse( wp_next_scheduled( 'syn_refresh_pull_jobs' ) );
	}

	/**
	 * Saving any other post type schedules nothing.
	 */
	public function test_saving_a_post_schedules_nothing(): void {
		self::factory()->post->create();

		$this->assertFalse( wp_next_scheduled( 'syn_refresh_pull_jobs' ) );
		$this->assertFalse( get_transient( 'syn_pull_jobs_refresh_pending' ) );
	}

	/**
	 * Creating a sitegroup schedules a refresh.
	 */
	public function test_creating_a_sitegroup_schedules_a_refresh(): void {
		self::factory()->term->create( array( 'taxonomy' => 'syn_sitegroup' ) );

		$this->assertNotFalse( wp_next_scheduled( 'syn_refresh_pull_jobs' ) );
	}

	/**
	 * Creating a term in any other taxonomy schedules nothing.
	 */
	public function test_creating_a_category_schedules_nothing(): void {
		self::factory()->category->create();

		$this->assertFalse( wp_next_scheduled( 'syn_refresh_pull_jobs' ) );
	}

	/**
	 * Further changes while a refresh is pending don't schedule another.
	 */
	public function test_changes_while_a_refresh_is_pending_are_debounced(): void {
		self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		wp_clear_scheduled_hook( 'syn_refresh_pull_jobs' );

		self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$this->assertFalse( wp_next_scheduled( 'syn_refresh_pull_jobs' ) );
	}
}
