<?php
/**
 * Create HTML list of categories. Allowing a multiple select list
 * @uses Walker
 */
class Walker_CategoryDropdownMultiple extends Walker {

	var $tree_type = 'category';

	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');

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

		$cat_name = apply_filters( 'list_cats', $category->name, $category );

		$output .= "\t<option class=\"level-$depth\" value=\"".$category->term_id."\"";
		if ( isset( $args['selected_array'] ) && in_array( $category->term_id, $args['selected_array'] ) ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad.$cat_name;

		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;('. $category->count .')';
		}
		$output .= "</option>\n";
	}
}
