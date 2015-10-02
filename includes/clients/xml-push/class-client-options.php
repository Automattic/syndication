<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 */

namespace Automattic\Syndication\Clients\XML_Push;


class Client_Options {

	public function __construct() {

		// Site options
		add_action( 'syndication/render_site_options/xml_push', [ $this, 'render_site_options_push' ] );
		add_action( 'syndication/save_site_options/xml_push', [ $this, 'save_site_options_push' ] );

		// Set up the connection test action.
		add_action( 'syndication/test_site_options/xml_push', [ $this, 'test_connection' ] );

		// Client settings
		add_action( 'syndication/render_client_options', [ $this, 'render_client_options' ] );
		add_action( 'syndication/save_client_options', [ $this, 'save_client_options' ] );

	}


	public function render_site_options_push( $site_id ) {
		global $settings_manager;

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

		/* This action is documented in includes/clients/rest-push/class-client-options.php */
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
	 * Test the connection, used to validate feed.
	 *
	 * @return bool
	 */
	public function test_connection( $site_ID ) {
		global $client_manager;

		$client_manager->test_connection( $site_ID );
	}


	/**
	 * Render client options on the Settings->Syndication screen.
	 */
	public function render_client_options() {
	}

	/**
	 * Save client settings from the Settings->Syndication screen.
	 */
	public function save_client_options() {
	}
}
