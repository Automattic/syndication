<?php
/**
 * WordPress REST API client for content syndication.
 *
 * @package Syndication
 */

require_once __DIR__ . '/interface-syndication-client.php';
require_once __DIR__ . '/push-syndicate-encryption.php';

/**
 * Class Syndication_WP_REST_Client
 *
 * Implements the Syndication_Client interface using the WordPress REST API
 * for pushing and pulling content between sites.
 */
class Syndication_WP_REST_Client implements Syndication_Client {

	private $access_token;
	private $blog_ID;

	private $port;
	private $useragent;
	private $timeout;

	/**
	 * Constructor.
	 *
	 * @param int $site_ID The site post ID.
	 * @param int $port    The port number. Default 80.
	 * @param int $timeout The request timeout in seconds. Default 45.
	 */
	public function __construct( $site_ID, $port = 80, $timeout = 45 ) {

		$this->access_token = push_syndicate_decrypt( get_post_meta( $site_ID, 'syn_site_token', true ) );
		$this->blog_ID      = get_post_meta( $site_ID, 'syn_site_id', true );
		$this->timeout      = $timeout;
		$this->useragent    = 'push-syndication-plugin';
		$this->port         = $port;
	}

	/**
	 * Get the client type data.
	 *
	 * @return array Client ID, supported modes, and display name.
	 */
	public static function get_client_data() {
		return array(
			'id'    => 'WP_REST',
			'modes' => array( 'push' ),
			'name'  => 'WordPress.com REST',
		);
	}

