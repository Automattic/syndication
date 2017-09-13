<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 *
 * @since 2.1
 * @package Automattic\Syndication\Clients\REST_Push_New
 */

namespace Automattic\Syndication\Clients\REST_Push_New;

/**
 * Class Client_Options
 *
 * @since 2.1
 * @package Automattic\Syndication\Clients\REST_Push_New
 */
class Client_Options {

	/**
	 * Client_Options constructor.
	 *
	 * @since 2.1
	 */
	public function __construct() {
		// Site options.
		add_action( 'syndication/render_site_options/rest_push_new', [ $this, 'render_site_options_push' ] );
		add_action( 'syndication/save_site_options/rest_push_new', [ $this, 'save_site_options_push' ] );

		// Set up the connection test action.
		add_action( 'syndication/test_site_options/rest_push_new', [ $this, 'test_connection' ] );

		// Client settings.
		add_action( 'syndication/render_client_options', [ $this, 'render_client_options' ] );
		add_action( 'syndication/save_client_options', [ $this, 'save_client_options' ] );
	}

	/**
	 * Render the options.
	 *
	 * @since 2.1
	 * @param integer $site_id The ID of the site.
	 */
	public function render_site_options_push( $site_id ) {
		global $settings_manager;

		$site_token = $settings_manager->syndicate_decrypt( get_post_meta( $site_id, 'syn_site_token', true ) );
		$site_url   = get_post_meta( $site_id, 'syn_site_url', true );
		?>
		<p>
			<label for="site_token"><?php echo esc_html__( 'Enter API Token', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_token" id="site_token" size="100" value="<?php echo esc_attr( $site_token ); ?>" />
		</p>
		<p>
			<label for="site_url"><?php echo esc_html__( 'Enter a valid Blog URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" size="100" value="<?php echo esc_url( $site_url ); ?>" />
		</p>
		<?php
		/**
		 * Fires after the site options form renders.
		 *
		 * @since 2.1
		 * @param int $site_id The id of the site being rendered.
		 */
		do_action( 'syn_after_site_form', $site_id );
	}

	/**
	 * Save the options.
	 *
	 * @since 2.1
	 * @param integer $site_id The site ID to save the options to.
	 * @return bool
	 */
	public function save_site_options_push( $site_id ) {
		global $settings_manager;

		// Verify values set before saving.
		$site_token   = isset( $_POST['site_token'] ) ? $_POST['site_token'] : '';
		$site_url     = isset( $_POST['site_url'] ) ? $_POST['site_url'] : '';

		// Save the options.
		update_post_meta( $site_id, 'syn_site_token', $settings_manager->syndicate_encrypt( sanitize_text_field( $site_token ) ) );
		update_post_meta( $site_id, 'syn_site_url', sanitize_text_field( $site_url ) );

		return true;
	}

	/**
	 * Test the connection, used to validate feed.
	 *
	 * @param integer $site_id The ID of the site.
	 * @return bool
	 */
	public function test_connection( $site_id ) {
		global $client_manager;

		$client_manager->test_connection( $site_id );
	}

	/**
	 * Render client options on the Settings->Syndication screen.
	 *
	 * @todo: This could use the same credentials as the legacy WP.com API. We
	 * currently don't have OAuth2 setup for the new REST API, once it's implemented
	 * we may have to create a new form here.
	 *
	 * @since 2.1
	 */
	public function render_client_options() { }

	/**
	 * Save client settings from the Settings->Syndication screen.
	 *
	 * @since 2.1
	 */
	public function save_client_options() { }
}
