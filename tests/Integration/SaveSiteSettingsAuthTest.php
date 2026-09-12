<?php
/**
 * Tests that save_site_settings() only ever writes for an authorised site save.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class SaveSiteSettingsAuthTest
 *
 * Regression guard: save_site_settings() is hooked to save_post for every post type
 * and used to gate only on a nonce whose action was shared with the syndicate metabox,
 * so anyone able to see that metabox held a nonce valid for this save path.
 *
 * @covers WP_Push_Syndication_Server::save_site_settings
 */
class SaveSiteSettingsAuthTest extends WPIntegrationTestCase {

	/**
	 * Set up a site post, a registered transport, and admin-authored POST data.
	 */
	public function set_up(): void {
		parent::set_up();

		global $push_syndication_server, $post;

		$push_syndication_server->push_syndicate_transports = array(
			'Mock' => array(
				'name'  => 'Mock',
				'modes' => array( 'pull' ),
			),
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post = get_post(
			self::factory()->post->create(
				array(
					'post_type'   => 'syn_site',
					'post_status' => 'publish',
				)
			)
		);

		$_POST = array(
			'site_settings_noncename' => wp_create_nonce( 'syn_save_site_settings' ),
			'transport_type'          => 'Mock',
			'site_enabled'            => 'on',
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * A valid site save still writes the settings.
	 */
	public function test_authorised_site_save_writes_settings(): void {
		global $push_syndication_server, $post;

		$push_syndication_server->save_site_settings();

		$this->assertSame( 'Mock', get_post_meta( $post->ID, 'syn_transport_type', true ) );
	}

	/**
	 * The syndicate metabox nonce is not valid for the site settings save path.
	 */
	public function test_syndicate_metabox_nonce_is_rejected(): void {
		global $push_syndication_server, $post;

		$server_file = ( new \ReflectionClass( $push_syndication_server ) )->getFileName();

		$_POST['site_settings_noncename'] = wp_create_nonce( plugin_basename( $server_file ) );

		$push_syndication_server->save_site_settings();

		$this->assertSame( '', get_post_meta( $post->ID, 'syn_transport_type', true ) );
	}

	/**
	 * A user without the syndication capability cannot write site settings.
	 */
	public function test_user_without_capability_cannot_write(): void {
		global $push_syndication_server, $post;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$push_syndication_server->save_site_settings();

		$this->assertSame( '', get_post_meta( $post->ID, 'syn_transport_type', true ) );
	}

	/**
	 * Saving an ordinary post never writes site settings, even with a valid nonce.
	 */
	public function test_other_post_types_are_ignored(): void {
		global $push_syndication_server, $post;

		$post = get_post( self::factory()->post->create() );

		$push_syndication_server->save_site_settings();

		$this->assertSame( '', get_post_meta( $post->ID, 'syn_transport_type', true ) );
	}

	/**
	 * An unregistered transport type is never persisted.
	 */
	public function test_unregistered_transport_is_rejected(): void {
		global $push_syndication_server, $post;

		$_POST['transport_type'] = 'Evil';

		$push_syndication_server->save_site_settings();

		$this->assertSame( '', get_post_meta( $post->ID, 'syn_transport_type', true ) );
	}
}
