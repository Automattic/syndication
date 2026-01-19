<?php
/**
 * Post type and taxonomy registrar.
 *
 * @package Automattic\Syndication\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\WordPress;

/**
 * Handles registration of the syn_site post type and syn_sitegroup taxonomy.
 *
 * This class encapsulates the post type and taxonomy registration that was
 * previously in WP_Push_Syndication_Server::init().
 */
final class PostTypeRegistrar {

	/**
	 * Post type name.
	 */
	public const POST_TYPE = 'syn_site';

	/**
	 * Taxonomy name.
	 */
	public const TAXONOMY = 'syn_sitegroup';

	/**
	 * Default capability for syndication operations.
	 */
	public const DEFAULT_CAPABILITY = 'manage_options';

	/**
	 * Register the post type and taxonomy.
	 *
	 * Safe to call multiple times - will skip if already registered.
	 */
	public function register(): void {
		$this->register_post_type();
		$this->register_taxonomy();
	}

	/**
	 * Register the syn_site post type.
	 */
	private function register_post_type(): void {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		$capability = $this->get_capability();

		$capabilities = array(
			'edit_post'          => $capability,
			'read_post'          => $capability,
			'delete_post'        => $capability,
			'delete_posts'       => $capability,
			'edit_posts'         => $capability,
			'edit_others_posts'  => $capability,
			'publish_posts'      => $capability,
			'read_private_posts' => $capability,
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $this->get_post_type_labels(),
				'description'         => __( 'Sites in the network', 'push-syndication' ),
				'public'              => false,
				'show_ui'             => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'menu_position'       => 100,
				'hierarchical'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'supports'            => array( 'title' ),
				'can_export'          => true,
				'capabilities'        => $capabilities,
			)
		);
	}

	/**
	 * Register the syn_sitegroup taxonomy.
	 */
	private function register_taxonomy(): void {
		if ( taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		$capabilities = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => $this->get_taxonomy_labels(),
				'public'            => false,
				'show_ui'           => true,
				'show_tagcloud'     => false,
				'show_in_nav_menus' => false,
				'hierarchical'      => true,
				'rewrite'           => false,
				'capabilities'      => $capabilities,
			)
		);
	}

	/**
	 * Get the capability required for syndication.
	 *
	 * @return string The capability.
	 */
	private function get_capability(): string {
		/**
		 * Filters the capability required for syndication operations.
		 *
		 * @param string $capability Default capability.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		return apply_filters( 'syn_syndicate_cap', self::DEFAULT_CAPABILITY );
	}

	/**
	 * Get post type labels.
	 *
	 * @return array<string, string> Labels array.
	 */
	private function get_post_type_labels(): array {
		return array(
			'name'          => __( 'Sites', 'push-syndication' ),
			'singular_name' => __( 'Site', 'push-syndication' ),
			'add_new'       => __( 'Add Site', 'push-syndication' ),
			'add_new_item'  => __( 'Add New Site', 'push-syndication' ),
			'edit_item'     => __( 'Edit Site', 'push-syndication' ),
			'new_item'      => __( 'New Site', 'push-syndication' ),
			'view_item'     => __( 'View Site', 'push-syndication' ),
			'search_items'  => __( 'Search Sites', 'push-syndication' ),
		);
	}

	/**
	 * Get taxonomy labels.
	 *
	 * @return array<string, string> Labels array.
	 */
	private function get_taxonomy_labels(): array {
		return array(
			'name'              => __( 'Site Groups', 'push-syndication' ),
			'singular_name'     => __( 'Site Group', 'push-syndication' ),
			'search_items'      => __( 'Search Site Groups', 'push-syndication' ),
			'popular_items'     => __( 'Popular Site Groups', 'push-syndication' ),
			'all_items'         => __( 'All Site Groups', 'push-syndication' ),
			'parent_item'       => __( 'Parent Site Group', 'push-syndication' ),
			'parent_item_colon' => __( 'Parent Site Group', 'push-syndication' ),
			'edit_item'         => __( 'Edit Site Group', 'push-syndication' ),
			'update_item'       => __( 'Update Site Group', 'push-syndication' ),
			'add_new_item'      => __( 'Add New Site Group', 'push-syndication' ),
			'new_item_name'     => __( 'New Site Group Name', 'push-syndication' ),
		);
	}
}
