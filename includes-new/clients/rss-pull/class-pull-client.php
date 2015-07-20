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
	 * Retrieves a list of posts from a remote site.
	 *
	 * @param   int $site_id The ID of the site to get posts for
	 * @return  array|bool   Array of posts on success, false on failure.
	 */
	public function get_posts( $site_id = 0 ) {
		// create $post with values from $this::node_to_post
		// create $post_meta with values from $this::node_to_meta

		//TODO: required fields for post
		//TODO: handle categories
		$new_posts = array();
		return $new_posts;
	}
}
