<?php
/**
 * Tests for WP_Push_Syndication_Server::cron_add_pull_time_interval().
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;
use WP_Push_Syndication_Server;

/**
 * Class CronPullTimeIntervalTest
 *
 * @covers WP_Push_Syndication_Server::cron_add_pull_time_interval
 */
class CronPullTimeIntervalTest extends WPIntegrationTestCase {

	/**
	 * Server instance under test.
	 *
	 * @var WP_Push_Syndication_Server
	 */
	private $server;

	/**
	 * Settings to restore after each test.
	 *
	 * @var array
	 */
	private $original_settings;

	/**
	 * Sets up the test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		global $push_syndication_server;
		$this->server            = $push_syndication_server;
		$this->original_settings = $this->server->push_syndicate_settings;
	}

	/**
	 * Restores the server settings.
	 */
	public function tear_down(): void {
		$this->server->push_syndicate_settings = $this->original_settings;

		parent::tear_down();
	}

	/**
	 * Test schedules are returned unchanged when the settings are not set.
	 *
	 * @dataProvider data_settings_without_an_interval
	 *
	 * @param mixed $settings Settings to place on the server.
	 */
	public function test_returns_unchanged_schedules_without_an_interval( $settings ): void {
		$this->server->push_syndicate_settings = $settings;

		$schedules = array(
			'hourly' => array(
				'interval' => 3600,
				'display'  => 'Once Hourly',
			),
		);

		$result = $this->server->cron_add_pull_time_interval( $schedules );

		$this->assertSame( $schedules, $result );
		$this->assertArrayNotHasKey( 'syn_pull_time_interval', $result );
	}

	/**
	 * Provides settings that should not produce a custom schedule.
	 *
	 * @return array[]
	 */
	public function data_settings_without_an_interval(): array {
		return array(
			'null settings'           => array( null ),
			'empty settings'          => array( array() ),
			'interval key is missing' => array( array( 'client_id' => 'test' ) ),
		);
	}

	/**
	 * Test the stored interval is used, floored at the minimum.
	 *
	 * Anything at or below the floor would make the pull event due on every
	 * cron run. Settings saved before validation was tightened can still hold
	 * a non-numeric string, which previously cast to an interval of zero.
	 *
	 * @dataProvider data_intervals
	 *
	 * @param mixed $stored   Interval as held in the settings.
	 * @param int   $expected Interval expected on the registered schedule.
	 */
	public function test_registers_interval_floored_at_the_minimum( $stored, int $expected ): void {
		$this->server->push_syndicate_settings = array( 'pull_time_interval' => $stored );

		$result = $this->server->cron_add_pull_time_interval( array() );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertSame( $expected, $result['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Provides stored intervals and the interval each should register.
	 *
	 * @return array[]
	 */
	public function data_intervals(): array {
		$minimum = WP_Push_Syndication_Server::MIN_PULL_TIME_INTERVAL;

		return array(
			'integer above the floor' => array( 1800, 1800 ),
			'string above the floor'  => array( '7200', 7200 ),
			'below the floor'         => array( 30, $minimum ),
			'zero'                    => array( 0, $minimum ),
			'negative'                => array( -100, $minimum ),
			'non-numeric string'      => array( 'not-a-number', $minimum ),
		);
	}

	/**
	 * Test the custom schedule is added alongside the existing ones.
	 */
	public function test_preserves_existing_schedules(): void {
		$this->server->push_syndicate_settings = array( 'pull_time_interval' => 3600 );

		$schedules = wp_get_schedules();

		$result = $this->server->cron_add_pull_time_interval( $schedules );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertSame( 'Pull Time Interval', $result['syn_pull_time_interval']['display'] );

		foreach ( array_keys( $schedules ) as $existing ) {
			$this->assertArrayHasKey( $existing, $result );
		}
	}

	/**
	 * Test the schedule is registered on the cron_schedules filter.
	 *
	 * This is the behaviour that actually matters: the method is only ever
	 * reached through the filter, so a broken hook registration would leave
	 * scheduled pulls without a schedule to run on.
	 */
	public function test_schedule_is_registered_on_the_cron_schedules_filter(): void {
		$this->server->push_syndicate_settings = array( 'pull_time_interval' => 1800 );

		add_filter( 'cron_schedules', array( $this->server, 'cron_add_pull_time_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected

		$schedules = wp_get_schedules();

		remove_filter( 'cron_schedules', array( $this->server, 'cron_add_pull_time_interval' ) );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $schedules );
		$this->assertSame( 1800, $schedules['syn_pull_time_interval']['interval'] );
	}
}
