<?php
/**
 * Tests that a pull runs as an import.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullAsImportTest
 *
 * pull_content() defines WP_IMPORTING, which core and other plugins read to
 * skip work for imported posts: core skips pingbacks and enclosures, and
 * plugins such as Jetpack Publicize skip sharing. A constant can't be
 * undefined, so this runs in its own process to see the pull define it.
 *
 * @covers WP_Push_Syndication_Server::pull_content
 */
class PullAsImportTest extends WPIntegrationTestCase {

	/**
	 * A pull defines WP_IMPORTING, so a pulled post is treated as imported.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_pull_runs_as_an_import(): void {
		global $push_syndication_server;

		$this->assertFalse( defined( 'WP_IMPORTING' ), 'Nothing should define WP_IMPORTING before the pull.' );

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
				array(
					'post_title'   => 'Pulled',
					'post_content' => 'Content',
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_guid'    => 'import-guid',
				),
			)
		);

		$push_syndication_server->pull_content( array( $site_id ) );

		$pulled = get_posts(
			array(
				'meta_key'   => 'syn_post_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'import-guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$this->assertTrue( defined( 'WP_IMPORTING' ) && WP_IMPORTING );
		$this->assertCount( 1, $pulled );
		$this->assertSame( '', get_post_meta( $pulled[0]->ID, '_encloseme', true ), 'Core should skip enclosure checks for an imported post.' );
	}
}
