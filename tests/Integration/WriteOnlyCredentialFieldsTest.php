<?php
/**
 * Tests that stored credentials are never rendered back into admin form fields.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class WriteOnlyCredentialFieldsTest
 *
 * Regression guard for VIPPLUG-65: decrypted secrets were printed into the value
 * attribute of the site edit screen and settings page fields on every load. The
 * fields are now write-only - blank means "keep what is stored".
 *
 * @covers Syndication_WP_XMLRPC_Client::display_settings
 * @covers Syndication_WP_XMLRPC_Client::save_settings
 * @covers Syndication_WP_REST_Client::display_settings
 * @covers Syndication_WP_REST_Client::save_settings
 * @covers WP_Push_Syndication_Server::push_syndicate_settings_validate
 */
class WriteOnlyCredentialFieldsTest extends WPIntegrationTestCase {

	/**
	 * A site post to hold the credentials.
	 *
	 * @var \WP_Post
	 */
	private $site;

	/**
	 * The server settings as they were before a test changed them.
	 *
	 * @var array
	 */
	private $original_settings;

	/**
	 * Create the site post used by each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->site              = self::factory()->post->create_and_get( array( 'post_type' => 'syn_site' ) );
		$this->original_settings = $GLOBALS['push_syndication_server']->push_syndicate_settings;
	}

	/**
	 * Clean up superglobals touched by save_settings().
	 */
	public function tear_down(): void {
		unset( $_POST['site_password'], $_POST['site_token'] );

		$GLOBALS['push_syndication_server']->push_syndicate_settings = $this->original_settings;

		parent::tear_down();
	}

	/**
	 * The XML-RPC password must not appear in the rendered form.
	 */
	public function test_xmlrpc_password_is_not_rendered(): void {
		update_post_meta( $this->site->ID, 'syn_site_password', push_syndicate_encrypt( 'hunter2' ) );

		ob_start();
		\Syndication_WP_XMLRPC_Client::display_settings( $this->site );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'hunter2', $html );
	}

	/**
	 * A blank password submission must leave the stored password untouched.
	 */
	public function test_blank_xmlrpc_password_keeps_stored_value(): void {
		update_post_meta( $this->site->ID, 'syn_site_password', push_syndicate_encrypt( 'hunter2' ) );

		$_POST['site_url']      = 'https://example.com';
		$_POST['site_password'] = '';

		\Syndication_WP_XMLRPC_Client::save_settings( $this->site->ID );

		$stored = push_syndicate_decrypt( get_post_meta( $this->site->ID, 'syn_site_password', true ) );
		$this->assertSame( 'hunter2', $stored );
	}

	/**
	 * A non-blank password submission must replace the stored password.
	 */
	public function test_submitted_xmlrpc_password_is_stored(): void {
		update_post_meta( $this->site->ID, 'syn_site_password', push_syndicate_encrypt( 'hunter2' ) );

		$_POST['site_url']      = 'https://example.com';
		$_POST['site_password'] = 'correct-horse';

		\Syndication_WP_XMLRPC_Client::save_settings( $this->site->ID );

		$stored = push_syndicate_decrypt( get_post_meta( $this->site->ID, 'syn_site_password', true ) );
		$this->assertSame( 'correct-horse', $stored );
	}

	/**
	 * The WordPress.com bearer token must not appear in the rendered form.
	 */
	public function test_rest_token_is_not_rendered(): void {
		update_post_meta( $this->site->ID, 'syn_site_token', push_syndicate_encrypt( 'bearer-token-123' ) );

		ob_start();
		\Syndication_WP_REST_Client::display_settings( $this->site );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'bearer-token-123', $html );
	}

	/**
	 * A blank token submission must leave the stored token untouched.
	 */
	public function test_blank_rest_token_keeps_stored_value(): void {
		update_post_meta( $this->site->ID, 'syn_site_token', push_syndicate_encrypt( 'bearer-token-123' ) );

		$_POST['site_token'] = '';

		\Syndication_WP_REST_Client::save_settings( $this->site->ID );

		$stored = push_syndicate_decrypt( get_post_meta( $this->site->ID, 'syn_site_token', true ) );
		$this->assertSame( 'bearer-token-123', $stored );
	}

	/**
	 * A blank client secret submission must leave the stored secret untouched.
	 */
	public function test_blank_client_secret_keeps_stored_value(): void {
		$server                          = $GLOBALS['push_syndication_server'];
		$server->push_syndicate_settings = array( 'client_secret' => 'stored-secret' );

		$settings = $server->push_syndicate_settings_validate(
			array(
				'client_id'     => 'abc',
				'client_secret' => '',
			)
		);

		$this->assertSame( 'stored-secret', $settings['client_secret'] );
	}

	/**
	 * A submitted client secret must replace the stored secret.
	 */
	public function test_submitted_client_secret_is_stored(): void {
		$server                          = $GLOBALS['push_syndication_server'];
		$server->push_syndicate_settings = array( 'client_secret' => 'stored-secret' );

		$settings = $server->push_syndicate_settings_validate(
			array(
				'client_id'     => 'abc',
				'client_secret' => 'new-secret',
			)
		);

		$this->assertSame( 'new-secret', $settings['client_secret'] );
	}
}
