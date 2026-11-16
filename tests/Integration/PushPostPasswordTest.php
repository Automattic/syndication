<?php
/**
 * Tests that pushing a password-protected post keeps it protected on the destination.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PushPostPasswordTest
 *
 * The XML-RPC tests loop each outbound request back into this install's own
 * wp_xmlrpc_server, so the assertions are made against the post that core
 * actually created or edited, not against the arguments the client built.
 *
 * @covers Syndication_WP_XMLRPC_Client::new_post
 * @covers Syndication_WP_XMLRPC_Client::edit_post
 * @covers Syndication_WP_REST_Client::new_post
 * @covers Syndication_WP_REST_Client::edit_post
 */
class PushPostPasswordTest extends WPIntegrationTestCase {

	/**
	 * Bodies of the requests sent to the WordPress.com REST API.
	 *
	 * @var array<int, array>
	 */
	private $rest_bodies = array();

	/**
	 * The syn_site post the XML-RPC client pushes to.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * Set up the loopback XML-RPC server and intercept outbound HTTP.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-includes/class-wp-xmlrpc-server.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rest-client.php';

		$this->rest_bodies = array();

		add_filter( 'pre_http_request', array( $this, 'intercept_request' ), 10, 3 );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['wp_xmlrpc_server'] );
		parent::tear_down();
	}

	/**
	 * Serve XML-RPC requests from this install, and record REST request bodies.
	 *
	 * @param false|array $preempt     Whether to preempt the request.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url         Request URL.
	 * @return array Fake HTTP response.
	 */
	public function intercept_request( $preempt, $parsed_args, $url ) {
		if ( false !== strpos( $url, 'public-api.wordpress.com' ) ) {
			$this->rest_bodies[] = $parsed_args['body'];
			return $this->response( wp_json_encode( array( 'ID' => 999 ) ) );
		}

		$message = new \IXR_Message( $parsed_args['body'] );
		$message->parse();

		$server                      = new \wp_xmlrpc_server();
		$GLOBALS['wp_xmlrpc_server'] = $server;
		$callback                    = $server->methods[ $message->methodName ]; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$method                      = substr( $callback, strlen( 'this:' ) );
		$result                      = $server->$method( $message->params );

		if ( $result instanceof \IXR_Error ) {
			return $this->response( $result->getXml() );
		}

		$value = new \IXR_Value( $result );
		return $this->response( '<?xml version="1.0"?><methodResponse><params><param><value>' . $value->getXml() . '</value></param></params></methodResponse>' );
	}

	/**
	 * Build a successful HTTP response.
	 *
	 * @param string $body Response body.
	 * @return array
	 */
	private function response( string $body ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Create an XML-RPC client whose destination credentials are a real editor here.
	 *
	 * @return \Syndication_WP_XMLRPC_Client
	 */
	private function xmlrpc_client(): \Syndication_WP_XMLRPC_Client {
		self::factory()->user->create(
			array(
				'user_login' => 'destination-editor',
				'user_pass'  => 'destination-pass',
				'role'       => 'editor',
			)
		);

		$this->site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $this->site_id, 'syn_site_url', 'https://destination.example' );
		update_post_meta( $this->site_id, 'syn_site_username', 'destination-editor' );
		update_post_meta( $this->site_id, 'syn_site_password', push_syndicate_encrypt( 'destination-pass' ) );
		update_post_meta( $this->site_id, 'syn_transport_type', 'WP_XMLRPC' );

		return new \Syndication_WP_XMLRPC_Client( $this->site_id );
	}

	/**
	 * Create a published source post.
	 *
	 * @param string $password Post password.
	 * @return int Post ID.
	 */
	private function source_post( string $password ): int {
		return self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => $password,
				'post_excerpt'  => 'Secret excerpt',
			)
		);
	}

	/**
	 * A new post pushed over XML-RPC is created with the source post's password.
	 */
	public function test_xmlrpc_new_post_keeps_password(): void {
		$remote_id = $this->xmlrpc_client()->new_post( $this->source_post( 'hunter2' ) );

		$this->assertIsInt( $remote_id );
		$this->assertGreaterThan( 0, $remote_id );

		$remote = get_post( $remote_id );
		$this->assertSame( 'hunter2', $remote->post_password );
		$this->assertTrue( post_password_required( $remote ) );
		$this->assertStringNotContainsString( 'Secret excerpt', get_the_excerpt( $remote ), 'Core hides the excerpt of a protected post.' );
	}

	/**
	 * Editing over XML-RPC applies a password added on the source.
	 */
	public function test_xmlrpc_edit_post_adds_password(): void {
		$remote_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->xmlrpc_client()->edit_post( $this->source_post( 'hunter2' ), $remote_id );

		$this->assertSame( 'hunter2', get_post( $remote_id )->post_password );
	}

	/**
	 * Editing over XML-RPC removes a password removed on the source.
	 */
	public function test_xmlrpc_edit_post_removes_password(): void {
		$remote_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => 'hunter2',
			)
		);

		$this->xmlrpc_client()->edit_post( $this->source_post( '' ), $remote_id );

		$this->assertSame( '', get_post( $remote_id )->post_password );
	}

	/**
	 * The cron and WP-CLI push both route through push_content(), so the password survives it too.
	 */
	public function test_push_content_keeps_password(): void {
		$this->xmlrpc_client();
		$post_id = $this->source_post( 'hunter2' );
		$site    = get_post( $this->site_id );

		$GLOBALS['push_syndication_server']->push_content(
			array(
				'post_ID'        => $post_id,
				'selected_sites' => array( $site ),
				'removed_sites'  => array(),
			)
		);

		$states    = get_post_meta( $post_id, '_syn_slave_post_states', true );
		$remote_id = $states['success'][ $site->ID ]['ext_ID'];

		$this->assertSame( 'hunter2', get_post( $remote_id )->post_password );
	}

	/**
	 * The WordPress.com REST client sends the password as `password`, the v1 API's parameter.
	 */
	public function test_rest_new_and_edit_send_password(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_site_id', '12345' );
		update_post_meta( $site_id, 'syn_site_token', push_syndicate_encrypt( 'test-token' ) );

		$client  = new \Syndication_WP_REST_Client( $site_id );
		$post_id = $this->source_post( 'hunter2' );

		$client->new_post( $post_id );
		$client->edit_post( $post_id, 999 );

		$this->assertCount( 2, $this->rest_bodies );
		foreach ( $this->rest_bodies as $body ) {
			$this->assertSame( 'hunter2', $body['password'] );
		}
	}
}
