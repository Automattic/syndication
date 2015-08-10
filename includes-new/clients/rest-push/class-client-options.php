<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 */

namespace Automattic\Syndication\Clients\REST_Push;


class Client_Options {

	public function __construct() {

		// Site options
		add_action( 'syndication/render_site_options/rest_push', [ $this, 'render_site_options_push' ] );
		add_action( 'syndication/save_site_options/rest_push', [ $this, 'save_site_options_push' ] );

		// Set up the connection test action.
		add_action( 'syndication/test_site_options/rest_push', [ $this, 'test_connection' ] );

		// Client settings
		add_action( 'syndication/render_client_options', [ $this, 'render_client_options' ] );
		add_action( 'syndication/save_client_options', [ $this, 'save_client_options' ] );

	}


	public function render_site_options_push( $site_id ) {

		$site_token = push_syndicate_decrypt( get_post_meta( $site->ID, 'syn_site_token', true) );
		$site_id	= get_post_meta( $site->ID, 'syn_site_id', true);
		$site_url   = get_post_meta( $site->ID, 'syn_site_url', true);

		// @TODO refresh UI

		?>

		<p>
			<?php echo esc_html__( 'To generate the following information automatically please visit the ', 'push-syndication' ); ?>
			<a href="<?php echo get_admin_url(); ?>/options-general.php?page=push-syndicate-settings" target="_blank"><?php echo esc_html__( 'settings page', 'push-syndication' ); ?></a>
		</p>
		<p>
			<label for=site_token><?php echo esc_html__( 'Enter API Token', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_token" id="site_token" size="100" value="<?php echo esc_attr( $site_token ); ?>" />
		</p>
		<p>
			<label for=site_id><?php echo esc_html__( 'Enter Blog ID', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_id" id="site_id" size="100" value="<?php echo esc_attr( $site_id ); ?>" />
		</p>
		<p>
			<label for=site_url><?php echo esc_html__( 'Enter a valid Blog URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" size="100" value="<?php echo esc_attr( $site_url ); ?>" />
		</p>

		<?php

		do_action( 'syn_after_site_form', $site );

	}

	public function save_site_options_push( $site_id ) {

		update_post_meta( $site_ID, 'syn_site_token', push_syndicate_encrypt( sanitize_text_field( $_POST['site_token'] ) ) );
		update_post_meta( $site_ID, 'syn_site_id', sanitize_text_field( $_POST['site_id'] ) );
		update_post_meta( $site_ID, 'syn_site_url', sanitize_text_field( $_POST['site_url'] ) );

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
