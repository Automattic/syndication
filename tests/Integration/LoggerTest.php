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
	 * Test that a stored value the plugin did not write is not turned into an object.
	 *
	 * `syn_log` is an unprotected meta key, so its contents cannot be assumed to be
	 * well-formed log data. Some values are stored verbatim rather than re-serialized,
	 * and reading one back must yield an array, never an object graph.
	 *
	 * @covers Syndication_Logger::get_messages
	 */
	public function test_get_messages_never_returns_an_object_from_stored_meta(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
			)
		);

		$inner   = 'x:i:0;a:1:{i:0;s:3:"pwn";};m:a:0:{}';
		$payload = 'C:11:"ArrayObject":' . strlen( $inner ) . ':{' . $inner . '}';

		add_post_meta( $post_id, 'syn_log', $payload );

		// Confirm the value was stored verbatim rather than re-serialized on the way in.
		$this->assertSame( $payload, get_post_meta( $post_id, 'syn_log', true ) );

		$log_entries = Syndication_Logger::get_messages();

		$this->assertArrayHasKey( $post_id, $log_entries );
		$this->assertSame( array(), $log_entries[ $post_id ] );
	}
}
