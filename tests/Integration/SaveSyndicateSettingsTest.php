<?php
/**
 * Tests that save_syndicate_settings() writes to the post it is handed.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class SaveSyndicateSettingsTest
 *
 * Regression guard: the callback used to ignore the post that transition_post_status
 * hands it and read global $post instead, which is only the post being saved because
 * wp-admin/post.php happens to set it before calling edit_post().
 *
 * @covers WP_Push_Syndication_Server::save_syndicate_settings
 */
class SaveSyndicateSettingsTest extends WPIntegrationTestCase {

	/**
	 * Set up an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Populate the POST data the syndicate metabox submits.
	 *
	 * Called after the fixtures exist: with this data in place, creating a post fires
	 * the very hook under test.
	 *
	 * @return void
	 */
	private function post_syndicate_metabox(): void {
		global $push_syndication_server;

		$server_file = ( new \ReflectionClass( $push_syndication_server ) )->getFileName();

		$_POST = array(
			'syndicate_noncename' => wp_create_nonce( plugin_basename( $server_file ) ),
			'selected_sitegroups' => array( 'test-sitegroup' ),
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * The transitioning post is the one written to, whatever global $post holds.
	 *
	 * Fired through do_action so that the hook's own accepted-args count is exercised:
	 * registered with fewer than three, this call fails with too few arguments.
	 */
	public function test_writes_to_the_transitioning_post(): void {
		global $post;

		$other_id     = self::factory()->post->create();
		$post         = get_post( $other_id );
		$transitioned = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->post_syndicate_metabox();

		do_action( 'transition_post_status', 'publish', 'draft', get_post( $transitioned ) );

		$this->assertSame( array( 'test-sitegroup' ), get_post_meta( $transitioned, '_syn_selected_sitegroups', true ) );
		$this->assertNotEmpty( get_post_meta( $transitioned, 'post_uniqueid', true ) );
		$this->assertSame( '', get_post_meta( $other_id, 'post_uniqueid', true ) );
	}
}
