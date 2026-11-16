<?php
/**
 * Tests that a feed can only update the posts it pulled itself.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullGuidScopeTest
 *
 * @covers WP_Push_Syndication_Server::find_post_by_guid
 * @covers WP_Push_Syndication_Server::pull_content
 */
class PullGuidScopeTest extends WPIntegrationTestCase {

	/**
	 * Turn on updates to pulled posts.
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['push_syndication_server']->push_syndicate_settings['update_pulled_posts'] = 'on';
	}

	/**
	 * Restore the update setting.
	 */
	public function tear_down(): void {
		$GLOBALS['push_syndication_server']->push_syndicate_settings['update_pulled_posts'] = 'off';

		parent::tear_down();
	}

	/**
	 * Create an enabled site that pulls through the mock client.
	 *
	 * @return int Site post ID.
	 */
	private function create_site(): int {
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );

		return $site_id;
	}

	/**
	 * Pull one item from a site.
	 *
	 * @param int    $site_id Site post ID.
	 * @param string $title   Item title.
	 */
	private function pull( int $site_id, string $title ): void {
		\Syndication_Mock_Client::set_posts(
			array(
				array(
					'post_title'  => $title,
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_guid'   => 'https://example.com/shared-item',
				),
			)
		);

		$GLOBALS['push_syndication_server']->pull_content( array( $site_id ) );
	}

	/**
	 * IDs of the local posts pulled for the shared item.
	 *
	 * @return int[]
	 */
	private function pulled_post_ids(): array {
		return get_posts(
			array(
				'post_status' => 'any',
				'fields'      => 'ids',
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'meta_key'    => 'syn_post_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'https://example.com/shared-item', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * A second feed reusing another feed's GUID gets its own post instead of overwriting the first.
	 */
	public function test_feed_cannot_update_another_feeds_post(): void {
		$trusted_site = $this->create_site();
		$other_site   = $this->create_site();

		$this->pull( $trusted_site, 'Trusted content' );
		$this->pull( $other_site, 'Replaced content' );

		$post_ids = $this->pulled_post_ids();

		$this->assertCount( 2, $post_ids );
		$this->assertSame( 'Trusted content', get_the_title( $post_ids[0] ) );
		$this->assertSame( (string) $other_site, get_post_meta( $post_ids[1], 'syn_source_site_id', true ) );
	}

	/**
	 * A feed still updates the posts it pulled itself.
	 */
	public function test_feed_updates_its_own_post(): void {
		$site_id = $this->create_site();

		$this->pull( $site_id, 'First version' );
		$this->pull( $site_id, 'Second version' );

		$post_ids = $this->pulled_post_ids();

		$this->assertCount( 1, $post_ids );
		$this->assertSame( 'Second version', get_the_title( $post_ids[0] ) );
	}
}
