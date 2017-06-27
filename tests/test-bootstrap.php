<?php
namespace Automattic\Syndication;

/**
 * Tests for Class Bootstrap.
 *
 * @since 2.1
 * @package Automattic\Syndication
 */
class Test_Bootstrap extends \WP_UnitTestCase {
	/**
	 * Test that post types and taxonomies are registered. More like an integration test.
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 * @covers Bootstrap::register_taxonomy()
	 */
	public function test_post_type_and_taxonomies_are_registered() {
		$this->assertTrue( post_type_exists( 'syn_site' ) );
		$this->assertTrue( taxonomy_exists( 'syn_sitegroup' ) );

		$post_type = get_post_type_object( 'syn_site' );
		$this->assertEquals( 'Syndication Endpoints', $post_type->labels->name );

		$taxonomy = get_taxonomy( 'syn_sitegroup' );
		$this->assertEquals( 'Syndication Endpoint Groups', $taxonomy->labels->name );
	}
}