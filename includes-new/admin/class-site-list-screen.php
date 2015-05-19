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
		add_action( 'manage_syn_site_posts_custom_column', array( $this, 'manage_columns' ), 10, 2);
	}


	public function load_scripts_and_styles( $hook ) {
		global $typenow;

		if ( 'syn_site' == $typenow ) {
			if( $hook == 'edit.php' ) {
				wp_enqueue_style( 'syn-edit-sites', plugins_url( 'css/sites.css', __FILE__ ), array(), SYNDICATION_VERSION );
			}
		}
	}

	public function add_new_columns( $columns ) {

		$new_columns = array();
		$new_columns['cb'] = '<input type="checkbox" />';
		$new_columns['title'] = _x( 'Site Name', 'column name' );
		$new_columns['client-type'] = _x( 'Client Type', 'column name' );
		$new_columns['syn_sitegroup'] = _x( 'Groups', 'column name' );
		$new_columns['date'] = _x('Date', 'column name');

		return $new_columns;
	}

	public function manage_columns( $column_name, $id ) {

		global $wpdb;
		switch ( $column_name ) {
			case 'client-type':
//				$transport_type = get_post_meta( $id, 'syn_transport_type', true );
//				try {
//					$client = Syndication_Client_Factory::get_client( $transport_type, $id );
//					$client_data = $client->get_client_data();
//					echo esc_html( sprintf( '%s (%s)', $client_data['name'], array_shift( $client_data['modes'] ) ) );
//				} catch ( Exception $e ) {
//					printf( __( 'Unknown (%s)', 'push-syndication' ), esc_html( $transport_type ) );
//				}
				break;
			case 'syn_sitegroup':
				the_terms( $id, 'syn_sitegroup', '', ', ', '' );
				break;
			default:
				break;
		}
	}
}
