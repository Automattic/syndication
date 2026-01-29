<?php
/**
 * Base test case for CLI integration tests.
 *
 * Provides helpers for testing WP-CLI commands with real WordPress
 * database but without full shell execution overhead.
 *
 * @package Automattic\Syndication\Tests\Integration\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\CLI;

use Automattic\Syndication\Application\Contracts\PullServiceInterface;
use Automattic\Syndication\Application\Contracts\PushServiceInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use Automattic\Syndication\Infrastructure\CLI\PullSiteCommand;
use Automattic\Syndication\Infrastructure\CLI\PushPostCommand;
use Automattic\Syndication\Tests\Integration\TestCase;

/**
 * Base test case for CLI command integration tests.
 *
 * This test case provides:
 * - WP_CLI output capture via the WpCliOutputCapture helper
 * - Command invocation helpers
 * - Assertion helpers for common patterns
 * - Real WordPress database with automatic cleanup
 *
 * Unlike unit tests which mock all dependencies, these integration tests
 * use real WordPress database operations while capturing CLI output.
 */
abstract class CliTestCase extends TestCase {

	/**
	 * The output capture helper.
	 *
	 * @var WpCliOutputCapture
	 */
	protected WpCliOutputCapture $output;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->output = new WpCliOutputCapture();
	}

	/**
	 * Create a PullSiteCommand with real dependencies.
	 *
	 * @return PullSiteCommand
	 */
	protected function create_pull_site_command(): PullSiteCommand {
		$pull_service    = $this->container()->get( PullServiceInterface::class );
		$site_repository = $this->container()->get( SiteRepositoryInterface::class );

		\assert( $pull_service instanceof PullServiceInterface );
		\assert( $site_repository instanceof SiteRepositoryInterface );

		return new PullSiteCommand( $pull_service, $site_repository );
	}

	/**
	 * Create a PushPostCommand with real dependencies.
	 *
	 * @return PushPostCommand
	 */
	protected function create_push_post_command(): PushPostCommand {
		$push_service    = $this->container()->get( PushServiceInterface::class );
		$site_repository = $this->container()->get( SiteRepositoryInterface::class );

		\assert( $push_service instanceof PushServiceInterface );
		\assert( $site_repository instanceof SiteRepositoryInterface );

		return new PushPostCommand( $push_service, $site_repository );
	}

	/**
	 * Invoke a CLI command directly.
	 *
	 * Captures all WP_CLI output during execution.
	 *
	 * @param object               $command    The command instance.
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	protected function invoke_command( object $command, array $args = array(), array $assoc_args = array() ): void {
		$this->output->reset();
		$this->output->start_capture();

		try {
			$command->__invoke( $args, $assoc_args );
		} catch ( \Exception $e ) {
			// Some commands throw exceptions instead of calling WP_CLI::error().
			$this->output->record_error( $e->getMessage() );
		} finally {
			$this->output->stop_capture();
		}
	}

	/**
	 * Get all stdout output as a string.
	 *
	 * @return string
	 */
	protected function get_stdout(): string {
		return $this->output->get_stdout_string();
	}

	/**
	 * Get all stderr output as a string.
	 *
	 * @return string
	 */
	protected function get_stderr(): string {
		return $this->output->get_stderr_string();
	}

	/**
	 * Get combined output (stdout + stderr).
	 *
	 * @return string
	 */
	protected function get_output(): string {
		return $this->output->get_combined_string();
	}

	/**
	 * Assert that the command succeeded.
	 *
	 * @param string $message Optional assertion message.
	 */
	protected function assert_command_success( string $message = '' ): void {
		$this->assertTrue(
			$this->output->had_success(),
			$message ?: 'Expected command to succeed. Output: ' . $this->get_output()
		);
		$this->assertFalse(
			$this->output->had_error(),
			$message ?: 'Expected no error. Got: ' . $this->get_stderr()
		);
	}

	/**
	 * Assert that the command failed.
	 *
	 * @param string $message Optional assertion message.
	 */
	protected function assert_command_error( string $message = '' ): void {
		$this->assertTrue(
			$this->output->had_error(),
			$message ?: 'Expected command to fail. Output: ' . $this->get_output()
		);
	}

	/**
	 * Assert that stdout contains a string.
	 *
	 * @param string $expected Expected substring.
	 * @param string $message  Optional assertion message.
	 */
	protected function assert_stdout_contains( string $expected, string $message = '' ): void {
		$this->assertStringContainsString(
			$expected,
			$this->get_stdout(),
			$message ?: sprintf( 'Expected stdout to contain "%s". Got: %s', $expected, $this->get_stdout() )
		);
	}

	/**
	 * Assert that stderr contains a string.
	 *
	 * @param string $expected Expected substring.
	 * @param string $message  Optional assertion message.
	 */
	protected function assert_stderr_contains( string $expected, string $message = '' ): void {
		$this->assertStringContainsString(
			$expected,
			$this->get_stderr(),
			$message ?: sprintf( 'Expected stderr to contain "%s". Got: %s', $expected, $this->get_stderr() )
		);
	}

	/**
	 * Assert that stdout does not contain a string.
	 *
	 * @param string $unexpected Unexpected substring.
	 * @param string $message    Optional assertion message.
	 */
	protected function assert_stdout_not_contains( string $unexpected, string $message = '' ): void {
		$this->assertStringNotContainsString(
			$unexpected,
			$this->get_stdout(),
			$message ?: sprintf( 'Expected stdout to not contain "%s". Got: %s', $unexpected, $this->get_stdout() )
		);
	}

	/**
	 * Assert success message contains text.
	 *
	 * Combines asserting success status and message content.
	 *
	 * @param string $expected Expected substring in success message.
	 * @param string $message  Optional assertion message.
	 */
	protected function assert_success_contains( string $expected, string $message = '' ): void {
		$this->assert_command_success( $message );
		$this->assert_stdout_contains( $expected, $message );
	}

	/**
	 * Assert error message contains text.
	 *
	 * Combines asserting error status and message content.
	 *
	 * @param string $expected Expected substring in error message.
	 * @param string $message  Optional assertion message.
	 */
	protected function assert_error_contains( string $expected, string $message = '' ): void {
		$this->assert_command_error( $message );
		$this->assert_stderr_contains( $expected, $message );
	}

	/**
	 * Assert that a warning was output.
	 *
	 * @param string $expected Expected substring in warning message.
	 * @param string $message  Optional assertion message.
	 */
	protected function assert_warning_contains( string $expected, string $message = '' ): void {
		$this->assertTrue(
			$this->output->had_warning(),
			$message ?: 'Expected a warning. Output: ' . $this->get_output()
		);
		$this->assert_stdout_contains( $expected, $message );
	}

	/**
	 * Create a syndication site for testing.
	 *
	 * @param array<string, mixed> $args Site arguments.
	 * @return int The site post ID.
	 */
	protected function create_syndication_site( array $args = array() ): int {
		$defaults = array(
			'post_type'   => 'syn_site',
			'post_title'  => 'Test Site',
			'post_status' => 'publish',
		);

		$site_id = wp_insert_post( array_merge( $defaults, $args ) );

		// Set default site meta if not provided.
		if ( ! isset( $args['meta_input'] ) ) {
			update_post_meta( $site_id, 'syn_site_url', 'https://example.com' );
			update_post_meta( $site_id, 'syn_site_id', '1' );
			update_post_meta( $site_id, 'syn_transport_type', 'rest' );
			update_post_meta( $site_id, 'syn_site_enabled', '1' );
		}

		return $site_id;
	}

	/**
	 * Create a sitegroup for testing.
	 *
	 * @param string $name Sitegroup name.
	 * @return int The term ID.
	 */
	protected function create_sitegroup( string $name = 'Test Sitegroup' ): int {
		$term = wp_insert_term( $name, 'syn_sitegroup' );

		if ( is_wp_error( $term ) ) {
			return 0;
		}

		return $term['term_id'];
	}

	/**
	 * Assign a site to a sitegroup.
	 *
	 * @param int $site_id      The site post ID.
	 * @param int $sitegroup_id The sitegroup term ID.
	 */
	protected function assign_site_to_sitegroup( int $site_id, int $sitegroup_id ): void {
		wp_set_object_terms( $site_id, $sitegroup_id, 'syn_sitegroup' );
	}

	/**
	 * Create a post with syndication sitegroups assigned.
	 *
	 * @param array<int> $sitegroup_ids Sitegroup IDs to assign.
	 * @return int The post ID.
	 */
	protected function create_post_with_sitegroups( array $sitegroup_ids ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_syn_selected_sitegroups', $sitegroup_ids );

		return $post_id;
	}
}
