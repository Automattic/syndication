<?php
/**
 * Unit tests for Failed_Syndication_Auto_Retry.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use WP_Post;

/**
 * Records the log entries the handler writes, in place of Syndication_Logger,
 * which needs WordPress.
 */
class LoggerSpy {

	/**
	 * Logged entries, each as the method name followed by its arguments.
	 *
	 * @var array<int, array>
	 */
	public static $entries = array();

	/**
	 * Record an info entry.
	 *
	 * @param mixed ...$args The logger arguments.
	 */
	public static function log_post_info( ...$args ) {
		self::$entries[] = array_merge( array( 'info' ), $args );
	}

	/**
	 * Record an error entry.
	 *
	 * @param mixed ...$args The logger arguments.
	 */
	public static function log_post_error( ...$args ) {
		self::$entries[] = array_merge( array( 'error' ), $args );
	}
}

if ( ! class_exists( 'Syndication_Logger', false ) ) {
	class_alias( LoggerSpy::class, 'Syndication_Logger' );
}

/**
 * Tests for Failed_Syndication_Auto_Retry::handle_pull_failure_event().
 *
 * @group unit
 * @covers Failed_Syndication_Auto_Retry
 */
class AutoRetryTest extends TestCase {

	/**
	 * The handler under test.
	 *
	 * @var \Failed_Syndication_Auto_Retry
	 */
	private $handler;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		LoggerSpy::$entries = array();

		$this->stubTranslationFunctions();
		Functions\stubs(
			array(
				'add_action',
				'number_format_i18n',
				'human_time_diff' => '1 min',
			)
		);

		// Mark site-time output, so tests can tell it apart from UTC.
		Functions\when( 'wp_date' )->alias(
			static function ( $format, $timestamp ) {
				return 'site ' . gmdate( $format, $timestamp );
			}
		);

		$this->handler = new \Failed_Syndication_Auto_Retry();
	}

	/**
	 * Expect a valid site post with the given feed URL and auto-retry count.
	 *
	 * @param string $url         The site's feed URL.
	 * @param int    $retry_count The number of auto-retries already made.
	 */
	private function given_site( string $url = 'https://example.com/feed/', int $retry_count = 0 ): void {
		$site            = Mockery::mock( WP_Post::class );
		$site->ID        = 123;
		$site->post_type = 'syn_site';

		Functions\expect( 'get_option' )
			->once()
			->with( 'push_syndication_max_pull_attempts', 0 )
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( $site );

		Functions\when( 'get_post_meta' )->alias(
			static function ( $id, $key ) use ( $url, $retry_count ) {
				return 'syn_feed_url' === $key ? $url : $retry_count;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
	}

	/**
	 * Test that validation fails when site_id is 0.
	 */
	public function test_returns_early_when_site_id_is_zero() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'push_syndication_max_pull_attempts', 0 )
			->andReturn( 3 );

		Functions\expect( 'get_post' )->never();

		$this->handler->handle_pull_failure_event( 0, 1 );

		$this->assertSame( array(), LoggerSpy::$entries );
	}

	/**
	 * Test that validation fails when max_pull_attempts is 0.
	 */
	public function test_returns_early_when_max_pull_attempts_is_zero() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'push_syndication_max_pull_attempts', 0 )
			->andReturn( 0 );

		Functions\expect( 'get_post' )->never();

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertSame( array(), LoggerSpy::$entries );
	}

	/**
	 * Test that validation fails when post doesn't exist.
	 */
	public function test_returns_early_when_post_does_not_exist() {
		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( null );

		Functions\expect( 'get_post_meta' )->never();

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertSame( array(), LoggerSpy::$entries );
	}

	/**
	 * Test that validation fails when post is wrong type.
	 */
	public function test_returns_early_when_post_is_wrong_type() {
		$wrong_post            = Mockery::mock( WP_Post::class );
		$wrong_post->ID        = 123;
		$wrong_post->post_type = 'post';

		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( $wrong_post );

		Functions\expect( 'get_post_meta' )->never();

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertSame( array(), LoggerSpy::$entries );
	}

	/**
	 * A valid site is scheduled for an auto-retry.
	 *
	 * @dataProvider valid_url_provider
	 *
	 * @param string $url The URL to test.
	 */
	public function test_retry_is_scheduled_for_valid_urls( $url ) {
		$this->given_site( $url );

		Functions\expect( 'wp_schedule_single_event' )->once();

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertSame( 'start_auto_retry', LoggerSpy::$entries[0][2] );
	}

	/**
	 * Provide valid URLs for testing.
	 *
	 * @return array
	 */
	public function valid_url_provider() {
		return [
			'https url'         => [ 'https://example.com/feed/' ],
			'http url'          => [ 'http://example.com/feed.xml' ],
			'url with port'     => [ 'https://example.com:8080/feed/' ],
			'url with query'    => [ 'https://example.com/feed/?format=rss' ],
			'url with fragment' => [ 'https://example.com/feed/#section' ],
			'subdomain url'     => [ 'https://blog.example.com/feed/' ],
			'url with path'     => [ 'https://example.com/api/v1/posts/feed' ],
		];
	}

	/**
	 * A site with an invalid feed URL is not retried.
	 *
	 * @dataProvider invalid_url_provider
	 *
	 * @param string $url The URL to test.
	 */
	public function test_retry_is_not_scheduled_for_invalid_urls( $url ) {
		$this->given_site( $url );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertSame( array(), LoggerSpy::$entries );
	}

	/**
	 * Provide invalid URLs for testing.
	 *
	 * @return array
	 */
	public function invalid_url_provider() {
		return [
			'empty string'     => [ '' ],
			'just text'        => [ 'not-a-url' ],
			'missing protocol' => [ 'example.com/feed/' ],
			'just protocol'    => [ 'https://' ],
			'javascript'       => [ 'javascript:alert(1)' ],
			'relative path'    => [ '/feed/rss' ],
			'spaces'           => [ 'https://example .com/' ],
		];
	}

	/**
	 * When the site has used up its pull attempts, the handler cleans up and logs the end of the retries.
	 */
	public function test_cleanup_is_logged_when_the_site_is_disabled() {
		$this->given_site( 'https://example.com/feed/', 2 );

		Functions\expect( 'wp_schedule_single_event' )->never();
		Functions\expect( 'delete_post_meta' )->once()->with( 123, 'syn_failed_auto_retry_attempts' );

		$this->handler->handle_pull_failure_event( 123, 3 );

		$this->assertCount( 1, LoggerSpy::$entries );
		$this->assertSame( 'error', LoggerSpy::$entries[0][0] );
		$this->assertSame( 'end_auto_retry', LoggerSpy::$entries[0][2] );
		$this->assertNotEmpty( LoggerSpy::$entries[0][4], 'The cleanup entry should carry the same log time as the others.' );
	}

	/**
	 * Auto-retry entries are stamped in site time, like every other log entry.
	 */
	public function test_log_entries_use_site_time() {
		$this->given_site();
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertStringStartsWith( 'site ', LoggerSpy::$entries[0][4] );
	}
}
