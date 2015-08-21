<?php
/**
 * Site List Screen
 */

namespace Automattic\Syndication\Admin;

class Site_List_Screen {

	public function __construct() {

		// loading necessary styles and scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );

		// custom columns
		add_filter( 'manage_edit-syn_site_columns', array( $this, 'add_new_columns' ) );
		add_action( 'manage_syn_site_posts_custom_column', array( $this, 'manage_columns' ), 10, 2 );
	}


	public function load_scripts_and_styles( $hook ) {
		global $typenow;

		if ( 'syn_site' == $typenow ) {
			if ( in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
				wp_enqueue_style( 'syn-edit-sites', SYNDICATION_URL . 'assets/css/admin-edit-site.css', array(), SYNDICATION_VERSION );
			}
		}
	}

	public function add_new_columns( $columns ) {

		$new_columns                  = array();
		$new_columns['cb']            = '<input type="checkbox" />';
		$new_columns['title']         = _x( 'Site Name', 'column name' );
		$new_columns['client-type']   = _x( 'Client Type', 'column name' );
		$new_columns['syn_sitegroup'] = _x( 'Groups', 'column name' );
		$new_columns['date']          = _x( 'Date', 'column name' );

		return $new_columns;
	}

	public function manage_columns( $column_name, $id ) {

		global $client_manager;
		switch ( $column_name ) {

			// Output the client label
			case 'client-type':

				// Fetch the site transport type
				$transport_type = get_post_meta( $id, 'syn_transport_type', true );

				// Fetch the corresponding client
				$pull_client = $client_manager->get_pull_or_push_client( $transport_type );

				// Output the client name
				if ( isset( $pull_client['label'] ) ) {
					echo esc_html( $pull_client['label'] );
				}

				break;
			case 'syn_sitegroup':
				the_terms( $id, 'syn_sitegroup', '', ', ', '' );
				break;
			default:
				break;
		}
	}
}
