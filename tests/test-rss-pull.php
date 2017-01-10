<?php
/**
* Class RSS_Pull
 *
 * @package Syndication
 */

/**
 * Sample test case.
 */
class RSS_Pull extends WP_UnitTestCase {

	/**
	 * Setup a sample RSS Pull site.
	 */
	function setUp() {

		// Create a site group
		$this->sitegroup = $this->factory->term->create_and_get( array(
			'taxonomy' => 'syn_sitegroup',
			'name' => 'Test Sitegroup',
		) );

		// Create a site
		$this->site = $this->factory->post->create_and_get( array(
			'post_title' => 'VIP! VIP! VIP!',
		) );

		// RSS Pull is the "WP_RSS" transport type
		add_post_meta( $this->site->ID, 'syn_transport_type', 'WP_RSS' );
		add_post_meta( $this->site->ID, 'syn_feed_url', 'https://vip.wordpress.com/feed' );
		add_post_meta( $this->site->ID, 'syn_default_post_type', 'post' );
		add_post_meta( $this->site->ID, 'syn_default_post_status', 'draft' );
		add_post_meta( $this->site->ID, 'syn_default_comment_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_ping_status', 'open' );
		add_post_meta( $this->site->ID, 'syn_default_cat_status', 'yes' );

		// Add the site to the sitegroup
		wp_set_object_terms( $this->site->ID, $this->sitegroup->term_id, 'syn_sitegroup' );

	}

	/**
	 * A single example test.
	 */
	function test_rss_pull() {

		$syn = new WP_Push_Syndication_Server();
		$syn->pull_content();

		$q = new WP_Query( array(
			'post_type' => 'post',
			'posts_per_page' => 10,
		) );

		$this->assertEquals( 10, $q->post_count );

	}

}
