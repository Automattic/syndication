<?php

include_once( ABSPATH . 'wp-includes/class-simplepie.php' );
include_once( dirname(__FILE__) . '/interface-wp-client.php' );

class WP_RSS_Client extends SimplePie implements WP_Client{

    private $response;
    private $error_message;
    private $error_code;

    function __construct( $site_ID ) {
        parent::SimplePie();
        $this->set_feed_url( get_post_meta( $site_ID, 'syn_feed_url', true ) );
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

    public function set_options($options, $ext_ID) {
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

        $feed_url               = get_post_meta( $site->ID, 'syn_feed_url', true );
        $selected_post_type     = get_post_meta( $site->ID, 'syn_selected_post_type', true );
        $selected_post_status   = get_post_meta( $site->ID, 'syn_selected_post_status', true );

        ?>

        <p xmlns="http://www.w3.org/1999/html">
            <label for="feed_url"><?php echo esc_html__( 'Enter feed URL', 'push-syndication' ); ?></label>
        </p>
        <p>
            <input type="text" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
        </p>
        <p>
            <label for="post_type"><?php echo esc_html__( 'Select post type', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="post_type" id="post_type" />

            <?php

            $post_types = get_post_types();

            foreach( $post_types as $post_type ) {
                echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_type, $selected_post_type ) . '>' . esc_html( $post_type )  . '</option>';
            }

            ?>

            </select>
        </p>
        <p>
            <label for="post_status"><?php echo esc_html__( 'Select post status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="post_status" id="post_status" />

            <?php

            $post_statuses  = get_post_statuses();

            foreach( $post_statuses as $post_status ) {
                echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_status, $selected_post_status ) . '>' . esc_html( $post_status )  . '</option>';
            }

            ?>

            </select>
        </p>

        <?php

    }

    public static function save_settings( $site_ID ) {

        update_post_meta( $site_ID, 'syn_feed_url', esc_url_raw( $_POST['feed_url'] ) );
        update_post_meta( $site_ID, 'syn_selected_post_type', $_POST['post_type'] );
        update_post_meta( $site_ID, 'syn_selected_post_status', $_POST['post_status'] );
        return true;

    }

    public function get_post( $ext_ID ) {
        // TODO: Implement get_post() method.
    }

    public function get_posts( $args ) {

        $this->init();
        $this->handle_content_type();

        // hold all the posts
        $posts = array();

        foreach( $this->get_items() as $item ) {
            $posts[] = array(
                'post_title'    => $item->get_title(),
                'post_content'  => $item->get_description(),
                'post_date'     => $item->get_date()
            );
        }

    }

}