<?php
/**
 * Tests for the 2.2.3 upgrade step that moves the log to protected meta keys.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use ReflectionMethod;
use ReflectionProperty;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class LogMetaUpgradeTest
 *
 * @covers WP_Push_Syndication_Server::upgrade
 */
class LogMetaUpgradeTest extends WPIntegrationTestCase {

	use RestoresServerState;

	/**
	 * A site's existing log, error count and overall error count move to the protected keys.
	 */
	public function test_upgrade_moves_site_logs_to_protected_keys(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		$log     = array( array( 'message' => 'Failed to parse feed at C:\\feeds\\a.xml' ) );

		add_post_meta( $site_id, 'syn_log', wp_slash( $log ) );
		add_post_meta( $site_id, 'syn_log_errors', 2 );
		add_post_meta( $site_id, 'syn_log_errors_overall', 5 );

		$this->upgrade_from_2_2_1();

		$this->assertSame( $log, get_post_meta( $site_id, '_syn_log', true ) );
		$this->assertSame( '2', get_post_meta( $site_id, '_syn_log_errors', true ) );
		$this->assertSame( '5', get_post_meta( $site_id, '_syn_log_errors_overall', true ) );

		foreach ( array( 'syn_log', 'syn_log_errors', 'syn_log_errors_overall' ) as $meta_key ) {
			$this->assertFalse( metadata_exists( 'post', $site_id, $meta_key ), $meta_key );
		}
	}

	/**
	 * A site that has logged since the update keeps its new log, which already holds the old one.
	 */
	public function test_upgrade_keeps_logs_written_since_the_update(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		add_post_meta( $site_id, 'syn_log', array( array( 'message' => 'Old' ) ) );
		add_post_meta( $site_id, 'syn_log_errors', 2 );
		add_post_meta( $site_id, '_syn_log', array( array( 'message' => 'Old' ), array( 'message' => 'New' ) ) );
		add_post_meta( $site_id, '_syn_log_errors', 0 );

		$this->upgrade_from_2_2_1();

		$this->assertCount( 2, get_post_meta( $site_id, '_syn_log', true ) );
		$this->assertSame( '0', get_post_meta( $site_id, '_syn_log_errors', true ) );
		$this->assertFalse( metadata_exists( 'post', $site_id, 'syn_log' ) );
		$this->assertFalse( metadata_exists( 'post', $site_id, 'syn_log_errors' ) );
	}

	/**
	 * Log meta on anything other than a site was not written by the plugin, so isn't moved.
	 */
	public function test_upgrade_ignores_log_meta_on_other_posts(): void {
		$post_id = $this->factory()->post->create();

		add_post_meta( $post_id, 'syn_log', array( array( 'message' => 'Planted' ) ) );

		$this->upgrade_from_2_2_1();

		$this->assertFalse( metadata_exists( 'post', $post_id, '_syn_log' ) );
		$this->assertSame( array( array( 'message' => 'Planted' ) ), get_post_meta( $post_id, 'syn_log', true ), 'Meta the plugin did not write should be left alone.' );
	}

	/**
	 * Run the server's upgrade routine as though upgrading from 2.2.1.
	 *
	 * upgrade() does nothing once the stored version has reached SYNDICATION_VERSION, which
	 * is still 2.2.2 until the release bumps it, so the stored version is set below both.
	 */
	private function upgrade_from_2_2_1(): void {
		$this->version_property()->setValue( $GLOBALS['push_syndication_server'], '2.2.1' );

		$upgrade = new ReflectionMethod( $GLOBALS['push_syndication_server'], 'upgrade' );
		if ( PHP_VERSION_ID < 80100 ) {
			$upgrade->setAccessible( true );
		}
		$upgrade->invoke( $GLOBALS['push_syndication_server'] );
	}

	/**
	 * The server's private version property.
	 *
	 * @return ReflectionProperty
	 */
	private function version_property(): ReflectionProperty {
		$property = new ReflectionProperty( $GLOBALS['push_syndication_server'], 'version' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property;
	}
}
