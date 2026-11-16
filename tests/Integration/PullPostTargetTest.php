<?php
/**
 * Tests that pulled feed data cannot choose which local post, or what kind of post, a pull writes.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullPostTargetTest
 *
 * @covers WP_Push_Syndication_Server::pull_content
 * @covers WP_Push_Syndication_Server::get_pull_post_types
 */
class PullPostTargetTest extends WPIntegrationTestCase {

	use RestoresServerState;

	/**
	 * The syn_site post pulled from.
	 *
	 * @var int
	 */
	private $site_id;

	/**
	 * Create an enabled site that pulls through the mock client.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->site_id = self::factory()->post->create(
			array(
				'post_type'   => 'syn_site',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->site_id, 'syn_site_enabled', 'on' );
		update_post_meta( $this->site_id, 'syn_transport_type', 'Mock' );
	}

	/**
	 * Clear the mock feed and the test post type, even when an assertion failed.
	 */
	public function tear_down(): void {
		\Syndication_Mock_Client::set_posts( array() );
		unregister_post_type( 'syn_test_article' );

		parent::tear_down();
	}

	/**
	 * Pull one feed post through the mock client.
	 *
	 * @param array $feed_post Post fields as a client would return them, merged over defaults.
	 */
	private function pull( array $feed_post ): void {
		global $push_syndication_server;

		\Syndication_Mock_Client::set_posts(
			array(
				array_merge(
					array(
						'post_title'  => 'From the feed',
						'post_type'   => 'post',
						'post_status' => 'publish',
						'post_guid'   => 'feed-guid',
					),
					$feed_post
				),
			)
		);

		$push_syndication_server->pull_content( array( get_post( $this->site_id ) ) );
	}

	/**
	 * Local posts, of any type, that a pull created for the feed item.
	 *
	 * @return \WP_Post[]
	 */
	private function pulled_posts(): array {
		return get_posts(
			array(
				// Not 'any', which leaves out the internal types this test looks for.
				'post_type'   => array_merge( array_values( get_post_types() ), array( 'not_a_post_type' ) ),
				'post_status' => 'any',
				'meta_key'    => 'syn_post_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'feed-guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Pull a new feed post and return the local post it created.
	 *
	 * @param array $feed_post Post fields as a client would return them.
	 * @return \WP_Post
	 */
	private function pull_new( array $feed_post ): \WP_Post {
		$this->pull( $feed_post );

		$created = $this->pulled_posts();
		$this->assertCount( 1, $created, 'The feed item should be created as a new post.' );

		return $created[0];
	}

	/**
	 * A feed value mapped to ID cannot overwrite an existing local post.
	 */
	public function test_feed_id_does_not_overwrite_existing_post(): void {
		$existing_id = self::factory()->post->create( array( 'post_title' => 'Existing post' ) );

		$created = $this->pull_new( array( 'ID' => $existing_id ) );

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

		$this->pull_new( array() );

		$this->assertSame( 'Existing post', get_post( $existing_id )->post_title );
	}

	/**
	 * A feed value mapped to import_id cannot choose the new post's ID.
	 */
	public function test_feed_import_id_does_not_choose_post_id(): void {
		$free_id = self::factory()->post->create() + 1000;

		$created = $this->pull_new( array( 'import_id' => $free_id ) );

		$this->assertNotSame( $free_id, $created->ID );
	}

	/**
	 * Post types a pull must never create.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_disallowed_post_types(): array {
		return array(
			'template'       => array( 'wp_template' ),
			'global styles'  => array( 'wp_global_styles' ),
			'pattern'        => array( 'wp_block' ),
			'navigation'     => array( 'wp_navigation' ),
			'attachment'     => array( 'attachment' ),
			'syndication'    => array( 'syn_site' ),
			'not registered' => array( 'not_a_post_type' ),
		);
	}

	/**
	 * A new pulled post of a disallowed type is skipped and logged.
	 *
	 * @dataProvider data_disallowed_post_types
	 *
	 * @param string $post_type Post type.
	 */
	public function test_new_post_of_disallowed_type_is_skipped( string $post_type ): void {
		$this->pull( array( 'post_type' => $post_type ) );

		$this->assertSame( array(), $this->pulled_posts() );
		$this->assertContains( 'invalid_post_type', array_column( get_post_meta( $this->site_id, '_syn_log', true ), 'status' ) );
	}

	/**
	 * An update that would change a pulled post to a disallowed type is skipped.
	 */
	public function test_update_to_disallowed_type_is_skipped(): void {
		global $push_syndication_server;

		$push_syndication_server->push_syndicate_settings['update_pulled_posts'] = 'on';

		$created = $this->pull_new( array() );

		$this->pull(
			array(
				'post_type'  => 'wp_template',
				'post_title' => 'Updated from the feed',
			)
		);

		$this->assertSame( 'post', get_post_type( $created->ID ) );
		$this->assertSame( 'From the feed', get_post( $created->ID )->post_title );
	}

	/**
	 * Pages and custom types with an admin UI can still be pulled.
	 */
	public function test_page_and_custom_types_are_allowed(): void {
		register_post_type( 'syn_test_article', array( 'show_ui' => true ) );

		$this->assertSame( 'page', $this->pull_new( array( 'post_type' => 'page' ) )->post_type );
		$this->assertContains( 'syn_test_article', \WP_Push_Syndication_Server::get_pull_post_types() );
	}

	/**
	 * The default post type dropdowns offer only the types a pull may write.
	 */
	public function test_settings_offer_only_allowed_post_types(): void {
		$site = get_post( $this->site_id );

		// The XML form can't yet render a site with no saved node config.
		update_post_meta( $this->site_id, 'syn_node_config', array( 'nodes' => array() ) );

		foreach ( array( 'Syndication_WP_XML_Client', 'Syndication_WP_RSS_Client' ) as $client ) {
			ob_start();
			$client::display_settings( $site );
			$html = ob_get_clean();

			$this->assertStringContainsString( 'value="page"', $html, $client );
			$this->assertStringNotContainsString( 'value="wp_template"', $html, $client );
			$this->assertStringNotContainsString( 'value="syn_site"', $html, $client );
		}
	}
}
