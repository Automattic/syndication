<?php
/**
 * Tests for removing a post from the sites of an unticked sitegroup.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Syndication_Recording_Client;
use WP_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

require_once __DIR__ . '/Syndication_Recording_Client.php';

/**
 * Class SitegroupRemovalTest
 *
 * @covers WP_Push_Syndication_Server::get_sites_by_post_ID
 * @covers WP_Push_Syndication_Server::push_content
 */
class SitegroupRemovalTest extends WPIntegrationTestCase {

	/**
	 * Reset the recording client.
	 */
	public function set_up(): void {
		parent::set_up();
		Syndication_Recording_Client::reset();
		delete_transient( 'syn_syndicate_lock' );
	}

	/**
	 * Clear any results a test configured on the recording client.
	 */
	public function tear_down(): void {
		Syndication_Recording_Client::reset();

		parent::tear_down();
	}

	/**
	 * Create a sitegroup.
	 *
	 * @param string $slug   Slug.
	 * @param int    $parent Parent term ID.
	 * @return int Term ID.
	 */
	private function create_sitegroup( string $slug, int $parent = 0 ): int {
		return self::factory()->term->create(
			array(
				'taxonomy' => 'syn_sitegroup',
				'slug'     => $slug,
				'name'     => $slug,
				'parent'   => $parent,
			)
		);
	}

	/**
	 * Create an enabled site in some sitegroups.
	 *
	 * @param string[] $sitegroups Sitegroup slugs.
	 * @return int Site ID.
	 */
	private function create_site( array $sitegroups ): int {
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $site_id, 'syn_transport_type', 'Recording' );
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		wp_set_object_terms( $site_id, $sitegroups, 'syn_sitegroup' );

