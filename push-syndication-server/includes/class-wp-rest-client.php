<?php

include_once( dirname( __FILE__ ) . '/interface-wp-client.php' );
include_once( dirname( __FILE__ ) . '/push-syndicate-encryption.php' );

class wp_rest_client implements wp_client{

	private $access_token;
	private $blog_ID;

	private $response;
	private $error_message;
	private $error_code;

	private $port;
	private $useragent;
	private $timeout;

	function __construct( $site_ID, $port = 80, $timeout = 45 ) {

		$this->access_token = push_syndicate_decrypt( get_post_meta( $site_ID, 'syn_site_token', true) );
		$this->blog_ID = get_post_meta( $site_ID, 'syn_site_id', true);

		$this->timeout = $timeout;
		$this->useragent = 'push syndication plugin';
		$this->port = $port;

	}

	public function new_post( $post_ID ) {

		$post = (array)get_post( $post_ID );

		$response = wp_remote_post( 'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/new/', array(
			'method' => 'POST',
			'timeout' => $this->timeout,
			'user-agent' => $this->useragent,
			'sslverify' => false,
			'headers' => array (
				'authorization' => 'Bearer ' . $this->access_token,
				'Content-Type' => 'application/x-www-form-urlencoded'
			),
			'body' => array (
				'title' => $post['post_title'],
				'content' => $post['post_content'],
				'excerpt' => $post['post_excerpt'],
				'status' => $post['post_status'],
				'password' => $post['post_password'],
				'categories' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'category', array('fields' => 'names') ) ),
				'tags' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'post_tag', array('fields' => 'names') ) )
			),
		) );

		if ( is_wp_error( $response ) ) {
			$this->error_message = 'HTTP  connection error!!';
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if( empty($response->error) ) {
			$this->response = $response->ID;
			return true;
		} else {
			$this->error_message = $response->message;
			return false;
		}

	}

	public function edit_post( $post_ID, $ext_ID ) {

		$post = (array)get_post( $post_ID );

		$response = wp_remote_post( 'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $ext_ID . '/', array(
			'method' => 'POST',
			'timeout' => $this->timeout,
			'user-agent' => $this->useragent,
			'sslverify' => false,
			'headers' => array (
				'authorization' => 'Bearer ' . $this->access_token,
				'Content-Type' => 'application/x-www-form-urlencoded'
			),
			'body' => array (
				'title' => $post['post_title'],
				'content' => $post['post_content'],
				'excerpt' => $post['post_excerpt'],
				'status' => $post['post_status'],
				'password' => $post['post_password'],
				'categories' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'category', array('fields' => 'names') ) ),
				'tags' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'post_tag', array('fields' => 'names') ) )
			),
		) );

		if ( is_wp_error( $response ) ) {
			$this->error_message = 'HTTP  connection error!!';
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if( empty($response->error) ) {
			return true;
		} else {
			$this->error_message = $response->message;
			return false;
		}

	}

	// get an array of values and convert it to CSV
	function _prepare_terms( $terms ) {

		$terms_csv = '';

		foreach( $terms as $term ) {
			$terms_csv .= $term . ',';
		}

		return $terms_csv;

	}

	public function delete_post( $ext_ID ) {

		$response = wp_remote_post( 'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $ext_ID . '/delete', array(
			'method' => 'POST',
			'timeout' => $this->timeout,
			'user-agent' => $this->useragent,
			'sslverify' => false,
			'headers' => array (
				'authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			$this->error_message = 'HTTP  connection error!!';
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if( empty($response->error) ) {
			return true;
		} else {
			$this->error_message = $response->message;
			return false;
		}

	}

	public function test_connection() {
		// @TODo find a better method
		$response = wp_remote_post( 'https://public-api.wordpress.com/rest/v1/me/?pretty=1', array(
			'method' => 'GET',
			'timeout' => $this->timeout,
			'user-agent' => $this->useragent,
			'sslverify' => false,
			'headers' => array (
				'authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			$this->error_message = 'HTTP  connection error!!';
			// @TODO error validation and error messages
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if( empty( $response->error ) ) {
			return true;
		} else {
			$this->error_message = $response->message;
			return false;
		}

	}

	public function is_post_exists( $post_ID ) {

		$response = wp_remote_post( 'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $post_ID . '/?pretty=1', array(
			'method' => 'GET',
			'timeout' => $this->timeout,
			'user-agent' => $this->useragent,
			'sslverify' => false,
			'headers' => array (
				'authorization' => 'Bearer ' . $this->access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			$this->error_message = 'HTTP  connection error!!';
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if( empty($response->error) ) {
			return true;
		} else {
			$this->error_message = $response->message;
			return false;
		}

	}

	public function get_response() {
		return $this->response;
	}

	public function get_error_code() {
		return $this->error_code;
	}

	public function get_error_message() {
		return $this->error_message;
	}

	public static function display_settings( $post ) {

		$site_token = push_syndicate_decrypt( get_post_meta( $post->ID, 'syn_site_token', true) );
		$site_id = get_post_meta( $post->ID, 'syn_site_id', true);
		$site_url = get_post_meta( $post->ID, 'syn_site_url', true);

		// @TODO refresh UI

?>
	<p>
		To generate the following information automatically please visit the <a href="<?php echo get_admin_url(); ?>/options-general.php?page=push-syndicate-settings" target="_blank">settings page</a>
	</p>
	<p>
		<label for=site_token>Enter API Token</label>
	</p>
	<p>
		<input type="text" name="site_token" id="site_token" size="100" value="<?php echo esc_html( $site_token ); ?>" />
	</p>
	<p>
		<label for=site_id>Enter Blog ID</label>
	</p>
	<p>
		<input type="text" name="site_id" id="site_id" size="100" value="<?php echo esc_html( $site_id ); ?>" />
	</p>
	<p>
		<label for=site_url>Enter a valid Blog URL</label>
	</p>
	<p>
		<input type="text" name="site_url" id="site_url" size="100" value="<?php echo esc_html( $site_url ); ?>" />
	</p>
<?php

	}

	public static function save_settings( $site_ID ) {

		update_post_meta( $site_ID, 'syn_site_token', push_syndicate_encrypt( sanitize_text_field( $_POST['site_token'] ) ) );
		update_post_meta( $site_ID, 'syn_site_id', sanitize_text_field( $_POST['site_id'] ) );
		update_post_meta( $site_ID, 'syn_site_url', sanitize_text_field( $_POST['site_url'] ) );

		return true;

	}

}