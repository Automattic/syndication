<?php
/**
 * Tests for WP_Push_Syndication_Server::get_sites_by_sitegroup().
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class GetSitesBySitegroupTest
 *
 * @covers WP_Push_Syndication_Server::get_sites_by_sitegroup
 */
class GetSitesBySitegroupTest extends WPIntegrationTestCase {

	/**
	 * Every site in a sitegroup is returned, not just the first page of 100.
	 */
	public function test_returns_every_site_in_a_sitegroup_of_more_than_100(): void {
		global $push_syndication_server;

		self::factory()->term->create(
			array(
				'taxonomy' => 'syn_sitegroup',
				'slug'     => 'big-group',
				'name'     => 'Big group',
			)
		);

		$site_ids = self::factory()->post->create_many(
			205,
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);

		foreach ( $site_ids as $site_id ) {
			wp_set_object_terms( $site_id, 'big-group', 'syn_sitegroup' );
		}

		$sites = $push_syndication_server->get_sites_by_sitegroup( 'big-group' );

		$this->assertCount( 205, $sites );

		$returned_ids = wp_list_pluck( $sites, 'ID' );
		sort( $returned_ids );
		sort( $site_ids );

		$this->assertSame( $site_ids, $returned_ids );
	}
}
