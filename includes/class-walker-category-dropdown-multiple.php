<?php
/**
 * Create HTML list of categories. Allowing a multiple select list
 *
 * @uses Walker
 */
class Walker_CategoryDropdownMultiple extends Walker {
	// @TODO: phpcs - rename class?

	public $tree_type = 'category';

	public $db_fields = array(
		'parent' => 'parent',
		'id'     => 'term_id',
	);

	/**
	 * Start the element output.
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $category Category data object.
	 * @param int    $depth Depth of category in reference to parents. Default 0.
	 * @param array  $args An array of arguments. @see wp_list_categories().
	 * @param int    $current_object_id The current object ID.
	 *
	 * @see Walker::start_el()
	 */
	public function start_el( &$output, $category, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$pad = str_repeat( '&nbsp;', $depth * 3 );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$cat_name = apply_filters( 'list_cats', $category->name, $category );

		$output .= '\t<option class="level-' . esc_attr( $depth ) . '" value="' . esc_attr( $category->term_id ) . '"';
		if ( isset( $args['selected_array'] ) && in_array( $category->term_id, $args['selected_array'] ) ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad . $cat_name;

		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;(' . esc_html( $category->count ) . ')';
		}
		$output .= "</option>\n";
	}
}
