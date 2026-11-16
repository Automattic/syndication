<?php
/**
 * Tests the message shown when a site's connection test fails.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class SiteConnectionTest
 *
 * @covers WP_Push_Syndication_Server::save_site_settings
 */
class SiteConnectionTest extends WPIntegrationTestCase {

	/**
	 * The server's transports before the test changed them.
	 *
	 * @var array
	 */
	private $original_transports;

	/**
	 * Remember the server's transports.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_transports = $GLOBALS['push_syndication_server']->push_syndicate_transports;
	}

	/**
	 * Restore the server and superglobals.
	 */
	public function tear_down(): void {
		$_POST = array();
		$GLOBALS['push_syndication_server']->push_syndicate_transports = $this->original_transports;
		parent::tear_down();
	}

	/**
	 * The XML-RPC client's specific error message is shown, not the generic one.
	 */
	public function test_client_error_message_is_not_overwritten(): void {
		$server                            = $GLOBALS['push_syndication_server'];
		$server->push_syndicate_transports = array(
			'WP_XMLRPC' => array(
				'name'  => 'WordPress XMLRPC',
				'modes' => array( 'push' ),
			),
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// The remote rejects the login.
		add_filter(
			'pre_http_request',
			static function () {
				$error = new \IXR_Error( 403, 'Incorrect username or password.' );

				return array(
					'headers'  => array(),
					'body'     => $error->getXml(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}
		);

		$_POST = array(
			'site_settings_noncename' => wp_create_nonce( 'syn_save_site_settings' ),
			'transport_type'          => 'WP_XMLRPC',
			'site_enabled'            => 'on',
			'site_url'                => 'http://remote.example',
			'site_username'           => 'user',
			'site_password'           => 'wrong',
		);

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$server->save_site_settings( $site_id );

		$location = apply_filters( 'redirect_post_location', 'http://example.org/wp-admin/post.php?message=1', $site_id );

		$this->assertStringContainsString( 'message=303', $location );
		$this->assertSame( 'off', get_post_meta( $site_id, 'syn_site_enabled', true ) );
	}
}
