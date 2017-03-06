<?php

namespace Automattic\Syndication\Custom_Taxonomies;

class Sitegroup_Taxonomy {

	public function __construct()	{
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	public function register_taxonomy() {
		$taxonomy_capabilities = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		register_taxonomy( 'syn_sitegroup', 'syn_site', array(
			'labels' => array(
				'name'              => __( 'Site Groups' ),
				'singular_name'     => __( 'Site Group' ),
				'search_items'      => __( 'Search Site Groups' ),
				'popular_items'     => __( 'Popular Site Groups' ),
				'all_items'         => __( 'All Site Groups' ),
				'parent_item'       => __( 'Parent Site Group' ),
				'parent_item_colon' => __( 'Parent Site Group' ),
				'edit_item'         => __( 'Edit Site Group' ),
				'update_item'       => __( 'Update Site Group' ),
				'add_new_item'      => __( 'Add New Site Group' ),
				'new_item_name'     => __( 'New Site Group Name' ),

			),
			'public'                => false,
			'show_ui'               => true,
			'show_tagcloud'         => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => true,
			'rewrite'               => false,
			'capabilities'          => $taxonomy_capabilities,
		));
	}
}
