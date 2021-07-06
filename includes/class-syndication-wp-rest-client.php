<?php

require_once dirname( __FILE__ ) . '/interface-syndication-client.php';
require_once dirname( __FILE__ ) . '/push-syndicate-encryption.php';

/**
 * Class Syndication_WP_REST_Client
 */
class Syndication_WP_REST_Client implements Syndication_Client {

	private $access_token;
	private $blog_id;

	private $port;
	private $useragent;
	private $timeout;

	/**
	 * Syndication_WP_REST_Client constructor.
	 *
	 * @param int	  $site_id
	 * @param int     $port
	 * @param int     $timeout
	 */
	public function __construct( $site_id, $port = 80, $timeout = 45 ) {
		$this->access_token = push_syndicate_decrypt( get_post_meta( $site_id, 'syn_site_token', true ) );
		$this->blog_id      = get_post_meta( $site_id, 'syn_site_id', true );
		$this->timeout      = $timeout;
		$this->useragent    = 'push-syndication-plugin';
		$this->port         = $port;
	}

	/**
	 * @return array
	 */
	public static function get_client_data() {
		return array(
			'id'    => 'WP_REST',
			'modes' => array( 'push' ),
			'name'  => 'WordPress.com REST',
		);
	}

	/**
	 * @param int $post_id
	 *
	 * @return array|bool|WP_Error
	 */
	public function new_post( $post_id ) {
		$post = (array) get_post( $post_id );

		// This filter can be used to exclude or alter posts during a content push.
		$post = apply_filters( 'syn_rest_push_filter_new_post', $post, $post_id );
		if ( false === $post ) {
			return true;
		}

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $post['post_date_gmt'],
			'categories' => $this->prepare_terms( wp_get_object_terms( $post_id, 'category', array( 'fields' => 'names' ) ) ),
			'tags'       => $this->prepare_terms( wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ) ),
		);

		$body = apply_filters( 'syn_rest_push_filter_new_post_body', $body, $post_id );

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_id . '/posts/new/',
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
	 * @param int $post_id
	 * @param int $ext_id
	 *
	 * @return array|bool|int|WP_Error
	 */
	public function edit_post( $post_id, $ext_id ) {
		$post = (array) get_post( $post_id );

		// This filter can be used to exclude or alter posts during a content push.
		$post = apply_filters( 'syn_rest_push_filter_edit_post', $post, $post_id );
		if ( false === $post ) {
			return true;
		}

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $post['post_date_gmt'],
			'categories' => $this->prepare_terms( wp_get_object_terms( $post_id, 'category', array( 'fields' => 'names' ) ) ),
			'tags'       => $this->prepare_terms( wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ) ),
		);

		$body = apply_filters( 'syn_rest_push_filter_edit_post_body', $body, $post_id );

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_id . '/posts/' . $ext_id . '/',
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
			return $post_id;
		} else {
			return new WP_Error( 'rest-push-edit-fail', $response->message );
		}
	}

	/**
	 * Get an array of values and convert it to CSV
	 *
	 * @param $terms
	 *
	 * @return string
	 */
	private function prepare_terms( $terms ) {
		$terms_csv = '';

		foreach ( $terms as $term ) {
			$terms_csv .= $term . ',';
		}

		return $terms_csv;
	}

	/**
	 * @param int $ext_id
	 *
	 * @return array|bool|WP_Error
	 */
	public function delete_post( $ext_id ) {
		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_id . '/posts/' . $ext_id . '/delete',
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
	 * @return bool
	 */
	public function test_connection() {
		// @TODo find a better method
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
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function is_post_exists( $post_id ) {
		$response = wp_remote_get( //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_id . '/posts/' . $post_id . '/?pretty=1',
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
	 * @param object $site
	 */
	public static function display_settings( $site ) {
		$site_token = push_syndicate_decrypt( get_post_meta( $site->ID, 'syn_site_token', true ) );
		$site_id    = get_post_meta( $site->ID, 'syn_site_id', true );
		$site_url   = get_post_meta( $site->ID, 'syn_site_url', true );

		// @TODO refresh UI

		?>

		<p>
			<?php echo esc_html__( 'To generate the following information automatically please visit the ', 'push-syndication' ); ?>
			<a href="<?php echo esc_url( get_admin_url() ); ?>/options-general.php?page=push-syndicate-settings" target="_blank"><?php echo esc_html__( 'settings page', 'push-syndication' ); ?></a>
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
	 * @param int $site_id
	 *
	 * @return bool
	 */
	public static function save_settings( $site_id ) {
		// @TODO: nonce validation

		if ( isset( $_POST['site_token'] ) ) {
			update_post_meta( $site_id, 'syn_site_token', push_syndicate_encrypt( sanitize_text_field( $_POST['site_token'] ) ) );
		}
		if ( isset( $_POST['site_id'] ) ) {
			update_post_meta( $site_id, 'syn_site_id', sanitize_text_field( $_POST['site_id'] ) );
		}
		if ( isset( $_POST['site_url'] ) ) {
			update_post_meta( $site_id, 'syn_site_url', sanitize_text_field( $_POST['site_url'] ) );
		}
		return true;
	}

	/**
	 * @param int $ext_id
	 *
	 * @return bool|void
	 */
	public function get_post( $ext_id ) {
		// TODO: Implement get_post() method.
	}

	/**
	 * @param array $args
	 *
	 * @return bool|void
	 */
	public function get_posts( $args = array() ) {
		// TODO: Implement get_posts() method.
	}
}
