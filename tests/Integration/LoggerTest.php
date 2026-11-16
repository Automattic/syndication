<?php
/**
 * Tests for the Syndication_Logger class.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;
use Syndication_Logger;

/**
 * Class LoggerTest
 *
 * @covers Syndication_Logger
 */
class LoggerTest extends WPIntegrationTestCase {

	/**
	 * Test that logging to a post without existing logs works correctly.
	 *
	 * This tests the fix for the "Array to string conversion" error that occurred
	 * when get_post_meta was called with the third parameter as true, returning
	 * an empty string instead of an empty array when no meta existed.
	 *
	 * Uses log_post_error because log_post_info requires debug_level='info'.
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_log_to_post_without_existing_logs(): void {
		// Create a site post.
		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		// Log should work without throwing an error.
		Syndication_Logger::log_post_error(
			$site_id,
			'test_status',
			'Test message',
			null,
			array()
		);

		// Verify the log was stored.
		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertIsArray( $log );
		$this->assertNotEmpty( $log );
	}

	/**
	 * Test that logging to a post with existing logs appends correctly.
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_log_to_post_with_existing_logs(): void {
		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		// Create initial log entry.
		Syndication_Logger::log_post_error(
			$site_id,
			'first_status',
			'First message',
			null,
			array()
		);

		// Add second log entry.
		Syndication_Logger::log_post_error(
			$site_id,
			'second_status',
			'Second message',
			null,
			array()
		);

		// Verify both logs exist.
		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertIsArray( $log );
		$this->assertCount( 2, $log );
	}

	/**
	 * Test that error logging works correctly.
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_log_post_error(): void {
		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		Syndication_Logger::log_post_error(
			$site_id,
			'error_status',
			'Error message',
			null,
			array()
		);

		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertIsArray( $log );
		$this->assertNotEmpty( $log );

		// Error count should be tracked.
		$errors = get_post_meta( $site_id, 'syn_log_errors', true );
		$this->assertEquals( 1, (int) $errors );
	}

	/**
	 * A successful push passes the local post ID, which has a `guid` but no `post_guid`.
	 *
	 * @covers Syndication_Logger::log_new
	 */
	public function test_push_new_post_logs_the_local_post_guid(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = $this->factory()->post->create();

		do_action( 'syn_post_push_new_post', 456, $post_id, get_post( $site_id ), 'WP_XMLRPC', null, array() );

		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertSame( 'success', $log[0]['msg_type'] );
		$this->assertSame( get_the_guid( $post_id ) . ',456', $log[0]['message'] );
	}

	/**
	 * The delete hook passes the site ID rather than the site post.
	 *
	 * @covers Syndication_Logger::log_delete
	 */
	public function test_push_delete_post_logs_to_the_site(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = $this->factory()->post->create();

		do_action( 'syn_post_push_delete_post', true, 789, $post_id, $site_id, 'WP_XMLRPC', null );

		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertSame( 'success', $log[0]['msg_type'] );
		$this->assertSame( 'delete', $log[0]['status'] );
	}

	/**
	 * A failed delete is also logged to the site, not dropped.
	 *
	 * @covers Syndication_Logger::log_delete
	 */
	public function test_failed_push_delete_post_logs_an_error_to_the_site(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = $this->factory()->post->create();

		do_action( 'syn_post_push_delete_post', false, 789, $post_id, $site_id, 'WP_XMLRPC', null );

		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertSame( 'error', $log[0]['msg_type'] );
		$this->assertSame( 1, (int) get_post_meta( $site_id, 'syn_log_errors', true ) );
	}

	/**
	 * Reading one site's log returns the same shape as reading every site's log.
	 *
	 * @covers Syndication_Logger::get_messages
	 */
	public function test_get_messages_for_one_site_returns_its_entries_and_applies_filters(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		Syndication_Logger::log_post_error( $site_id, 'error', 'First failure' );
		Syndication_Logger::log_post_error( $site_id, 'error', 'Second failure' );

		$log_entries = Syndication_Logger::get_messages( null, null, $site_id );

		$this->assertCount( 2, $log_entries[ $site_id ] );
		$this->assertSame( 'First failure', $log_entries[ $site_id ][0]['message'] );

		$log_entries = Syndication_Logger::get_messages( null, 'error', $site_id, 'post', null, null, null, 'Second' );

		$this->assertSame( array( 1 ), array_keys( $log_entries[ $site_id ] ) );
	}

