<?php
/**
 * Tests for the RSS Pull Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */

namespace Automattic\Syndication\Clients\RSS_Pull;

use Automattic\Syndication\Syndication_Logger;
use Automattic\Syndication\Types;

/**
 * Class Test_Pull_Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Pull_Client extends \WP_UnitTestCase {
	/**
	 * The site (Syndication Endpoint) post object.
	 *
	 * @var object
	 */
	private $site;

	/**
	 * Instance of the RSS Pull Client.
	 *
	 * @var object
	 */
	private $client;

	/**
	 * Setup a sample RSS Pull site.
	 *
	 * @since 2.1
	 */
	public function setUp() {
		parent::setUp();

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
		 * RSS Pull is the "WP_RSS" transport type.
		 *
		 * Use the VIP feed which isn't ideal, but mocking SimplePie is a challenge.
		 * In the future we should use dependency injection and a wrapper for
		 * fetching a feed.
		 */
		add_post_meta( $this->site->ID, 'syn_transport_type', 'WP_RSS' );
		add_post_meta( $this->site->ID, 'syn_feed_url', 'https://vip.wordpress.com/feed' );
		add_post_meta( $this->site->ID, 'syn_default_post_type', 'post' );
		add_post_meta( $this->site->ID, 'syn_default_post_status', 'publish' );
		add_post_meta( $this->site->ID, 'syn_default_comment_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_ping_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_cat_status', 'yes' );
		add_post_meta( $this->site->ID, 'syn_site_enabled', 'on' );

		// Add the site to the sitegroup.
		wp_set_object_terms( $this->site->ID, $sitegroup->term_id, 'syn_sitegroup' );

		$this->client = new Pull_Client();
		$this->client->init( $this->site->ID );
	}

	/**
	 * Clean up after yourself.
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		$q = new \WP_Query( array(
			'post_type' => array( 'syn_site', 'syn_sitegroup', 'post' ),
			'posts_per_page' => -1,
		) );

		$post_ids = wp_list_pluck( $q->posts, 'ID' );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id );
		}
	}

	/**
	 * Test that processing a site pulls new posts.
	 *
	 * @since 2.1
	 * @covers Puller::process_site()
	 */
	public function test_processing_site_pulls_new_posts() {
		$processed_posts = $this->client->process_site( $this->site->ID, $this->client );

		// Check new posts fetched.
		$this->assertEquals( 10, count( $processed_posts ) );

		// Check new posts were added.
		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 10,
		) );

		$this->assertEquals( 10, $posts->post_count );

		// Test log was added.
		$logs = Syndication_Logger::get_messages();

		$this->assertEquals( 'success', $logs[ $this->site->ID ][ $posts->posts[1]->ID ]['msg_type'] );
		$this->assertEquals( 'new', $logs[ $this->site->ID ][ $posts->posts[1]->ID ]['status'] );
	}

	public function test_processing_invalid_feed_returns_false() {
		// Setup invalid feed.
		update_post_meta( $this->site->ID, 'syn_feed_url', 'https://localhost/invalidfeed' );

		$processed_posts = $this->client->process_site( $this->site->ID, $this->client );

		$this->assertFalse( $processed_posts );

		// Reset to valid feed.
		update_post_meta( $this->site->ID, 'syn_feed_url', 'https://vip.wordpress.com/feed' );
	}

	/**
	 * Test that processing a site doesn't create duplicates.
	 *
	 * @since 2.1
	 * @covers Puller::process_site()
	 */
	public function test_process_site_site_no_duplicates() {
		/*
		 * We should process the feed twice to make sure posts only get added
		 * once. The likely hood of the feed getting updated between the two
		 * process runs is slim to none.
		 */
		$this->client->process_site( $this->site->ID, $this->client );
		$this->client->process_site( $this->site->ID, $this->client );

		// Check posts we updated and not added i.e. we should still have 10 posts in the DB.
		$q = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 100,
		) );

		$this->assertEquals( 10, $q->post_count );
	}

	public function test_process_site_updates_content() {
		/*
		 * To pull this off, we need to use a mock class so that we can control
		 * the data that gets returned from `get_posts`. Usually that method
		 * fetches a feed, in our case we're returning dummy data so that we can
		 * test an update (instead of waiting for an update to happen to a real
		 * feed).
		 */
		$this->client = new Mock_RSS_Pull_Client_New();
		$this->client->init( $this->site->ID );

		$processed_posts = $this->client->process_site( $this->site->ID, $this->client );

		// Check new posts fetched.
		$this->assertEquals( 1, count( $processed_posts ) );

		// Check new posts were added.
		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 10,
		) );

		$this->assertEquals( 1, $posts->post_count );

		// Check that new post just added gets updated
		add_action( 'pre_option_push_syndicate_settings', function() {
			return array(
				'selected_pull_sitegroups' => array(),
				'selected_post_types'      => array( 'post' ),
				'delete_pushed_posts'      => 'off',
				'pull_time_interval'       => '3600',
				'update_pulled_posts'      => 'on',
			);
		} );

		global $settings_manager;
		$settings_manager->init();

		$this->client = new Mock_RSS_Pull_Client_Update();
		$this->client->init( $this->site->ID );
		$this->client->process_site( $this->site->ID, $this->client );

		$posts = new \WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 10,
		) );

		$this->assertEquals( 1, $posts->post_count );
		$this->assertEquals( 'Test post title (updated)', $posts->posts[0]->post_title );
		$this->assertEquals( '2017-01-01 12:00:00', $posts->posts[0]->post_date );
	}
}

