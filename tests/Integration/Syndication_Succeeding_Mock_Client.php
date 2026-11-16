<?php
/**
 * Mock client whose pushes always succeed.
 *
 * Use transport_type 'Succeeding_Mock' to load this client.
 *
 * @package Automattic\Syndication\Tests
 */

/**
 * Creates every post as remote post 501, accepts every update, and reports
 * every remote post as existing so that a previously pushed post takes the
 * edit path.
 */
class Syndication_Succeeding_Mock_Client extends Syndication_Mock_Client {

	/**
	 * Create the remote post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Remote post ID.
	 */
	public function new_post( $post_id ) {
		return 501;
	}

	/**
	 * Update the remote post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $ext_id  External ID.
	 * @return bool
	 */
	public function edit_post( $post_id, $ext_id ) {
		return true;
	}

	/**
	 * Every remote post exists.
	 *
	 * @param int $ext_id External ID.
	 * @return bool
	 */
	public function is_post_exists( $ext_id ) {
		return true;
	}
}
