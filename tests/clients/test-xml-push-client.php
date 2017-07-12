<?php
/**
 * Tests for the XML Push Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */

namespace Automattic\Syndication\Clients\XML_Push;

/**
 * Class Test_Push_Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Push_Client extends \WP_XMLRPC_UnitTestCase {
	/**
	 * Setup a sample XML Push site.
	 *
	 * @since 2.1
	 */
	public function setUp() {
		parent::setUp();

		$this->setup_XMLRPC_inteceptor();

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
		 * Setup a fake XML RPC service, we actually intercept the XMLRPC
		 * call below and send the request to a multisite install in our tests.
		 */
		add_post_meta( $this->site->ID, 'syn_transport_type', 'WP_RSS' );
		add_post_meta( $this->site->ID, 'syn_site_url', 'http://localhost/xmlrpc/xmlrpc.php' );
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
	 * Test that a when updating a post it gets updated on the other side.
	 *
	 * @since 2.1
	 * @covers Push_Client::edit_post()
	 */
	public function test_edit_post() {
		// Create a new post.
		$post_id = wp_insert_post( array( 'post_title' => 'Test Post', 'post_content' => 'Test post content', 'post_status' => 'publish' ) );

		// Send the new post to the other side.
		$remote_post_id = $this->client->new_post( $post_id );

		// Update our post.
		wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Test Post (Updated)', 'post_content' => 'Test post content (updated)' ) );

		// Send the update across the wire.
		$this->client->edit_post( $post_id, $remote_post_id );

		// Switch to the other blog and fetch it's posts.
		switch_to_blog( $this->blog_id );

		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		restore_current_blog();

		// Test that the post was updated as expected.
		$this->assertEquals( 'Test Post (Updated)', $posts->post->post_title );
		$this->assertEquals( 'Test post content (updated)', $posts->post->post_content );
	}

	/**
	 * Test that a post gets deleted on the other side.
	 *
	 * @since 2.1
	 * @covers Push_Client::delete_post()
	 */
	public function test_delete_post() {
		// Create a new post.
		$post_id = wp_insert_post( array( 'post_title' => 'Test Post', 'post_content' => 'Test post content', 'post_status' => 'publish' ) );

		// Send the new post to the other side.
		$remote_post_id = $this->client->new_post( $post_id );

		// Make sure it exists.
		switch_to_blog( $this->blog_id );

		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		restore_current_blog();

		$this->assertEquals( 'Test Post', $posts->post->post_title );

		// Delete it on the other site and test it was deleted.
		$this->client->delete_post( $remote_post_id );

		switch_to_blog( $this->blog_id );

		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		restore_current_blog();

		$this->assertNotEquals( 'Test Post', $posts->post->post_title );
		$this->assertNotEquals( 'Test post content', $posts->post->post_content );
	}

	/**
	 * This method intercepts our XML RPC calls and sends them to a multisite
	 * blog that was created above. It handles new posts, updating posts and
	 * deleting posts. Once the request is fulfilled, it retuns a valid HTTP
	 * response back to the caller.
	 *
	 * @since 2.1
	 */
	public function setup_XMLRPC_inteceptor() {
		// Mock remote HTTP calls made by XMLRPC
		add_action( 'pre_http_request', function( $short_circuit, $args, $url ) {
			if ( 'http://localhost/xmlrpc/xmlrpc.php' === $url ) {
				switch_to_blog( $this->blog_id );

				// Create user.
				$this->make_user_by_role( 'author' );

				// Parse message.
				$message = new \IXR_Message( $args['body'] );
				$message->parse();

				// Set username and password.
				$message->params[1] = 'author';
				$message->params[2] = 'author';

				// Add method callback.
				$this->myxmlrpcserver->callbacks['wp.getPost']    = 'this:wp_getPost';
				$this->myxmlrpcserver->callbacks['wp.newPost']    = 'this:wp_newPost';
				$this->myxmlrpcserver->callbacks['wp.editPost']   = 'this:wp_editPost';
				$this->myxmlrpcserver->callbacks['wp.deletePost'] = 'this:wp_deletePost';

				// Post.
				$result = $this->myxmlrpcserver->call( $message->methodName, $message->params );

				// Encode the result
				$r = new \IXR_Value( $result );
				$resultxml = $r->getXml();

				$xml = <<<EOD
<?xml version="1.0"?>
<methodResponse>
  <params>
    <param>
      <value>
      $resultxml
      </value>
    </param>
  </params>
</methodResponse>

EOD;

				restore_current_blog();

				return array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => $xml,
				);
			}

			return $short_circuit;
		}, 10, 3 );
	}
}
