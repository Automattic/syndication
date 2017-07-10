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

	/**
	 * Test to see if we can create a site (Endpoint).
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 */
	public function test_add_new_site() {
		$site = $this->factory->post->create_and_get( array(
			'post_type' => 'syn_site',
		) );

		$this->assertInstanceOf( 'WP_Post', $site );
	}

	/**
	 * Test to see if we can delete a site (Endpoint).
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 */
	public function test_delete_site() {
		$site = $this->factory->post->create_and_get( array(
			'post_type' => 'syn_site',
		) );

		$deleted = wp_delete_post( $site->ID );

		$this->assertNotFalse( $deleted );
	}

	/**
	 * Test to see if we can edit a site (Endpoint).
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 */
	public function test_edit_site() {
		$site = $this->factory->post->create_and_get( array(
			'post_type' => 'syn_site',
		) );

		$site->post_title = 'New Site ID';

		$site_updated = wp_update_post( $site );

		$this->assertEquals( $site->ID, $site_updated );
	}

	/**
	 * Test to see if we can create a sitegroup.
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 */
	public function test_add_new_sitegroup() {
		$sitegroup_taxonomy = 'syn_sitegroup';
		$sitegroup_name = 'Site Group A';

		$term = $this->factory->term->create_and_get( array(
			'taxonomy' => $sitegroup_taxonomy,
			'name' => $sitegroup_name,
		) );

		$this->assertInstanceOf( 'WP_Term', $term );
	}

	/**
	 * Test to see if we can delete a sitegroup.
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 */
	public function test_delete_sitegroup() {
		$sitegroup_taxonomy = 'syn_sitegroup';
		$sitegroup_name = 'Site Group B';

		$term = $this->factory->term->create_and_get( array(
			'taxonomy' => $sitegroup_taxonomy,
			'name' => $sitegroup_name,
		) );

		$deleted = wp_delete_term( $term->term_id, $term->taxonomy );

		$this->assertTrue( $deleted );
	}

	/**
	 * Test to see if we can edit a sitegroup.
	 *
	 * @since 2.1
	 * @covers Bootstrap::register_post_type()
	 */
	public function test_edit_sitegroup() {
		$sitegroup_taxonomy = 'syn_sitegroup';
		$sitegroup_name = 'Site Group C';

		$term = $this->factory->term->create_and_get( array(
			'taxonomy' => $sitegroup_taxonomy,
			'name' => $sitegroup_name,
		) );

		$deleted = wp_update_term( $term->term_id, $term->taxonomy, array(
			'name' => 'Site Group C1',
			'slug' => 'site-group-c1',
		) );

		$modified_term = get_term_by( 'name', 'Site Group C1', $sitegroup_taxonomy );

		$this->assertInstanceOf( 'WP_Term', $modified_term );
	}
}