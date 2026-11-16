<?php
/**
 * Tests for WP_Push_Syndication_Server::validate_result_new_post().
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use WP_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class ValidateResultNewPostTest
 *
 * @covers WP_Push_Syndication_Server::validate_result_new_post
 */
class ValidateResultNewPostTest extends WPIntegrationTestCase {

	/**
	 * A remote post ID is recorded as a successful push.
	 *
	 * The XML-RPC client returns an int; a numeric string is accepted too.
	 *
	 * @dataProvider data_remote_ids
	 *
	 * @param mixed $result Value returned by the client's new_post().
	 */
	public function test_records_a_remote_post_id_as_success( $result ): void {
		global $push_syndication_server;

		$states = array();
		$push_syndication_server->validate_result_new_post( $result, $states, 10, null );

		$this->assertSame( array( 'success' => array( 10 => array( 'ext_ID' => 42 ) ) ), $states );
	}

	/**
	 * Remote post IDs.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function data_remote_ids(): array {
		return array(
			'int'            => array( 42 ),
			'numeric string' => array( '42' ),
		);
	}

	/**
	 * A client that did not create a remote post records nothing.
	 *
	 * The REST and XML-RPC clients return true when a push filter returned false,
	 * and the pull-only RSS and XML clients return false. Neither is a remote ID:
	 * (int) true is 1, which would aim later edits and deletes at remote post 1.
	 *
	 * @dataProvider data_non_ids
	 *
	 * @param mixed $result Value returned by the client's new_post().
	 */
	public function test_does_not_record_a_result_that_is_not_a_remote_id( $result ): void {
		global $push_syndication_server;

		$states = array();
		$push_syndication_server->validate_result_new_post( $result, $states, 10, null );

		$this->assertSame( array(), $states );
	}

	/**
	 * Results that are not remote post IDs.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function data_non_ids(): array {
		return array(
			'true (push filter returned false)' => array( true ),
			'false (pull-only transport)'       => array( false ),
			'null'                              => array( null ),
			'zero'                              => array( 0 ),
		);
	}

	/**
	 * A failed push is still recorded as a new-error.
	 */
	public function test_records_an_error_as_new_error(): void {
		global $push_syndication_server;

		$error  = new WP_Error( 'push-failed', 'Nope' );
		$states = array();
		$push_syndication_server->validate_result_new_post( $error, $states, 10, null );

		$this->assertSame( array( 'new-error' => array( 10 => $error ) ), $states );
	}

	/**
	 * Pushing to a pull-only site leaves no success state behind.
	 *
	 * The mock transport's new_post() returns false, as the RSS and XML clients do.
	 *
	 * @covers WP_Push_Syndication_Server::push_content
	 */
	public function test_push_to_a_client_returning_false_records_no_success(): void {
		global $push_syndication_server;

		$post_id = self::factory()->post->create();
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );

		$push_syndication_server->push_content(
			array(
				'post_ID'        => $post_id,
				'selected_sites' => array( get_post( $site_id ) ),
				'removed_sites'  => array(),
			)
		);

		$states = get_post_meta( $post_id, '_syn_slave_post_states', true );
		$this->assertArrayNotHasKey( 'success', (array) $states );
	}
}
