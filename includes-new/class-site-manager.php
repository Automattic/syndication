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

	public $site_status_meta_key = 'syn_site_status';

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
				$site_enabled = get_post_meta( $site_post->ID, 'syn_site_enabled', true);
				$site_groups  = wp_get_object_terms( $site_post->ID, 'syn_sitegroup' );
				$site_enabled = 'on' === $site_enabled ? true : false;

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
		global $settings_manager;

		$selected_sitegroups = $settings_manager->get_setting( 'selected_pull_sitegroups' );

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

	/**
	 * Retrieve a list of sites by status.
	 */
	public function get_sites_by_status( $status_slug ) {
		$sites = $this->get_site_index();

		if ( isset( $sites['by_status'][ $status_slug ] ) ) {
			return $sites['by_status'][ $status_slug ];
		}
		return array();
	}

	/**
	 * Get site data from a site group.
	 *
	 * @param $post_ID The id of the site group.
	 *
	 * @return array
	 *          post_ID        The passed post ID
	 *          selected_sites A list of selected sites.
	 *          removed_sites  A list of removed sites.
	 */
	public function get_sites_by_post_ID( $post_ID ) {
		$all_sites = $this->get_site_index();

		$selected_sitegroups    = get_post_meta( $post_ID, '_syn_selected_sitegroups', true );
		$selected_sitegroups    = !empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;
		$old_sitegroups         = get_post_meta( $post_ID, '_syn_old_sitegroups', true );
		$old_sitegroups         = !empty( $old_sitegroups ) ? $old_sitegroups : array() ;
		$removed_sitegroups     = array_diff( $old_sitegroups, $selected_sitegroups );

		// Initialize return object.
		$data = array(
			'post_ID'           => $post_ID,
			'selected_sites'    => array(),
			'removed_sites'     => array(),
		);

		// Find sites in selected site groups.
		if( ! empty( $selected_sitegroups ) ) {

			foreach( $selected_sitegroups as $selected_sitegroup ) {

				// get all the sites in the sitegroup
				$sites = $all_sites['by_site_group'][ $selected_sitegroup ];
				if( empty( $sites ) ) {
					continue;
				}

				foreach( $sites as $site ) {
					$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true);
					if( $site_enabled == 'on' ) {
						$data[ 'selected_sites' ][] = $site;
					}
				}

			}

		}

		// Find sites in removed site groups.
		if( ! empty( $removed_sitegroups ) ) {

			foreach( $removed_sitegroups as $removed_sitegroup ) {

				// get all the sites in the sitegroup
				$sites = $all_sites['by_site_group'][ $removed_sitegroup ];
				if( empty( $sites ) ) {
					continue;
				}

				foreach( $sites as $site ) {
					$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true);
					if( $site_enabled == 'on' ) {
						$data[ 'removed_sites' ][] = $site;
					}
				}

			}

		}

		update_post_meta( $post_ID, '_syn_old_sitegroups', $selected_sitegroups );

		return $data;

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

	/**
	 * Wrapper for get_post_meta to return a site's current status
	 *
	 * Possible site statuses:
	 * - idle
	 * - pulling
	 * - pushing
	 * - processing
	 *
	 * @param int $site_id
	 * @return mixed|bool Meta key value on success, false on failure
	 */
	public function get_site_status( $site_id = 0 ) {
		if ( isset( $site_id ) && 0 !== $site_id ) {
			return get_post_meta( (int) $site_id, $this->site_status_meta_key, true );
		} else {
			return false;
		}
	}

	/**
	 * Update a site's status
	 *
	 * Available site statuses:
	 * - idle
	 * - pulling
	 * - pushing
	 * - processing
	 *
	 * @param int $site_id       The site ID for which to update
	 * @param string $new_status The new site status
	 * @return mixed|bool        $meta_id on success, false on failure
	 */
	public function update_site_status( $site_id = 0, $new_status = '' ) {
		if ( isset( $new_status ) && ! empty( $new_status ) ) {
			return update_post_meta( (int) $site_id, $this->site_status_meta_key, sanitize_title( $new_status ) );
		} else {
			return false;
		}
	}
}
