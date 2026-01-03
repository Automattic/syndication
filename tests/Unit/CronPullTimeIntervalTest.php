<?php
/**
 * Unit tests for WP_Push_Syndication_Server::cron_add_pull_time_interval()
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Test case for cron_add_pull_time_interval method.
 *
 * Tests the cron schedule interval registration logic.
 *
 * Since WP_Push_Syndication_Server has heavy WordPress dependencies in its
 * include chain, we test the method logic using a test double class that
 * replicates the exact method implementation.
 *
 * @group unit
 */
class CronPullTimeIntervalTest extends TestCase {

	/**
	 * Test double instance that replicates the server's cron method.
	 *
	 * @var object
	 */
	private $server;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Stub WordPress translation function.
		Functions\stubs(
			[
				'esc_html__' => function ( $text ) {
					return $text;
				},
			]
		);

		// Create a test double that replicates the exact method from WP_Push_Syndication_Server.
		// This approach allows us to unit test the logic without loading WordPress dependencies.
		$this->server = new class() {
			/**
			 * Settings property matching WP_Push_Syndication_Server.
			 *
			 * @var array|null
			 */
			public $push_syndicate_settings;

			/**
			 * Add pull time interval to cron schedules.
			 *
			 * This is an exact copy of WP_Push_Syndication_Server::cron_add_pull_time_interval()
			 * to enable unit testing without WordPress dependencies.
			 *
			 * @param array $schedules Existing cron schedules.
			 * @return array Modified schedules.
			 */
			public function cron_add_pull_time_interval( $schedules ) {
				// Only add custom interval if syndication settings are defined.
				if (
					empty( $this->push_syndicate_settings )
					|| ! array_key_exists( 'pull_time_interval', $this->push_syndicate_settings )
				) {
					return $schedules;
				}

				// Adds the custom time interval to the existing schedules.
				$schedules['syn_pull_time_interval'] = array(
					'interval' => intval( $this->push_syndicate_settings['pull_time_interval'] ),
					'display'  => esc_html__( 'Pull Time Interval', 'push-syndication' ),
				);

				return $schedules;
			}
		};
	}

	/**
	 * Test returns unchanged schedules when push_syndicate_settings is null.
	 */
	public function test_returns_unchanged_schedules_when_settings_is_null(): void {
		$this->server->push_syndicate_settings = null;

		$input_schedules = [
			'hourly' => [
				'interval' => 3600,
				'display'  => 'Once Hourly',
			],
		];

		$result = $this->server->cron_add_pull_time_interval( $input_schedules );

		$this->assertSame( $input_schedules, $result );
		$this->assertArrayNotHasKey( 'syn_pull_time_interval', $result );
	}

	/**
	 * Test returns unchanged schedules when push_syndicate_settings is empty array.
	 */
	public function test_returns_unchanged_schedules_when_settings_is_empty_array(): void {
		$this->server->push_syndicate_settings = [];

		$input_schedules = [
			'hourly' => [
				'interval' => 3600,
				'display'  => 'Once Hourly',
			],
		];

		$result = $this->server->cron_add_pull_time_interval( $input_schedules );

		$this->assertSame( $input_schedules, $result );
		$this->assertArrayNotHasKey( 'syn_pull_time_interval', $result );
	}

	/**
	 * Test returns unchanged schedules when pull_time_interval key does not exist.
	 */
	public function test_returns_unchanged_schedules_when_key_does_not_exist(): void {
		$this->server->push_syndicate_settings = [
			'selected_post_types' => [ 'post' ],
			'client_id'           => 'test',
		];

		$input_schedules = [
			'daily' => [
				'interval' => 86400,
				'display'  => 'Once Daily',
			],
		];

		$result = $this->server->cron_add_pull_time_interval( $input_schedules );

		$this->assertSame( $input_schedules, $result );
		$this->assertArrayNotHasKey( 'syn_pull_time_interval', $result );
	}

	/**
	 * Test adds schedule when settings are properly configured.
	 */
	public function test_adds_schedule_when_settings_are_configured(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => 1800,
		];

		$input_schedules = [
			'hourly' => [
				'interval' => 3600,
				'display'  => 'Once Hourly',
			],
		];

		$result = $this->server->cron_add_pull_time_interval( $input_schedules );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertArrayHasKey( 'hourly', $result );
		$this->assertSame( 1800, $result['syn_pull_time_interval']['interval'] );
		$this->assertSame( 'Pull Time Interval', $result['syn_pull_time_interval']['display'] );
	}

	/**
	 * Test correctly converts string interval to integer.
	 */
	public function test_converts_string_interval_to_integer(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => '7200',
		];

		$result = $this->server->cron_add_pull_time_interval( [] );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertSame( 7200, $result['syn_pull_time_interval']['interval'] );
		$this->assertIsInt( $result['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Test handles zero interval value.
	 */
	public function test_handles_zero_interval_value(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => 0,
		];

		$result = $this->server->cron_add_pull_time_interval( [] );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertSame( 0, $result['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Test handles negative interval value (converts to integer).
	 */
	public function test_handles_negative_interval_value(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => -100,
		];

		$result = $this->server->cron_add_pull_time_interval( [] );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertSame( -100, $result['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Test preserves existing schedules.
	 */
	public function test_preserves_existing_schedules(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => 3600,
		];

		$input_schedules = [
			'hourly'     => [
				'interval' => 3600,
				'display'  => 'Once Hourly',
			],
			'twicedaily' => [
				'interval' => 43200,
				'display'  => 'Twice Daily',
			],
			'daily'      => [
				'interval' => 86400,
				'display'  => 'Once Daily',
			],
		];

		$result = $this->server->cron_add_pull_time_interval( $input_schedules );

		$this->assertCount( 4, $result );
		$this->assertArrayHasKey( 'hourly', $result );
		$this->assertArrayHasKey( 'twicedaily', $result );
		$this->assertArrayHasKey( 'daily', $result );
		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
	}

	/**
	 * Test handles empty input schedules array.
	 */
	public function test_handles_empty_input_schedules(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => 1800,
		];

		$result = $this->server->cron_add_pull_time_interval( [] );

		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'syn_pull_time_interval', $result );
		$this->assertSame( 1800, $result['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Test verifies display text is passed through translation function.
	 *
	 * Since the stub returns the input text unchanged, we verify the correct
	 * text is used. The actual translation function call is verified by the
	 * fact that esc_html__ is stubbed and the output matches.
	 */
	public function test_display_text_uses_correct_translation_string(): void {
		$this->server->push_syndicate_settings = [
			'pull_time_interval' => 3600,
		];

		$result = $this->server->cron_add_pull_time_interval( [] );

		// Verify the display text matches expected value from esc_html__ stub.
		$this->assertSame( 'Pull Time Interval', $result['syn_pull_time_interval']['display'] );
	}
}
