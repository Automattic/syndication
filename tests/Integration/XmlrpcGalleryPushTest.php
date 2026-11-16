<?php
/**
 * Tests pushing posts with [gallery] shortcodes over XML-RPC.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcGalleryPushTest
 *
 * Runs the real XML-RPC client with the remote site faked at the HTTP layer.
 *
 * @covers Syndication_WP_XMLRPC_Client::new_post
 * @covers Syndication_WP_XMLRPC_Client::edit_post
 * @covers Syndication_WP_XMLRPC_Client::syndicate_gallery_images
 */
class XmlrpcGalleryPushTest extends WPIntegrationTestCase {

	/**
	 * Requests the fake remote received: method name => params.
	 *
	 * @var array<string, array>
	 */
	private $calls = array();

	/**
	 * Whether syndication.postGalleryImage fails on the fake remote.
	 *
	 * @var bool
	 */
	private $gallery_upload_fails = false;

	/**
	 * The client under test.
	 *
	 * @var \Syndication_WP_XMLRPC_Client
	 */
	private $client;

	/**
	 * Set up a site, its client and the fake remote.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->calls                = array();
		$this->gallery_upload_fails = false;

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_site_url', 'http://remote.example' );
		update_post_meta( $site_id, 'syn_site_username', 'user' );
		update_post_meta( $site_id, 'syn_site_password', push_syndicate_encrypt( 'pass' ) );

		add_filter( 'pre_http_request', array( $this, 'fake_remote' ), 10, 2 );

		$this->client = new \Syndication_WP_XMLRPC_Client( $site_id );
	}

	/**
	 * Answer XML-RPC requests as a remote WordPress site would.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $args    Request arguments.
	 * @return array Fake HTTP response.
	 */
	public function fake_remote( $preempt, $args ) {
		$message = new \IXR_Message( $args['body'] );
		$message->parse();
		$this->calls[ $message->methodName ] = $message->params;

		if ( 'syndication.postGalleryImage' === $message->methodName && $this->gallery_upload_fails ) {
			$error = new \IXR_Error( 500, 'Upload failed.' );
			$body  = $error->getXml();
		} else {
			$responses = array(
				'syndication.postGalleryImage' => 77,
				'wp.newPost'                   => '99',
				'wp.getPost'                   => array(
					'post_id'       => '99',
					'custom_fields' => array(),
				),
			);
			$value     = new \IXR_Value( isset( $responses[ $message->methodName ] ) ? $responses[ $message->methodName ] : true );
			$body      = '<?xml version="1.0"?><methodResponse><params><param><value>' . $value->getXml() . '</value></param></params></methodResponse>';
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

	/**
	 * Create a local post with the given content.
	 *
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	private function post_with( string $content ): int {
		return self::factory()->post->create( array( 'post_content' => $content ) );
	}

	/**
	 * Create a local image attachment.
	 *
	 * @return int Attachment ID.
	 */
	private function attachment(): int {
		return self::factory()->attachment->create_object( 'image.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
	}

	/**
	 * A [gallery] shortcode without ids is pushed unchanged.
	 */
	public function test_gallery_without_ids_is_pushed_unchanged(): void {
		$result = $this->client->new_post( $this->post_with( '[gallery]' ) );

		$this->assertSame( 99, $result );
		$this->assertSame( '[gallery]', $this->calls['wp.newPost'][3]['post_content'] );
	}

	/**
	 * In mixed content, only the gallery with ids gets the remote IDs.
	 */
	public function test_mixed_galleries_replace_only_the_one_with_ids(): void {
		$attachment_id = $this->attachment();

		$this->client->new_post( $this->post_with( '[gallery] [gallery ids="' . $attachment_id . '"]' ) );

		$this->assertSame( '[gallery] [gallery ids="77"]', $this->calls['wp.newPost'][3]['post_content'] );
	}
}
