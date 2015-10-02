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
		global $settings_manager;

		$site_token = $settings_manager->syndicate_decrypt( get_post_meta( $site_id, 'syn_site_token', true) );
		$rm_site_id = get_post_meta( $site_id, 'syn_site_id', true);
		$site_url   = get_post_meta( $site_id, 'syn_site_url', true);

		// @TODO refresh UI

		?>

		<p>
			<?php echo esc_html__( 'To generate the following information automatically please visit the ', 'push-syndication' ); ?>
			<a href="<?php echo esc_url( get_admin_url() . 'options-general.php?page=push-syndicate-settings' ); ?>" target="_blank"><?php echo esc_html__( 'settings page', 'push-syndication' ); ?></a>
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
			<input type="text" class="widefat" name="site_id" id="site_id" size="100" value="<?php echo esc_attr( $rm_site_id ); ?>" />
		</p>
		<p>
			<label for=site_url><?php echo esc_html__( 'Enter a valid Blog URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" size="100" value="<?php echo esc_attr( $site_url ); ?>" />
		</p>

		<?php

		/**
		 * Fires after the site options form renders.
		 *
		 * @param int $site_id The id of the site being rendered.
		 */
		do_action( 'syn_after_site_form', $site_id );

	}

	public function save_site_options_push( $site_id ) {
		global $settings_manager;

		// Verify values set before saving.
		$site_token   = isset( $_POST['site_token'] ) ? $_POST['site_token'] : '';
		$feed_site_id = isset( $_POST['site_id'] ) ? $_POST['site_id'] : '';
		$site_url     = isset( $_POST['site_url'] ) ? $_POST['site_url'] : '';

		// Save the options
		update_post_meta( $site_id, 'syn_site_token', $settings_manager->syndicate_encrypt( sanitize_text_field( $site_token ) ) );
		update_post_meta( $site_id, 'syn_site_id', sanitize_text_field( $feed_site_id ) );
		update_post_meta( $site_id, 'syn_site_url', sanitize_text_field( $site_url ) );

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
		add_settings_section( 'api_token', esc_html__( ' API Token Configuration ', 'push-syndication' ), array( $this, 'display_apitoken_description' ), 'api_token' );
		add_settings_field( 'client_id', esc_html__( ' Enter your client id ', 'push-syndication' ), array( $this, 'display_client_id' ), 'api_token', 'api_token' );
		add_settings_field( 'client_secret', esc_html__( ' Enter your client secret ', 'push-syndication' ), array( $this, 'display_client_secret' ), 'api_token', 'api_token' );
		do_settings_sections( 'api_token' );
		$this->get_api_token();
	}

	public function  display_apitoken_description() {
		// @TODO add client type information
		echo '<p>' . esc_html__( 'To syndicate content to WordPress.com you must ', 'push-syndication' ). '<a href="https://developer.wordpress.com/apps/new/">' . esc_html__( 'create a new application', 'push-syndication' ) . '</a></p>';
		echo '<p>' . esc_html__( 'Enter the Redirect URI as follows', 'push-syndication' ) . '</p>';
		echo '<p><b>' . esc_html( menu_page_url( 'push-syndicate-settings', false ) ) . '</p></b>';
	}

	public function display_client_id() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_id]" value="' . esc_attr( $settings_manager->get_setting( 'client_id' ) ) . '"/>';
	}

	public function display_client_secret() {
		global $settings_manager;
		echo '<input type="text" size=100 name="push_syndicate_settings[client_secret]" value="' . esc_attr( $settings_manager->get_setting( 'client_secret' ) ) . '"/>';
	}

	public function get_api_token() {
		global $settings_manager;
		$redirect_uri           = menu_page_url( 'push-syndicate-settings', false );
		$authorization_endpoint = 'https://public-api.wordpress.com/oauth2/authorize?client_id=' . $settings_manager->get_setting( 'client_id' ) . '&redirect_uri=' .  $redirect_uri . '&response_type=code';

		echo '<h3>' . esc_html__( 'Authorization ', 'push-syndication' ) . '</h3>';

		// if code is not found return or settings updated return
		if ( empty( $_GET['code'] ) || ! empty( $_GET['settings-updated'] ) ) {

			echo '<p>' . esc_html__( 'Click the authorize button to generate api token', 'push-syndication' ) . '</p>';

			?>

			<input type=button class="button-primary" onClick="parent.location='<?php echo esc_url( $authorization_endpoint ); ?>'" value=" Authorize  ">

			<?php

			return;

		}

		$response = wp_remote_post(
			'https://public-api.wordpress.com/oauth2/token',
			array(
				'sslverify' => false,
				'body'      => array(
					'client_id'     => $settings_manager->get_setting( 'client_id' ),
					'redirect_uri'  => $redirect_uri,
					'client_secret' => $settings_manager->get_setting( 'client_secret' ),
					'code'          => $_GET['code'],
					'grant_type'    => 'authorization_code',
				),
			)
		);

		$result = json_decode( $response['body'] );

		if ( ! empty( $result->error ) ) {

			echo '<p>' . esc_html__( 'Error retrieving API token ', 'push-syndication' ) . esc_html( $result->error_description ) . esc_html__( 'Please authorize again', 'push-syndication' ) . '</p>';

			?>

			<input type=button class="button-primary" onClick="parent.location='<?php echo esc_url( $authorization_endpoint ); ?>'" value=" Authorize  ">

			<?php

			return;

		}
		?>

		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row">Access token</th>
				<td><?php echo esc_html( $result->access_token ); ?></td>
			</tr>
			<tr valign="top">
				<th scope="row">Blog ID</th>
				<td><?php echo esc_html( $result->blog_id ); ?></td>
			</tr>
			<tr valign="top">
				<th scope="row">Blog URL</th>
				<td><?php echo esc_html( $result->blog_url ); ?></td>
			</tr>
			</tbody>
		</table>

		<?php

		echo '<p>' . esc_html__( 'Enter the above details in relevant fields when registering a ', 'push-syndication' ). '<a href="http://wordpress.com" target="_blank">WordPress.com</a>' . esc_html__( 'site', 'push-syndication' ) . '</p>';

	}

	/**
	 * Save client settings from the Settings->Syndication screen.
	 */
	public function save_client_options() {
	}
}
