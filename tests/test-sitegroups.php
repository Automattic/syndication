<?php
/**
 * Class SampleTest
 *
 * @package Syndication
 */

/**
 * Sample test case.
 */
class SiteGroups extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	function test_add_new_sitegroup() {

		$sitegroup_taxonomy = 'syn_sitegroup';
		$sitegroup_name = 'Site Group A';

		$this->factory->term->create_object( array(
			'taxonomy' => $sitegroup_taxonomy,
			'name' => $sitegroup_name,
		) );

		$term = get_term_by( 'name', $sitegroup_name, $sitegroup_taxonomy );

		$this->assertInstanceOf( 'WP_Term', $term );
	}

	function test_delete_sitegroup() {

		$sitegroup_taxonomy = 'syn_sitegroup';
		$sitegroup_name = 'Site Group B';

		$this->factory->term->create_object( array(
			'taxonomy' => $sitegroup_taxonomy,
			'name' => $sitegroup_name,
		) );

		$term = get_term_by( 'name', $sitegroup_name, $sitegroup_taxonomy );

		$deleted = wp_delete_term( $term->term_id, $term->taxonomy );

		$this->assertTrue( $deleted );

	}

	function test_edit_sitegroup() {

		$sitegroup_taxonomy = 'syn_sitegroup';
		$sitegroup_name = 'Site Group C';

		$this->factory->term->create_object( array(
			'taxonomy' => $sitegroup_taxonomy,
			'name' => $sitegroup_name,
		) );

		$term = get_term_by( 'name', $sitegroup_name, $sitegroup_taxonomy );

		$deleted = wp_update_term( $term->term_id, $term->taxonomy, array(
			'name' => 'Site Group C1',
			'slug' => 'site-group-c1',
		) );

		$modified_term = get_term_by( 'name', 'Site Group C1', $sitegroup_taxonomy );

		$this->assertInstanceOf( 'WP_Term', $modified_term );

	}
}