	/**
	 * A site with no log yet returns an empty list rather than failing.
	 *
	 * @covers Syndication_Logger::get_messages
	 */
	public function test_get_messages_for_a_site_without_a_log_returns_no_entries(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$this->assertSame( array( $site_id => array() ), Syndication_Logger::get_messages( null, null, $site_id ) );
	}

	/**
	 * Backslashes in a message survive being written, and being written again with later entries.
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_log_keeps_backslashes_in_messages(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		Syndication_Logger::log_post_error( $site_id, 'error', 'Failed to parse feed at C:\\feeds\\a.xml' );
		Syndication_Logger::log_post_error( $site_id, 'error', 'Second failure' );
		Syndication_Logger::log_post_error( $site_id, 'error', 'Third failure' );

		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertSame( 'Failed to parse feed at C:\\feeds\\a.xml', $log[0]['message'] );
	}

	/**
	 * log_post_success() is callable statically, like its error and info siblings.
	 *
	 * @covers Syndication_Logger::log_post_success
	 */
	public function test_log_post_success_can_be_called_statically(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		Syndication_Logger::log_post_success( $site_id, 'ok', 'Pushed' );

		$log = get_post_meta( $site_id, 'syn_log', true );
		$this->assertSame( 'success', $log[0]['msg_type'] );
	}

	/**
	 * Test that a stored value the plugin did not write is not turned into an object.
	 *
	 * `syn_log` is an unprotected meta key, so its contents cannot be assumed to be
	 * well-formed log data. Some values are stored verbatim rather than re-serialized,
	 * and reading one back must yield an array, never an object graph.
	 *
	 * @covers Syndication_Logger::get_messages
	 */
	public function test_get_messages_never_returns_an_object_from_stored_meta(): void {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$inner   = 'x:i:0;a:1:{i:0;s:3:"pwn";};m:a:0:{}';
		$payload = 'C:11:"ArrayObject":' . strlen( $inner ) . ':{' . $inner . '}';

		add_post_meta( $post_id, 'syn_log', $payload );

		// Confirm the value was stored verbatim rather than re-serialized on the way in.
		$this->assertSame( $payload, get_post_meta( $post_id, 'syn_log', true ) );

		$log_entries = Syndication_Logger::get_messages();

		$this->assertArrayHasKey( $post_id, $log_entries );
		$this->assertSame( array(), $log_entries[ $post_id ] );
	}

	/**
	 * Only sites' logs are read, so log meta on any other post can't crowd them out.
	 *
	 * @covers Syndication_Logger::get_messages
	 */
	public function test_get_messages_only_reads_logs_on_sites(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$post_id = $this->factory()->post->create();

		Syndication_Logger::log_post_error( $site_id, 'error', 'Logged against a site' );
		Syndication_Logger::log_post_error( $post_id, 'error', 'Logged against a post' );

		$log_entries = Syndication_Logger::get_messages();

		$this->assertArrayHasKey( $site_id, $log_entries );
		$this->assertArrayNotHasKey( $post_id, $log_entries );
	}

