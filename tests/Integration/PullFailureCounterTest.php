<?php
/**
 * Tests for the pull failure counter that disables failing sites.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullFailureCounterTest
 *
 * Fires the same events the clients fire, through the event counter, failure
 * monitor and auto-retry handler the plugin registers at load.
 *
 * @covers Syndication_Site_Failure_Monitor
 */
class PullFailureCounterTest extends WPIntegrationTestCase {

	/**
	 * Create an enabled site and allow three failures before disabling it.
	 *
	 * @param string $transport_type The site's transport type.
	 * @return int The site post ID.
	 */
	private function make_site( string $transport_type = 'WP_RSS' ): int {
		update_option( 'push_syndication_max_pull_attempts', 3 );

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', $transport_type );
		update_post_meta( $site_id, 'syn_feed_url', 'http://example.com/feed/' );

		return $site_id;
	}

	/**
	 * Fire a pull event for a site.
	 *
	 * @param string $event   The event: pull_success or pull_failure.
	 * @param int    $site_id The site post ID.
	 */
	private function pull( string $event, int $site_id ): void {
		do_action( 'push_syndication_event', $event, $site_id );
	}

	/**
	 * Occasional failures between successful pulls don't add up to disabling the site.
	 */
	public function test_successful_pull_resets_the_failure_count(): void {
		$site_id = $this->make_site();

		$this->pull( 'pull_failure', $site_id );
		$this->pull( 'pull_failure', $site_id );
		$this->pull( 'pull_success', $site_id );
		$this->pull( 'pull_failure', $site_id );
		$this->pull( 'pull_failure', $site_id );

		$this->assertSame( 'on', get_post_meta( $site_id, 'syn_site_enabled', true ) );
	}

	/**
	 * Consecutive failures still disable the site.
	 */
	public function test_consecutive_failures_still_disable_the_site(): void {
		$site_id = $this->make_site();

		$this->pull( 'pull_failure', $site_id );
		$this->pull( 'pull_failure', $site_id );
		$this->pull( 'pull_failure', $site_id );

		$this->assertEmpty( get_post_meta( $site_id, 'syn_site_enabled', true ) );
	}

	/**
	 * A site disabled after one failure says so in the singular, in the log and the admin notice.
	 *
	 * @covers ::syn_add_site_disabled_notice
	 */
	public function test_disabling_message_uses_the_singular_for_one_failure(): void {
		$site_id = $this->make_site();
		update_option( 'push_syndication_max_pull_attempts', 1 );

		$this->pull( 'pull_failure', $site_id );

		$expected = "Site {$site_id} disabled after 1 pull failure.";

		$this->assertContains( $expected, wp_list_pluck( get_post_meta( $site_id, '_syn_log', true ), 'message' ) );

		$notices = get_option( 'syn_notices' );
		$this->assertSame( array( $expected ), array_values( wp_list_pluck( $notices['Syndication site disabled'], 'message_text' ) ) );
	}
}
