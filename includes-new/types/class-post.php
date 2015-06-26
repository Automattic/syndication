<?php

namespace Automattic\Syndication\Types;

/**
 * Post content type
 *
 * The role of this class is to unify all remotely-fetched posts
 * so that we have clearly defined post attributes, and so we let
 * the pull clients simply handle pulling data, using this unified type.
 *
 * @package Automattic\Syndication\Types
 */
class Post {

	public $post_data = [];
	public $post_meta = [];
	public $post_terms = [];

	// Instantiation
	public function __construct() {

		// Prime the post_data array
		$this->post_data = [
			'ID'                    => '', //must be left blank
			'post_content'          => '',
			'post_name'             => '',
			'post_title'            => '',
			'post_status'           => '',
			'post_type'             => '',
			'post_author'           => '',
			'ping_status'           => '',
			'post_parent'           => '',
			'menu_order'            => '',
			'to_ping'               => '',
			'pinged'                => '',
			'post_password'         => '',
			'guid'                  => '', //must be left blank
			'post_content_filtered' => '',
			'post_excerpt'          => '',
			'post_date'             => '',
			'post_date_gmt'         => '',
			'comment_status'        => '',
			'post_category'         => '',
			'tags_input'            => '',
			'tax_input'             => '',
			'page_template'         => '', //na
		];

		// Prime the post_meta array
		$this->post_meta = [
			'enc_field' => null,
			'enclosures' => null,
		];
	}
}
