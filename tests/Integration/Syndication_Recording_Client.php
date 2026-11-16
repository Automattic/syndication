<?php
/**
 * Recording syndication client for testing the push and delete lifecycle.
 *
 * Named to match the factory's expected format: Syndication_{transport_type}_Client
 * Use transport_type 'Recording' to load this client.
 *
 * @package Automattic\Syndication\Tests
 */

/**
 * Client that records every push-side call and returns configurable results.
 */
class Syndication_Recording_Client implements Syndication_Client {

	/**
	 * Calls made, as array( method, site ID, arguments ).
	 *
	 * @var array
	 */
	public static $calls = array();

	/**
	 * Return values by method name. Unset methods use the defaults below.
	 *
	 * @var array
	 */
	public static $results = array();

	/**
	 * Site the client was built for.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * Forget recorded calls and configured results.
	 */
	public static function reset() {
		self::$calls   = array();
		self::$results = array();
	}

	/**
	 * Recorded calls to one method.
	 *
	 * @param string $method Method name.
	 * @return array
	 */
	public static function calls_to( $method ) {
		return array_values(
			array_filter(
				self::$calls,
				function ( $call ) use ( $method ) {
					return $method === $call[0];
				}
			)
		);
	}

	/**
	 * Constructor.
	 *
	 * @param int $site_id Site ID.
	 */
	public function __construct( $site_id ) {
		$this->site_id = $site_id;
	}

	/**
	 * Record a call and return the configured result.
	 *
	 * @param string $method  Method name.
	 * @param array  $args    Arguments.
	 * @param mixed  $default Result when none is configured.
	 * @return mixed
	 */
	private function record( $method, $args, $default ) {
		self::$calls[] = array( $method, $this->site_id, $args );
		return array_key_exists( $method, self::$results ) ? self::$results[ $method ] : $default;
	}

	/**
	 * Create a remote post. Defaults to remote ID 1000 + local post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return mixed
	 */
	public function new_post( $post_id ) {
		return $this->record( __FUNCTION__, array( $post_id ), 1000 + $post_id );
	}

	/**
	 * Edit a remote post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $ext_id  External ID.
	 * @return mixed
	 */
	public function edit_post( $post_id, $ext_id ) {
		return $this->record( __FUNCTION__, array( $post_id, $ext_id ), $post_id );
	}

	/**
	 * Delete a remote post.
	 *
	 * @param int $ext_id External ID.
	 * @return mixed
	 */
	public function delete_post( $ext_id ) {
		return $this->record( __FUNCTION__, array( $ext_id ), true );
	}

	/**
	 * Get a remote post.
	 *
	 * @param int $ext_id External ID.
	 * @return bool
	 */
	public function get_post( $ext_id ) {
		return false;
	}

	/**
	 * Get remote posts.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function get_posts( $args = array() ) {
		return array();
	}

	/**
	 * Test the connection.
	 *
	 * @return bool
	 */
	public function test_connection() {
		return true;
	}

	/**
	 * Check whether a remote post exists.
	 *
	 * @param int $ext_id External ID.
	 * @return mixed
	 */
	public function is_post_exists( $ext_id ) {
		return $this->record( __FUNCTION__, array( $ext_id ), true );
	}

	/**
	 * Client data.
	 *
	 * @return array
	 */
	public static function get_client_data() {
		return array(
			'id'    => 'Recording',
			'modes' => array( 'push' ),
			'name'  => 'Recording Client',
		);
	}

	/**
	 * Display settings.
	 *
	 * @param object $site Site object.
	 */
	public static function display_settings( $site ) {}

	/**
	 * Save settings.
	 *
	 * @param int $site_id Site ID.
	 */
	public static function save_settings( $site_id ) {}
}
