<?php
/**
 * Tests that the XML-RPC thumbnail methods only write meta keys the caller may edit.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcThumbnailMetaKeyTest
 *
 * syndication.addThumbnail and syndication.deleteThumbnail take the meta key from
 * the caller, so they must apply the same protected meta rules as core's own
 * custom fields handling.
 *
 * @covers Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_add_thumbnail
 * @covers Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_delete_thumbnail
 */
class XmlrpcThumbnailMetaKeyTest extends WPIntegrationTestCase {

	/**
	 * Password of the author making the calls.
	 *
	 * @var string
	 */
	private const PASSWORD = 'test-password';

	/**
	 * A post the author owns.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Whether an outbound HTTP request was attempted.
	 *
	 * @var bool
	 */
	private $http_attempted = false;

	/**
	 * Set up the XML-RPC server, an author with a post, and block outbound HTTP.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-includes/class-wp-xmlrpc-server.php';
		$GLOBALS['wp_xmlrpc_server'] = new \wp_xmlrpc_server();

		$author_id = self::factory()->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'syndication-author',
				'user_pass'  => self::PASSWORD,
			)
		);
		wp_set_current_user( $author_id );

		$this->post_id = self::factory()->post->create( array( 'post_author' => $author_id ) );

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
	 * Adding a thumbnail under a protected meta key is refused before the image is fetched.
	 */
	public function test_add_thumbnail_refuses_protected_meta_key(): void {
		$result = \Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_add_thumbnail(
			array( 1, 'syndication-author', self::PASSWORD, $this->post_id, 'http://example.com/image.jpg', '_protected_key', array(), '' )
		);

		$this->assertInstanceOf( \IXR_Error::class, $result );
		$this->assertFalse( $this->http_attempted, 'The image should not be fetched for a refused meta key.' );
	}

	/**
	 * Adding a featured image to someone else's post is refused before the image is fetched.
	 */
	public function test_add_thumbnail_refuses_post_the_caller_cannot_edit(): void {
		$other_post_id = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) );

		$result = \Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_add_thumbnail(
			array( 1, 'syndication-author', self::PASSWORD, $other_post_id, 'http://example.com/image.jpg', '_thumbnail_id', array(), '' )
		);

		$this->assertInstanceOf( \IXR_Error::class, $result );
		$this->assertFalse( $this->http_attempted, 'The image should not be fetched for a post the caller cannot edit.' );
	}

	/**
	 * Removing a thumbnail under a protected meta key is refused and leaves the meta in place.
	 */
	public function test_delete_thumbnail_refuses_protected_meta_key(): void {
		update_post_meta( $this->post_id, '_protected_key', 'set by an editor' );

		$result = \Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_delete_thumbnail(
			array( 1, 'syndication-author', self::PASSWORD, $this->post_id, '_protected_key' )
		);

		$this->assertInstanceOf( \IXR_Error::class, $result );
		$this->assertSame( 'set by an editor', get_post_meta( $this->post_id, '_protected_key', true ) );
	}

	/**
	 * An unprotected thumbnail key, as used by Multiple Post Thumbnails, can still be removed.
	 */
	public function test_delete_thumbnail_allows_unprotected_meta_key(): void {
		update_post_meta( $this->post_id, 'post_secondary-image_thumbnail_id', 123 );

		$result = \Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_delete_thumbnail(
			array( 1, 'syndication-author', self::PASSWORD, $this->post_id, 'post_secondary-image_thumbnail_id' )
		);

		$this->assertTrue( $result );
		$this->assertSame( '', get_post_meta( $this->post_id, 'post_secondary-image_thumbnail_id', true ) );
	}

	/**
	 * The featured image itself can still be removed by the post's author.
	 */
	public function test_delete_thumbnail_allows_featured_image(): void {
		update_post_meta( $this->post_id, '_thumbnail_id', 123 );

		$result = \Syndication_WP_XMLRPC_Client_Extensions::xmlrpc_delete_thumbnail(
			array( 1, 'syndication-author', self::PASSWORD, $this->post_id, '_thumbnail_id' )
		);

		$this->assertTrue( $result );
		$this->assertSame( '', get_post_meta( $this->post_id, '_thumbnail_id', true ) );
	}
}
