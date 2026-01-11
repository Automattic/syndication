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
	 * @return bool True if post exists on target site, false otherwise.
	 */
	public function is_source_site_post( $meta_key = '', $meta_value = '' ) {

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

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
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
				'sslverify'  => false,
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

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return $response->ID;
		} else {
			return new WP_Error( 'rest-push-new-fail', $response->message );
		}
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
				'sslverify'  => false,
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

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return $post_ID;
		} else {
			return new WP_Error( 'rest-push-edit-fail', $response->message );
		}
	}

	/**
	 * Get an array of values and convert it to CSV.
	 *
	 * @param array $terms Array of term values.
	 *
	 * @return string CSV formatted string of terms.
	 */
	private function _prepare_terms( $terms ) {

		$terms_csv = '';

		foreach ( $terms as $term ) {
			$terms_csv .= $term . ',';
		}

		return $terms_csv;
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
	 * @param string $mysql_date Date in MySQL format (Y-m-d H:i:s).
	 * @return string Date in ISO 8601 format, or empty string if invalid.
	 */
	private function format_date_for_api( $mysql_date ) {
		if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		return mysql2date( 'c', $mysql_date, false );
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
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			) 
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return true;
		} else {
			return new WP_Error( 'rest-push-delete-fail', $response->message );
		}
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
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			) 
		);

		// TODO: return WP_Error.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return true;
		} else {
			return false;
		}
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
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			) 
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Display the site settings form fields.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( $site ) {

		$site_token = push_syndicate_decrypt( get_post_meta( $site->ID, 'syn_site_token', true ) );
		$site_id    = get_post_meta( $site->ID, 'syn_site_id', true );
		$site_url   = get_post_meta( $site->ID, 'syn_site_url', true );

		// @TODO refresh UI.

		?>

		<p>
			<?php echo esc_html__( 'To generate the following information automatically please visit the ', 'push-syndication' ); ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=push-syndicate-settings' ) ); ?>" target="_blank"><?php esc_html_e( 'settings page', 'push-syndication' ); ?></a>
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
		update_post_meta( $site_ID, 'syn_site_token', push_syndicate_encrypt( $token ) );
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
