<?php
/**
 * Site Options
 *
 * Site options are options specific to site using a particular client.
 */

namespace Automattic\Syndication\Clients\XML_Pull;

class Site_Options {

	public function __construct() {

		add_action( 'syndication/render_site_options/xml_pull', [ $this, 'render_site_options_pull' ] );
		add_action( 'syndication/save_site_options/xml_pull', [ $this, 'save_site_options_pull' ] );

		/**
		 * Load the {@see Walker_CategoryDropdownMultiple}
		 */
		include_once( dirname( __FILE__ ) . '/class-walker-category-dropdown-multiple.php' );

	}

	public function render_site_options_pull( $site_id ) {
		//TODO: JS if is_meta show text box, if is_photo show photo select with numbers as values, else show select of post fields
		//TODO: JS Validation
		//TODO: deal with ability to select, i.e. media:group/media:thumbnail[@width="75"]/@url (can't be unserialized as is with quotes around 75)
		$feed_url					= get_post_meta( $site_id, 'syn_feed_url', true );
		$default_post_type			= get_post_meta( $site_id, 'syn_default_post_type', true );
		$default_post_status		= get_post_meta( $site_id, 'syn_default_post_status', true );
		$default_comment_status		= get_post_meta( $site_id, 'syn_default_comment_status', true );
		$default_ping_status		= get_post_meta( $site_id, 'syn_default_ping_status', true );
		$node_config				= get_post_meta( $site_id, 'syn_node_config', true );
		$id_field					= get_post_meta( $site_id, 'syn_id_field', true );
		$enc_field					= get_post_meta( $site_id, 'syn_enc_field', true );
		$enc_is_photo				= get_post_meta( $site_id, 'syn_enc_is_photo', true);

		if ( isset( $node_config['namespace'] )) {
			$namespace = $node_config['namespace'];
		}

		//unset is outside of isset() test to remove the item from the array if the key is there with no value
		$namespace = isset( $node_config['namespace'] ) ? $node_config['namespace'] : null;
		unset( $node_config['namespace'] );

		$post_root = isset( $node_config['post_root'] ) ? $node_config['post_root'] : null;
		unset( $node_config['post_root'] );

		$enc_parent = isset( $node_config['enc_parent'] ) ? $node_config['enc_parent'] : null;
		unset( $node_config['enc_parent'] );

		$categories = isset( $node_config['categories'] ) && ! empty( $node_config['categories'] ) ? (array) $node_config['categories'] : null;
		unset( $node_config['categories'] );

		$custom_nodes = $node_config['nodes'];
		?>
		<p>
			<label for="feed_url"><?php esc_html_e( 'Enter feed URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="feed_url" id="feed_url" size="100" value="<?php esc_attr_e( $feed_url ); ?>" />
		</p>
		<p>
			<label for="default_post_type"><?php esc_html_e( 'Select post type', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_type" id="default_post_type">
			<?php
			$post_types = get_post_types();

			foreach ( $post_types as $post_type ) {
				?>
				<option value="<?php esc_attr_e( $post_type ); ?>" <?php selected( $post_type, $default_post_type ); ?>><?php esc_html_e( $post_type ); ?></option>
			<?php } ?>
			</select>
		</p>
		<p>
			<label for="default_post_status"><?php esc_html_e( 'Select post status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_status" id="default_post_status">
			<?php
			$post_statuses = get_post_statuses();

			foreach ( $post_statuses as $key => $value ) {
				?>
				<option value="<?php esc_attr_e( $key ); ?>" <?php selected( $key, $default_post_status ); ?>><?php esc_html_e( $key ); ?></option>
			<?php } ?>
			</select>
		</p>
		<p>
			<label for="default_comment_status"><?php esc_html_e( 'Select comment status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_comment_status" id="default_comment_status">
			<option value="open" <?php selected( 'open', $default_comment_status ); ?>><?php _e( 'open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_comment_status ); ?>><?php _e( 'closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php esc_html_e( 'Select ping status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status">
			<option value="open" <?php selected( 'open', $default_ping_status ); ?>><?php _e( 'open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_ping_status ); ?>><?php _e( 'closed', 'push-syndication' ); ?></option>
			</select>
		</p>

		<p>
			<label for="namespace"><?php esc_html_e( 'Enter XML namespace', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" size="75" name="namespace" id="namespace" value="<?php esc_attr_e( $namespace ); ?>" />
		</p>

		<p>
			<label for="post_root"><?php esc_html_e( 'Enter XPath to post root', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="post_root" id="post_root" value="<?php esc_attr_e( $post_root ); ?>" />
		</p>

		<p>
			<label for="id_field"><?php esc_html_e( 'Enter post meta key for unique post identifier', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="id_field" id="id_field" value="<?php esc_attr_e( $id_field ); ?>" />
		</p>

		<p>
			<label for="enc_parent"><?php esc_html_e( 'Enter parent element for enclosures', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="enc_parent" id="enc_parent" value="<?php esc_attr_e( $enc_parent ); ?>" />
		</p>

		<p>
			<label for="enc_field"><?php esc_html_e( 'Enter meta name for enclosures', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="enc_field" id="enc_field" value="<?php esc_attr_e( $enc_field ); ?>" />
		</p>

		<p>
			<label for="enc_is_photo">
				<input type="checkbox" name="enc_is_photo" id="enc_is_photo" value="1" <?php checked( $enc_is_photo ); ?> />
				<?php esc_html_e( 'Enclosure is an image file', 'push-syndication' ); ?>
			</label>
		</p>

		<p>
			<label for="categories"><?php esc_html_e( 'Select category/categories', 'push-syndication' ); ?></label>

		</p>
		<p>
			<?php
				add_filter( 'wp_dropdown_cats', array( __CLASS__, 'make_multiple_categories_dropdown' ) );
				wp_dropdown_categories( array(
						'hide_empty' => false,
						'hierarchical' => true,
						'selected_array' => $categories,
						'walker' => new Walker_CategoryDropdownMultiple,
						'name' => 'categories[]'
				) );
				remove_filter( 'wp_dropdown_cats', array( __CLASS__, 'make_multiple_categories_dropdown' ) );
			?>
		</p>

		<h2><?php _e( 'XPath-to-Data Mapping', 'push-syndication' ); ?></h2>

		<p><?php printf( __( '<strong>PLEASE NOTE:</strong> %s are required. If you want a link to another site, %s is required. To include a static string, enclose the string as "%s(your_string_here)" &mdash; no quotes.', 'push-syndication' ), 'post_title, post_guid, guid', 'is_permalink', 'string' ); ?></p>

		<ul class='syn-xml-client-xpath-head syn-xml-client-list-head'>
			<li class="text">
				<label for="xpath"><?php esc_html_e( 'XPath Expression', 'push-syndication' ); ?></label>
			</li>
			<li>
				<label for="item_node"><?php esc_html_e( 'Item', 'push-syndication' ); ?></label>
			</li>
			<li>
				<label for="photo_node"><?php esc_html_e( 'Enc.', 'push-syndication' ); ?></label>
			</li>
			<li>
				<label for="meta_node"><?php esc_html_e( 'Meta', 'push-syndication' ); ?></label>
			</li>
			<li>
				<label for="tax_node"><?php esc_html_e( 'Tax', 'push-syndication' ); ?></label>
			</li>
			<li class="text">
				<label for="item_field"><?php esc_html_e( 'Field in Post', 'push-syndication' ); ?></label>
			</li>
		</ul>

		<?php
		$rowcount = 0;
		if ( ! empty( $custom_nodes ) ) :
			foreach ( $custom_nodes as $key => $storage_locations ) :
				foreach ( $storage_locations as $storage_location ) : ?>
					<ul class='syn-xml-client-xpath-form syn-xml-client-xpath-list syn-xml-client-list' data-row-count="<?php echo (int) $rowcount; ?>">
					<li class="text">
						<input type="text" name="node[<?php echo (int) $rowcount; ?>][xpath]" id="node-<?php echo (int) $rowcount; ?>-xpath" value="<?php echo htmlspecialchars( stripslashes( $key ) ); ?>" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_item]" id="node-<?php echo (int) $rowcount; ?>-is_item" <?php checked( $storage_location['is_item'] ); ?> value="true" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_photo]" id="node-<?php echo (int) $rowcount; ?>-is_photo" <?php checked( $storage_location['is_photo'] ); ?> value="true" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_meta]" id="node-<?php echo (int) $rowcount; ?>-is_meta" <?php checked( $storage_location['is_meta'] ); ?> value="true" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_tax]" id="node-<?php echo (int) $rowcount; ?>-is_tax" <?php checked( $storage_location['is_tax'] ); ?> value="true" />
					</li>
					<li class="text">
						<input type="text" name="node[<?php echo (int) $rowcount; ?>][field]" id="node-<?php echo (int) $rowcount; ?>-field" value="<?php echo stripcslashes( $storage_location['field'] ); ?>" />
					</li>
					<a href="#" class="syn-delete syn-pull-xpath-delete"><?php _e( 'Delete', 'push-syndication' ); ?></a>
				<?php endforeach; ?>
				</ul>
				<?php
				++ $rowcount;
			endforeach;
		endif;
		?>

		<ul class='syn-xml-client-xpath-form syn-xml-xpath-list syn-xml-client-list' data-row-count="<?php echo (int) $rowcount; ?>">
			<li class="text">
				<input type="text" name="node[<?php echo (int) $rowcount; ?>][xpath]" id="node-<?php echo (int) $rowcount; ?>-xpath" />
			</li>
			<li>
				<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_item]" id="node-<?php echo (int) $rowcount; ?>-is_item" />
			</li>
			<li>
				<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_photo]" id="node-<?php echo (int) $rowcount; ?>-is_photo" />
			</li>
			<li>
				<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_meta]" id="node-<?php echo (int) $rowcount; ?>-is_meta" />
			</li>
			<li>
				<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][is_tax]" id="node-<?php echo (int) $rowcount; ?>-is_tax" />
			</li>
			<li class="text">
				<input type="text" name="node[<?php echo (int) $rowcount; ?>][field]" id="node-<?php echo (int) $rowcount; ?>-field" />
			</li>
			<a href="#" class="syn-delete syn-pull-xpath-delete"><?php _e( 'Delete', 'push-syndication' ); ?></a>
		</ul>

		<a href="#" class="syn-pull-xpath-add-new button"><?php _e( 'Add new', 'push-syndication' ); ?></a>

		<script>
			jQuery( document ).ready( function ( $ ) {
				$( '.syn-pull-xpath-delete' ).on( 'click', function ( e ) {
					e.preventDefault();

					$( this ).closest( '.syn-xml-client-xpath-form' ).remove();
				} );

				$( '.syn-pull-xpath-add-new' ).on( 'click', function ( e ) {
					e.preventDefault();

					var $lastForm = $( '.syn-xml-client-xpath-form:last' ),
					    $newForm = $lastForm.clone(),
					    originalRowCount = parseInt( $lastForm.attr( 'data-row-count' ) ),
					    newRowCount = originalRowCount + 1;

					$newForm.attr( 'data-row-count', newRowCount );

					$newForm.find( 'input' ).each( function () {
						var $this = $( this ),
						    name = $this.attr( 'name' ),
						    type = $this.attr( 'type' );

						if ( 'radio' === type || 'checkbox' === type ) {
							$this.attr( 'checked', false );
						}
						else if ( 'select' === type ) {
							$this.attr( 'selected', false );
						}
						else {
							$this.val( '' );
						}

						name = name.replace( '[' + ( originalRowCount ) + ']', '[' + newRowCount + ']' );
						$this.attr( 'name', name ); // hack hack hack!!!
					} );

					$newForm.insertAfter( $lastForm );
				} );
			} );
		</script>

		<?php
	}

	public function save_site_options_pull( $site_id ) {
		// TODO: adjust to save all settings required by XML feed
		// TODO: validate saved values (e.g. valid post_type? valid status?)
		// TODO: actually check if saving was successful or not and return a proper bool

		update_post_meta( $site_id, 'syn_feed_url', esc_url_raw( $_POST['feed_url'] ) );
		update_post_meta( $site_id, 'syn_default_post_type', sanitize_text_field( $_POST['default_post_type'] ) );
		update_post_meta( $site_id, 'syn_default_post_status', sanitize_text_field( $_POST['default_post_status'] ) );
		update_post_meta( $site_id, 'syn_default_comment_status', sanitize_text_field( $_POST['default_comment_status'] ) );
		update_post_meta( $site_id, 'syn_default_ping_status', sanitize_text_field( $_POST['default_ping_status'] ) );
		update_post_meta( $site_id, 'syn_id_field', sanitize_text_field( $_POST['id_field'] ) );
		update_post_meta( $site_id, 'syn_enc_field', sanitize_text_field( $_POST['enc_field'] ) );
		update_post_meta( $site_id, 'syn_enc_is_photo', isset( $_POST['enc_is_photo'] ) ? sanitize_text_field( $_POST['enc_is_photo'] ) : null );


		$node_changes = $_POST['node'];
		$node_config  = array();
		$custom_nodes = array();
		if ( isset( $node_changes ) ) {
			foreach ( $node_changes as $row ) {
				$row_data = array();

				//if no new has been added to the empty row at the end, ignore it
				if ( ! empty( $row['xpath'] ) ) {

					foreach ( array( 'is_item', 'is_meta', 'is_tax', 'is_photo' ) as $field ) {
						$row_data[ $field ] = isset( $row[ $field ] ) && in_array( $row[ $field ], array(
								'true',
								'on'
							) ) ? 1 : 0;
					}
					$xpath = html_entity_decode( $row['xpath'] );

					unset( $row['xpath'] );

					$row_data['field'] = sanitize_text_field( $row['field'] );

					if ( ! isset( $custom_nodes[ $xpath ] ) ) {
						$custom_nodes[ $xpath ] = array();
					}

					$custom_nodes[ $xpath ][] = $row_data;
				}
			}
		}

		$node_config['namespace']  = sanitize_text_field( $_POST['namespace'] );
		$node_config['post_root']  = sanitize_text_field( $_POST['post_root'] );
		$node_config['enc_parent'] = sanitize_text_field( $_POST['enc_parent'] );
		$node_config['categories'] = isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'intval', $_POST['categories'] ) : array();
		$node_config['nodes']      = $custom_nodes;
		update_post_meta( $site_id, 'syn_node_config', $node_config );
		return true;
	}

	/**
	 * Rewrite wp_dropdown_categories output to enable a multiple select
	 * @param  string $result rendered category dropdown list
	 * @return string altered category dropdown list
	 */
	public static function make_multiple_categories_dropdown( $result ) {
		$result = preg_replace( '#^<select name#', '<select multiple="multiple" name', $result );
		return $result;
	}

}
