<?php
/**
 * Integration tests for post type and taxonomy registration.
 *
 * @package Automattic\Syndication\Tests\Integration\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\Application;

use Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Integration tests for post type and taxonomy registration.
 *
 * Verifies that syn_site post type and syn_sitegroup taxonomy are registered.
 *
 * @group integration
 * @covers \Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar
 */
class PostTypeRegistrationTest extends WPIntegrationTestCase {

	/**
	 * Test syn_site post type is registered.
	 */
	public function test_syn_site_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( PostTypeRegistrar::POST_TYPE ) );
	}

	/**
	 * Test syn_sitegroup taxonomy is registered.
	 */
	public function test_syn_sitegroup_taxonomy_is_registered(): void {
		$this->assertTrue( taxonomy_exists( PostTypeRegistrar::TAXONOMY ) );
	}

	/**
	 * Test syn_sitegroup taxonomy is attached to syn_site post type.
	 */
	public function test_taxonomy_is_attached_to_post_type(): void {
		$taxonomies = get_object_taxonomies( PostTypeRegistrar::POST_TYPE );

		$this->assertContains( PostTypeRegistrar::TAXONOMY, $taxonomies );
	}

	/**
	 * Test syn_site post type has correct properties.
	 */
	public function test_post_type_properties(): void {
		$post_type = get_post_type_object( PostTypeRegistrar::POST_TYPE );

		$this->assertNotNull( $post_type );
		$this->assertFalse( $post_type->public );
		$this->assertTrue( $post_type->show_ui );
		$this->assertFalse( $post_type->publicly_queryable );
		$this->assertTrue( $post_type->exclude_from_search );
	}

	/**
	 * Test syn_sitegroup taxonomy has correct properties.
	 */
	public function test_taxonomy_properties(): void {
		$taxonomy = get_taxonomy( PostTypeRegistrar::TAXONOMY );

		$this->assertNotNull( $taxonomy );
		$this->assertFalse( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_ui );
		$this->assertTrue( $taxonomy->hierarchical );
	}

	/**
	 * Test can create syn_site post.
	 */
	public function test_can_create_syn_site_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => PostTypeRegistrar::POST_TYPE,
				'post_title' => 'Test Site',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertSame( PostTypeRegistrar::POST_TYPE, $post->post_type );
		$this->assertSame( 'Test Site', $post->post_title );
	}

	/**
	 * Test can create syn_sitegroup term.
	 */
	public function test_can_create_syn_sitegroup_term(): void {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => PostTypeRegistrar::TAXONOMY,
				'name'     => 'Test Site Group',
			)
		);

		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( PostTypeRegistrar::TAXONOMY, $term->taxonomy );
		$this->assertSame( 'Test Site Group', $term->name );
	}

	/**
	 * Test can assign syn_sitegroup to syn_site.
	 */
	public function test_can_assign_sitegroup_to_site(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => PostTypeRegistrar::POST_TYPE,
				'post_title' => 'Test Site',
			)
		);

		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => PostTypeRegistrar::TAXONOMY,
				'name'     => 'Test Site Group',
			)
		);

		wp_set_object_terms( $post_id, $term->term_id, PostTypeRegistrar::TAXONOMY );

		$terms = wp_get_object_terms( $post_id, PostTypeRegistrar::TAXONOMY );

		$this->assertCount( 1, $terms );
		$this->assertSame( $term->term_id, $terms[0]->term_id );
	}
}
