<?php

namespace Automattic\Syndication;
use WP_Query;

/**
 * Site Manager
 *
 * The role of the site manager is to manage syndication sites.
 * This entails fetching sites and pushing/pulling content for them.
 *
 * The actual site post type and sitegroup taxonomy registration is handled in the main plugin bootstrap.
 *
 * Likewise, because sites are simply post types and sitegroups are taxonomy terms;
 * the CRUD operations for each are handled by WordPress core.
 *
 * @package Automattic\Syndication
 */
class Site_Manager {

	protected $_sites = null;

	public function __construct() {
		add_action( 'syndication/init', [ $this, 'init' ] );
		add_action( 'save_post_syn_site', [ $this, 'prime_site_cache' ] );
		add_action( 'before_delete_post', [ $this, 'prime_site_cache_delete' ] );
	}

	public function init() {
		do_action( 'syndication/site_manager_init', [ $this ] );
		$this->_sites = $this->get_site_index();
	}

	public function get_site_index( $prime_cache=false ) {

		if ( ! is_null( $this->_sites ) && false === $prime_cache ) {
			return $this->_sites;
		}

		$sites = wp_cache_get( 'syn_site_cache', 'syndication' );

		if ( false === $sites || true === $prime_cache ) {
			$results = new WP_Query( array(
				'post_type'         => 'syn_site',
				'posts_per_page'    => apply_filters( 'syn_posts_per_page_override', 100 ),
			) );
			$sites = array(
				'by_site_group' => array(),
				'by_client' => array(),
				'by_status' => array(),
			);
			foreach( (array) $results->posts as $site_post ) {
				$site_enabled = (boolean) get_post_meta( $site_post->ID, 'syn_site_enabled', true);
				$site_groups = wp_get_object_terms( $site_post->ID, 'syn_sitegroup' );

				$sites['all'][$site_post->ID] = $site_post;
				if ( ! isset( $sites['by_status'][$site_enabled] ) || ! in_array( $site_post->ID, $sites['by_status'][$site_enabled] ) ) {
					$sites['by_status'][(true === $site_enabled) ? 'enabled' : 'disabled'][] = $site_post->ID;
				}
				foreach( $site_groups as $group_term ) {
					if ( ! isset( $sites['by_site_group'][$group_term->slug] ) || ! in_array( $site_post->ID, $sites['by_site_group'][$group_term->slug] ) ) {
						$sites['by_site_group'][$group_term->slug][] = $site_post->ID;
					}
				}
			}

			wp_cache_set( 'syn_site_cache', $sites, 'syndication' );
		}

		return $sites;
	}

	private function sort_sites_by_last_pull_date( $site_a, $site_b ) {
		$site_a_pull_date = (int) get_post_meta( $site_a->ID, 'syn_last_pull_time', true );
		$site_b_pull_date = (int) get_post_meta( $site_b->ID, 'syn_last_pull_time', true );

		if ( $site_a_pull_date == $site_b_pull_date )
			return 0;

		return ( $site_a_pull_date < $site_b_pull_date ) ? -1 : 1;
	}

	public function pull_get_selected_sites() {
		$selected_sitegroups = array( 'local' ); // $this->push_syndicate_settings['selected_pull_sitegroups'];

		$sites = array();
		foreach( $selected_sitegroups as $selected_sitegroup ) {
			$sites = array_merge( $sites, $this->get_sites_by_sitegroup( $selected_sitegroup ) );
		}

		// Order by last update date
		usort( $sites, array( $this, 'sort_sites_by_last_pull_date' ) );

		return $sites;
	}


	public function get_sites_by_sitegroup( $site_group_slug ) {
		$sites = $this->get_site_index();

		if ( isset( $sites['by_site_group'][ $site_group_slug ] ) ) {
			$site_post_ids = $sites['by_site_group'][ $site_group_slug ];

			return $sites['by_site_group'][ $site_group_slug ];
		}
		return array();
	}

	public function get_sites_by_client( $client_slug ) {
		$sites = $this->get_site_index();

		if ( isset( $sites['by_client'][ $client_slug ] ) ) {
			return $sites['by_client'][ $client_slug ];
		}
		return array();
	}

	public function get_sites_by_post_ID( $post_ID ) {
		// TODO
	}

	public function prime_site_cache( $post_id ) {
		$this->get_site_index( $prime_cache = true );
	}

	public function prime_site_cache_delete( $post_id ) {
		$post = get_post( $post_id );
		if ( 'syn_site' == $post->post_type ) {
			$this->get_site_index( $prime_cache = true );
		}
	}
}
