<?php

namespace Automattic\Syndication\Clients\XML_Pull;

use Automattic\Syndication\Client_Manager;

/**
 * Syndication Client: XML Pull
 *
 * Create 'syndication sites' to pull external content into your
 * WordPress install via XML. Includes XPath mapping to map incoming
 * XML data to specific post data.
 *
 * @package Automattic\Syndication\Clients\XML_PULL
 * @internal Called via instantiation in includes/class-bootstrap.php
 */
class Bootstrap {

	public function __construct() {
		// Register our syndication client
		add_action( 'syndication/register_clients', [ $this, 'register_clients' ] );

		add_action( 'syndication/pre_load_client/xml_pull', [ $this, 'pre_load' ] );

		new Client_Options();
	}

	/**
	 * Register our new Syndication client
	 *
	 * @param Client_Manager $client_man
	 */
	public function register_clients( Client_Manager $client_man ) {
		$client_man->register_pull_client(
			'xml_pull', array(
				'label' => 'XML Pull Client',
				'class' => __NAMESPACE__ . '\Pull_Client',
			)
		);
	}

	public function pre_load() {
		// Clients could use this hook to make sure the class is included.
	}

}


use Walker;

/**
 * Create HTML list of categories. Allowing a multiple select list
 * @uses Walker
 */
class Walker_CategoryDropdownMultiple extends Walker {

	var $tree_type = 'category';

	var $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	/**
	 * Start the element output.
	 *
	 * @see Walker::start_el()
	 *
	 *
	 * @param string $output   Passed by reference. Used to append additional content.
	 * @param object $category Category data object.
	 * @param int    $depth    Depth of category in reference to parents. Default 0.
	 * @param array  $args     An array of arguments. @see wp_list_categories()
	 * @param int    $id       ID of the current category.
	 */
	function start_el( &$output, $category, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$pad = str_repeat( '&nbsp;', $depth * 3 );

		/**
		 * Filter the category name used in the category dropdown used in the XML pull client.
		 *
		 * @param string $category_name The name of the category.
		 * @param object $category      Category data object.
		 */
		$cat_name = apply_filters( 'list_cats', $category->name, $category );

		$output .= "\t<option class=\"level-$depth\" value=\"" . $category->term_id . "\"";
		if ( isset( $args['selected_array'] ) && in_array( $category->term_id, $args['selected_array'] ) ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad . $cat_name;

		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;(' . $category->count . ')';
		}
		$output .= "</option>\n";
	}
}