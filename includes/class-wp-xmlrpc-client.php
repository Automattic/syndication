<?php

include_once( ABSPATH . 'wp-includes/class-IXR.php' );
include_once( ABSPATH . 'wp-includes/class-wp-http-ixr-client.php' );
include_once( dirname(__FILE__) . '/interface-wp-client.php' );
include_once( dirname( __FILE__ ) . '/push-syndicate-encryption.php' );

class WP_XMLRPC_Client extends WP_HTTP_IXR_Client implements WP_Client {

    private $username;
    private $password;

    private $ext_thumbnail_ids;
    private $site_ID;

    function __construct( $site_ID ) {

	    // @TODO check port, timeout etc
		$server         = untrailingslashit( get_post_meta( $site_ID, 'syn_site_url', true ) );
		$server         = esc_url_raw( $server . '/xmlrpc.php' );
        $this->username = get_post_meta( $site_ID, 'syn_site_username', true);
        $this->password = push_syndicate_decrypt( get_post_meta( $site_ID, 'syn_site_password', true) );
        $this->site_ID  = $site_ID;

        // get the thumbnail ids
        $this->ext_thumbnail_ids = get_option( 'syn_post_thumbnail_ids' );
        $this->ext_thumbnail_ids = !empty( $this->ext_thumbnail_ids ) ? $this->ext_thumbnail_ids : array() ;

        parent::__construct( $server );

    }

    function __destruct() {
        update_option( 'syn_post_thumbnail_ids', $this->ext_thumbnail_ids );
    }

    public function new_post( $post_ID ) {

        $post = (array)get_post( $post_ID );

        // rearranging arguments
        $args = array();
        $args['post_title']     = $post['post_title'];
        $args['post_content']   = $post['post_content'];
        $args['post_excerpt']   = $post['post_excerpt'];
        $args['post_status']    = $post['post_status'];
        $args['post_type']      = $post['post_type'];
        $args['wp_password']    = $post['post_password'];
        $args['post_date_gmt']  = $this->convert_date_gmt( $post['post_date_gmt'], $post['post_date'] );
        $args['post_thumbnail'] = $this->manage_thumbnails( $post_ID );

	    // @TODO extend this to custom taxonomies
	    $args['terms_names'] = array(
		    'category' => wp_get_object_terms( $post_ID, 'category', array('fields' => 'names') ),
		    'post_tag' => wp_get_object_terms( $post_ID, 'post_tag', array('fields' => 'names') )
	    );

	    // post meta
        $args['custom_fields'] = array(
            array(
                'key'   => '_masterpost_url',
                'value' =>  $post['guid']
            )
        );

        $result = $this->query(
            'wp.newPost',
            '1',
            $this->username,
            $this->password,
            $args
        );

        if( !$result )
            return false;

        return true;

    }

    public function edit_post( $post_ID, $ext_ID ) {

        $post = (array)get_post( $post_ID );

        // rearranging arguments
        $args = array();
        $args['post_title']     = $post['post_title'];
        $args['post_content']   = $post['post_content'];
        $args['post_excerpt']   = $post['post_excerpt'];
        $args['post_status']    = $post['post_status'];
        $args['post_type']      = $post['post_type'];
        $args['wp_password']    = $post['post_password'];
        $args['post_date_gmt']  = $this->convert_date_gmt( $post['post_date_gmt'], $post['post_date'] );
        $args['post_thumbnail'] = $this->manage_thumbnails( $post_ID );

	    // @TODO extend this to custom taxonomies
	    $args['terms_names'] = array(
		    'category' => wp_get_object_terms( $post_ID, 'category', array('fields' => 'names') ),
		    'post_tag' => wp_get_object_terms( $post_ID, 'post_tag', array('fields' => 'names') )
	    );

	    // post meta
	    $args['custom_fields'] = array(
            array(
                'key'   => '_masterpost_url',
                'value' =>  $post['guid']
            )
        );

        $result = $this->query(
            'wp.editPost',
            '1',
            $this->username,
            $this->password,
            $ext_ID,
            $args
        );

        if( !$result )
            return false;

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

        if( !$result )
            return false;

        return true;

    }

    public function manage_thumbnails( $post_ID ) {

        $post_thumbnail_id = get_post_thumbnail_id( $post_ID );
        if( empty( $post_thumbnail_id ) )
            return '';

        if( !empty( $this->ext_thumbnail_ids[ $this->site_ID ] ) ) {
            if( array_key_exists( $post_thumbnail_id, $this->ext_thumbnail_ids[ $this->site_ID ] ) )
                return $this->ext_thumbnail_ids[ $this->site_ID ][ $post_thumbnail_id ];
        }

        if( $this->insert_post_thumbnail( $post_thumbnail_id ) ) {
            $this->ext_thumbnail_ids[ $this->site_ID ][ $post_thumbnail_id ] = $this->get_response();
            return $this->get_response();
        }

        return '';

    }

    public function insert_post_thumbnail( $post_ID ) {

        $post = (array)get_post( $post_ID );

        // rearranging arguments
        $args = array();
        $args['post_title']     = $post['post_title'];
        $args['post_content']   = $post['post_content'];
        $args['guid']           = $post['guid'];

        $result = $this->query(
            'pushSyndicateInsertThumbnail',
            '1',
            $this->username,
            $this->password,
            $args
        );

        if( !$result )
            return false;

        return true;

    }

	public function set_options($options, $ext_ID)
	{

		$result = $this->query(
			'pushSyndicateSetOption',
			'1',
			$this->username,
			$this->password,
			$options
		);

		if( !$result )
            return false;

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

        if( !$result )
            return false;

        $post = $this->getResponse();

        if( $ext_ID != $post['post_id'] )
            return false;

        return true;

    }

    protected function convert_date_gmt( $date_gmt, $date ) {
        if ( $date !== '0000-00-00 00:00:00' && $date_gmt === '0000-00-00 00:00:00' ) {
            return new IXR_Date( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $date, false ), 'Ymd\TH:i:s' ) );
        }
        return $this->convert_date( $date_gmt );
    }

    protected function convert_date( $date ) {
        if ( $date === '0000-00-00 00:00:00' ) {
            return new IXR_Date( '00000000T00:00:00Z' );
        }
        return new IXR_Date( mysql2date( 'Ymd\TH:i:s', $date, false ) );
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

        ?>

		<p>
			<label for=site_url><?php echo esc_html__( 'Enter a valid site URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="site_url" id="site_url" size="100" value="<?php echo esc_html( $site_url ); ?>" />
		</p>
		<p>
			<label for="site_username"><?php echo esc_html__( 'Enter Username', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="site_username" id="site_username" size="100" value="<?php echo esc_attr( $site_username ); ?>" />
		</p>
		<p>
			<label><?php echo esc_html__( 'Enter Password', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="password" name="site_password" id="site_password" size="100"  autocomplete="off" value="<?php echo esc_attr( $site_password ); ?>" />
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

    public function get_post( $ext_ID )
    {
        // TODO: Implement get_post() method.
    }

    public function get_posts( $args = array() )
    {
        // TODO: Implement get_posts() method.
    }

}