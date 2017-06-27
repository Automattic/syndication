<?php
/**
 * Site List Screen
 *
 * Functionality added to the list of Syndication Endpoints screen.
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */

namespace Automattic\Syndication\Admin;

/**
 * Class Site_List_Screen
 *
 * @since 2.1
 * @package Automattic\Syndication\Admin
 */
class Site_List_Screen {
	/**
	 * Site_List_Screen constructor.
	 */
	public function __construct() {
		// Loading necessary styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );

		// Custom columns.
		add_filter( 'manage_edit-syn_site_columns', array( $this, 'add_new_columns' ) );
		add_action( 'manage_syn_site_posts_custom_column', array( $this, 'manage_columns' ), 10, 2 );
	}

	/**
	 * Load Scripts and Styles
	 *
	 * Enqueues CSS and JS to the Syndication Endpoint listing screen.
	 *
	 * @since 2.1
	 * @see admin_enqueue_scripts
	 * @param string $hook Current admin page.
	 */
	public function load_scripts_and_styles( $hook ) {
		global $typenow;

		if ( 'syn_site' === $typenow ) {
			if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				wp_enqueue_style( 'syn-edit-sites', SYNDICATION_URL . 'assets/css/admin-edit-site.css', array(), SYNDICATION_VERSION );
			}
		}
	}

	/**
	 * Add New Columns
	 *
	 * Add new columns to the Syndication Endpoint listing screen.
	 *
	 * @param array $columns The existing columns.
	 * @return array
	 */
	public function add_new_columns( $columns ) {
		$new_columns                  = array();
		$new_columns['cb']            = '<input type="checkbox" />';
		$new_columns['title']         = _x( 'Syndication Endpoint Name', 'column name', 'push-syndication' );
		$new_columns['client-type']   = _x( 'Client Type', 'column name', 'push-syndication' );
		$new_columns['syn_sitegroup'] = _x( 'Groups', 'column name', 'push-syndication' );
		$new_columns['date']          = _x( 'Date', 'column name', 'push-syndication' );
		return $new_columns;
	}

	public function manage_columns( $column_name, $id ) {
		global $client_manager;

		switch ( $column_name ) {
			// Output the client label.
			case 'client-type':
				// Fetch the site transport type.
				$transport_type = get_post_meta( $id, 'syn_transport_type', true );

				// Fetch the corresponding client.
				$pull_client = $client_manager->get_pull_or_push_client( $transport_type );

				// Output the client name.
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
