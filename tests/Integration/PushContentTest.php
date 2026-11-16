<?php
/**
 * Tests for WP_Push_Syndication_Server::push_content() and its siblings.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use RuntimeException;
use Syndication_Recording_Client;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

require_once __DIR__ . '/Syndication_Recording_Client.php';

/**
 * Class PushContentTest
 *
 * @covers WP_Push_Syndication_Server::push_content
 */
class PushContentTest extends WPIntegrationTestCase {

	/**
	 * Reset the recording client.
	 */
	public function set_up(): void {
		parent::set_up();
		Syndication_Recording_Client::reset();
		delete_transient( 'syn_syndicate_lock' );
	}

	/**
	 * Create an enabled site, optionally in a sitegroup.
	 *
	 * @param string $transport_type Transport type.
	 * @param string $sitegroup      Sitegroup slug, or '' for none.
	 * @return int Site ID.
	 */
	private function create_site( string $transport_type = 'Recording', string $sitegroup = '' ): int {
		$site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $site_id, 'syn_transport_type', $transport_type );
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );

		if ( '' !== $sitegroup ) {
			wp_set_object_terms( $site_id, $sitegroup, 'syn_sitegroup' );
		}

		return $site_id;
	}

	/**
	 * Sites data for pushing a post to some sites.
	 *
	 * @param int   $post_id  Post ID.
	 * @param int[] $site_ids Selected site IDs.
	 * @return array
	 */
	private function sites_for( int $post_id, array $site_ids ): array {
		return array(
			'post_ID'        => $post_id,
			'selected_sites' => array_map( 'get_post', $site_ids ),
			'removed_sites'  => array(),
		);
	}

	/**
	 * A push that finds the lock held is rescheduled, not dropped.
	 */
	public function test_a_locked_push_is_rescheduled(): void {
		global $push_syndication_server;

		$sites = $this->sites_for( self::factory()->post->create(), array( $this->create_site() ) );
		set_transient( 'syn_syndicate_lock', 'locked', 60 * 5 );

		$push_syndication_server->push_content( $sites );

		$this->assertSame( array(), Syndication_Recording_Client::$calls );
		$this->assertNotFalse( wp_next_scheduled( 'syn_push_content', array( $sites ) ) );
	}

	/**
	 * The lock is released even when something in the push throws.
	 */
	public function test_the_lock_is_released_when_a_push_throws(): void {
		global $push_syndication_server;

		$sites = $this->sites_for( self::factory()->post->create(), array( $this->create_site() ) );

		add_filter(
			'syn_pre_push_post_sites',
			function () {
				throw new RuntimeException( 'Boom' );
			}
		);

		try {
			$push_syndication_server->push_content( $sites );
			$this->fail( 'The exception should escape.' );
		} catch ( RuntimeException $e ) {
			$this->assertFalse( get_transient( 'syn_syndicate_lock' ) );
		}
	}

	/**
	 * A site whose client can't be built is skipped, and the rest still get the push.
	 */
	public function test_a_site_with_an_unknown_transport_is_skipped(): void {
		global $push_syndication_server;

		$post_id = self::factory()->post->create();
		$sites   = $this->sites_for( $post_id, array( $this->create_site( 'No_Such_Transport' ), $this->create_site() ) );

		$push_syndication_server->push_content( $sites );

		$this->assertCount( 1, Syndication_Recording_Client::calls_to( 'new_post' ) );
		$this->assertFalse( get_transient( 'syn_syndicate_lock' ) );
	}

	/**
	 * A site whose client can't be built is skipped when deleting remote copies.
	 *
	 * @covers WP_Push_Syndication_Server::delete_content
	 */
	public function test_delete_content_skips_a_site_with_an_unknown_transport(): void {
		global $push_syndication_server;

		$broken_id = $this->create_site( 'No_Such_Transport' );
		$site_id   = $this->create_site();
		$post_id   = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'_syn_slave_post_states',
			array(
				'success' => array(
					$broken_id => array( 'ext_ID' => 11 ),
					$site_id   => array( 'ext_ID' => 22 ),
				),
			)
		);

		$push_syndication_server->delete_content( $post_id );

		$this->assertCount( 1, Syndication_Recording_Client::calls_to( 'delete_post' ) );
	}
}
