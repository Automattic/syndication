<?php

namespace Automattic\Syndication\Clients\Test;

use Automattic\Syndication\Types\Import_Post;

class Pull_Client
{
	public function __construct() {

	}

	public function init() {}

	public function is_post_exists( $id ) {
		return false;
	}
	public function get_posts() {

		$post = new Import_Post();

		$post->remote_id = 'hamburger';
		$post->post_data = array(
			'post_title'   => 'This is the post title',
			'post_content' => 'This is the post content.',
		);
		$post->post_meta  = array();
		$post->post_terms = array(
			'category' => array( 'Bacon', 'Lettuce', 'Tomato', ),
		);

		return [ $post ];
	}
}
