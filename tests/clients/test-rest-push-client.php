<?php
/**
 * Tests for the REST Push Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */

namespace Automattic\Syndication\Clients\REST_Push_New;

/**
 * Class Test_Push_Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Push_Client extends \WP_UnitTestCase {
	/**
	 * Setup a sample REST Push site.
	 *
	 * @since 2.1
	 */
	public function setUp() {
		parent::setUp();

		$this->setup_REST_inteceptor();

		// Create a site group.
		$sitegroup = $this->factory->term->create_and_get( array(
			'taxonomy' => 'syn_sitegroup',
			'name' => 'Test Syndication Endpoint Group',
		) );

		// Create a site.
		$this->site = $this->factory->post->create_and_get( array(
			'post_title' => 'RSS Pull Syndication Endpoint',
			'post_type' => 'syn_site',
		) );

		/*
		 * Setup a fake REST service, we actually intercept the REST call below
		 * and send the request to a multisite install in our tests.
		 */
		add_post_meta( $this->site->ID, 'syn_transport_type', 'WP_RSS' );
		add_post_meta( $this->site->ID, 'syn_site_token', '123' );
		add_post_meta( $this->site->ID, 'syn_site_url', 'http://localhost/' );
		add_post_meta( $this->site->ID, 'syn_default_post_type', 'post' );
		add_post_meta( $this->site->ID, 'syn_default_post_status', 'publish' );
		add_post_meta( $this->site->ID, 'syn_default_comment_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_ping_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_cat_status', 'yes' );
		add_post_meta( $this->site->ID, 'syn_site_enabled', 'on' );

		// Add the site to the sitegroup.
		wp_set_object_terms( $this->site->ID, $sitegroup->term_id, 'syn_sitegroup' );

		// Instance of the actual client.
		$this->client = new Push_Client();
		$this->client->init( $this->site->ID );

		// Create a new multisite blog to send the request to.
		$this->blog_id = $this->factory->blog->create();

		// Create a user.
		switch_to_blog( $this->blog_id );

		$this->user_id = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_login' => 'superadmin',
		) );

		restore_current_blog();
	}

	/**
	 * Clean up after yourself.
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		switch_to_blog( $this->blog_id );

		$q = new \WP_Query( array(
			'post_type' => array( 'syn_site', 'syn_sitegroup', 'post' ),
			'posts_per_page' => -1,
		) );

		$post_ids = wp_list_pluck( $q->posts, 'ID' );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id );
		}

		restore_current_blog();
	}

	/**
	 * Test that a new posts gets sent sent and created on the other side.
	 *
	 * @since 2.1
	 * @covers Push_Client::new_post()
	 */
	public function test_new_post() {
		// Create a new post.
		$post_id = wp_insert_post( array( 'post_title' => 'Test Post', 'post_content' => 'Test post content', 'post_status' => 'publish' ) );

		// Send the new post to the other side.
		$this->client->new_post( $post_id );

		// Switch to the other blog and fetch it's posts.
		switch_to_blog( $this->blog_id );

		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		restore_current_blog();

		// Test that the post was created as expected.
		$this->assertEquals( 'Test Post', $posts->post->post_title );
		$this->assertEquals( 'Test post content', $posts->post->post_content );
	}

	/**
	 * This method intercepts our XML RPC calls and sends them to a multisite
	 * blog that was created above. It handles new posts, updating posts and
	 * deleting posts. Once the request is fulfilled, it retuns a valid HTTP
	 * response back to the caller.
	 *
	 * @since 2.1
	 */
	public function setup_REST_inteceptor() {
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \Spy_REST_Server;
		do_action( 'rest_api_init' );

		// Mock remote HTTP calls made by XMLRPC
		add_action( 'pre_http_request', function( $short_circuit, $args, $url ) {
			if ( 'http://localhost/wp-json/wp/v2/posts' === $url ) {
				switch_to_blog( $this->blog_id );
				wp_set_current_user( $this->user_id );

				$request = new \WP_REST_Request( 'POST', '/wp/v2/posts' );
				$request->set_body_params( (array) json_decode( $args['body'] ) );
				$response = $this->server->dispatch( $request );

				restore_current_blog();

				return array(
					'headers'  => $response->get_headers(),
					'response' => array(
						'code'    => $response->get_status(),
						'message' => 'OK',
					),
					'body'     => wp_json_encode( $response->get_data() ),
				);
			} elseif ( 'http://localhost/wp-json/wp/v2/categories' === $url ) {
				switch_to_blog( $this->blog_id );
				wp_set_current_user( $this->user_id );

				$request = new \WP_REST_Request( 'POST', '/wp/v2/categories' );
				$request->set_body_params( (array) json_decode( $args['body'] ) );
				$response = $this->server->dispatch( $request );

				restore_current_blog();

				return array(
					'headers'  => $response->get_headers(),
					'response' => array(
						'code'    => $response->get_status(),
						'message' => 'OK',
					),
					'body'     => wp_json_encode( $response->get_data() ),
				);
			} elseif ( 'http://localhost/wp-json/wp/v2/tags' === $url ) {
				switch_to_blog( $this->blog_id );
				wp_set_current_user( $this->user_id );

				$request = new \WP_REST_Request( 'POST', '/wp/v2/categories' );
				$request->set_body_params( (array) json_decode( $args['body'] ) );
				$response = $this->server->dispatch( $request );

				restore_current_blog();

				return array(
					'headers'  => $response->get_headers(),
					'response' => array(
						'code'    => $response->get_status(),
						'message' => 'OK',
					),
					'body'     => wp_json_encode( $response->get_data() ),
				);
			}

			return $short_circuit;
		}, 10, 3 );
	}
}
