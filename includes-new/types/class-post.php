<?php

// @tott I did this because I thought it was a more explicit documentation of
// this data type rather than describing it in a comment. If you think Auto
// won't like this I can just make it an array.
//
// @tott What's a better name for this? I feel like "post" confuses it with a
// WP post, which is not exactly what it is at all.

namespace Automattic\Syndication\Types;

class Post {

	public $local_id = null;

	public $remote_id = null;

//'post' => [
//'post_date_gmt' => '',
//'post_content' => '',
//'post_excerpt' => '',
//'post_status' => '', // maybe
//'post_type' => '', // maybe
//'comment_status' => '',
//'ping_status' => '',
//'post_password' => '',
//'post_name' => '',
//'post_modified_gmt' => '',
//'post_parent' => '', // maybe
//],
	public $post_data = [];

	public $post_meta = [];

	// I don't understand the WP style for commenting arrays
	// [ 'category' ] => [ 'bacon' => 'Bacon', 'lettuce' => 'Lettuce', 'tomato' => 'Tomato' ];
	public $post_terms = [];

	/**
	 * @var DateTime
	 */
	public $publish_date = null;

	/**
	 * @var DateTime
	 */
	public $modified_date = null;
}