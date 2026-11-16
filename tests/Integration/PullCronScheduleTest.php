<?php
/**
 * Tests for scheduling and running pull cron jobs.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use ReflectionClass;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

require_once __DIR__ . '/Syndication_Recording_Client.php';

/**
 * Class PullCronScheduleTest
 *
 * @covers WP_Push_Syndication_Server::schedule_pull_content
 * @covers WP_Push_Syndication_Server::pull_content
 */
class PullCronScheduleTest extends WPIntegrationTestCase {

	/**
	 * Create an enabled site.
	 *
	 * @param string $transport_type Transport type.
	 * @return int Site ID.
	 */
	private function create_site( string $transport_type = 'Recording' ): int {
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $site_id, 'syn_transport_type', $transport_type );
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );

		return $site_id;
	}

	/**
	 * Arguments of every scheduled syn_pull_content event.
	 *
	 * @return array
	 */
	private function scheduled_pull_args(): array {
		$args = array();
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( $hooks['syn_pull_content'] ?? array() as $event ) {
				$args[] = $event['args'];
			}
		}
		return $args;
	}

	/**
	 * Pull jobs are keyed by site ID, one per site.
	 */
	public function test_schedules_one_job_per_site_keyed_by_id(): void {
		global $push_syndication_server;

		$site_a = $this->create_site();
		$site_b = $this->create_site();

		$push_syndication_server->schedule_pull_content( array( get_post( $site_a ), get_post( $site_b ) ) );

		$this->assertEqualsCanonicalizing(
			array( array( array( $site_a ) ), array( array( $site_b ) ) ),
			$this->scheduled_pull_args()
		);
	}

	/**
	 * Rescheduling after a site changes replaces its job rather than adding another.
	 */
	public function test_rescheduling_after_a_site_changes_leaves_one_job_per_site(): void {
		global $push_syndication_server;

		$site_a = $this->create_site();
		$site_b = $this->create_site();

		$push_syndication_server->schedule_pull_content( array( get_post( $site_a ), get_post( $site_b ) ) );

		wp_update_post(
			array(
				'ID'         => $site_a,
				'post_title' => 'Renamed site',
			)
		);

		$push_syndication_server->schedule_pull_content( array( get_post( $site_a ), get_post( $site_b ) ) );

		$this->assertCount( 2, $this->scheduled_pull_args() );
	}

	/**
	 * Jobs scheduled with whole site objects, before 2.2.3, are cleared on reschedule.
	 */
	public function test_rescheduling_clears_jobs_scheduled_with_site_objects(): void {
		global $push_syndication_server;

		$site_id = $this->create_site();
		wp_schedule_event( time() - 1, 'syn_pull_time_interval', 'syn_pull_content', array( array( get_post( $site_id ) ) ) );

		wp_update_post(
			array(
				'ID'         => $site_id,
				'post_title' => 'Renamed site',
			)
		);

		$push_syndication_server->schedule_pull_content( array( get_post( $site_id ) ) );

		$this->assertSame( array( array( array( $site_id ) ) ), $this->scheduled_pull_args() );
	}

	/**
	 * A pull job given a site ID pulls that site.
	 */
	public function test_pull_content_accepts_site_ids(): void {
		global $push_syndication_server;

		$site_id = $this->create_site();

		$push_syndication_server->pull_content( array( $site_id ) );

		$this->assertNotEmpty( get_post_meta( $site_id, 'syn_last_pull_time', true ) );
	}

	/**
	 * A pull job scheduled before 2.2.3, with a site object, still pulls that site.
	 */
	public function test_pull_content_still_accepts_site_objects(): void {
		global $push_syndication_server;

		$site_id = $this->create_site();

		$push_syndication_server->pull_content( array( get_post( $site_id ) ) );

		$this->assertNotEmpty( get_post_meta( $site_id, 'syn_last_pull_time', true ) );
	}

	/**
	 * A pull job for a site that no longer exists does nothing.
	 */
	public function test_pull_content_skips_a_deleted_site(): void {
		global $push_syndication_server;

		$site_id = $this->create_site();
		wp_delete_post( $site_id, true );

		$push_syndication_server->pull_content( array( $site_id ) );

		$this->assertSame( '', get_post_meta( $site_id, 'syn_last_pull_time', true ) );
	}

	/**
	 * A post that fails to insert is not treated as post ID 1.
	 */
	public function test_pull_content_ignores_posts_that_fail_to_insert(): void {
		global $push_syndication_server;

		$site_id = $this->create_site( 'Mock' );

		// Empty title, content and excerpt make wp_insert_post() return a WP_Error.
		\Syndication_Mock_Client::set_posts(
			array(
				array(
					'post_guid'    => 'https://example.com/?p=1',
					'post_type'    => 'post',
					'post_title'   => '',
					'post_content' => '',
					'post_excerpt' => '',
				),
			)
		);

		$cleaned = array();
		add_action(
			'clean_post_cache',
			function ( $post_id ) use ( &$cleaned ) {
				$cleaned[] = $post_id;
			}
		);

		$push_syndication_server->pull_content( array( $site_id ) );

		\Syndication_Mock_Client::set_posts( array() );

		$this->assertNotContains( 1, $cleaned );
	}

	/**
	 * A site with an unknown transport is skipped, and the rest are still pulled.
	 */
	public function test_pull_content_skips_a_site_with_an_unknown_transport(): void {
		global $push_syndication_server;

		$broken_id = $this->create_site( 'No_Such_Transport' );
		$site_id   = $this->create_site();

		$push_syndication_server->pull_content( array( $broken_id, $site_id ) );

		$this->assertNotEmpty( get_post_meta( $site_id, 'syn_last_pull_time', true ) );
	}

	/**
	 * Upgrading from before 2.2.3 drops the stored site objects and refreshes the jobs.
	 */
	public function test_upgrade_clears_stored_site_objects_and_refreshes_jobs(): void {
		global $push_syndication_server;

		update_option( 'syn_old_pull_sites', array( get_post( $this->create_site() ) ) );
		delete_transient( 'syn_pull_jobs_refresh_pending' );

		$reflection = new ReflectionClass( $push_syndication_server );
		$version    = $reflection->getProperty( 'version' );
		$version->setAccessible( true );
		$version->setValue( $push_syndication_server, '2.2.1' );
		$upgrade = $reflection->getMethod( 'upgrade' );
		$upgrade->setAccessible( true );
		$upgrade->invoke( $push_syndication_server );

		$this->assertFalse( get_option( 'syn_old_pull_sites' ) );
		$this->assertNotFalse( wp_next_scheduled( 'syn_refresh_pull_jobs' ) );
	}
}
