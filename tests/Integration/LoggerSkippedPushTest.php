<?php
/**
 * Tests that a push skipped by a client filter is not logged as a success.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Syndication_Logger;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class LoggerSkippedPushTest
 *
 * @covers Syndication_Logger::log_new
 * @covers Syndication_Logger::log_update
 */
class LoggerSkippedPushTest extends WPIntegrationTestCase {

	/**
	 * Log entries recorded against a site.
	 *
	 * @param int $site_id Site ID.
	 * @return array
	 */
	private function log_entries( int $site_id ): array {
		$log = get_post_meta( $site_id, '_syn_log', true );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Set the logger's debug level.
	 *
	 * The logger reads `syn_pre_debug_level` each time it logs, and the test case
	 * restores hooks after each test, so nothing needs resetting.
	 *
	 * @param string $level 'info' to record info entries, 'default' for errors and successes only.
	 */
	private function set_debug_level( string $level ): void {
		add_filter(
			'syn_pre_debug_level',
			static function () use ( $level ) {
				return $level;
			}
		);
	}

	/**
	 * Push hooks and the action each fires.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_push_hooks(): array {
		return array(
			'new post'  => array( 'syn_post_push_new_post', 'new' ),
			'edit post' => array( 'syn_post_push_edit_post', 'update' ),
		);
	}

	/**
	 * A client returns true when a syn_*_push_filter_* callback returned false.
	 *
	 * That is a skip, not a push of remote post 1, so at the info debug level it is
	 * logged as information.
	 *
	 * @dataProvider data_push_hooks
	 *
	 * @param string $hook  Push hook.
	 * @param string $event Logged event.
	 */
	public function test_a_skipped_push_is_logged_as_info_not_success( string $hook, string $event ): void {
		$this->set_debug_level( 'info' );

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = self::factory()->post->create();

		do_action( $hook, true, $post_id, get_post( $site_id ), 'WP_XMLRPC', null, array() );

		$entries = $this->log_entries( $site_id );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'info', $entries[0]['msg_type'] );
		$this->assertSame( 'skip_' . $event, $entries[0]['status'] );
	}

	/**
	 * At the default debug level, which records only errors and successes, a skip is not logged.
	 *
	 * @dataProvider data_push_hooks
	 *
	 * @param string $hook  Push hook.
	 */
	public function test_a_skipped_push_is_not_logged_at_the_default_level( string $hook ): void {
		$this->set_debug_level( 'default' );

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = self::factory()->post->create();

		do_action( $hook, true, $post_id, get_post( $site_id ), 'WP_XMLRPC', null, array() );

		$this->assertSame( array(), $this->log_entries( $site_id ) );
	}

	/**
	 * A real push is still logged as a success with its remote ID.
	 *
	 * @dataProvider data_push_hooks
	 *
	 * @param string $hook  Push hook.
	 * @param string $event Logged event.
	 */
	public function test_a_real_push_is_still_logged_as_success( string $hook, string $event ): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = self::factory()->post->create();

		do_action( $hook, 456, $post_id, get_post( $site_id ), 'WP_XMLRPC', null, array() );

		$entries = $this->log_entries( $site_id );
		$this->assertSame( 'success', $entries[0]['msg_type'] );
		$this->assertSame( $event, $entries[0]['status'] );
		$this->assertSame( get_the_guid( $post_id ) . ',456', $entries[0]['message'] );
	}
}
