<?php
/**
 * Tests for how the XML client handles feeds it can fetch but not use.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class XmlClientFeedErrorsTest
 *
 * @covers Syndication_WP_XML_Client
 */
class XmlClientFeedErrorsTest extends WPIntegrationTestCase {

	/**
	 * The body returned to the client in place of a real HTTP response.
	 *
	 * @var string
	 */
	private $feed_body = '';

	/**
	 * The HTTP status code returned with the feed body.
	 *
	 * @var int
	 */
	private $status = 200;

	/**
	 * Intercept the feed fetch.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-xml-client.php';

		add_filter(
			'pre_http_request',
			function () {
				return array(
					'headers'  => array(),
					'body'     => $this->feed_body,
					'response' => array(
						'code'    => $this->status,
						'message' => get_status_header_desc( $this->status ),
					),
					'cookies'  => array(),
					'filename' => '',
				);
			}
		);
	}

	/**
	 * Create an enabled XML site that allows three pull failures.
	 *
	 * @return int The site post ID.
	 */
	private function make_site(): int {
		update_option( 'push_syndication_max_pull_attempts', 3 );

		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );

		update_post_meta( $site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $site_id, 'syn_transport_type', 'WP_XML' );
		update_post_meta( $site_id, 'syn_feed_url', 'http://example.com/feed.xml' );
		update_post_meta(
			$site_id,
			'syn_node_config',
			array(
				'namespace'  => '',
				'post_root'  => '/rss/channel/item',
				'enc_parent' => '',
				'categories' => array(),
				'nodes'      => array(),
			)
		);

		return $site_id;
	}

	/**
	 * A feed that isn't well-formed XML yields no posts, without an error.
	 */
	public function test_unparseable_feed_yields_no_posts(): void {
		$this->feed_body = '<rss><channel><item>';

		$this->assertSame( array(), ( new \Syndication_WP_XML_Client( $this->make_site() ) )->get_posts() );
	}

	/**
	 * A feed with no items yields no posts, without an error.
	 */
	public function test_feed_without_items_yields_no_posts(): void {
		$this->feed_body = '<rss><channel><title>Empty</title></channel></rss>';

		$this->assertSame( array(), ( new \Syndication_WP_XML_Client( $this->make_site() ) )->get_posts() );
	}

	/**
	 * A feed that downloads but never parses counts as a failed pull every time,
	 * not as a success that resets the count, so the site is still disabled.
	 */
	public function test_unparseable_feed_still_disables_the_site(): void {
		$this->feed_body = '<rss><channel><item>';

		$site_id = $this->make_site();

		for ( $pull = 0; $pull < 3; $pull++ ) {
			( new \Syndication_WP_XML_Client( $site_id ) )->get_posts();
		}

		$this->assertEmpty( get_post_meta( $site_id, 'syn_site_enabled', true ) );
	}

	/**
	 * Fetch errors reach the Logs screen, so they go through the text domain.
	 *
	 * @dataProvider data_fetch_errors
	 *
	 * @param int    $status   HTTP status code of the response.
	 * @param string $body     Response body.
	 * @param string $expected Expected error message, marked as translated.
	 */
	public function test_fetch_errors_are_translatable( int $status, string $body, string $expected ): void {
		add_filter(
			'gettext_push-syndication',
			static function ( $translation ) {
				return '[' . $translation . ']';
			}
		);

		$this->status    = $status;
		$this->feed_body = $body;

		$error = ( new \Syndication_WP_XML_Client( $this->make_site() ) )->fetch_feed();

		$this->assertWPError( $error );
		$this->assertSame( $expected, $error->get_error_message() );
	}

	/**
	 * Responses the fetch refuses, and the message each gives.
	 *
	 * @return array<string, array{int, string, string}>
	 */
	public static function data_fetch_errors(): array {
		return array(
			'HTTP error status' => array( 500, '', '[Failed to fetch XML feed; HTTP status 500.]' ),
			'Doctype in feed'   => array( 200, '<!DOCTYPE rss><rss></rss>', '[Refused XML feed: it declares a document type.]' ),
		);
	}
}
