<?php
/**
 * Tests for removing a post from the sites of an unticked sitegroup.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class SitegroupRemovalTest
 *
 * @covers WP_Push_Syndication_Server::get_sites_by_post_ID
 */
class SitegroupRemovalTest extends WPIntegrationTestCase {

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
}
