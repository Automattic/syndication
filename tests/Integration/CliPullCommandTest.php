<?php
/**
 * Tests for the WP-CLI pull commands.
 *
 * @package Automattic\Syndication\Tests
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests;

use Syndication_CLI_Command;
use Syndication_Mock_Client;
use WP_CLI;
use WP_CLI_Stub_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class CliPullCommandTest
 *
 * @coversDefaultClass \Syndication_CLI_Command
 */
class CliPullCommandTest extends WPIntegrationTestCase {

	/**
	 * Server settings before the test changed them.
	 *
	 * @var array
	 */
	private $original_settings;

	/**
	 * Load the WP-CLI stand-ins and the real command class.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! class_exists( 'WP_CLI', false ) ) {
			require_once __DIR__ . '/WP_CLI_Stub.php';
		}
		require_once dirname( __DIR__, 2 ) . '/includes/class-wp-cli.php';
	}

	/**
	 * Start each test with no recorded output.
	 */
	protected function setUp(): void {
		parent::setUp();
		WP_CLI::$output          = array();
		$this->original_settings = $GLOBALS['push_syndication_server']->push_syndicate_settings;
	}

	/**
	 * Restore the server settings and the mock feed.
	 */
	protected function tearDown(): void {
		$GLOBALS['push_syndication_server']->push_syndicate_settings = $this->original_settings;
		Syndication_Mock_Client::set_posts( array() );
		parent::tearDown();
	}

	/**
	 * Create a pull site using the mock client.
	 *
	 * @param string $sitegroup Sitegroup slug to put the site in.
	 * @param string $enabled   Value of syn_site_enabled.
	 * @return int Site ID.
	 */
	private function create_site( string $sitegroup, string $enabled = 'on' ): int {
		if ( ! term_exists( $sitegroup, 'syn_sitegroup' ) ) {
			wp_insert_term( $sitegroup, 'syn_sitegroup' );
		}

		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
				'post_title'  => 'Feed',
			)
		);
		update_post_meta( $site_id, 'syn_site_enabled', $enabled );
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );
		wp_set_object_terms( $site_id, $sitegroup, 'syn_sitegroup' );

		return $site_id;
	}

	/**
	 * Schedule a site for pulling, so an unscoped pull_content() call would reach it.
	 *
	 * @return int Site ID.
	 */
	private function create_scheduled_site(): int {
		$site_id = $this->create_site( 'scheduled-group' );
		$GLOBALS['push_syndication_server']->push_syndicate_settings['selected_pull_sitegroups'] = array( 'scheduled-group' );

		return $site_id;
	}

	/**
	 * Run pull_site on a feed whose post fails to save.
	 *
	 * pull_content() casts each result to int after firing the pull hooks, so a
	 * WP_Error raises a PHP warning there (a server-side defect, not the
	 * command's). Silence it so the test checks only what the command reports.
	 *
	 * @param int $site_id Site ID.
	 */
	private function pull_site_despite_server_cast( int $site_id ): void {
		@( new Syndication_CLI_Command() )->pull_site( array(), array( 'site_id' => $site_id ) );
	}

	/**
	 * A mistyped sitegroup is an error, and pulls nothing.
	 *
	 * @covers ::pull_sitegroup
	 */
	public function test_pull_sitegroup_errors_for_unknown_sitegroup(): void {
		$scheduled_site = $this->create_scheduled_site();

		try {
			( new Syndication_CLI_Command() )->pull_sitegroup( array(), array( 'sitegroup' => 'no-such-group' ) );
			$this->fail( 'Expected an error for an unknown sitegroup.' );
		} catch ( WP_CLI_Stub_Error $e ) {
			$this->assertStringContainsString( 'no-such-group', $e->getMessage() );
		}

		$this->assertSame( '', get_post_meta( $scheduled_site, 'syn_last_pull_time', true ), 'No site should be pulled.' );
	}

	/**
	 * A sitegroup with no sites warns, and pulls nothing rather than every scheduled site.
	 *
	 * @covers ::pull_sitegroup
	 */
	public function test_pull_sitegroup_warns_and_pulls_nothing_for_empty_sitegroup(): void {
		$scheduled_site = $this->create_scheduled_site();
		wp_insert_term( 'empty-group', 'syn_sitegroup' );

		( new Syndication_CLI_Command() )->pull_sitegroup( array(), array( 'sitegroup' => 'empty-group' ) );

		$warnings = WP_CLI::messages( 'warning' );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'empty-group', $warnings[0] );
		$this->assertSame( '', get_post_meta( $scheduled_site, 'syn_last_pull_time', true ), 'No site should be pulled.' );
	}

	/**
	 * A disabled site is reported rather than skipped in silence.
	 *
	 * @covers ::pull_site
	 */
	public function test_pull_site_warns_when_site_is_disabled(): void {
		$site_id = $this->create_site( 'some-group', 'off' );

		( new Syndication_CLI_Command() )->pull_site( array(), array( 'site_id' => $site_id ) );

		$warnings = WP_CLI::messages( 'warning' );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( (string) $site_id, $warnings[0] );
	}

	/**
	 * A pulled post that can't be inserted is reported with its error, not as post #1.
	 *
	 * @covers ::pull_site
	 */
	public function test_pull_site_reports_failed_insert(): void {
		$site_id = $this->create_site( 'some-group' );
		Syndication_Mock_Client::set_posts(
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

		$this->pull_site_despite_server_cast( $site_id );

		$this->assertSame(
			array( '-- Failed to add post https://example.com/?p=1: Content, title, and excerpt are empty.' ),
			WP_CLI::messages( 'warning' )
		);
		foreach ( WP_CLI::messages( 'log' ) as $line ) {
			$this->assertStringNotContainsString( 'New post', $line );
		}
	}

	/**
	 * A pulled post that can't be updated is reported with its error, not as post #1.
	 *
	 * @covers ::pull_site
	 */
	public function test_pull_site_reports_failed_update(): void {
		$GLOBALS['push_syndication_server']->push_syndicate_settings['update_pulled_posts'] = 'on';

		$site_id = $this->create_site( 'some-group' );
		$post_id = $this->factory()->post->create();
		update_post_meta( $post_id, 'syn_post_guid', 'https://example.com/?p=2' );
		Syndication_Mock_Client::set_posts(
			array(
				array(
					'post_guid'    => 'https://example.com/?p=2',
					'post_type'    => 'post',
					'post_title'   => '',
					'post_content' => '',
					'post_excerpt' => '',
				),
			)
		);

		$this->pull_site_despite_server_cast( $site_id );

		$this->assertSame(
			array( '-- Failed to update post https://example.com/?p=2: Content, title, and excerpt are empty.' ),
			WP_CLI::messages( 'warning' )
		);
		foreach ( WP_CLI::messages( 'log' ) as $line ) {
			$this->assertStringNotContainsString( 'Updated post', $line );
		}
	}
}
