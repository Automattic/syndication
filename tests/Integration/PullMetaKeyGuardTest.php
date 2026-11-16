<?php
/**
 * Tests that pulled feed data is never written to protected or plugin-owned meta keys.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class PullMetaKeyGuardTest
 *
 * @covers Syndication_WP_XML_Client::is_allowed_meta_key
 * @covers Syndication_WP_XML_Client::save_meta
 * @covers Syndication_WP_XML_Client::update_meta
 * @covers Syndication_WP_RSS_Client::save_meta
 * @covers Syndication_WP_RSS_Client::update_meta
 */
class PullMetaKeyGuardTest extends WPIntegrationTestCase {

	/**
	 * Meta keys a node config must not be able to target.
	 *
	 * Slashed keys are unslashed by add_post_meta(), and MySQL's default
	 * collations match keys case-insensitively, so both must be caught.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_disallowed_keys(): array {
		return array(
			'core protected key'        => array( '_thumbnail_id' ),
			'core protected, slashed'   => array( '\\_wp_page_template' ),
			'plugin key'                => array( 'syn_log' ),
			'plugin key, other case'    => array( 'SYN_post_guid' ),
			'plugin key, slashed'       => array( 'syn\\_log' ),
			'plugin protected key'      => array( '_syn_slave_post_states' ),
			'non-ASCII lookalike'       => array( "\u{FF53}yn_log" ),
			'control character'         => array( "s\x01yn_log" ),
			'empty key'                 => array( '' ),
		);
	}

	/**
	 * Protected, plugin-owned and lookalike keys are refused.
	 *
	 * @dataProvider data_disallowed_keys
	 *
	 * @param string $meta_key Meta key.
	 */
	public function test_disallowed_keys_are_refused( string $meta_key ): void {
		$this->assertFalse( \Syndication_WP_XML_Client::is_allowed_meta_key( $meta_key ) );
	}

	/**
	 * Ordinary keys, including ones that merely start with "syn", are allowed.
	 */
	public function test_ordinary_keys_are_allowed(): void {
		$this->assertTrue( \Syndication_WP_XML_Client::is_allowed_meta_key( 'source_byline' ) );
		$this->assertTrue( \Syndication_WP_XML_Client::is_allowed_meta_key( 'synopsis' ) );
	}

	/**
	 * Each client method that writes pulled post meta.
	 *
	 * @return array<string, array{0: callable}>
	 */
	public function data_meta_writers(): array {
		return array(
			'XML save_meta'   => array( array( 'Syndication_WP_XML_Client', 'save_meta' ) ),
			'XML update_meta' => array( array( 'Syndication_WP_XML_Client', 'update_meta' ) ),
			'RSS save_meta'   => array( array( 'Syndication_WP_RSS_Client', 'save_meta' ) ),
			'RSS update_meta' => array( array( 'Syndication_WP_RSS_Client', 'update_meta' ) ),
		);
	}

	/**
	 * A stored config written before the fix still cannot write a disallowed key.
	 *
	 * @dataProvider data_meta_writers
	 *
	 * @param callable $writer Meta writer under test.
	 */
	public function test_writers_skip_disallowed_keys( callable $writer ): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_wp_page_template', 'original.php' );
		update_post_meta( $post_id, 'syn_post_guid', 'original-guid' );

		$post = array(
			'post_category' => array(),
			'postmeta'      => array(
				'source_byline'     => 'From the feed',
				'_wp_page_template' => 'feed.php',
				'SYN_POST_GUID'     => 'feed-guid',
				'syn_log'           => 'feed-log',
				'enc_field'         => '_syn_slave_post_states',
				'enclosures'        => array( array( 'url' => 'https://example.com/a.jpg' ) ),
			),
		);

		call_user_func( $writer, $post_id, $post, null, 'WP_XML', null );

		$this->assertSame( 'From the feed', get_post_meta( $post_id, 'source_byline', true ) );
		$this->assertSame( 'original.php', get_post_meta( $post_id, '_wp_page_template', true ) );
		$this->assertSame( 'original-guid', get_post_meta( $post_id, 'syn_post_guid', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'syn_log', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_syn_slave_post_states', true ) );
	}
}
