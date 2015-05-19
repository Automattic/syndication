<?php

namespace Automattic\Syndication\Custom_Post_Types;

class Site_Post_Type {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		$post_type_capabilities = array(
			'edit_post'          => $capability,
			'read_post'          => $capability,
			'delete_post'        => $capability,
			'edit_posts'         => $capability,
			'edit_others_posts'  => $capability,
			'publish_posts'      => $capability,
			'read_private_posts' => $capability
		);

		register_post_type( 'syn_site', array(
			'labels' => array(
				'name'              => __( 'Sites' ),
				'singular_name'     => __( 'Site' ),
				'add_new'           => __( 'Add Site' ),
				'add_new_item'      => __( 'Add New Site' ),
				'edit_item'         => __( 'Edit Site' ),
				'new_item'          => __( 'New Site' ),
				'view_item'         => __( 'View Site' ),
				'search_items'      => __( 'Search Sites' ),
			),
			'description'           => __( 'Sites in the network' ),
			'public'                => false,
			'show_ui'               => true,
			'publicly_queryable'    => false,
			'exclude_from_search'   => true,
			'menu_position'         => 100,
			// @TODO we need a menu icon here
			'hierarchical'          => false, // @TODO check this
			'query_var'             => false,
			'rewrite'               => false,
			'supports'              => array( 'title' ),
			'can_export'            => true,
			// 'register_meta_box_cb'  => array( $this, 'site_metaboxes' ),
			'capabilities'          => $post_type_capabilities,
		));
	}
}