/**
 * Class Mock_RSS_Pull_Client_New
 *
 * Mock class for faking an RSS feed's results and creating a new post.
 *
 * @package Automattic\Syndication\Clients\RSS_Pull
 */
class Mock_RSS_Pull_Client_New extends Pull_Client {
	/**
	 * Returns a list of posts that would be fetched from an RSS feed.
	 *
	 * @param   int $site_id The ID of the site to get posts for.
	 * @return  array        Array of posts on success, false on failure.
	 */
	public function get_posts( $site_id = 0 ) {
		$new_post                              = new Types\Post();
		$new_post->post_data['post_title']     = 'Test post title';
		$new_post->post_data['post_content']   = 'Content of the test post';
		$new_post->post_data['post_excerpt']   = 'Test excerpt';
		$new_post->post_data['post_type']      = 'post';
		$new_post->post_data['post_status']    = 'publish';
		$new_post->post_data['post_date']      = '2017-01-01 00:00:00';
		$new_post->post_data['comment_status'] = 'open';
		$new_post->post_data['ping_status']    = 'open';
		$new_post->post_data['post_guid']      = 'unique_id_1';
		$new_post->post_data['post_category']  = '';
		$new_post->post_data['tags_input']     = '';
		$new_post->post_meta['site_id']        = $site_id;

		return array( $new_post );
	}
}

/**
 * Class Mock_RSS_Pull_Client_Update
 *
 * Mock class for faking an RSS feed's results and updating the new post created
 * above.
 *
 * @package Automattic\Syndication\Clients\RSS_Pull
 */
class Mock_RSS_Pull_Client_Update extends Pull_Client {
	/**
	 * Returns a list of posts that would be fetched from an RSS feed.
	 *
	 * @param   int $site_id The ID of the site to get posts for.
	 * @return  array        Array of posts on success, false on failure.
	 */
	public function get_posts( $site_id = 0 ) {
		$new_post                              = new Types\Post();
		$new_post->post_data['post_title']     = 'Test post title (updated)';
		$new_post->post_data['post_content']   = 'Content of the test post';
		$new_post->post_data['post_excerpt']   = 'Test excerpt';
		$new_post->post_data['post_type']      = 'post';
		$new_post->post_data['post_status']    = 'publish';
		$new_post->post_data['post_date']      = '2017-01-01 12:00:00';
		$new_post->post_data['comment_status'] = 'open';
		$new_post->post_data['ping_status']    = 'open';
		$new_post->post_data['post_guid']      = 'unique_id_1';
		$new_post->post_data['post_category']  = '';
		$new_post->post_data['tags_input']     = '';
		$new_post->post_meta['site_id']        = $site_id;

		return array( $new_post );
	}
}
