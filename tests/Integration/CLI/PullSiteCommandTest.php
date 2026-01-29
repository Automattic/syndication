<?php
/**
 * Integration tests for PullSiteCommand.
 *
 * Tests the pull-site CLI command against a real WordPress database.
 *
 * @package Automattic\Syndication\Tests\Integration\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\CLI;

/**
 * Tests for PullSiteCommand.
 *
 * @group cli
 * @group integration
 * @covers \Automattic\Syndication\Infrastructure\CLI\PullSiteCommand
 */
class PullSiteCommandTest extends CliTestCase {

	/**
	 * Test that command errors when site_id is not provided.
	 */
	public function test_errors_when_site_id_not_provided(): void {
		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array() );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'valid site' );
	}

	/**
	 * Test that command errors when site_id is zero.
	 */
	public function test_errors_when_site_id_is_zero(): void {
		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => 0 ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'valid site' );
	}

	/**
	 * Test that command errors when site does not exist.
	 */
	public function test_errors_when_site_does_not_exist(): void {
		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => 99999 ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'valid site' );
	}

	/**
	 * Test that command errors when post is not a syn_site.
	 */
	public function test_errors_when_post_is_not_syn_site(): void {
		// Create a regular post, not a syn_site.
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Regular Post',
				'post_status' => 'publish',
			)
		);

		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => $post_id ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'valid site' );

		// Clean up.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that command logs site name when pulling.
	 */
	public function test_logs_site_name_when_pulling(): void {
		$site_id = $this->create_syndication_site(
			array(
				'post_title' => 'My Test Site',
			)
		);

		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => $site_id ) );

		// The command should log the site name (even if pull fails due to no real remote).
		$this->assert_stdout_contains( 'My Test Site' );

		// Clean up.
		wp_delete_post( $site_id, true );
	}

	/**
	 * Test that command handles pull from site with missing configuration.
	 *
	 * A site without proper transport configuration should still be handled
	 * gracefully by the command.
	 */
	public function test_handles_site_with_missing_transport_config(): void {
		$site_id = wp_insert_post(
			array(
				'post_type'   => 'syn_site',
				'post_title'  => 'Misconfigured Site',
				'post_status' => 'publish',
			)
		);

		// Don't set any meta - site has no configuration.

		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => $site_id ) );

		// Command should handle this gracefully (may error or warn).
		$output = $this->get_output();
		$this->assertNotEmpty( $output, 'Expected some output from command' );

		// Clean up.
		wp_delete_post( $site_id, true );
	}

	/**
	 * Test that command accepts verbose flag.
	 */
	public function test_accepts_verbose_flag(): void {
		$site_id = $this->create_syndication_site();

		$command = $this->create_pull_site_command();

		// Should not error on verbose flag.
		$this->invoke_command(
			$command,
			array(),
			array(
				'site_id' => $site_id,
				'verbose' => true,
			)
		);

		// The command should have run (may succeed or fail depending on remote).
		$output = $this->get_output();
		$this->assertNotEmpty( $output, 'Expected some output from command' );

		// Clean up.
		wp_delete_post( $site_id, true );
	}

	/**
	 * Test that command handles disabled site.
	 *
	 * A disabled site should still be pullable via CLI (the enabled flag
	 * is for automated cron pulls, not manual CLI operations).
	 */
	public function test_handles_disabled_site(): void {
		$site_id = $this->create_syndication_site(
			array(
				'post_title' => 'Disabled Site',
			)
		);

		// Disable the site.
		update_post_meta( $site_id, 'syn_site_enabled', '0' );

		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => $site_id ) );

		// Command should still attempt the pull.
		$this->assert_stdout_contains( 'Disabled Site' );

		// Clean up.
		wp_delete_post( $site_id, true );
	}

	/**
	 * Test that command handles draft site.
	 *
	 * A site in draft status should still be pullable via CLI.
	 */
	public function test_handles_draft_site(): void {
		$site_id = $this->create_syndication_site(
			array(
				'post_title'  => 'Draft Site',
				'post_status' => 'draft',
			)
		);

		$command = $this->create_pull_site_command();

		$this->invoke_command( $command, array(), array( 'site_id' => $site_id ) );

		// Command should attempt the pull.
		$this->assert_stdout_contains( 'Draft Site' );

		// Clean up.
		wp_delete_post( $site_id, true );
	}
}
