<?php
/**
 * Tests that each XML-RPC push sends its thumbnail only to its own site.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcThumbnailPushTest
 *
 * Runs push_content() against two XML-RPC sites, with both remotes faked at
 * the HTTP layer.
 *
 * @covers Syndication_WP_XMLRPC_Client::new_post
 * @covers Syndication_WP_XMLRPC_Client::edit_post
 * @covers Syndication_WP_XMLRPC_Client::post_push_send_thumbnail
 */
class XmlrpcThumbnailPushTest extends WPIntegrationTestCase {

	/**
	 * Remote post ID each fake remote gives a new post, keyed by host.
	 */
	const REMOTE_POST_IDS = array(
		'remote-a.example' => 501,
		'remote-b.example' => 502,
	);

	/**
	 * syndication.addThumbnail calls received, as host and remote post ID.
	 *
	 * @var array<int, array{0: string, 1: int}>
	 */
	private $thumbnail_calls = array();

	/**
	 * Hosts whose syndication.addThumbnail fails.
	 *
	 * @var string[]
	 */
	private $failing_thumbnail_hosts = array();

	/**
	 * The two syn_site posts.
	 *
	 * @var \WP_Post[]
	 */
	private $sites = array();

	/**
	 * Set up two sites and their fake remotes.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->thumbnail_calls         = array();
		$this->failing_thumbnail_hosts = array();
		$this->sites                   = array();

		foreach ( array_keys( self::REMOTE_POST_IDS ) as $host ) {
			$site = self::factory()->post->create_and_get( array( 'post_type' => 'syn_site' ) );
			update_post_meta( $site->ID, 'syn_transport_type', 'WP_XMLRPC' );
			update_post_meta( $site->ID, 'syn_site_url', 'http://' . $host );
			update_post_meta( $site->ID, 'syn_site_username', 'user' );
			update_post_meta( $site->ID, 'syn_site_password', push_syndicate_encrypt( 'pass' ) );
			$this->sites[] = $site;
		}

		add_filter( 'pre_http_request', array( $this, 'fake_remote' ), 10, 3 );
	}

	/**
	 * Answer XML-RPC requests as the remote WordPress site for the URL's host would.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 * @return array Fake HTTP response.
	 */
	public function fake_remote( $preempt, $args, $url ) {
		$host    = wp_parse_url( $url, PHP_URL_HOST );
		$message = new \IXR_Message( $args['body'] );
		$message->parse();

		$response = true;
		switch ( $message->methodName ) {
			case 'wp.getPosts':
				$response = array();
				break;
			case 'wp.newPost':
				$response = (string) self::REMOTE_POST_IDS[ $host ];
				break;
			case 'syndication.addThumbnail':
				$this->thumbnail_calls[] = array( $host, (int) $message->params[3] );
				$response                = 900;
				break;
		}

		if ( 'syndication.addThumbnail' === $message->methodName && in_array( $host, $this->failing_thumbnail_hosts, true ) ) {
			$error = new \IXR_Error( 500, 'Upload failed.' );
			$body  = $error->getXml();
		} else {
			$value = new \IXR_Value( $response );
			$body  = '<?xml version="1.0"?><methodResponse><params><param><value>' . $value->getXml() . '</value></param></params></methodResponse>';
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
	 * Create a local post with a featured image.
	 *
	 * @return int Post ID.
	 */
	private function post_with_thumbnail(): int {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'post_uniqueid', uniqid() );
		update_post_meta(
			$post_id,
			'_thumbnail_id',
			self::factory()->attachment->create_object( 'image.jpg', $post_id, array( 'post_mime_type' => 'image/jpeg' ) )
		);
		return $post_id;
	}

	/**
	 * Push a post to both sites.
	 *
	 * @param int $post_id Post ID.
	 */
	private function push( int $post_id ): void {
		$GLOBALS['push_syndication_server']->push_content(
			array(
				'post_ID'        => $post_id,
				'selected_sites' => $this->sites,
				'removed_sites'  => array(),
			)
		);
	}

	/**
	 * When the first site's thumbnail fails, the second site's push doesn't retry it against the second site's post.
	 */
	public function test_each_site_gets_only_its_own_thumbnail_in_one_push(): void {
		$this->failing_thumbnail_hosts = array( 'remote-a.example' );

		$this->push( $this->post_with_thumbnail() );

		$this->assertSame(
			array(
				array( 'remote-a.example', 501 ),
				array( 'remote-b.example', 502 ),
			),
			$this->thumbnail_calls
		);
	}

	/**
	 * Clients from an earlier push in the same request don't send the next post's thumbnail.
	 */
	public function test_each_site_gets_only_its_own_thumbnail_across_pushes(): void {
		$this->push( $this->post_with_thumbnail() );
		$this->push( $this->post_with_thumbnail() );

		$this->assertSame(
			array(
				array( 'remote-a.example', 501 ),
				array( 'remote-b.example', 502 ),
				array( 'remote-a.example', 501 ),
				array( 'remote-b.example', 502 ),
			),
			$this->thumbnail_calls
		);
	}

	/**
	 * The syn_xmlrpc_push_send_thumbnail filter still turns thumbnail pushes off.
	 */
	public function test_thumbnail_push_can_be_turned_off(): void {
		add_filter( 'syn_xmlrpc_push_send_thumbnail', '__return_false' );

		$this->push( $this->post_with_thumbnail() );

		$this->assertSame( array(), $this->thumbnail_calls );
	}

	/**
	 * The public success actions still fire once per site.
	 */
	public function test_success_action_still_fires(): void {
		$this->push( $this->post_with_thumbnail() );

		$this->assertSame( 2, did_action( 'syn_xmlrpc_push_new_post_success' ) );
	}
}
