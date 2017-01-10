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
}
