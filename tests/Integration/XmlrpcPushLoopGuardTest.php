<?php
/**
 * Tests the syndication loop guard for XML-RPC pushes.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlrpcPushLoopGuardTest
 *
 * Runs push_content() against the real XML-RPC client, with the remote site
 * faked at the HTTP layer.
 *
 * @covers WP_Push_Syndication_Server::push_content
 * @covers Syndication_WP_XMLRPC_Client::is_source_site_post
 */
class XmlrpcPushLoopGuardTest extends WPIntegrationTestCase {

	/**
	 * XML-RPC method names the fake remote received, in order.
	 *
	 * @var string[]
	 */
	private $calls = array();

	/**
	 * Posts on the fake remote, as returned by wp.getPosts.
	 *
	 * @var array
	 */
	private $remote_posts = array();

	/**
	 * Whether the fake remote ignores the wp.getPosts offset.
	 *
	 * @var bool
	 */
	private $ignore_offset = false;

	/**
	 * Whether wp.getPosts returns a fault on the fake remote.
	 *
	 * @var bool
	 */
	private $get_posts_fails = false;

	/**
	 * The syn_site post for the remote.
	 *
	 * @var \WP_Post
	 */
	private $site;

	/**
	 * The local post being pushed.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up a site, a post and the fake remote.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->calls         = array();
		$this->remote_posts  = array();
		$this->ignore_offset   = false;
		$this->get_posts_fails = false;

		$this->site = self::factory()->post->create_and_get( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $this->site->ID, 'syn_transport_type', 'WP_XMLRPC' );
		update_post_meta( $this->site->ID, 'syn_site_url', 'http://remote.example' );
		update_post_meta( $this->site->ID, 'syn_site_username', 'user' );
		update_post_meta( $this->site->ID, 'syn_site_password', push_syndicate_encrypt( 'pass' ) );

		$this->post_id = self::factory()->post->create();
		update_post_meta( $this->post_id, 'post_uniqueid', 'abc123' );

		add_filter( 'pre_http_request', array( $this, 'fake_remote' ), 10, 3 );
	}

	/**
	 * Answer XML-RPC requests as a remote WordPress site would.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 * @return array Fake HTTP response.
	 */
	public function fake_remote( $preempt, $args, $url ) {
		$message = new \IXR_Message( $args['body'] );
		$message->parse();
		$this->calls[] = $message->methodName;

		switch ( $message->methodName ) {
			case 'wp.getPost':
				$response = array(
					'post_id'       => (string) $message->params[3],
					'custom_fields' => array(),
				);
				break;
			case 'wp.getPosts':
				$filter   = $message->params[3];
				$offset   = isset( $filter['offset'] ) && ! $this->ignore_offset ? (int) $filter['offset'] : 0;
				$response = array_slice( $this->remote_posts, $offset, (int) $filter['number'] );
				break;
			case 'wp.newPost':
				$response = '99';
				break;
			default:
				$response = true;
		}

		if ( 'wp.getPosts' === $message->methodName && $this->get_posts_fails ) {
			$error = new \IXR_Error( 500, 'Failed.' );
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
	 * Build a page of remote posts, optionally carrying our post's unique ID.
	 *
	 * @param int      $count       Number of posts.
	 * @param int|null $match_index Index of the post that carries post_uniqueid, if any.
	 * @return array Remote posts.
	 */
	private function remote_posts( int $count, ?int $match_index = null ): array {
		$posts = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$posts[] = array(
				'post_id'       => (string) ( 1000 + $i ),
				'custom_fields' => array(
					array(
						'id'    => (string) $i,
						'key'   => 'post_uniqueid',
						'value' => $i === $match_index ? 'abc123' : 'other' . $i,
					),
				),
			);
		}
		return $posts;
	}

	/**
	 * Push the post to the site.
	 */
	private function push(): void {
		$GLOBALS['push_syndication_server']->push_content(
			array(
				'post_ID'        => $this->post_id,
				'selected_sites' => array( $this->site ),
				'removed_sites'  => array(),
			)
		);
	}

	/**
	 * An edit reaches the remote even though the remote copy carries our post_uniqueid.
	 *
	 * The XML-RPC client copies post_uniqueid to the remote, so after the first
	 * push the guard used to find our own copy and skip every later edit.
	 */
	public function test_edit_is_pushed_when_remote_copy_carries_unique_id(): void {
		update_post_meta(
			$this->post_id,
			'_syn_slave_post_states',
			array( 'success' => array( $this->site->ID => array( 'ext_ID' => 1000 ) ) )
		);
		$this->remote_posts = $this->remote_posts( 1, 0 );

		$this->push();

		$this->assertContains( 'wp.editPost', $this->calls );
		$this->assertNotContains( 'wp.getPosts', $this->calls, 'The loop guard should not run for a post already pushed to this site.' );
	}

	/**
	 * A new post is not pushed to a site where it already exists, even beyond the first 100 remote posts.
	 */
	public function test_new_post_is_not_pushed_when_source_post_is_beyond_first_page(): void {
		$this->remote_posts = $this->remote_posts( 250, 180 );

		$this->push();

		$this->assertNotContains( 'wp.newPost', $this->calls );
	}

	/**
	 * A new post is pushed when no remote post carries its unique ID.
	 */
	public function test_new_post_is_pushed_when_no_remote_post_matches(): void {
		$this->remote_posts = $this->remote_posts( 250 );

		$this->push();

		$this->assertSame( 3, count( array_keys( $this->calls, 'wp.getPosts', true ) ), 'Every page of remote posts should be checked.' );
		$this->assertContains( 'wp.newPost', $this->calls );
	}

	/**
	 * A remote that ignores the offset doesn't keep the guard paging forever.
	 */
	public function test_guard_stops_when_remote_ignores_offset(): void {
		$this->ignore_offset = true;
		$this->remote_posts  = $this->remote_posts( 250 );

		$this->push();

		$this->assertSame( 2, count( array_keys( $this->calls, 'wp.getPosts', true ) ), 'The guard should stop at the first page with nothing new.' );
		$this->assertContains( 'wp.newPost', $this->calls );
		$this->assertContains( 'loop_guard_incomplete', array_column( (array) get_post_meta( $this->site->ID, 'syn_log', true ), 'status' ) );
	}

	/**
	 * The guard stops at the page limit, logs it, and lets the push go ahead.
	 */
	public function test_guard_stops_at_page_limit(): void {
		add_filter(
			'syndication_xmlrpc_loop_guard_max_pages',
			static function () {
				return 1;
			}
		);
		$this->remote_posts = $this->remote_posts( 250, 180 );

		$this->push();

		$this->assertSame( 1, count( array_keys( $this->calls, 'wp.getPosts', true ) ) );
		$this->assertContains( 'wp.newPost', $this->calls );
		$this->assertContains( 'loop_guard_incomplete', array_column( (array) get_post_meta( $this->site->ID, 'syn_log', true ), 'status' ) );
	}

	/**
	 * A post that failed to push the first time is still checked by the guard.
	 */
	public function test_guard_runs_for_new_error_state(): void {
		update_post_meta(
			$this->post_id,
			'_syn_slave_post_states',
			array( 'new-error' => array( $this->site->ID => new \WP_Error( 'failed', 'Failed' ) ) )
		);
		$this->remote_posts = $this->remote_posts( 1, 0 );

		$this->push();

		$this->assertContains( 'wp.getPosts', $this->calls );
		$this->assertNotContains( 'wp.newPost', $this->calls );
	}

	/**
	 * The client used for direct calls to is_source_site_post().
	 *
	 * @return \Syndication_WP_XMLRPC_Client
	 */
	private function client(): \Syndication_WP_XMLRPC_Client {
		return new \Syndication_WP_XMLRPC_Client( $this->site->ID );
	}

	/**
	 * Without both a meta key and value there is nothing to look for, so no request is made.
	 */
	public function test_is_source_site_post_needs_key_and_value(): void {
		$this->assertFalse( $this->client()->is_source_site_post( '', 'abc123' ) );
		$this->assertFalse( $this->client()->is_source_site_post( 'post_uniqueid', '' ) );
		$this->assertSame( array(), $this->calls );
	}

	/**
	 * A failed request counts as not found.
	 */
	public function test_is_source_site_post_is_false_when_request_fails(): void {
		$this->get_posts_fails = true;

		$this->assertFalse( $this->client()->is_source_site_post( 'post_uniqueid', 'abc123' ) );
	}

	/**
	 * The key and value must match on the same custom field.
	 */
	public function test_is_source_site_post_needs_key_and_value_on_one_field(): void {
		$this->remote_posts = array(
			array(
				'post_id'       => '1',
				'custom_fields' => array(
					array(
						'key'   => 'post_uniqueid',
						'value' => 'other',
					),
					array(
						'key'   => 'other_key',
						'value' => 'abc123',
					),
				),
			),
		);

		$this->assertFalse( $this->client()->is_source_site_post( 'post_uniqueid', 'abc123' ) );
	}

	/**
	 * Posts without custom fields are skipped, and a later post can still match.
	 */
	public function test_is_source_site_post_skips_posts_without_custom_fields(): void {
		$this->remote_posts = array_merge(
			array(
				array(
					'post_id'       => '1',
					'custom_fields' => array(),
				),
			),
			$this->remote_posts( 1, 0 )
		);

		$this->assertTrue( $this->client()->is_source_site_post( 'post_uniqueid', 'abc123' ) );
	}
}
