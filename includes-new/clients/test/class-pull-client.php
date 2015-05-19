<?php

namespace Automattic\Syndication\Clients\Test;

use Automattic\Syndication\Types\Post;

class Pull_Client implements \Automattic\Syndication\Pull_Client
{
	public function __construct( $site_id ) {

	}

	public function get_posts() {

		$post = new Post();

		$post->remote_id = 'hamburger';
		$post->post_data = [
			'post_title' => 'This is the post title',
			'post_content' => 'This is the post content.',
		];
		$post->post_meta = [

		];
		$post->post_terms = [
			'category' => [ 'Bacon', 'Lettuce', 'Tomato' ],
		];

		return [ $post ];
	}
}