<?php

include_once( ABSPATH . 'wp-includes/class-simplepie.php' );
include_once( dirname(__FILE__) . '/interface-syndication-client.php' );

class Syndication_WP_RSS_Client extends SimplePie implements Syndication_Client {

    private $default_post_type;
    private $default_post_status;
    private $default_comment_status;
    private $default_ping_status;

    private $response;
    private $error_message;
    private $error_code;

    function __construct( $site_ID ) {

        switch( SIMPLEPIE_VERSION ) {
            case '1.2.1':
                parent::SimplePie();
                break;
            case '1.3':
                parent::__construct();
                break;
            default:
                parent::__construct();
                break;
        }

        parent::__construct();

        $this->set_feed_url( get_post_meta( $site_ID, 'syn_feed_url', true ) );

        $this->default_post_type        = get_post_meta( $site_ID, 'syn_default_post_type', true );
        $this->default_post_status      = get_post_meta( $site_ID, 'syn_default_post_status', true );
        $this->default_comment_status   = get_post_meta( $site_ID, 'syn_default_comment_status', true );
        $this->default_ping_status      = get_post_meta( $site_ID, 'syn_default_ping_status', true );

    }

	public static function get_client_data() {
		return array( 'id' => 'WP_RSS', 'modes' => array( 'pull' ), 'name' => 'RSS' );
	}
		
    public function new_post($post_ID) {
        // Not supported
        return false;
    }

    public function edit_post($post_ID, $ext_ID) {
        // Not supported
        return false;
    }

    public function delete_post($ext_ID) {
        // Not supported
        return false;
    }

    public function test_connection() {
        // TODO: Implement test_connection() method.
        return true;
    }

    public function is_post_exists($ext_ID) {
        // Not supported
        return false;
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

    public static function display_settings($site) {

        $feed_url                   = get_post_meta( $site->ID, 'syn_feed_url', true );
        $default_post_type          = get_post_meta( $site->ID, 'syn_default_post_type', true );
        $default_post_status        = get_post_meta( $site->ID, 'syn_default_post_status', true );
        $default_comment_status     = get_post_meta( $site->ID, 'syn_default_comment_status', true );
        $default_ping_status        = get_post_meta( $site->ID, 'syn_default_ping_status', true );

        ?>

        <p>
            <label for="feed_url"><?php echo esc_html__( 'Enter feed URL', 'push-syndication' ); ?></label>
        </p>
        <p>
            <input type="text" class="widefat" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
        </p>
        <p>
            <label for="default_post_type"><?php echo esc_html__( 'Select post type', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_post_type" id="default_post_type" />

            <?php

            $post_types = get_post_types();

            foreach( $post_types as $post_type ) {
                echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_type, $default_post_type ) . '>' . esc_html( $post_type )  . '</option>';
            }

            ?>

            </select>
        </p>
        <p>
            <label for="default_post_status"><?php echo esc_html__( 'Select post status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_post_status" id="default_post_status" />

            <?php

            $post_statuses  = get_post_statuses();

            foreach( $post_statuses as $key => $value ) {
                echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $default_post_status ) . '>' . esc_html( $key )  . '</option>';
            }

            ?>

            </select>
        </p>
        <p>
            <label for="default_comment_status"><?php echo esc_html__( 'Select comment status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_comment_status" id="default_comment_status" />
                <option value="open" <?php selected( 'open', $default_comment_status )  ?> >open</option>
                <option value="closed" <?php selected( 'closed', $default_comment_status )  ?> >closed</option>
            </select>
        </p>
        <p>
            <label for="default_ping_status"><?php echo esc_html__( 'Select ping status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_ping_status" id="default_ping_status" />
            <option value="open" <?php selected( 'open', $default_ping_status )  ?> >open</option>
            <option value="closed" <?php selected( 'closed', $default_ping_status )  ?> >closed</option>
            </select>
        </p>

        <?php

    }

    public static function save_settings( $site_ID ) {

        update_post_meta( $site_ID, 'syn_feed_url', esc_url_raw( $_POST['feed_url'] ) );
        update_post_meta( $site_ID, 'syn_default_post_type', $_POST['default_post_type'] );
        update_post_meta( $site_ID, 'syn_default_post_status', $_POST['default_post_status'] );
        update_post_meta( $site_ID, 'syn_default_comment_status', $_POST['default_comment_status'] );
        update_post_meta( $site_ID, 'syn_default_ping_status', $_POST['default_ping_status'] );
        return true;

    }

    public function get_post( $ext_ID ) {
        // TODO: Implement get_post() method.
    }

    public function get_posts( $args = array() ) {

        $this->init();
        $this->handle_content_type();

        // hold all the posts
        $posts = array();

        foreach( $this->get_items() as $item ) {
            $post = array(
                'post_title'        => $item->get_title(),
                'post_content'      => $item->get_content(),
                'post_excerpt'      => $item->get_description(),
                'post_type'         => $this->default_post_type,
                'post_status'       => $this->default_post_status,
                'post_date'         => date( 'Y-m-d H:i:s', strtotime( $item->get_date() ) ),
                'comment_status'    => $this->default_comment_status,
                'ping_status'       => $this->default_ping_status,
                'post_guid'         => $item->get_id()
            );
			// This filter can be used to exclude or alter posts during a pull import
			$post = apply_filters( 'syn_rss_pull_filter_post', $post, $args );
			if ( false === $post )
				continue;
			$posts[] = $post;
        }

        return $posts;

    }

}