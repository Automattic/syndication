<?php
/**
 * Mock client whose pushes always fail.
 *
 * Use transport_type 'Failing_Mock' to load this client.
 *
 * @package Automattic\Syndication\Tests
 */

/**
 * Returns a WP_Error from every push, and reports every remote post as existing
 * so that a previously pushed post takes the edit path.
 */
class Syndication_Failing_Mock_Client extends Syndication_Mock_Client {

	/**
	 * Fail to create the remote post.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Error
	 */
	public function new_post( $post_id ) {
		return new WP_Error( 'syn_test_failure', 'Remote site rejected the post' );
	}

	/**
	 * Fail to update the remote post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $ext_id  External ID.
	 * @return WP_Error
	 */
	public function edit_post( $post_id, $ext_id ) {
		return new WP_Error( 'syn_test_failure', 'Remote site rejected the update' );
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
