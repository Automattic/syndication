<?php
/**
 * Tests that the settings screen can be saved by whoever syn_syndicate_cap lets in.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class SettingsCapabilityTest
 *
 * @covers WP_Push_Syndication_Server::settings_capability
 * @covers WP_Push_Syndication_Server::push_syndicate_settings_validate
 */
class SettingsCapabilityTest extends WPIntegrationTestCase {

	/**
	 * Give editors syndication access, and act as one.
	 */
	public function set_up(): void {
		parent::set_up();

		add_filter(
			'syn_syndicate_cap',
			function () {
				return 'edit_others_posts';
			}
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * The capability options.php checks before saving the settings group.
	 *
	 * @return string
	 */
	private function options_php_capability(): string {
		// As wp-admin/options.php does for the submitted option_page.
		return apply_filters( 'option_page_capability_push_syndicate_settings', 'manage_options' );
	}

	/**
	 * Saving needs the syndicate capability, not manage_options.
	 */
	public function test_saving_needs_the_syndicate_capability(): void {
		$this->assertSame( 'edit_others_posts', $this->options_php_capability() );
		$this->assertTrue( current_user_can( $this->options_php_capability() ) );
	}

	/**
	 * Without a custom capability, saving still needs manage_options.
	 */
	public function test_saving_needs_manage_options_by_default(): void {
		remove_all_filters( 'syn_syndicate_cap' );

		$this->assertSame( 'manage_options', $this->options_php_capability() );
		$this->assertFalse( current_user_can( $this->options_php_capability() ) );
	}

	/**
	 * Validation holds for a non-admin: selections, the interval minimum and the secret.
	 */
	public function test_validation_holds_for_a_non_admin(): void {
		global $push_syndication_server;

		$settings = $push_syndication_server->push_syndicate_settings_validate(
			array(
				'selected_post_types'      => array( 'post', 'no_such_post_type' ),
				'selected_pull_sitegroups' => array( 'no-such-sitegroup' ),
				'pull_time_interval'       => '10',
				'client_id'                => '<b>id</b>',
				'client_secret'            => 'new-secret',
			)
		);

		$this->assertSame( array( 'post' ), $settings['selected_post_types'] );
		$this->assertSame( array(), $settings['selected_pull_sitegroups'] );
		$this->assertSame( \WP_Push_Syndication_Server::MIN_PULL_TIME_INTERVAL, $settings['pull_time_interval'] );
		$this->assertSame( 'id', $settings['client_id'] );
		$this->assertNotSame( 'new-secret', $settings['client_secret'] );
		$this->assertSame( 'new-secret', push_syndicate_decrypt( $settings['client_secret'] ) );
	}

	/**
	 * The maximum pull attempts are clamped for a non-admin too.
	 *
	 * @covers WP_Push_Syndication_Server::validate_max_pull_attempts
	 */
	public function test_max_pull_attempts_are_clamped(): void {
		global $push_syndication_server;

		$this->assertSame( 100, $push_syndication_server->validate_max_pull_attempts( '5000' ) );
		$this->assertSame( 0, $push_syndication_server->validate_max_pull_attempts( '-3' ) );
	}
}
