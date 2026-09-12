<?php
/**
 * Tests for WP_Push_Syndication_Server::push_syndicate_settings_validate().
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;
use WP_Push_Syndication_Server;

/**
 * Class SettingsValidationTest
 *
 * @covers WP_Push_Syndication_Server::push_syndicate_settings_validate
 */
class SettingsValidationTest extends WPIntegrationTestCase {

	/**
	 * Server instance under test.
	 *
	 * @var WP_Push_Syndication_Server
	 */
	private $server;

	/**
	 * Sets up the test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		global $push_syndication_server;
		$this->server = $push_syndication_server;

		// Validation schedules pull content, which requires the syndicate capability.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Test unregistered post types are dropped from the selection.
	 */
	public function test_drops_unregistered_post_types(): void {
		$settings = $this->server->push_syndicate_settings_validate(
			array( 'selected_post_types' => array( 'post', 'no_such_post_type', '<script>' ) )
		);

		$this->assertSame( array( 'post' ), $settings['selected_post_types'] );
	}

	/**
	 * Test a nested array submitted as a post type is discarded without error.
	 */
	public function test_discards_non_string_post_types(): void {
		$settings = $this->server->push_syndicate_settings_validate(
			array( 'selected_post_types' => array( array( 'post' ), 1234 ) )
		);

		$this->assertSame( array(), $settings['selected_post_types'] );
	}

	/**
	 * Test only slugs of existing sitegroups survive validation.
	 */
	public function test_drops_unknown_sitegroups(): void {
		$term = $this->factory()->term->create_and_get(
			array(
				'taxonomy' => 'syn_sitegroup',
				'name'     => 'Test Group',
			)
		);

		$settings = $this->server->push_syndicate_settings_validate(
			array( 'selected_pull_sitegroups' => array( $term->slug, 'not-a-sitegroup' ) )
		);

		$this->assertSame( array( $term->slug ), $settings['selected_pull_sitegroups'] );
	}

	/**
	 * Test a non-numeric pull interval falls back to the floor rather than zero.
	 */
	public function test_non_numeric_pull_interval_is_floored(): void {
		$settings = $this->server->push_syndicate_settings_validate(
			array( 'pull_time_interval' => 'abc' )
		);

		$this->assertSame( WP_Push_Syndication_Server::MIN_PULL_TIME_INTERVAL, $settings['pull_time_interval'] );
	}

	/**
	 * Test an interval below the floor is raised to it.
	 */
	public function test_short_pull_interval_is_floored(): void {
		$settings = $this->server->push_syndicate_settings_validate(
			array( 'pull_time_interval' => '30' )
		);

		$this->assertSame( WP_Push_Syndication_Server::MIN_PULL_TIME_INTERVAL, $settings['pull_time_interval'] );
	}

	/**
	 * Test an omitted interval keeps the hourly default.
	 */
	public function test_omitted_pull_interval_defaults_to_hourly(): void {
		$settings = $this->server->push_syndicate_settings_validate( array() );

		$this->assertSame( 3600, $settings['pull_time_interval'] );
	}

	/**
	 * Test a valid interval is preserved.
	 */
	public function test_valid_pull_interval_is_preserved(): void {
		$settings = $this->server->push_syndicate_settings_validate(
			array( 'pull_time_interval' => '7200' )
		);

		$this->assertSame( 7200, $settings['pull_time_interval'] );
	}

	/**
	 * Test the checkbox options only ever store 'on' or 'off'.
	 */
	public function test_checkbox_options_are_normalised(): void {
		$settings = $this->server->push_syndicate_settings_validate(
			array(
				'delete_pushed_posts' => 'anything-else',
				'update_pulled_posts' => 'on',
			)
		);

		$this->assertSame( 'off', $settings['delete_pushed_posts'] );
		$this->assertSame( 'on', $settings['update_pulled_posts'] );
	}
}
