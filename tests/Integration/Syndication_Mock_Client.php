<?php
/**
 * Mock syndication client for testing.
 *
 * Named to match the factory's expected format: Syndication_{transport_type}_Client
 * Use transport_type 'Mock' to load this client.
 *
 * @package Automattic\Syndication\Tests
 */

/**
 * Mock client for testing pull_content with various return values.
 */
class Syndication_Mock_Client implements Syndication_Client {

	/**
	 * Posts to return from get_posts().
	 *
	 * @var mixed
	 */
	private static $posts_to_return = array();

	/**
	 * Set the posts that get_posts() will return.
	 *
	 * @param mixed $posts Posts to return (can be array, false, null, etc.).
	 */
	public static function set_posts( $posts ) {
		self::$posts_to_return = $posts;
	}

	/**
	 * Constructor.
	 *
	 * @param int $site_id Site ID.
	 */
	public function __construct( $site_id ) {}

	/**
	 * Get posts from the remote site.
	 *
	 * @param array $args Arguments.
	 * @return mixed
	 */
	public function get_posts( $args = array() ) {
		return self::$posts_to_return;
	}

	/**
	 * Create a new post on the remote site.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function new_post( $post_id ) {
		return false;
	}

	/**
	 * Edit a post on the remote site.
	 *
	 * @param int $post_id Post ID.
	 * @param int $ext_id  External ID.
	 * @return bool
	 */
	public function edit_post( $post_id, $ext_id ) {
		return false;
	}

	/**
	 * Delete a post on the remote site.
	 *
	 * @param int $ext_id External ID.
	 * @return bool
	 */
	public function delete_post( $ext_id ) {
		return false;
	}

	/**
	 * Get a single post from the remote site.
	 *
	 * @param int $ext_id External ID.
	 * @return bool
	 */
	public function get_post( $ext_id ) {
		return false;
	}

	/**
	 * Test the connection to the remote site.
	 *
	 * @return bool
	 */
	public function test_connection() {
		return true;
	}

	/**
	 * Check if a post exists on the remote site.
	 *
	 * @param int $ext_id External ID.
	 * @return bool
	 */
	public function is_post_exists( $ext_id ) {
		return false;
	}

	/**
	 * Get client data.
	 *
	 * @return array
	 */
	public static function get_client_data() {
		return array(
			'id'    => 'Mock',
			'modes' => array( 'pull' ),
			'name'  => 'Mock Client',
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
