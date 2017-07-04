<?php
namespace Automattic\Syndication\Clients\RSS_Pull;

/**
 * Tests for Class Test_Pull_Client.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Pull_Client extends \WP_UnitTestCase {
	/**
	 * Setup a sample RSS Pull site.
	 *
	 * @since 2.1
	 */
	function setUp() {
		// Create a site group.
		$this->sitegroup = $this->factory->term->create_and_get( array(
			'taxonomy' => 'syn_sitegroup',
			'name' => 'Test Sitegroup',
		) );

		// Create a site.
		$this->site = $this->factory->post->create_and_get( array(
			'post_title' => 'VIP! VIP! VIP!',
			'post_type' => 'syn_site',
		) );

		// RSS Pull is the "WP_RSS" transport type
		add_post_meta( $this->site->ID, 'syn_transport_type', 'WP_RSS' );
		add_post_meta( $this->site->ID, 'syn_feed_url', 'https://vip.wordpress.com/feed' );
		add_post_meta( $this->site->ID, 'syn_default_post_type', 'post' );
		add_post_meta( $this->site->ID, 'syn_default_post_status', 'draft' );
		add_post_meta( $this->site->ID, 'syn_default_comment_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_ping_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_cat_status', 'yes' );
		add_post_meta( $this->site->ID, 'syn_site_enabled', 'on' );

		// Add the site to the sitegroup
		wp_set_object_terms( $this->site->ID, $this->sitegroup->term_id, 'syn_sitegroup' );
	}

	/**
	 * Clean up after yourself.
	 *
	 * @since 2.1
	 */
	function tearDown() {
		$q = new WP_Query( array(
			'post_type' => 'syn_site',
			'posts_per_page' => -1, // mwahaha
		) );

		$post_ids = wp_list_pluck( $q->posts, 'ID' );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id );
		}
	}

	/**
	 * A single example test.
	 *
	 * @since 2.1
	 */
	function test_rss_pull() {
		/*$syn = new WP_Push_Syndication_Server();
		$syn->pull_content();

		$q = new WP_Query( array(
			'post_type' => 'post',
			'posts_per_page' => 10,
		) );

		$this->assertEquals( 10, $q->post_count );*/
	}
}
