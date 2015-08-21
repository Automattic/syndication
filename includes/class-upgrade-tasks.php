<?php
/**
 * Upgrade Tasks
 *
 * Perform any tasks necessary to migrate old plugin data to the current plugin.
 */

namespace Automattic\Syndication;

class Upgrade_Tasks {

	public $version;

	public function __construct() {
		$this->version = get_option( 'syn_version' );

		// @todo Confirm that upgrades do not also need to happen on regular init.
		add_action( 'admin_init', [ $this, 'upgrade' ] );
		add_action( 'admin_init', [ $this, 'upgrade_to_3_0_0' ] );
	}

	public function upgrade() {

		global $wpdb;

		if ( version_compare( $this->version, SYNDICATION_VERSION, '>=' ) )
			return;

		// upgrade to 2.1
		if ( version_compare( $this->version, '2.0', '<=' ) ) {
			$inserted_posts_by_site = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'syn_inserted_posts'" ); // cache pass : runs only on upgrades and needs fresh data
			foreach ( $inserted_posts_by_site as $site_id ) {
				$inserted_posts = get_post_meta( $site_id, 'syn_inserted_posts', true );

				foreach ( $inserted_posts as $inserted_post_id => $inserted_post_guid ) {
					update_post_meta( $inserted_post_id, 'syn_post_guid', $inserted_post_guid );
					update_post_meta( $inserted_post_id, 'syn_source_site_id', $site_id );
				}
			}

			update_option( 'syn_version', '2.1' );
		}

		update_option( 'syn_version', SYNDICATION_VERSION );
	}

	/**
	 * Version 3.0.0 Upgrade Routine
	 *
	 * + In Version 2.0 we set each site's transport_type post meta to WP_XML, WP_RSS, WP_REST, or
	 * WP_XMLRPC. In version 3.0.0 we need to convert these to xml_pull, rss_pull, rest_push,
	 * xmlrpc_push; respectively.
	 *
	 */
	public function upgrade_to_3_0_0() {

		// Only proceed if an update to 3.0.0. is required
		if ( version_compare( $this->version, '3.0.0', '<' ) ) :

			// Upgrade individual sites
			global $site_manager;

			// Fetch all sites
			$sites = $site_manager->get_site_index();


			// Loop through each site
			foreach ( $sites['all'] as $site ) :
				$new_transport_type = '';

				// Fetch the site's old transport type
				$transport_type = get_post_meta( $site->ID, 'syn_transport_type', true );

				// Only proceed if we found a transport type
				if ( false !== $transport_type ) :

					// Determine the site's new transport type
					switch ( $transport_type ) :
						case 'WP_XML'    : $new_transport_type = 'xml_pull';    break;
						case 'WP_RSS'    : $new_transport_type = 'rss_pull';    break;
						case 'WP_REST'   : $new_transport_type = 'rest_push';   break;
						case 'WP_XMLRPC' : $new_transport_type = 'xml_push'; break;
					endswitch;

					// Update the site's transport type
					update_post_meta( $site->ID, 'syn_transport_type', $new_transport_type );
				endif;
			endforeach;

		endif;
	}
}