		return $site_id;
	}

	/**
	 * Create a post that was pushed to some sitegroups and now has others ticked.
	 *
	 * @param string[] $old_sitegroups      Sitegroups ticked at the last push.
	 * @param string[] $selected_sitegroups Sitegroups ticked now.
	 * @return int Post ID.
	 */
	private function create_post( array $old_sitegroups, array $selected_sitegroups ): int {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_syn_old_sitegroups', $old_sitegroups );
		update_post_meta( $post_id, '_syn_selected_sitegroups', $selected_sitegroups );

		return $post_id;
	}

	/**
	 * A site in both an unticked and a ticked sitegroup keeps its copy.
	 */
	public function test_a_site_still_in_a_ticked_sitegroup_is_not_removed(): void {
		global $push_syndication_server;

		$this->create_sitegroup( 'group-a' );
		$this->create_sitegroup( 'group-b' );
		$shared_id = $this->create_site( array( 'group-a', 'group-b' ) );
		$only_b_id = $this->create_site( array( 'group-b' ) );

		$sites = $push_syndication_server->get_sites_by_post_ID( $this->create_post( array( 'group-a', 'group-b' ), array( 'group-a' ) ) );

		$this->assertSame( array( $shared_id ), wp_list_pluck( $sites['selected_sites'], 'ID' ) );
		$this->assertSame( array( $only_b_id ), wp_list_pluck( $sites['removed_sites'], 'ID' ) );
	}

	/**
	 * A site in an unticked child sitegroup is still targeted through its ticked parent.
	 */
	public function test_a_site_still_targeted_through_a_parent_sitegroup_is_not_removed(): void {
		global $push_syndication_server;

		$parent_id = $this->create_sitegroup( 'parent' );
		$this->create_sitegroup( 'child', $parent_id );
		$site_id = $this->create_site( array( 'child' ) );

		$sites = $push_syndication_server->get_sites_by_post_ID( $this->create_post( array( 'parent', 'child' ), array( 'parent' ) ) );

		$this->assertSame( array( $site_id ), wp_list_pluck( $sites['selected_sites'], 'ID' ) );
		$this->assertSame( array(), $sites['removed_sites'] );
	}

	/**
	 * Removing a copy fires the same hooks as deleting one on trash, so it is logged.
	 */
	public function test_a_removal_fires_the_delete_hooks(): void {
		global $push_syndication_server;

		$site_id = $this->create_site( array() );
		$post_id = $this->create_post( array(), array() );
		update_post_meta( $post_id, '_syn_slave_post_states', array( 'success' => array( $site_id => array( 'ext_ID' => 77 ) ) ) );

		$fired = array();
		add_action(
			'syn_post_push_delete_post',
			function ( ...$args ) use ( &$fired ) {
				$fired[] = array_slice( $args, 0, 4 );
			},
			10,
			6
		);

		$push_syndication_server->push_content(
			array(
				'post_ID'        => $post_id,
				'selected_sites' => array(),
				'removed_sites'  => array( get_post( $site_id ) ),
			)
		);

		$this->assertSame( array( array( true, 77, $post_id, $site_id ) ), $fired );
	}

	/**
	 * A removal skipped through the short-circuit filter leaves the copy, and its record, in place.
	 */
	public function test_a_skipped_removal_keeps_the_copy_and_its_record(): void {
		global $push_syndication_server;

		$skipped_id = $this->create_site( array() );
		$failing_id = $this->create_site( array() );
		$post_id    = $this->create_post( array(), array() );
		update_post_meta(
			$post_id,
			'_syn_slave_post_states',
			array(
				'success' => array(
					$skipped_id => array( 'ext_ID' => 11 ),
					$failing_id => array( 'ext_ID' => 22 ),
				),
			)
		);

		add_filter(
			'syn_pre_push_delete_post_shortcircuit',
			function ( $shortcircuit, $ext_id, $local_id, $site_id ) use ( $skipped_id ) {
				return $site_id === $skipped_id ? true : $shortcircuit;
			},
			10,
			4
		);

		// The second removal fails, which saves the post's states.
		Syndication_Recording_Client::$results['delete_post'] = new WP_Error( 'delete-failed', 'Nope' );

		$push_syndication_server->push_content(
			array(
				'post_ID'        => $post_id,
				'selected_sites' => array(),
				'removed_sites'  => array( get_post( $skipped_id ), get_post( $failing_id ) ),
			)
		);

		$this->assertSame(
			array( array( 'delete_post', $failing_id, array( 22 ) ) ),
			Syndication_Recording_Client::calls_to( 'delete_post' )
		);

		$states = get_post_meta( $post_id, '_syn_slave_post_states', true );
		$this->assertSame( array( 'ext_ID' => 11 ), $states['success'][ $skipped_id ] );
	}

	/**
	 * An edit skipped through the short-circuit filter keeps the record of the remote copy.
	 *
	 * @covers WP_Push_Syndication_Server::push_content
	 */
	public function test_a_skipped_edit_keeps_the_record_of_the_copy(): void {
		global $push_syndication_server;

		$skipped_id = $this->create_site( array() );
		$edited_id  = $this->create_site( array() );
		$post_id    = $this->create_post( array(), array() );
		update_post_meta(
			$post_id,
			'_syn_slave_post_states',
			array(
				'success' => array(
					$skipped_id => array( 'ext_ID' => 11 ),
					$edited_id  => array( 'ext_ID' => 22 ),
				),
			)
		);

		add_filter(
			'syn_pre_push_edit_post_shortcircuit',
			function ( $shortcircuit, $local_id, $site ) use ( $skipped_id ) {
				return $site->ID === $skipped_id ? true : $shortcircuit;
			},
			10,
			3
		);

		// The second site's edit saves the post's states.
		$push_syndication_server->push_content(
			array(
				'post_ID'        => $post_id,
				'selected_sites' => array( get_post( $skipped_id ), get_post( $edited_id ) ),
				'removed_sites'  => array(),
			)
		);

		$this->assertSame(
			array( array( 'edit_post', $edited_id, array( $post_id, 22 ) ) ),
			Syndication_Recording_Client::calls_to( 'edit_post' )
		);

		$states = get_post_meta( $post_id, '_syn_slave_post_states', true );
		$this->assertSame( array( 'ext_ID' => 11 ), $states['success'][ $skipped_id ] );
	}
}
