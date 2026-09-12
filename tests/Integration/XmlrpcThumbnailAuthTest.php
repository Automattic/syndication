<?php
/**
 * Tests that the custom XML-RPC thumbnail methods authenticate before fetching URLs.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcThumbnailAuthTest
 *
 * Regression guard for the unauthenticated blind SSRF in syndication.addThumbnail
 * and syndication.postGalleryImage: both fetched the caller-supplied URL before
 * any authentication or capability check took place.
 *
 * @covers Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_add_thumbnail
 * @covers Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_post_gallery_images
 */
class XmlrpcThumbnailAuthTest extends WPIntegrationTestCase {

	/**
	 * Whether an outbound HTTP request was attempted.
	 *
	 * @var bool
	 */
	private $http_attempted = false;

	/**
	 * Set up the XML-RPC server and block (and record) outbound HTTP requests.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-includes/class-wp-xmlrpc-server.php';
		$GLOBALS['wp_xmlrpc_server'] = new \wp_xmlrpc_server();

		$this->http_attempted = false;

		add_filter(
			'pre_http_request',
			function () {
				$this->http_attempted = true;
				return new \WP_Error( 'blocked', 'Blocked in tests.' );
			}
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['wp_xmlrpc_server'] );
		parent::tear_down();
	}

	/**
	 * Arguments for each method, keyed by method name, with the URL in the right position.
	 *
	 * @param string $username Username to pass.
	 * @param string $password Password to pass.
	 * @return array<string, array<int, mixed>> Callable name => args.
	 */
	private function method_args( string $username, string $password ): array {
		$url = 'http://169.254.169.254/latest/meta-data/';

		return array(
			'xmlrpc_add_thumbnail'       => array( 1, $username, $password, 1, $url, '_thumbnail_id', array(), '' ),
			'xmlrpc_post_gallery_images' => array( 1, $username, $password, $url, array(), '' ),
		);
	}

	/**
	 * An unauthenticated caller gets an error and triggers no outbound request.
	 */
	public function test_unauthenticated_call_makes_no_request(): void {
		foreach ( $this->method_args( 'nobody', 'wrong-password' ) as $method => $args ) {
			$result = \Syndication_WP_XMLRPC_Client_Extensions::$method( $args );

			$this->assertInstanceOf( \IXR_Error::class, $result, $method . ' should reject unauthenticated callers.' );
			$this->assertFalse( $this->http_attempted, $method . ' should not fetch the URL before authenticating.' );
		}
	}

	/**
	 * A logged-in user without upload_files gets an error and triggers no outbound request.
	 */
	public function test_user_without_upload_files_makes_no_request(): void {
		$password = 'test-password';
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role'      => 'subscriber',
					'user_login' => 'syndication-subscriber',
					'user_pass' => $password,
				)
			)
		);

		foreach ( $this->method_args( 'syndication-subscriber', $password ) as $method => $args ) {
			$result = \Syndication_WP_XMLRPC_Client_Extensions::$method( $args );

			$this->assertInstanceOf( \IXR_Error::class, $result, $method . ' should reject users without upload_files.' );
			$this->assertFalse( $this->http_attempted, $method . ' should not fetch the URL before the capability check.' );
		}
	}
}
