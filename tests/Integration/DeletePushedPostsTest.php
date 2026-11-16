<?php
/**
 * Tests for deleting the remote copies of a pushed post.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use ReflectionClass;
use Syndication_Recording_Client;
use WP_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

require_once __DIR__ . '/Syndication_Recording_Client.php';

/**
 * Class DeletePushedPostsTest
 *
 * @covers WP_Push_Syndication_Server::pre_schedule_delete_content
 * @covers WP_Push_Syndication_Server::schedule_delete_content
 * @covers WP_Push_Syndication_Server::delete_content
 */
class DeletePushedPostsTest extends WPIntegrationTestCase {

	use RestoresServerState;

	/**
	 * Remote post ID of the pushed copy.
	 */
	const EXT_ID = 55;

	/**
	 * Site the post was pushed to.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * Pushed post.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up a post that has been pushed to one site.
	 */
	public function set_up(): void {
		parent::set_up();

		Syndication_Recording_Client::reset();

		$this->site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->site_id, 'syn_transport_type', 'Recording' );
		update_post_meta( $this->site_id, 'syn_site_enabled', 'on' );

		$this->post_id = self::factory()->post->create();
		update_post_meta(
			$this->post_id,
			'_syn_slave_post_states',
			array( 'success' => array( $this->site_id => array( 'ext_ID' => self::EXT_ID ) ) )
		);
	}

	/**
	 * Set the "Delete pushed posts" setting.
	 *
	 * @param string $value 'on' or 'off'.
	 */
	private function set_delete_pushed_posts( string $value ): void {
		global $push_syndication_server;
		$push_syndication_server->push_syndicate_settings['delete_pushed_posts'] = $value;
	}

	/**
	 * Run, and unschedule, every scheduled event for a hook.
	 *
	 * @param string $hook Hook name.
	 * @return int Number of events run.
	 */
	private function run_scheduled( string $hook ): int {
		$run = 0;
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[ $hook ] ?? array() as $event ) {
				wp_unschedule_event( $timestamp, $hook, $event['args'] );
				do_action_ref_array( $hook, $event['args'] );
				++$run;
			}
		}
		return $run;
	}

	/**
	 * Remote IDs passed to delete_post().
	 *
	 * @return array
	 */
	private function deleted_ext_ids(): array {
		return array_map(
			function ( $call ) {
				return $call[2][0];
			},
			Syndication_Recording_Client::calls_to( 'delete_post' )
		);
	}

	/**
	 * Trashing a post with the setting off leaves its remote copies alone.
	 */
	public function test_trash_with_setting_off_keeps_remote_copies(): void {
		$this->set_delete_pushed_posts( 'off' );

		wp_trash_post( $this->post_id );
		$this->run_scheduled( 'syn_delete_content' );

		$this->assertSame( array(), $this->deleted_ext_ids() );
	}

	/**
	 * Trashing a post with the setting on deletes its remote copies, in a cron job.
	 */
	public function test_trash_with_setting_on_deletes_remote_copies(): void {
		$this->set_delete_pushed_posts( 'on' );

		wp_trash_post( $this->post_id );

		$this->assertSame( array(), $this->deleted_ext_ids(), 'Remote copies are deleted by cron, not during the request.' );
		$this->assertSame( 1, $this->run_scheduled( 'syn_delete_content' ) );
		$this->assertSame( array( self::EXT_ID ), $this->deleted_ext_ids() );
	}

	/**
	 * Permanently deleting an untrashed post with the setting on deletes its remote copies.
	 *
	 * The post meta that records the remote IDs is gone by the time the cron job runs,
	 * so they have to be captured when the job is scheduled.
	 */
	public function test_permanent_delete_with_setting_on_deletes_remote_copies(): void {
		$this->set_delete_pushed_posts( 'on' );

		wp_delete_post( $this->post_id, true );
		$this->run_scheduled( 'syn_delete_content' );

		$this->assertSame( array( self::EXT_ID ), $this->deleted_ext_ids() );
	}

	/**
	 * Permanently deleting a post with the setting off leaves its remote copies alone.
	 */
	public function test_permanent_delete_with_setting_off_keeps_remote_copies(): void {
		$this->set_delete_pushed_posts( 'off' );

		wp_delete_post( $this->post_id, true );
		$this->run_scheduled( 'syn_delete_content' );

		$this->assertSame( array(), $this->deleted_ext_ids() );
	}

	/**
	 * Emptying the trash does not delete remote copies a second time.
	 */
	public function test_deleting_a_trashed_post_does_not_schedule_again(): void {
		$this->set_delete_pushed_posts( 'on' );

		wp_trash_post( $this->post_id );
		$this->run_scheduled( 'syn_delete_content' );
		wp_delete_post( $this->post_id, true );

		$this->assertSame( 0, $this->run_scheduled( 'syn_delete_content' ) );
	}

	/**
	 * Deleting a post that was never pushed schedules nothing.
	 */
	public function test_deleting_an_unpushed_post_schedules_nothing(): void {
		$this->set_delete_pushed_posts( 'on' );

		wp_delete_post( self::factory()->post->create(), true );

		$this->assertSame( 0, $this->run_scheduled( 'syn_delete_content' ) );
	}

	/**
	 * A WP_Error from the client is recorded as a delete failure.
	 */
	public function test_records_a_wp_error_as_a_delete_failure(): void {
		global $push_syndication_server;

		delete_option( 'syn_delete_error_sites' );
		Syndication_Recording_Client::$results['delete_post'] = new WP_Error( 'delete-failed', 'Nope' );

		$push_syndication_server->delete_content( $this->post_id );

		$this->assertSame( array( $this->site_id => array( self::EXT_ID ) ), get_option( 'syn_delete_error_sites' ) );
	}

	/**
	 * Run the private upgrade routine as if upgrading from a given version.
	 *
	 * @param string $from_version Stored syn_version, or '' for a new install.
	 */
	private function upgrade_from( string $from_version ): void {
		global $push_syndication_server;

		$reflection = new ReflectionClass( $push_syndication_server );

		$version = $reflection->getProperty( 'version' );
		if ( PHP_VERSION_ID < 80100 ) {
			$version->setAccessible( true );
		}
		$version->setValue( $push_syndication_server, $from_version );

		$upgrade = $reflection->getMethod( 'upgrade' );
		if ( PHP_VERSION_ID < 80100 ) {
			$upgrade->setAccessible( true );
		}
		$upgrade->invoke( $push_syndication_server );
	}

	/**
	 * Store settings as admin_init would find them, with the validation callback registered.
	 *
	 * @param array $settings Stored settings.
	 */
	private function store_settings( array $settings ): void {
		global $push_syndication_server;

		update_option( 'push_syndicate_settings', $settings );
		$push_syndication_server->push_syndicate_settings = wp_parse_args( $settings, $push_syndication_server->push_syndicate_default_settings );
		register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $push_syndication_server, 'push_syndicate_settings_validate' ) );
	}

	/**
	 * Existing installs keep deleting on trash, now through the setting.
	 */
	public function test_upgrade_turns_the_setting_on_for_existing_installs(): void {
		global $push_syndication_server;

		$this->store_settings(
			array(
				'delete_pushed_posts' => 'off',
				'client_secret'       => 'stored-encrypted-secret',
			)
		);

		$this->upgrade_from( '2.2.1' );

		$stored = get_option( 'push_syndicate_settings' );
		$this->assertSame( 'on', $stored['delete_pushed_posts'] );
		$this->assertSame( 'stored-encrypted-secret', $stored['client_secret'], 'The stored secret is not re-encrypted.' );
		$this->assertSame( 'on', $push_syndication_server->push_syndicate_settings['delete_pushed_posts'] );
	}

	/**
	 * New installs keep the off default.
	 */
	public function test_upgrade_leaves_the_setting_off_for_new_installs(): void {
		global $push_syndication_server;

		delete_option( 'push_syndicate_settings' );
		$push_syndication_server->push_syndicate_settings = $push_syndication_server->push_syndicate_default_settings;

		$this->upgrade_from( '' );

		$this->assertFalse( get_option( 'push_syndicate_settings' ) );
		$this->assertSame( 'off', $push_syndication_server->push_syndicate_settings['delete_pushed_posts'] );
	}
}
