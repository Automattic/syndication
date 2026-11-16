<?php
/**
 * Tests that pulled feed data cannot choose which local post a pull writes to.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullPostTargetTest
 *
 * @covers WP_Push_Syndication_Server::pull_content
 */
class PullPostTargetTest extends WPIntegrationTestCase {

	/**
	 * Pull one feed post through the mock client and return the local post it created.
	 *
	 * @param array $feed_post Post array as a client would return it.
	 * @return \WP_Post The post created for the feed item.
	 */
	private function pull( array $feed_post ): \WP_Post {
		global $push_syndication_server;

		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', 'Mock' );

		\Syndication_Mock_Client::set_posts(
			array(
				array_merge(
					array(
						'post_title'  => 'From the feed',
						'post_type'   => 'post',
						'post_status' => 'publish',
						'post_guid'   => 'feed-guid-' . $site_id,
					),
					$feed_post
				),
			)
		);

		$push_syndication_server->pull_content( array( get_post( $site_id ) ) );

		$created = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => 'any',
				'meta_key'    => 'syn_post_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'feed-guid-' . $site_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$this->assertCount( 1, $created, 'The feed item should be created as a new post.' );

		return $created[0];
	}

	/**
	 * A feed value mapped to ID cannot overwrite an existing local post.
	 */
	public function test_feed_id_does_not_overwrite_existing_post(): void {
		$existing_id = self::factory()->post->create( array( 'post_title' => 'Existing post' ) );

		$created = $this->pull( array( 'ID' => $existing_id ) );

		$this->assertSame( 'Existing post', get_post( $existing_id )->post_title );
		$this->assertNotSame( $existing_id, $created->ID );
	}

	/**
	 * A syn_pull_new_post callback that injects ID cannot overwrite an existing local post either.
	 */
	public function test_filtered_id_does_not_overwrite_existing_post(): void {
		$existing_id = self::factory()->post->create( array( 'post_title' => 'Existing post' ) );

		add_filter(
			'syn_pull_new_post',
			function ( $post ) use ( $existing_id ) {
				$post['ID'] = $existing_id;
				return $post;
			}
		);

		$this->pull( array() );

		$this->assertSame( 'Existing post', get_post( $existing_id )->post_title );
	}

	/**
	 * A feed value mapped to import_id cannot choose the new post's ID.
	 */
	public function test_feed_import_id_does_not_choose_post_id(): void {
		$free_id = self::factory()->post->create() + 1000;

		$created = $this->pull( array( 'import_id' => $free_id ) );

		$this->assertNotSame( $free_id, $created->ID );
	}
}