	/**
	 * The log meta keys must not be writable through capability-checked paths.
	 *
	 * This is what keeps them out of the Custom Fields panel, the add/delete meta
	 * AJAX endpoints and XML-RPC set_custom_fields(), all of which gate on these caps.
	 *
	 * @covers Syndication_Logger::register_log_meta
	 */
	public function test_log_meta_keys_are_not_writable_by_capability(): void {
		/*
		 * The plugin registers these on `init`, which fires once per PHPUnit run, and
		 * WP_UnitTestCase_Base::tear_down() calls unregister_all_meta_keys() after every
		 * test. Register them here rather than relying on a registration an earlier test
		 * has already torn down.
		 */
		Syndication_Logger::register_log_meta();

		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		foreach ( array( 'syn_log', 'syn_log_errors', 'syn_log_errors_overall' ) as $meta_key ) {
			$this->assertFalse( current_user_can( 'add_post_meta', $post_id, $meta_key ), $meta_key );
			$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, $meta_key ), $meta_key );
			$this->assertFalse( current_user_can( 'delete_post_meta', $post_id, $meta_key ), $meta_key );
		}

		// An unrelated key is still writable, so the denial is not blanket.
		$this->assertTrue( current_user_can( 'edit_post_meta', $post_id, 'syn_site_url' ) );

		// The logger writes via update_post_meta(), which does not consult the auth
		// callback, so normal logging is unaffected.
		Syndication_Logger::log_post_error( $post_id, 'error', 'still logging' );
		$this->assertNotEmpty( get_post_meta( $post_id, 'syn_log', true ) );
	}

	/**
	 * The debug level filter is read when logging, so a theme or later plugin can set it.
	 *
	 * @covers Syndication_Logger::log_post_info
	 */
	public function test_debug_level_filter_added_after_load_is_honored(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		add_filter(
			'syn_pre_debug_level',
			static function () {
				return 'info';
			}
		);
		Syndication_Logger::log_post_info( $site_id, 'fetch_feed', 'Logged at info level' );

		$this->assertSame( 'Logged at info level', get_post_meta( $site_id, 'syn_log', true )[0]['message'] );

		add_filter(
			'syn_pre_debug_level',
			static function () {
				return 'default';
			},
			20
		);
		Syndication_Logger::log_post_info( $site_id, 'fetch_feed', 'Not logged at default level' );

		$this->assertCount( 1, get_post_meta( $site_id, 'syn_log', true ) );
	}

	/**
	 * The PHP error logging filter is read when logging, so a theme or later plugin can set it.
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_php_error_logging_filter_added_after_load_is_honored(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		add_filter( 'syn_use_php_error_logging', '__return_true' );

		$output = $this->capture_error_log(
			static function () use ( $site_id ) {
				Syndication_Logger::log_post_error( $site_id, 'error', 'Sent to the PHP error log' );
			}
		);

		$this->assertStringContainsString( "SYN_ERROR:post,{$site_id},error,Sent to the PHP error log", $output );
	}

	/**
	 * Returning false from the PHP error logging filter turns it off, even under WP_DEBUG.
	 *
	 * WP_DEBUG is a constant, so the test can't switch it on. Instead it checks that the
	 * filter's default is distinct from false, which is what lets false mean "off".
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_php_error_logging_filter_false_turns_it_off(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$default = 'unset';

		add_filter(
			'syn_use_php_error_logging',
			static function ( $value ) use ( &$default ) {
				$default = $value;
				return false;
			}
		);

		$output = $this->capture_error_log(
			static function () use ( $site_id ) {
				Syndication_Logger::log_post_error( $site_id, 'error', 'Not sent to the PHP error log' );
			}
		);

		$this->assertNull( $default );
		$this->assertSame( '', $output );
	}

	/**
	 * With no filter, PHP error logging follows WP_DEBUG.
	 *
	 * @covers Syndication_Logger::log_post_error
	 */
	public function test_php_error_logging_follows_wp_debug_by_default(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$output = $this->capture_error_log(
			static function () use ( $site_id ) {
				Syndication_Logger::log_post_error( $site_id, 'error', 'Maybe sent to the PHP error log' );
			}
		);

		$this->assertSame( defined( 'WP_DEBUG' ) && WP_DEBUG, '' !== $output );
	}

	/**
	 * An entry without a status or message is still formatted for the PHP error log.
	 *
	 * @covers Syndication_Logger::log_post_info
	 */
	public function test_php_error_log_line_tolerates_missing_status_and_message(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		add_filter( 'syn_use_php_error_logging', '__return_true' );
		add_filter(
			'syn_pre_debug_level',
			static function () {
				return 'info';
			}
		);

		$output = $this->capture_error_log(
			static function () use ( $site_id ) {
				Syndication_Logger::log_post_info( $site_id );
			}
		);

		$this->assertStringContainsString( "SYN_INFO:post,{$site_id},\n", $output );
	}

	/**
	 * Run a callback with the PHP error log redirected to a temporary file.
	 *
	 * @param callable $callback Code that may write to the error log.
	 * @return string What was written to the error log.
	 */
	private function capture_error_log( callable $callback ): string {
		$log_file     = wp_tempnam( 'syn-error-log' );
		$original_log = ini_set( 'error_log', $log_file );

		try {
			$callback();
		} finally {
			ini_set( 'error_log', $original_log );
		}

		$output = (string) file_get_contents( $log_file );
		unlink( $log_file );

		return $output;
	}
}
