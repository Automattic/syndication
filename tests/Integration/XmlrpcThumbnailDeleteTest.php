<?php
/**
 * Tests that thumbnail tracking survives a failed remote delete.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcThumbnailDeleteTest
 *
 * Runs the real XML-RPC client with the remote site faked at the HTTP layer.
 *
 * @covers Syndication_WP_XMLRPC_Client::post_push_send_thumbnail
 */
class XmlrpcThumbnailDeleteTest extends WPIntegrationTestCase {

	/**
	 * Whether syndication.deleteThumbnail fails on the fake remote.
	 *
	 * @var bool
	 */
	private $delete_fails = false;

	/**
	 * The syn_site post ID.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * A local post whose featured image was removed after it was pushed.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up a site, a post with thumbnail tracking, and the fake remote.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->delete_fails = false;

		$this->site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $this->site_id, 'syn_site_url', 'http://remote.example' );

		$this->post_id = self::factory()->post->create();
		update_post_meta( $this->post_id, '_syn_push_thumb__thumbnail_id', array( $this->site_id => 123 ) );

		add_filter(
			'pre_http_request',
			function () {
				if ( $this->delete_fails ) {
					$error = new \IXR_Error( 403, 'Could not remove post thumbnail.' );
					$body  = $error->getXml();
				} else {
					$body = '<?xml version="1.0"?><methodResponse><params><param><value><boolean>1</boolean></value></param></params></methodResponse>';
				}

				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}
		);
	}

	/**
	 * Tracking is kept when the remote delete fails, so the next push retries it.
	 */
	public function test_tracking_is_kept_when_remote_delete_fails(): void {
		$this->delete_fails = true;

		( new \Syndication_WP_XMLRPC_Client( $this->site_id ) )->post_push_send_thumbnail( 99, $this->post_id );

		$this->assertSame( array( $this->site_id => 123 ), get_post_meta( $this->post_id, '_syn_push_thumb__thumbnail_id', true ) );
	}

	/**
	 * Tracking is cleared once the remote confirms the delete.
	 */
	public function test_tracking_is_cleared_when_remote_delete_succeeds(): void {
		( new \Syndication_WP_XMLRPC_Client( $this->site_id ) )->post_push_send_thumbnail( 99, $this->post_id );

		$this->assertSame( array(), get_post_meta( $this->post_id, '_syn_push_thumb__thumbnail_id', true ) );
	}
}
