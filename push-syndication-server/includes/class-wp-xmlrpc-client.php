<?php

include_once( ABSPATH . 'wp-includes/class-IXR.php' );
include_once( ABSPATH . 'wp-includes/class-wp-http-ixr-client.php' );
include_once( dirname( __FILE__ ) . '/interface-wp-client.php' );
include_once( dirname( __FILE__ ) . '/push-syndicate-encryption.php' );

class wp_xmlrpc_client extends WP_HTTP_IXR_Client implements wp_client {

    private $username;
    private $password;

    function __construct( $site_ID ) {

	    // @TODO check port, timeout etc
		$server = untrailingslashit( get_post_meta( $site_ID, 'syn_site_url', true ) );
		$server = esc_url_raw( $server . '/xmlrpc.php' );

	    parent::__construct( $server );
        $this->username = get_post_meta( $site_ID, 'syn_site_username', true);
        $this->password = push_syndicate_decrypt( get_post_meta( $site_ID, 'syn_site_password', true) );

    }

    public function new_post( $post_ID ) {

        $post = (array)get_post( $post_ID );

        // rearranging arguments
        $args = array();
        $args['post_title'] = $post['post_title'];
        $args['post_content'] = $post['post_content'];
        $args['post_excerpt'] = $post['post_excerpt'];
        $args['post_status'] = $post['post_status'];
        $args['post_type'] = $post['post_type'];
        $args['wp_password'] = $post['post_password'];

	    // @TODO extend this to custom taxonomies
	    $args['terms_names'] = array(
		    'category' => wp_get_object_terms( $post_ID, 'category', array('fields' => 'names') ),
		    'post_tag' => wp_get_object_terms( $post_ID, 'post_tag', array('fields' => 'names') )
	    );

	    // post meta
	    $custom_fields= array();
	    $custom_fields[] = array( 'key' => 'masterpost_url', 'value' =>  $post['guid'] );
	    $args['custom_fields'] = $custom_fields;

        $result = $this->query(
            'wp.newPost',
            '1',
            $this->username,
            $this->password,
            $args
        );

        if( !$result ) {
            return false;
        }

        return true;

    }

    public function edit_post( $post_ID, $ext_ID ) {

        $post = (array)get_post( $post_ID );

        // rearranging arguments
        $args = array();
        $args['post_title'] = $post['post_title'];
        $args['post_content'] = $post['post_content'];
        $args['post_excerpt'] = $post['post_excerpt'];
        $args['post_status'] = $post['post_status'];
        $args['post_type'] = $post['post_type'];
        $args['wp_password'] = $post['post_password'];

	    // @TODO extend this to custom taxonomies
	    $args['terms_names'] = array(
		    'category' => wp_get_object_terms( $post_ID, 'category', array('fields' => 'names') ),
		    'post_tag' => wp_get_object_terms( $post_ID, 'post_tag', array('fields' => 'names') )
	    );

	    // post meta
	    $custom_fields= array();
	    $custom_fields[] = array( 'key' => 'masterpost_url', 'value' =>  $post['guid'] );
	    $args['custom_fields'] = $custom_fields;

        $result = $this->query(
            'wp.editPost',
            '1',
            $this->username,
            $this->password,
            $ext_ID,
            $args
        );

        if( !$result ) {
            return false;
        }

        return true;

    }

    public function delete_post( $ext_ID ) {

        $result = $this->query(
                'wp.deletePost',
                '1',
                $this->username,
                $this->password,
                $ext_ID
        );

        if( !$result ) {
                return false;
        }

        return true;
    }

	public function test_connection() {

		$result = $this->query(
			'wp.getPostTypes', // @TODO find a better suitable function
			'1',
			$this->username,
			$this->password
		);

		if( !$result ) {

			$error_code = absint($this->get_error_code());

			switch( $error_code ) {
				case 32301:
					add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 305, $location);' ) );
					break;
				case 401:
					add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 302, $location);' ) );
					break;
				case 403:
					add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 303, $location);' ) );
					break;
				case 405:
					add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 304, $location);' ) );
					break;
				default:
					add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 306, $location);' ) );
					break;
			}

			return false;

		}

		return true;
	}

    public function is_post_exists( $ext_ID ) {

        $result = $this->query(
            'wp.getPost',
            '1',
            $this->username,
            $this->password,
            $ext_ID
        );

        if( !$result ) {
            return false;
        }

        $post = $this->getResponse();

        if( $ext_ID != $post['post_id'] ) {
            return false;
        }

        return true;

    }

	public function get_response() {
		return parent::getResponse();
	}

	public function get_error_code() {
		return parent::getErrorCode();
	}

	public function get_error_message() {
		return parent::getErrorMessage();
	}

	public static function display_settings( $site ) {

		$site_url = get_post_meta( $site->ID, 'syn_site_url', true);
		$site_username = get_post_meta( $site->ID, 'syn_site_username', true);
		$site_password = push_syndicate_decrypt( get_post_meta( $site->ID, 'syn_site_password', true) );

		// @TODO refresh UI

?>
		<p>
			<label for=site_url>Enter a valid site URL</label>
		</p>
		<p>
			<input type="text" name="site_url" id="site_url" size="100" value="<?php echo esc_html( $site_url ); ?>" />
		</p>
		<p>
			<label for="site_username">Enter Username</label>
		</p>
		<p>
			<input type="text" name="site_username" id="site_username" size="100" value="<?php echo esc_html( $site_username ); ?>" />
		</p>
		<p>
			<label>Enter Password</label>
		</p>
		<p>
			<input type="password" name="site_password" id="site_password" size="100"  autocomplete="off" value="<?php echo esc_html( $site_password ); ?>" />
		</p>
<?php

	}

	public static function save_settings( $site_ID ) {

		str_replace( '/xmlrpc.php', '', $_POST['site_url'] );

		update_post_meta( $site_ID, 'syn_site_url', esc_url_raw( $_POST['site_url'] ) );
		update_post_meta( $site_ID, 'syn_site_username', sanitize_text_field( $_POST['site_username'] ) );
		update_post_meta( $site_ID, 'syn_site_password', push_syndicate_encrypt( sanitize_text_field( $_POST['site_password'] ) ) );

		if( !filter_var( $_POST['site_url'], FILTER_VALIDATE_URL ) ) {
			add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 301, $location);' ) );
			return false;
		}

		return true;

	}

}