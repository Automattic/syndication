<?php
/**
 * Integration tests for PushPostCommand.
 *
 * Tests the push-post CLI command against a real WordPress database.
 *
 * @package Automattic\Syndication\Tests\Integration\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\CLI;

/**
 * Tests for PushPostCommand.
 *
 * @group cli
 * @group integration
 * @covers \Automattic\Syndication\Infrastructure\CLI\PushPostCommand
 */
class PushPostCommandTest extends CliTestCase {

	/**
	 * Test that command errors when post_id is not provided.
	 */
	public function test_errors_when_post_id_not_provided(): void {
		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array() );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'Invalid post_id' );
	}

	/**
	 * Test that command errors when post_id is zero.
	 */
	public function test_errors_when_post_id_is_zero(): void {
		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => 0 ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'Invalid post_id' );
	}

	/**
	 * Test that command errors when post does not exist.
	 */
	public function test_errors_when_post_does_not_exist(): void {
		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => 99999 ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'Invalid post_id' );
	}

	/**
	 * Test that command errors when post has no sitegroups.
	 */
	public function test_errors_when_post_has_no_sitegroups(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'no selected sitegroups' );

		// Clean up.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that command errors when post has empty sitegroups array.
	 */
	public function test_errors_when_post_has_empty_sitegroups(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		// Set empty sitegroups array.
		update_post_meta( $post_id, '_syn_selected_sitegroups', array() );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		$this->assert_command_error();
		$this->assert_stderr_contains( 'no selected sitegroups' );

		// Clean up.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that command logs progress when pushing.
	 */
	public function test_logs_progress_when_pushing(): void {
		// Create sitegroup with a site.
		$sitegroup_id = $this->create_sitegroup( 'Test Group' );
		$site_id      = $this->create_syndication_site( array( 'post_title' => 'Test Site' ) );
		$this->assign_site_to_sitegroup( $site_id, $sitegroup_id );

		// Create post assigned to sitegroup.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup_id ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should log the push progress.
		$this->assert_stdout_contains( 'Pushing post' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $site_id, true );
		wp_delete_term( $sitegroup_id, 'syn_sitegroup' );
	}

	/**
	 * Test that command filters out disabled sites.
	 */
	public function test_filters_out_disabled_sites(): void {
		// Create sitegroup with one enabled and one disabled site.
		$sitegroup_id  = $this->create_sitegroup( 'Mixed Group' );
		$enabled_site  = $this->create_syndication_site( array( 'post_title' => 'Enabled Site' ) );
		$disabled_site = $this->create_syndication_site( array( 'post_title' => 'Disabled Site' ) );

		// Disable one site.
		update_post_meta( $disabled_site, 'syn_site_enabled', '0' );

		$this->assign_site_to_sitegroup( $enabled_site, $sitegroup_id );
		$this->assign_site_to_sitegroup( $disabled_site, $sitegroup_id );

		// Create post assigned to sitegroup.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup_id ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should only push to 1 site (the enabled one).
		$this->assert_stdout_contains( '1 site(s)' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $enabled_site, true );
		wp_delete_post( $disabled_site, true );
		wp_delete_term( $sitegroup_id, 'syn_sitegroup' );
	}

	/**
	 * Test that command handles sitegroup with no enabled sites.
	 */
	public function test_errors_when_sitegroup_has_no_enabled_sites(): void {
		// Create sitegroup with only a disabled site.
		$sitegroup_id  = $this->create_sitegroup( 'Disabled Group' );
		$disabled_site = $this->create_syndication_site( array( 'post_title' => 'Disabled Site' ) );

		// Disable the site.
		update_post_meta( $disabled_site, 'syn_site_enabled', '0' );

		$this->assign_site_to_sitegroup( $disabled_site, $sitegroup_id );

		// Create post assigned to sitegroup.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup_id ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should error because no enabled sites exist.
		$this->assert_command_error();
		$this->assert_stderr_contains( 'no selected sitegroups' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $disabled_site, true );
		wp_delete_term( $sitegroup_id, 'syn_sitegroup' );
	}

	/**
	 * Test that command handles sitegroup with no sites.
	 */
	public function test_errors_when_sitegroup_is_empty(): void {
		// Create empty sitegroup.
		$sitegroup_id = $this->create_sitegroup( 'Empty Group' );

		// Create post assigned to empty sitegroup.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup_id ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should error because sitegroup has no sites.
		$this->assert_command_error();
		$this->assert_stderr_contains( 'no selected sitegroups' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_term( $sitegroup_id, 'syn_sitegroup' );
	}

	/**
	 * Test that command accepts verbose flag.
	 */
	public function test_accepts_verbose_flag(): void {
		// Create sitegroup with a site.
		$sitegroup_id = $this->create_sitegroup( 'Verbose Test Group' );
		$site_id      = $this->create_syndication_site( array( 'post_title' => 'Test Site' ) );
		$this->assign_site_to_sitegroup( $site_id, $sitegroup_id );

		// Create post assigned to sitegroup.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup_id ) );

		$command = $this->create_push_post_command();

		// Should not error on verbose flag.
		$this->invoke_command(
			$command,
			array(),
			array(
				'post_id' => $post_id,
				'verbose' => true,
			)
		);

		// The command should have run.
		$output = $this->get_output();
		$this->assertNotEmpty( $output, 'Expected some output from command' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $site_id, true );
		wp_delete_term( $sitegroup_id, 'syn_sitegroup' );
	}

	/**
	 * Test that command handles multiple sitegroups.
	 */
	public function test_handles_multiple_sitegroups(): void {
		// Create two sitegroups with one site each.
		$sitegroup1 = $this->create_sitegroup( 'Group 1' );
		$sitegroup2 = $this->create_sitegroup( 'Group 2' );

		$site1 = $this->create_syndication_site( array( 'post_title' => 'Site 1' ) );
		$site2 = $this->create_syndication_site( array( 'post_title' => 'Site 2' ) );

		$this->assign_site_to_sitegroup( $site1, $sitegroup1 );
		$this->assign_site_to_sitegroup( $site2, $sitegroup2 );

		// Create post assigned to both sitegroups.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup1, $sitegroup2 ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should push to 2 sites.
		$this->assert_stdout_contains( '2 site(s)' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $site1, true );
		wp_delete_post( $site2, true );
		wp_delete_term( $sitegroup1, 'syn_sitegroup' );
		wp_delete_term( $sitegroup2, 'syn_sitegroup' );
	}

	/**
	 * Test that command deduplicates sites appearing in multiple sitegroups.
	 */
	public function test_deduplicates_sites_in_multiple_sitegroups(): void {
		// Create two sitegroups with the same site.
		$sitegroup1 = $this->create_sitegroup( 'Group 1' );
		$sitegroup2 = $this->create_sitegroup( 'Group 2' );

		$shared_site = $this->create_syndication_site( array( 'post_title' => 'Shared Site' ) );

		$this->assign_site_to_sitegroup( $shared_site, $sitegroup1 );
		$this->assign_site_to_sitegroup( $shared_site, $sitegroup2 );

		// Create post assigned to both sitegroups.
		$post_id = $this->create_post_with_sitegroups( array( $sitegroup1, $sitegroup2 ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should push to only 1 site (deduplicated).
		$this->assert_stdout_contains( '1 site(s)' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $shared_site, true );
		wp_delete_term( $sitegroup1, 'syn_sitegroup' );
		wp_delete_term( $sitegroup2, 'syn_sitegroup' );
	}

	/**
	 * Test that command handles draft posts.
	 */
	public function test_handles_draft_posts(): void {
		// Create sitegroup with a site.
		$sitegroup_id = $this->create_sitegroup( 'Test Group' );
		$site_id      = $this->create_syndication_site( array( 'post_title' => 'Test Site' ) );
		$this->assign_site_to_sitegroup( $site_id, $sitegroup_id );

		// Create draft post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Draft Post',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $post_id, '_syn_selected_sitegroups', array( $sitegroup_id ) );

		$command = $this->create_push_post_command();

		$this->invoke_command( $command, array(), array( 'post_id' => $post_id ) );

		// Should attempt to push (CLI allows pushing drafts).
		$this->assert_stdout_contains( 'Pushing post' );

		// Clean up.
		wp_delete_post( $post_id, true );
		wp_delete_post( $site_id, true );
		wp_delete_term( $sitegroup_id, 'syn_sitegroup' );
	}
}
