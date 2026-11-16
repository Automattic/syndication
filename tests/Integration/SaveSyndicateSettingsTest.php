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

	/**
	 * A user who can syndicate but can't edit the post doesn't change its sitegroups.
	 */
	public function test_skips_post_the_user_cannot_edit(): void {
		add_filter( 'syn_syndicate_cap', fn () => 'edit_posts' );

		$editor_post = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$this->post_syndicate_metabox();

		do_action( 'transition_post_status', 'publish', 'publish', get_post( $editor_post ) );

		$this->assertSame( '', get_post_meta( $editor_post, '_syn_selected_sitegroups', true ) );
	}

	/**
	 * A malformed sitegroup selection is treated as none rather than causing a fatal error.
	 */
	public function test_non_array_selection_is_treated_as_none(): void {
		$post_id = self::factory()->post->create();

		$this->post_syndicate_metabox();
		$_POST['selected_sitegroups'] = 'test-sitegroup';

		do_action( 'transition_post_status', 'publish', 'publish', get_post( $post_id ) );

		$this->assertSame( '', get_post_meta( $post_id, '_syn_selected_sitegroups', true ) );
	}
}
