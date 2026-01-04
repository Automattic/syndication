<?php
/**
 * Unit tests for Failed_Syndication_Auto_Retry validation.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use WP_Post;

/**
 * Tests for the handle_pull_failure_event method validation.
 *
 * Since Failed_Syndication_Auto_Retry has WordPress dependencies,
 * we test the method logic using a test double class that replicates
 * the exact validation logic.
 *
 * @group unit
 */
class AutoRetryValidationTest extends TestCase {

	/**
	 * Test double instance that replicates the validation logic.
	 *
	 * @var object
	 */
	private $handler;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a test double that replicates the validation logic.
		$this->handler = new class() {
			/**
			 * Track whether the cron was scheduled.
			 *
			 * @var bool
			 */
			public $cron_scheduled = false;

			/**
			 * Handle a site pull failure event.
			 *
			 * @param int $site_id         The post id of the site we need to retry.
			 * @param int $failed_attempts The number of pull failures.
			 */
			public function handle_pull_failure_event( $site_id = 0, $failed_attempts = 0 ) {
				$site_id         = (int) $site_id;
				$failed_attempts = (int) $failed_attempts;

				// Fetch the allowable number of max pull attempts.
				$max_pull_attempts = (int) get_option( 'push_syndication_max_pull_attempts', 0 );

				// Bail if we've already met the max pull attempt count.
				if ( ! $max_pull_attempts ) {
					return;
				}

				// Only proceed if we have a valid site id.
				if ( 0 === $site_id ) {
					return;
				}

				// Fetch the site post.
				$site = get_post( $site_id );

				// Validate the site post exists and is the correct post type.
				if ( ! $site instanceof WP_Post || 'syn_site' !== $site->post_type ) {
					return;
				}

				// Fetch the site url.
				$site_url = get_post_meta( $site->ID, 'syn_feed_url', true );

				// Validate the site has a valid-looking syndication URL.
				if ( empty( $site_url ) || false === filter_var( $site_url, FILTER_VALIDATE_URL ) ) {
					return;
				}

				// If we got here, all validations passed.
				// In the real code, this would schedule a cron job.
				$this->cron_scheduled = true;
			}
		};
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

		$this->assertFalse( $this->handler->cron_scheduled );
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

		$this->assertFalse( $this->handler->cron_scheduled );
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

		$this->assertFalse( $this->handler->cron_scheduled );
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

		$this->assertFalse( $this->handler->cron_scheduled );
	}

	/**
	 * Test that validation fails when syndication URL is empty.
	 */
	public function test_returns_early_when_syndication_url_is_empty() {
		$site            = Mockery::mock( WP_Post::class );
		$site->ID        = 123;
		$site->post_type = 'syn_site';

		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( $site );

		Functions\expect( 'get_post_meta' )
			->once()
			->with( 123, 'syn_feed_url', true )
			->andReturn( '' );

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertFalse( $this->handler->cron_scheduled );
	}

	/**
	 * Test that validation fails when syndication URL is invalid.
	 */
	public function test_returns_early_when_syndication_url_is_invalid() {
		$site            = Mockery::mock( WP_Post::class );
		$site->ID        = 123;
		$site->post_type = 'syn_site';

		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( $site );

		Functions\expect( 'get_post_meta' )
			->once()
			->with( 123, 'syn_feed_url', true )
			->andReturn( 'not-a-valid-url' );

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertFalse( $this->handler->cron_scheduled );
	}

	/**
	 * Test that validation passes with valid site.
	 */
	public function test_validation_passes_with_valid_site() {
		$site            = Mockery::mock( WP_Post::class );
		$site->ID        = 123;
		$site->post_type = 'syn_site';

		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->with( 123 )
			->andReturn( $site );

		Functions\expect( 'get_post_meta' )
			->once()
			->with( 123, 'syn_feed_url', true )
			->andReturn( 'https://example.com/feed/' );

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertTrue( $this->handler->cron_scheduled );
	}

	/**
	 * Test that validation passes with various valid URLs.
	 *
	 * @dataProvider valid_url_provider
	 *
	 * @param string $url The URL to test.
	 */
	public function test_validation_passes_with_various_valid_urls( $url ) {
		$site            = Mockery::mock( WP_Post::class );
		$site->ID        = 123;
		$site->post_type = 'syn_site';

		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->andReturn( $site );

		Functions\expect( 'get_post_meta' )
			->once()
			->andReturn( $url );

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertTrue( $this->handler->cron_scheduled, "URL '$url' should be valid" );
	}

	/**
	 * Provide valid URLs for testing.
	 *
	 * @return array
	 */
	public function valid_url_provider() {
		return [
			'https url'           => [ 'https://example.com/feed/' ],
			'http url'            => [ 'http://example.com/feed.xml' ],
			'url with port'       => [ 'https://example.com:8080/feed/' ],
			'url with query'      => [ 'https://example.com/feed/?format=rss' ],
			'url with fragment'   => [ 'https://example.com/feed/#section' ],
			'subdomain url'       => [ 'https://blog.example.com/feed/' ],
			'url with path'       => [ 'https://example.com/api/v1/posts/feed' ],
		];
	}

	/**
	 * Test that validation fails with various invalid URLs.
	 *
	 * @dataProvider invalid_url_provider
	 *
	 * @param string $url The URL to test.
	 */
	public function test_validation_fails_with_various_invalid_urls( $url ) {
		$site            = Mockery::mock( WP_Post::class );
		$site->ID        = 123;
		$site->post_type = 'syn_site';

		Functions\expect( 'get_option' )
			->once()
			->andReturn( 3 );

		Functions\expect( 'get_post' )
			->once()
			->andReturn( $site );

		Functions\expect( 'get_post_meta' )
			->once()
			->andReturn( $url );

		$this->handler->handle_pull_failure_event( 123, 1 );

		$this->assertFalse( $this->handler->cron_scheduled, "URL '$url' should be invalid" );
	}

	/**
	 * Provide invalid URLs for testing.
	 *
	 * @return array
	 */
	public function invalid_url_provider() {
		return [
			'empty string'        => [ '' ],
			'just text'           => [ 'not-a-url' ],
			'missing protocol'    => [ 'example.com/feed/' ],
			'just protocol'       => [ 'https://' ],
			'javascript'          => [ 'javascript:alert(1)' ],
			'relative path'       => [ '/feed/rss' ],
			'spaces'              => [ 'https://example .com/' ],
		];
	}
}
