<?php

namespace Automattic\Syndication\Clients\RSS_Pull;

use Automattic\Syndication;
use Automattic\Syndication\Puller;
use Automattic\Syndication\Types;

/**
 * Syndication Client: RSS Pull
 *
 * Create 'syndication sites' to pull external content into your
 * WordPress install via RSS.
 *
 * @package Automattic\Syndication\Clients\RSS
 */
class Pull_Client extends Puller {

	/**
	 * Hook into WordPress
	 */
	public function __construct() {}

	/**
	 * Initialize the client for a specific site id.
	 */
	public function init( $site_id ) {}

	public function set_wp_feed_cache_transient_lifetime( $time ) {
		global $settings_manager;

		return (int) $settings_manager->get_setting( 'pull_time_interval' );
	}
	/**
	 * Retrieves a list of posts from a remote site.
	 *
	 * @param   int $site_id The ID of the site to get posts for
	 * @return  array|bool   Array of posts on success, false on failure.
	 */
	public function get_posts( $site_id = 0 ) {

		/**
		 * Filter the RSS pull client feed url.
		 *
		 * @param string $feed_url The site's feed url.
		 * @todo Consider adding $site_id for context.
		 */
		$feed_url               = apply_filters( 'syn_feed_url', get_post_meta( $site_id, 'syn_feed_url', true ) );
		$default_post_type      = get_post_meta( $site_id, 'syn_default_post_type', true );
		$default_post_status    = get_post_meta( $site_id, 'syn_default_post_status', true );
		$default_comment_status = get_post_meta( $site_id, 'syn_default_comment_status', true );
		$default_ping_status    = get_post_meta( $site_id, 'syn_default_ping_status', true );
		$default_cat_status     = get_post_meta( $site_id, 'syn_default_cat_status', true );

		/**
		 * The following filter allows for local testing.
		 * @todo Remove after testing.
		 */
		add_filter(
			'http_request_args',
			function( $args ) {
				$args['reject_unsafe_urls'] = false;
				return $args;
			}
		);

		/**
		 * Fetch the RSS feed, with limited caching.
		 */
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'set_wp_feed_cache_transient_lifetime' ) );
		$feed = fetch_feed( $feed_url );
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'set_wp_feed_cache_transient_lifetime' ) );

		if ( is_wp_error( $feed ) ) {

			/**
			 * Fires after a syndication event.
			 *
			 * @param string $event Event type. One of 'pull_failure', 'pull_success'.
			 * @param int $site_id    The id of the site triggering the event.
			 */
			do_action( 'push_syndication_event', 'pull_failure', $site_id );

			return array();
		}

		$all_items = $feed->get_items();

		$posts = array();
		foreach ( $all_items as $item ) {
			if ( 'yes' == $default_cat_status ) {
				$taxonomy = $this->set_taxonomy( $item );
			}

			$new_post = new Types\Post();

			$new_post->post_data['post_title']     = $item->get_title();
			$new_post->post_data['post_content']   = $item->get_content();
			$new_post->post_data['post_excerpt']   = $item->get_description();
			$new_post->post_data['post_type']      = $default_post_type;
			$new_post->post_data['post_status']    = $default_post_status;
			$new_post->post_data['post_date']      = date( 'Y-m-d H:i:s', strtotime( $item->get_date() ) );
			$new_post->post_data['comment_status'] = $default_comment_status;
			$new_post->post_data['ping_status']    = $default_ping_status;
			$new_post->post_data['post_guid']      = $item->get_id();
			$new_post->post_data['post_category']  = isset( $taxonomy['cats'] ) ? $taxonomy['cats'] : '';
			$new_post->post_data['tags_input']     = isset( $taxonomy['tags'] ) ? $taxonomy['tags'] : '';

			$new_post->post_meta['site_id'] = $site->ID;

			// This filter can be used to exclude or alter posts during a pull import
			/**
			 * Filter the post used by the RSS pull client when pulling an update.
			 *
			 * This filter can be used to exclude or alter posts during a content update to an
			 * existing post on the remote. Return false to short circuit the post push update.
			 *
			 * @param WP_Post $post    The post the be pushed.
			 * @param int     $post_ID The id of the post originating this request.
			 */
			$new_post = apply_filters( 'syn_rss_pull_filter_post', $new_post, array(), $item );
			if ( false === $new_post ) {
				continue;
			}
			$posts[] = $new_post;
		}
		/* This action is documented in includes/clients/rss-pull/class-pull-client.php */
		do_action( 'push_syndication_event', 'pull_success', $site->ID );

		return $posts;
	}

	public function set_taxonomy( $item ) {
		$cats = $item->get_categories();
		$ids = array(
			'cats'    => array(),
			'tags'            => array()
		);

		foreach ( $cats as $cat ) {
			// checks if term exists
			if ( $result = get_term_by( 'name', $cat->term, 'category' ) ) {
				if ( isset( $result->term_id ) ) {
					$ids['cats'][] = $result->term_id;
				}
			} elseif ( $result = get_term_by( 'name', $cat->term, 'post_tag' ) ) {
				if ( isset( $result->term_id ) ) {
					$ids['tags'][] = $result->term_id;
				}
			} else {
				// creates if not
				$result = wp_insert_term( $cat->term, 'category' );
				if ( isset( $result->term_id ) ) {
					$ids['cats'][] = $result->term_id;
				}
			}
		}

		// returns array ready for post creation
		return $ids;
	}

}
