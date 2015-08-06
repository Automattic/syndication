<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 */

namespace Automattic\Syndication\Clients\XML_Push;


class Site_Options {

	public function __construct() {

		add_action( 'syndication/render_site_options/xml_push', [ $this, 'render_site_options_push' ] );
		add_action( 'syndication/save_site_options/xml_push', [ $this, 'save_site_options_push' ] );

		// Set up the connection test action.
		add_action( 'syndication/test_site_options/xml_push', [ $this, 'test_connection' ] );

	}


	public function render_site_options_push( $site_id ) {
		global $settings_manager;

		error_log( 'push xml render_client_options ' );
		$site_url      = get_post_meta( $site_id, 'syn_site_url', true );
		$site_username = get_post_meta( $site_id, 'syn_site_username', true );
		$site_password = $settings_manager->syndicate_decrypt( get_post_meta( $site_id, 'syn_site_password', true ) );

		?>

		<p>
			<label for=site_url><?php echo esc_html__( 'Enter a valid site URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" size="100" value="<?php echo esc_html( $site_url ); ?>" />
		</p>
		<p>
			<label for="site_username"><?php echo esc_html__( 'Enter Username', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_username" id="site_username" size="100" value="<?php echo esc_attr( $site_username ); ?>" />
		</p>
		<p>
			<label><?php echo esc_html__( 'Enter Password', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="password" class="widefat" name="site_password" id="site_password" size="100"  autocomplete="off" value="<?php echo esc_attr( $site_password ); ?>" />
		</p>

		<?php

		do_action( 'syn_after_site_form', $site_id );

	}

	public function save_site_options_push( $site_id ) {
		global $settings_manager;

		/**
		 * If this isn't a save action, bail.
		 */
		if ( ! isset( $_POST['site_url'] ) ) {
			return false;
		}

		/**
		 * Grab and sanitize save values.
		 */
		$site_url = isset( $_POST['site_url'] )      ? sanitize_text_field( $_POST['site_url'] )      : '';
		$username = isset( $_POST['site_username'] ) ? sanitize_text_field( $_POST['site_username'] ) : '';
		$password = isset( $_POST['site_password'] ) ? sanitize_text_field( $_POST['site_password'] ) : '';

		// Remove training `/xmlrpc.php` from site_url if present.
		$site_url = str_replace( '/xmlrpc.php', '', $site_url );

		//
		update_post_meta( $site_id, 'syn_site_url', esc_url_raw( $site_url ) );
		update_post_meta( $site_id, 'syn_site_username', $username );
		update_post_meta( $site_id, 'syn_site_password', $settings_manager->syndicate_encrypt( $password ) );

		if ( ! filter_var( $_POST['site_url'], FILTER_VALIDATE_URL ) ) {
			add_filter( 'redirect_post_location', create_function( '$location', 'return add_query_arg( "message", 301, $location );' ) );
			return false;
		}

		return true;

	}

	/**
	 * Rewrite wp_dropdown_categories output to enable a multiple select
	 * @param  string $result rendered category dropdown list
	 * @return string altered category dropdown list
	 */
	public static function make_multiple_categories_dropdown( $result ) {
		$result = preg_replace( '#^<select name#', '<select multiple="multiple" name', $result );
		return $result;
	}


	/**
	 * Test the connection.
	 *
	 * @return bool
	 */
	public function test_connection( $site_ID ) {
		global $client_manager;

		// Get the required client.
		$client_transport_type = get_post_meta( $site_ID, 'syn_transport_type', true );
		if ( ! $client_transport_type ) {
			return false;
		}

		// Fetch the client so we may pull it's posts
		$client_details = $client_manager->get_push_client( $client_transport_type );

		if ( ! $client_details ) {
			return false;
		}

		// Run the client's process_site method
		$client = new $client_details['class'];

		//@todo this show the user an error message.
		if ( $client->test_connection( $site_ID ) ) {
			add_filter( 'redirect_post_location', create_function( '$location', 'return add_query_arg( "message", 251, $location);' ) );
		} else {
			add_filter( 'redirect_post_location', create_function( '$location', 'return add_query_arg( "message", 252, $location);' ) );
		}
	}

}
