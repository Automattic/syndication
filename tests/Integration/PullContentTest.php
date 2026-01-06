<?php
/**
 * Tests for the pull_content functionality in WP_Push_Syndication_Server.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullContentTest
 *
 * @covers WP_Push_Syndication_Server::pull_content
 */
class PullContentTest extends WPIntegrationTestCase {

	/**
	 * Test that pull_content handles non-array posts without PHP warnings.
	 *
	 * This tests the fix for PHP warnings that occurred when a client's
	 * get_posts() method returned a non-array value (e.g., false, null).
	 *
	 * @covers WP_Push_Syndication_Server::pull_content
	 */
	public function test_pull_content_handles_non_array_posts(): void {
		global $push_syndication_server;

		// Configure mock client to return false.
		\Syndication_Mock_Client::set_posts( false );

		// Create a site post with pull enabled.
		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		// Enable the site for syndication.
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );

		// Use our mock transport type.
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );

		// Get the site post object to pass directly.
		$site = get_post( $site_id );

		// This should not trigger any PHP warnings.
		// If is_array() check is missing, count() on false would warn.
		$push_syndication_server->pull_content( array( $site ) );

		// Verify the last pull time was still updated.
		$last_pull_time = get_post_meta( $site_id, 'syn_last_pull_time', true );
		$this->assertNotEmpty( $last_pull_time, 'Last pull time should be updated even when no posts returned' );
	}

	/**
	 * Test that pull_content handles null posts without PHP warnings.
	 *
	 * @covers WP_Push_Syndication_Server::pull_content
	 */
	public function test_pull_content_handles_null_posts(): void {
		global $push_syndication_server;

		\Syndication_Mock_Client::set_posts( null );

		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );

		$site = get_post( $site_id );

		// Should not trigger warnings.
		$push_syndication_server->pull_content( array( $site ) );

		$last_pull_time = get_post_meta( $site_id, 'syn_last_pull_time', true );
		$this->assertNotEmpty( $last_pull_time );
	}

	/**
	 * Test that pull_content handles empty array without issues.
	 *
	 * @covers WP_Push_Syndication_Server::pull_content
	 */
	public function test_pull_content_handles_empty_array(): void {
		global $push_syndication_server;

		\Syndication_Mock_Client::set_posts( array() );

		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );

		$site = get_post( $site_id );

		$push_syndication_server->pull_content( array( $site ) );

		$last_pull_time = get_post_meta( $site_id, 'syn_last_pull_time', true );
		$this->assertNotEmpty( $last_pull_time, 'Last pull time should be updated even with empty posts array' );
	}
}
