<?php
/**
 * Tests that post status transitions schedule a push from the saved sitegroup selection.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use ReflectionClass;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PushOnTransitionTest
 *
 * @covers WP_Push_Syndication_Server::pre_schedule_push_content
 */
class PushOnTransitionTest extends WPIntegrationTestCase {

	/**
	 * Site in the test sitegroup.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * Create an enabled site in a sitegroup, and act as nobody, as cron and WP-CLI do.
	 */
	public function set_up(): void {
		parent::set_up();

		self::factory()->term->create(
			array(
				'taxonomy' => 'syn_sitegroup',
				'slug'     => 'test-sitegroup',
				'name'     => 'Test sitegroup',
			)
		);

		$this->site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->site_id, 'syn_site_enabled', 'on' );
		wp_set_object_terms( $this->site_id, 'test-sitegroup', 'syn_sitegroup' );

		wp_set_current_user( 0 );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Sites data of every scheduled push, by post ID.
	 *
	 * @return array<int, array[]>
	 */
	private function scheduled_pushes(): array {
		$pushes = array();
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( $hooks['syn_push_content'] ?? array() as $event ) {
				$sites                         = $event['args'][0];
				$pushes[ $sites['post_ID'] ][] = $sites;
			}
		}
		return $pushes;
	}

	/**
	 * Create a post with the test sitegroup selected.
	 *
	 * @param array $args Post arguments.
	 * @return int Post ID.
	 */
	private function create_selected_post( array $args = array() ): int {
		$post_id = self::factory()->post->create( $args );
		update_post_meta( $post_id, '_syn_selected_sitegroups', array( 'test-sitegroup' ) );

		// Ignore any push scheduled while setting up.
		wp_unschedule_hook( 'syn_push_content' );

		return $post_id;
	}

	/**
	 * A scheduled post published by cron is pushed.
	 */
	public function test_cron_publishing_a_scheduled_post_pushes_it(): void {
		global $wpdb;

		$post_id = $this->create_selected_post(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);

		// The publish time arrives, and cron fires the core event.
		$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		do_action( 'publish_future_post', $post_id );

		$this->assertSame( 'publish', get_post_status( $post_id ) );

		$pushes = $this->scheduled_pushes();
		$this->assertCount( 1, $pushes[ $post_id ] ?? array() );
		$this->assertSame( array( $this->site_id ), wp_list_pluck( $pushes[ $post_id ][0]['selected_sites'], 'ID' ) );
	}

	/**
	 * Publishing a post programmatically, as WP-CLI does, pushes it.
	 */
	public function test_wp_publish_post_pushes(): void {
		$post_id = $this->create_selected_post( array( 'post_status' => 'draft' ) );

		wp_publish_post( $post_id );

		$this->assertCount( 1, $this->scheduled_pushes()[ $post_id ] ?? array() );
	}

	/**
	 * A post inserted already published, with its selection, is pushed.
	 */
	public function test_inserting_a_published_post_with_a_selection_pushes_it(): void {
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Inserted',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'meta_input'   => array( '_syn_selected_sitegroups' => array( 'test-sitegroup' ) ),
			)
		);

		$this->assertCount( 1, $this->scheduled_pushes()[ $post_id ] ?? array() );
	}

	/**
	 * A post with no sitegroups selected is not pushed.
	 */
	public function test_a_post_without_a_selection_is_not_pushed(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		wp_publish_post( $post_id );

		$this->assertArrayNotHasKey( $post_id, $this->scheduled_pushes() );
		$this->assertSame( '', get_post_meta( $post_id, '_syn_old_sitegroups', true ), 'Unselected posts gain no meta.' );
	}

	/**
	 * Trashing a post does not push it: the delete settings handle the remote copies.
	 */
	public function test_trashing_a_post_does_not_push_it(): void {
		$post_id = $this->create_selected_post();

		wp_trash_post( $post_id );

		$this->assertArrayNotHasKey( $post_id, $this->scheduled_pushes() );
	}

	/**
	 * Revisions are not pushed, even if they carry a selection.
	 */
	public function test_a_revision_is_not_pushed(): void {
		global $push_syndication_server;

		$post_id     = $this->create_selected_post();
		$revision_id = _wp_put_post_revision( get_post( $post_id ), true );
		update_metadata( 'post', $revision_id, '_syn_selected_sitegroups', array( 'test-sitegroup' ) );

		$push_syndication_server->pre_schedule_push_content( 'inherit', 'new', get_post( $revision_id ) );

		$this->assertArrayNotHasKey( $revision_id, $this->scheduled_pushes() );
	}

	/**
	 * Saving the Syndicate box in the editor pushes once, to the sitegroups just saved.
	 *
	 * The box save and the push both run on the same status transition; the push
	 * must see the new selection and must not be scheduled twice. Saves in the
	 * editor push whatever the post's status, including drafts.
	 */
	public function test_saving_the_box_in_the_editor_pushes_once(): void {
		global $push_syndication_server;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		wp_unschedule_hook( 'syn_push_content' );

		$server_file = ( new ReflectionClass( $push_syndication_server ) )->getFileName();
		$_POST       = array(
			'syndicate_noncename' => wp_create_nonce( plugin_basename( $server_file ) ),
			'selected_sitegroups' => array( 'test-sitegroup' ),
		);

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Edited draft',
			)
		);

		$pushes = $this->scheduled_pushes();
		$this->assertCount( 1, $pushes[ $post_id ] ?? array() );
		$this->assertSame( array( $this->site_id ), wp_list_pluck( $pushes[ $post_id ][0]['selected_sites'], 'ID' ) );
	}
}
