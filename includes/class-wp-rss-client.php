<?php

include_once( dirname(__FILE__) . '/interface-wp-client.php' );

class WP_RSS_Client implements WP_Client{

    private $response;
    private $error_message;
    private $error_code;

    function __construct() {

    }

    public function new_post($post_ID)
    {
        // Not supported
        return false;
    }

    public function edit_post($post_ID, $ext_ID)
    {
        // Not supported
        return false;
    }

    public function delete_post($ext_ID)
    {
        // Not supported
        return false;
    }

    public function set_options($options, $ext_ID)
    {
        // Not supported
        return false;
    }

    public function test_connection()
    {
        // TODO: Implement test_connection() method.
    }

    public function is_post_exists($ext_ID)
    {
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

    public static function display_settings($site)
    {
        // TODO: Implement display_settings() method.
    }

    public static function save_settings($site_ID)
    {
        // TODO: Implement save_settings() method.
    }

    public function get_post()
    {
        // TODO: Implement get_post() method.
    }

    public function get_posts()
    {
        // TODO: Implement get_posts() method.
    }

}