	/**
	 * Check if a post with the given meta key/value exists on the target site.
	 *
	 * Used to prevent syndication loops when syndicating back to source.
	 *
	 * @since 2.2.0
	 *
	 * @param string $meta_key   The meta key to search for.
	 * @param string $meta_value The meta value to match.
	 * @param string $post_type  Optional. Post type to search. Default empty, which searches the API's default, 'post'.
	 * @return bool True if post exists on target site, false otherwise.
	 */
	public function is_source_site_post( $meta_key = '', $meta_value = '', $post_type = '' ) {

		// If meta key or value are empty.
		if ( empty( $meta_key ) || empty( $meta_value ) ) {
			return false;
		}

		// Get posts from the target website matching the meta key and value.
		$url = sprintf(
			'https://public-api.wordpress.com/rest/v1/sites/%s/posts/?meta_key=%s&meta_value=%s',
			$this->blog_ID,
			rawurlencode( $meta_key ),
			rawurlencode( $meta_value )
		);
		if ( $post_type ) {
			$url .= '&type=' . rawurlencode( $post_type );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) && ! empty( $response->found ) && $response->found > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Create a new post on the remote site.
	 *
	 * @param int $post_ID The local post ID to syndicate.
	 * @return int|WP_Error|true The remote post ID on success, WP_Error on failure, or true if filtered out.
	 */
	public function new_post( $post_ID ) {

		$post = (array) get_post( $post_ID );

		// This filter can be used to exclude or alter posts during a content push.
		$post = apply_filters( 'syn_rest_push_filter_new_post', $post, $post_ID );
		if ( false === $post ) {
			return true;
		}

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $this->format_date_for_api( $post['post_date_gmt'] ),
			'categories' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'category', array( 'fields' => 'names' ) ) ),
			'tags'       => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'post_tag', array( 'fields' => 'names' ) ) ),
		);

		$body = apply_filters( 'syn_rest_push_filter_new_post_body', $body, $post_ID );

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/new/',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'       => $body,
			) 
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = $this->parse_response( $response, 'rest-push-new-fail' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response->ID ) ) {
			return new WP_Error( 'rest-push-new-fail', __( 'WordPress.com did not return the ID of the new post.', 'push-syndication' ) );
		}

		return $response->ID;
	}

	/**
	 * Update an existing post on the remote site.
	 *
	 * @param int $post_ID The local post ID.
	 * @param int $ext_ID  The remote post ID.
	 * @return int|WP_Error|true The local post ID on success, WP_Error on failure, or true if filtered out.
	 */
	public function edit_post( $post_ID, $ext_ID ) {

		$post = (array) get_post( $post_ID );

		// This filter can be used to exclude or alter posts during a content push.
		$post = apply_filters( 'syn_rest_push_filter_edit_post', $post, $post_ID );
		if ( false === $post ) {
			return true;
		}

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $this->format_date_for_api( $post['post_date_gmt'] ),
			'categories' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'category', array( 'fields' => 'names' ) ) ),
			'tags'       => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'post_tag', array( 'fields' => 'names' ) ) ),
		);

		$body = apply_filters( 'syn_rest_push_filter_edit_post_body', $body, $post_ID );

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $ext_ID . '/',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'       => $body,
			) 
		);

		$response = $this->parse_response( $response, 'rest-push-edit-fail' );

		return is_wp_error( $response ) ? $response : $post_ID;
	}

	/**
	 * Get an array of values and convert it to CSV.
	 *
	 * @param array $terms Array of term values.
	 *
	 * @return string CSV formatted string of terms.
	 */
	private function _prepare_terms( $terms ) {

		return is_array( $terms ) ? implode( ',', $terms ) : '';
	}

	/**
	 * Decode a WordPress.com REST API response, turning any failure into a WP_Error.
	 *
	 * @param array|WP_Error $response   Response from wp_remote_*().
	 * @param string         $error_code Error code for a failed response.
	 * @return object|WP_Error The decoded body, or WP_Error on failure.
	 */
	private function parse_response( $response, $error_code ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $status >= 200 && $status < 300 && is_object( $body ) && empty( $body->error ) ) {
			return $body;
		}

		if ( is_object( $body ) && ! empty( $body->message ) && is_string( $body->message ) ) {
			$message = $body->message;
		} else {
			/* translators: %d: HTTP status code. */
			$message = sprintf( __( 'Unexpected response from WordPress.com (HTTP status %d).', 'push-syndication' ), $status );
		}

		return new WP_Error( $error_code, $message );
	}

	/**
	 * Format a MySQL date string for the WordPress.com REST API.
	 *
	 * The API expects dates in ISO 8601 format. This is especially important
	 * for scheduled posts (status 'future') to ensure the scheduled date is
	 * preserved on the target site.
	 *
	 * @since 2.2.0
	 *
	 * @param string $mysql_date GMT date in MySQL format (Y-m-d H:i:s).
	 * @return string Date in ISO 8601 format, or empty string if invalid.
	 */
	private function format_date_for_api( $mysql_date ) {
		if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		// The date is GMT, so read it as UTC. mysql2date() would read it in the
		// site's timezone and shift the post by the site's UTC offset.
		$timestamp = strtotime( $mysql_date . ' UTC' );

		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	/**
	 * Delete a post on the remote site.
	 *
	 * @param int $ext_ID The remote post ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_post( $ext_ID ) {

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $ext_ID . '/delete',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			) 
		);

		$response = $this->parse_response( $response, 'rest-push-delete-fail' );

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Test the connection to the remote WordPress.com site.
	 *
	 * @return bool True if connection is successful, false otherwise.
	 */
	public function test_connection() {
		// @TODo find a better method.
		$response = wp_remote_get(
			'https://public-api.wordpress.com/rest/v1/me/?pretty=1',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			) 
		);

		return ! is_wp_error( $this->parse_response( $response, 'rest-test-connection-fail' ) );
	}

	/**
	 * Check if a post exists on the remote site.
	 *
	 * @param int $post_ID The remote post ID.
	 * @return bool True if post exists, false otherwise.
	 */
	public function is_post_exists( $post_ID ) {

		$response = wp_remote_get(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $post_ID . '/?pretty=1',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			) 
		);

		// Only a 404 says the post is gone. The caller recreates a missing post, so
		// treating a timeout or outage as missing would duplicate it on the remote
		// site; assume it exists and let the following edit or delete report the error.
		return 404 !== wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Display the site settings form fields.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( $site ) {

		$has_token = '' !== (string) get_post_meta( $site->ID, 'syn_site_token', true );
		$site_id   = get_post_meta( $site->ID, 'syn_site_id', true );
		$site_url  = get_post_meta( $site->ID, 'syn_site_url', true );

		// @TODO refresh UI.

		?>

		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: URL of the plugin settings page, 2: Screen reader text saying the link opens in a new tab. */
					__( 'To generate the following information automatically, visit the <a href="%1$s" target="_blank">settings page%2$s</a>.', 'push-syndication' ),
					esc_url( admin_url( 'options-general.php?page=push-syndicate-settings' ) ),
					' <span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'push-syndication' ) . '</span>'
				),
				array(
					'a'    => array(
						'href'   => array(),
						'target' => array(),
					),
					'span' => array( 'class' => array() ),
				)
			);
			?>
		</p>
		<p>
			<label for="site_token"><?php echo esc_html__( 'Enter API Token', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="password" class="widefat" name="site_token" id="site_token" size="100" autocomplete="off" value=""<?php echo $has_token ? ' aria-describedby="site_token-description"' : ''; ?> />
		</p>
		<?php if ( $has_token ) : ?>
			<p class="description" id="site_token-description"><?php esc_html_e( 'Leave blank to keep the saved value.', 'push-syndication' ); ?></p>
		<?php endif; ?>
		<p>
			<label for="site_id"><?php echo esc_html__( 'Enter Blog ID', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_id" id="site_id" size="100" value="<?php echo esc_attr( $site_id ); ?>" />
		</p>
		<p>
			<label for="site_url"><?php echo esc_html__( 'Enter a valid Blog URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" size="100" value="<?php echo esc_attr( $site_url ); ?>" />
		</p>

		<?php

		do_action( 'syn_after_site_form', $site );
	}

	/**
	 * Save the site settings from POST data.
	 *
	 * @param int $site_ID The site post ID.
	 * @return bool True on success.
	 */
	public static function save_settings( $site_ID ) {
		// Use wp_strip_all_tags() for the token instead of sanitize_text_field()
		// because sanitize_text_field() converts encoded octets (e.g., %B2) which
		// can break OAuth tokens. The token is encrypted before storage anyway.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Token sanitized with wp_strip_all_tags.
		$token = isset( $_POST['site_token'] ) ? wp_strip_all_tags( wp_unslash( $_POST['site_token'] ) ) : '';

		// The token field is write-only: a blank submission keeps the stored token.
		if ( '' !== $token ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- $site_ID is the parameter name set by Syndication_Client.
			update_post_meta( $site_ID, 'syn_site_token', push_syndicate_encrypt( $token ) );
		}
		update_post_meta( $site_ID, 'syn_site_id', isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_site_url', isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : '' );

		return true;
	}

	/**
	 * Get a post from the remote site.
	 *
	 * @param int $ext_ID The remote post ID.
	 */
	public function get_post( $ext_ID ) {
		// TODO: Implement get_post() method.
	}

	/**
	 * Get posts from the remote site.
	 *
	 * @param array $args Optional arguments.
	 */
	public function get_posts( $args = array() ) {
		// TODO: Implement get_posts() method.
	}
}
