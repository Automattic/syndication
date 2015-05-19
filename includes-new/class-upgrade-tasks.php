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

	public function upgrade_to_3() {

		// @todo migrate options
	}
}
