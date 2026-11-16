<?php
/**
 * Tests for the WP-CLI push commands.
 *
 * @package Automattic\Syndication\Tests
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests;

use ReflectionMethod;
use Syndication_CLI_Command;
use WP_CLI;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class CliCommandTest
 *
 * @coversDefaultClass \Syndication_CLI_Command
 */
class CliCommandTest extends WPIntegrationTestCase {

	/**
	 * Load the WP-CLI stand-ins and the real command class.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! class_exists( 'WP_CLI', false ) ) {
			require_once __DIR__ . '/WP_CLI_Stub.php';
		}
		require_once __DIR__ . '/Syndication_Failing_Mock_Client.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-wp-cli.php';
	}

	/**
	 * Start each test with no recorded output.
	 */
	protected function setUp(): void {
		parent::setUp();
		WP_CLI::$output = array();
	}

	/**
	 * Create a post whose only sitegroup holds one enabled site using the failing client.
	 *
	 * @param string $site_title Title of the syn_site post.
	 * @return array{0: int, 1: int} Post ID and site ID.
	 */
	private function create_post_with_failing_site( string $site_title ): array {
		$sitegroup = 'cli-test-group';
		if ( ! term_exists( $sitegroup, 'syn_sitegroup' ) ) {
			wp_insert_term( $sitegroup, 'syn_sitegroup' );
		}

		$site_id = $this->factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
				'post_title'  => $site_title,
			)
		);
		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', 'Failing_Mock' );
		wp_set_object_terms( $site_id, $sitegroup, 'syn_sitegroup' );

		$post_id = $this->factory()->post->create();
		update_post_meta( $post_id, '_syn_selected_sitegroups', array( $sitegroup ) );

		return array( $post_id, $site_id );
	}

	/**
	 * A post with no sitegroups is skipped with a warning rather than pushed to nowhere in silence.
	 *
	 * @covers ::push_post
	 */
	public function test_push_post_warns_when_post_has_no_sitegroups(): void {
		$post_id = $this->factory()->post->create();

		( new Syndication_CLI_Command() )->push_post( array(), array( 'post_id' => $post_id ) );

		$warnings = WP_CLI::messages( 'warning' );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( (string) $post_id, $warnings[0] );
		$this->assertSame( array(), WP_CLI::messages( 'log' ), 'Nothing should be pushed.' );
	}

	/**
	 * Posts that can't be pushed don't stop the rest of the run.
	 *
	 * @covers ::push_all_posts
	 */
	public function test_push_all_posts_continues_past_posts_without_sitegroups(): void {
		$first_unsyndicated = $this->factory()->post->create();
		list( $syndicated ) = $this->create_post_with_failing_site( 'Failing site' );
		$last_unsyndicated  = $this->factory()->post->create();

		( new Syndication_CLI_Command() )->push_all_posts( array(), array() );

		$warnings = implode( "\n", WP_CLI::messages( 'warning' ) );
		$this->assertStringContainsString( "Post {$first_unsyndicated} ", $warnings );
		$this->assertStringContainsString( "Post {$last_unsyndicated} ", $warnings );
		$this->assertContains( "Processing post_id #{$syndicated} (" . get_the_title( $syndicated ) . ')', WP_CLI::messages( 'log' ) );
	}

	/**
	 * A failed push of a new post is reported, not logged as a success.
	 *
	 * @covers ::push_post
	 */
	public function test_push_post_reports_failed_new_post(): void {
		list( $post_id ) = $this->create_post_with_failing_site( 'Failing site' );

		( new Syndication_CLI_Command() )->push_post( array(), array( 'post_id' => $post_id ) );

		$this->assertSame(
			array( '-- Failed to add remote post on Failing site: Remote site rejected the post' ),
			WP_CLI::messages( 'warning' )
		);
		foreach ( WP_CLI::messages( 'log' ) as $line ) {
			$this->assertStringNotContainsString( 'Added remote post', $line );
		}
	}

	/**
	 * A failed update of a previously pushed post is reported, not logged as a success.
	 *
	 * @covers ::push_post
	 */
	public function test_push_post_reports_failed_edit_post(): void {
		list( $post_id, $site_id ) = $this->create_post_with_failing_site( 'Failing site' );
		update_post_meta( $post_id, '_syn_slave_post_states', array( 'success' => array( $site_id => array( 'ext_ID' => 42 ) ) ) );

		( new Syndication_CLI_Command() )->push_post( array(), array( 'post_id' => $post_id ) );

		$this->assertSame(
			array( '-- Failed to update remote post on Failing site: Remote site rejected the update' ),
			WP_CLI::messages( 'warning' )
		);
		foreach ( WP_CLI::messages( 'log' ) as $line ) {
			$this->assertStringNotContainsString( 'Updated remote post', $line );
		}
	}

	/**
	 * The object cache's remote connections are reset between pages of a long run.
	 *
	 * @covers ::stop_the_insanity
	 */
	public function test_stop_the_insanity_resets_remote_cache_connections(): void {
		global $wp_object_cache;

		$fake_cache = new class() {
			/**
			 * Properties the method clears.
			 *
			 * @var array
			 */
			public $group_ops, $stats, $memcache_debug, $cache;

			/**
			 * Whether __remoteset() ran.
			 *
			 * @var bool
			 */
			public $remoteset_called = false;

			/**
			 * Stand-in for the memcached drop-in's connection reset.
			 */
			public function __remoteset(): void {
				$this->remoteset_called = true;
			}
		};

		$original_cache  = $wp_object_cache;
		$wp_object_cache = $fake_cache;
		try {
			$method = new ReflectionMethod( Syndication_CLI_Command::class, 'stop_the_insanity' );
			$method->setAccessible( true );
			$method->invoke( new Syndication_CLI_Command() );
		} finally {
			$wp_object_cache = $original_cache;
		}

		$this->assertTrue( $fake_cache->remoteset_called );
	}
}
