<?php

namespace Automattic\Syndication\Types;

class Import_Post {

	public $local_id = null;

	public $remote_id = null;

	public $site_id = null;

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
