<?php
/**
 * Tests for the RSS client.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class RssClientTest
 *
 * Feeds are handed to SimplePie as raw data, so the pull tests exercise the
 * real parsing and mapping without making an HTTP request.
 *
 * @covers Syndication_WP_RSS_Client
 */
class RssClientTest extends WPIntegrationTestCase {

	/**
	 * Load the client.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rss-client.php';
	}

	/**
	 * Build a client that parses the given items instead of fetching a feed.
	 *
	 * @param string $items The <item> elements of the feed.
	 * @return \Syndication_WP_RSS_Client
	 */
	private function make_client( string $items ) {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_default_post_type', 'post' );
		update_post_meta( $site_id, 'syn_default_post_status', 'publish' );
		update_post_meta( $site_id, 'syn_default_cat_status', 'yes' );

		$client           = new \Syndication_WP_RSS_Client( $site_id );
		$client->feed_url = null;
		$client->set_raw_data( '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title>' . $items . '</channel></rss>' );

		return $client;
	}

	/**
	 * A category that doesn't exist yet is created and attached on the same pull.
	 */
	public function test_new_category_is_attached_on_the_pull_that_creates_it(): void {
		$posts = $this->make_client( '<item><guid>a</guid><title>A</title><category>Brand New Category</category></item>' )->get_posts();

		$term = get_term_by( 'name', 'Brand New Category', 'category' );

		$this->assertInstanceOf( \WP_Term::class, $term, 'The category should have been created.' );
		$this->assertSame( array( $term->term_id ), $posts[0]['post_category'] );
	}

	/**
	 * A category that appears between the lookup and the insert is still attached.
	 *
	 * Another pull can create the term after get_term_by() misses it, so
	 * wp_insert_term() reports term_exists with the existing term's ID.
	 */
	public function test_category_created_concurrently_is_attached(): void {
		$raced_id = 0;
		$racer    = function ( $term, $taxonomy ) use ( &$racer, &$raced_id ) {
			remove_filter( 'pre_insert_term', $racer );
			$raced_id = wp_insert_term( $term, $taxonomy )['term_id'];

			return $term;
		};
		add_filter( 'pre_insert_term', $racer, 10, 2 );

		$posts = $this->make_client( '<item><guid>a</guid><title>A</title><category>Raced Category</category></item>' )->get_posts();

		$this->assertNotSame( 0, $raced_id, 'The concurrent insert should have run.' );
		$this->assertSame( array( $raced_id ), $posts[0]['post_category'] );
	}

	/**
	 * An item with no categories is pulled without a warning.
	 */
	public function test_item_without_categories_has_none(): void {
		$posts = $this->make_client( '<item><guid>a</guid><title>A</title><pubDate>Mon, 15 Jan 2024 12:34:56 +0000</pubDate></item>' )->get_posts();

		$this->assertSame( array(), $posts[0]['post_category'] );
		$this->assertSame( array(), $posts[0]['tags_input'] );
	}

	/**
	 * The item date is stored as GMT and converted to the site's timezone.
	 */
	public function test_item_date_is_stored_in_gmt_and_site_time(): void {
		update_option( 'timezone_string', 'America/New_York' );

		$posts = $this->make_client( '<item><guid>a</guid><title>A</title><pubDate>Mon, 15 Jan 2024 12:34:56 +0000</pubDate></item>' )->get_posts();

		$this->assertSame( '2024-01-15 12:34:56', $posts[0]['post_date_gmt'] );
		$this->assertSame( '2024-01-15 07:34:56', $posts[0]['post_date'] );
	}

	/**
	 * An item with no date leaves the date to WordPress rather than 1970.
	 */
	public function test_item_without_a_date_has_no_post_date(): void {
		$posts = $this->make_client( '<item><guid>a</guid><title>A</title></item>' )->get_posts();

		$this->assertArrayNotHasKey( 'post_date', $posts[0] );
		$this->assertArrayNotHasKey( 'post_date_gmt', $posts[0] );
	}

	/**
	 * Saving a form that omits a field stores an empty value without a warning.
	 */
	public function test_save_settings_tolerates_missing_fields(): void {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		$_POST = array( 'feed_url' => 'https://example.com/feed/' );

		$this->assertTrue( \Syndication_WP_RSS_Client::save_settings( $site_id ) );
		$this->assertSame( 'https://example.com/feed/', get_post_meta( $site_id, 'syn_feed_url', true ) );
		$this->assertSame( '', get_post_meta( $site_id, 'syn_default_cat_status', true ) );
	}
}
