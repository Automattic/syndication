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

	public $post_data  = array();
	public $post_meta  = array();
	public $post_terms = array();

	// Instantiation
	public function __construct() {

		// Prime the post_data array
		$this->post_data = array(
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
			'post_guid'             => '', //must be left blank
			'post_content_filtered' => '',
			'post_excerpt'          => '',
			'post_date'             => '',
			'post_date_gmt'         => '',
			'comment_status'        => '',
			'post_category'         => '',
			'tags_input'            => '',
			'tax_input'             => '',
			'page_template'         => '', //na
		);

		// Prime the post_meta array
		$this->post_meta = array(
			'enc_field'  => null,
			'site_id'    => '',
			'enclosures' => null,
		);
	}
}
