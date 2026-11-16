<?php
/**
 * Tests that the upgrade routine runs on every kind of request, once.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use ReflectionProperty;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class UpgradeOnInitTest
 *
 * @covers WP_Push_Syndication_Server::maybe_upgrade
 */
class UpgradeOnInitTest extends WPIntegrationTestCase {

	/**
	 * Times the upgrade routine has written syn_version.
	 *
	 * @var int
	 */
	private $upgrade_runs = 0;

	/**
	 * Start from an install on an old version, with no upgrade in progress.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'syn_version', '2.0.1' );
		delete_option( 'syn_upgrade.lock' );

		// upgrade() always finishes by writing syn_version, even when the value is unchanged.
		add_filter(
			'pre_update_option_syn_version',
			function ( $value ) {
				++$this->upgrade_runs;
				return $value;
			}
		);
		$this->upgrade_runs = 0;
	}

	/**
	 * Set the version a request read when it started.
	 *
	 * @param string $version Stored version.
	 */
	private function set_loaded_version( string $version ): void {
		global $push_syndication_server;

		$property = new ReflectionProperty( $push_syndication_server, 'version' );
		$property->setAccessible( true );
		$property->setValue( $push_syndication_server, $version );
	}

	/**
	 * A front-end, cron, WP-CLI or REST request upgrades: none of them fire admin_init.
	 */
	public function test_upgrades_on_init_without_admin(): void {
		$this->assertFalse( is_admin() );

		do_action( 'init' );

		$this->assertSame( SYNDICATION_VERSION, get_option( 'syn_version' ) );
		$this->assertSame( 1, $this->upgrade_runs );
	}

	/**
	 * The upgrade runs after the server's own init callback, which loads the stored version.
	 */
	public function test_runs_after_the_server_init_callback(): void {
		global $push_syndication_server;

		$this->assertGreaterThan(
			has_action( 'init', array( $push_syndication_server, 'init' ) ),
			has_action( 'init', array( $push_syndication_server, 'maybe_upgrade' ) )
		);
	}

	/**
	 * Admin requests no longer run it a second time from admin_init.
	 */
	public function test_admin_init_does_not_upgrade(): void {
		global $push_syndication_server;

		$this->set_loaded_version( '2.0.1' );
		$push_syndication_server->admin_init();

		$this->assertSame( 0, $this->upgrade_runs );
	}

	/**
	 * A request that read the old version before another one upgraded does not upgrade again.
	 */
	public function test_a_concurrent_request_does_not_run_the_upgrade_again(): void {
		global $push_syndication_server;

		$this->set_loaded_version( '2.0.1' );
		$push_syndication_server->maybe_upgrade();
		$this->assertSame( 1, $this->upgrade_runs );

		// A second request, which loaded syn_version before the first one updated it.
		$this->set_loaded_version( '2.0.1' );
		$push_syndication_server->maybe_upgrade();

		$this->assertSame( 1, $this->upgrade_runs );
	}

	/**
	 * A lock left behind by a request that died is taken over once it expires.
	 */
	public function test_an_expired_lock_does_not_block_the_upgrade(): void {
		global $push_syndication_server;

		add_option( 'syn_upgrade.lock', time() - HOUR_IN_SECONDS, '', false );

		$this->set_loaded_version( '2.0.1' );
		$push_syndication_server->maybe_upgrade();

		$this->assertSame( 1, $this->upgrade_runs );
	}

	/**
	 * An up-to-date install does no upgrade work.
	 */
	public function test_an_up_to_date_install_does_not_upgrade(): void {
		global $push_syndication_server;

		$this->set_loaded_version( SYNDICATION_VERSION );
		$push_syndication_server->maybe_upgrade();

		$this->assertSame( 0, $this->upgrade_runs );
		$this->assertFalse( get_option( 'syn_upgrade.lock' ) );
	}
}
