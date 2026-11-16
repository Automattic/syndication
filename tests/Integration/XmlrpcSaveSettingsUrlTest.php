<?php
/**
 * Tests that the XML-RPC site settings reject an invalid URL before saving.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcSaveSettingsUrlTest
 *
 * @covers Syndication_WP_XMLRPC_Client::save_settings
 */
class XmlrpcSaveSettingsUrlTest extends WPIntegrationTestCase {

	/**
	 * Clean up superglobals touched by save_settings().
	 */
	public function tear_down(): void {
		unset( $_POST['site_url'], $_POST['site_username'], $_POST['site_password'] );
		parent::tear_down();
	}

	/**
	 * An invalid URL is rejected and the stored URL is kept.
	 */
	public function test_invalid_url_is_not_saved(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_site_url', 'https://good.example' );

		$_POST['site_url'] = 'not a url';

		$this->assertFalse( \Syndication_WP_XMLRPC_Client::save_settings( $site_id ) );
		$this->assertSame( 'https://good.example', get_post_meta( $site_id, 'syn_site_url', true ) );
	}

	/**
	 * A valid URL is saved.
	 */
	public function test_valid_url_is_saved(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$_POST['site_url'] = 'https://remote.example/xmlrpc.php';

		$this->assertTrue( \Syndication_WP_XMLRPC_Client::save_settings( $site_id ) );
		$this->assertSame( 'https://remote.example', get_post_meta( $site_id, 'syn_site_url', true ) );
	}
}
