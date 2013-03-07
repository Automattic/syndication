<?php

include_once( dirname(__FILE__) . '/interface-wp-client.php' );

class Syndication_WP_XML_Client implements Syndication_Client {

	private $site_ID;
	
	private $default_post_type;
	private $default_post_status;
	private $default_comment_status;
	private $default_ping_status;
	private $nodes_to_post;
	private $id_field;
	private $enc_field;
	private $enc_is_photo;

	private $response;
	private $error_message;
	private $error_code;

	private $feed_url;

	
	function __construct( $site_ID ) {

		$this->site_ID = $site_ID;
		$this->set_feed_url( get_post_meta( $site_ID, 'syn_feed_url', true ) );
	
		$this->default_post_type= get_post_meta( $site_ID, 'syn_default_post_type', true );
		$this->default_post_status = get_post_meta( $site_ID, 'syn_default_post_status', true );
		$this->default_comment_status = get_post_meta( $site_ID, 'syn_default_comment_status', true );
		$this->default_ping_status = get_post_meta( $site_ID, 'syn_default_ping_status', true );
		$this->nodes_to_post = get_post_meta( $site_ID, 'syn_node_config', false);
		$this->id_field = get_post_meta( $site_ID, 'syn_id_field', true);
		$this->enc_field = get_post_meta( $site_ID, 'syn_enc_field', true );
		$this->enc_is_photo = get_post_meta( $site_ID, 'syn_enc_is_photo', true);

		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_meta' ), 10, 5 );
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_tax' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_meta' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_tax' ), 10, 5 );
		add_action ( 'syn_post_pull_new_post', array(__CLASS__, 'log_new' ), 10, 5 );
		add_action ( 'syn_post_pull_edit_post', array(__CLASS__, 'log_update' ), 10, 5 );
		add_action ( 'syn_post_pull_new_post', array(__CLASS__ , 'publish_pulled_post' ), 10, 5 );
	}

	/**
	 * Return Client Data
	 * @return array array( 'id' => (string) $transport_name, 'modes' => array( 'push', 'pull' ), 'name' => (string) $name );
	 */
	private function set_feed_url($url) {
		if ( parse_url( $url ) ) {
			$this->feed_url = $url;
		} else {
			$this->error_code = 0;
			$this->error_message = sprintf( __( 'Feed url not set for this feed: %s' ), $site_ID );
		}
	}

	public static function get_client_data() {
		return array( 'id' => 'WP_XML', 'modes' => array( 'pull' ), 'name' => 'WordPress XML' );
	}

	public function new_post( $post_ID ) {
		return false; // Not supported
	}
	
	public function edit_post( $post_ID, $ext_ID ) {
		return false; // Not supported
	}

	public function delete_post( $ext_ID ) {
		return false; // Not supported
	}
	
	public function get_post( $ext_ID ) {
		return false; // Not supported
	}
	
	/**
	 * Retrieves a list of posts from a slave site.
	 *
	 * @param   array   $args  Arguments when retrieving posts.
	 *
	 * @return  boolean true on success false on failure.
	*/
	public function get_posts( $args = array()) {
		// create $post with values from $this::node_to_post
		// create $post_meta with values from $this::node_to_meta

		//TODO: required fields for post
		//TODO: handle categories

		// clear last log in feed's data
		delete_post_meta($this->site_ID, 'syn_log');

		$abs_nodes = array();
		$item_nodes = array();
		$enc_nodes = array();
		$tax_nodes = array();
		$abs_post_fields = array();
		$abs_meta_data = array();
		$abs_tax_data = array();
		$posts = array();
		$nodes = $this->nodes_to_post[0];
		$post_root = $nodes['post_root'];
		unset($nodes['post_root']);
		$namespace = isset($nodes['namespace']) ? $nodes['namespace'] : null;
		unset($nodes['namespace']);
		$enc_parent = $nodes['enc_parent'];
		unset($nodes['enc_parent']);
		$enc_field = isset( $this->enc_field ) ? $this->enc_field : null;
		$categories = (array) $nodes['categories'];
		unset($nodes['categories']);
		$enclosures_as_strings = isset($nodes['enclosures_as_strings']) ? true : false;
		unset($nodes['enclosures_as_strings']);

		//TODO: add checkbox on feed config to allow enclosures to be saved as strings as SI does
		//TODO: add tags here and in feed set up UI
		foreach( $nodes as $key => $storage_locations) {
			foreach ($storage_locations as $storage_location) {
				$storage_location['xpath'] = $key;
				if ($storage_location['is_item']) {
					$item_nodes[] = $storage_location;
				} else if ($storage_location['is_photo']) {
					$enc_nodes[] = $storage_location;
				} else if ( $storage_location['is_tax'] && $storage_location['is_item'] ) {
					$tax_nodes[] = $storage_location;
				} else {
					$abs_nodes[] = $storage_location;
				}
			}
		}

		// TODO: kill feed client if too many failures
		$request = wp_remote_get( $this->feed_url );

		// catch attempts to pull content from a file which doesn't exist.
		if ( is_wp_error( $request ) || 200 != wp_remote_retrieve_response_message( $request ) ) {
			self::log_post( 'n/a', null, get_post($this->site_ID), "could not reach feed at url: " . $this->feed_url ); // TODO: log error
			return;
		}

		$xml_string = wp_remote_retrieve_body( $request );
		$xml = new SimpleXmlElement( $xml_string, 0, false, $namespace, false );

		$abs_post_fields['enclosures_as_strings'] = $enclosures_as_strings;

		// TODO: handle constant strings in XML
		foreach( $abs_nodes as $abs_node) {
			$value_array = array();
			try {
				if ('string(' == substr( $abs_node['xpath'], 0, 7 ) ) {
					$value_array[0] = substr( $abs_node['xpath'], 7, strlen($abs_node['xpath'])-8 );
				} else {
					$value_array = $xml->xpath(stripslashes($abs_node['xpath']));
				}
				if ($abs_node['is_meta']) {
					$abs_meta_data[$abs_node['field']] = (string)$value_array[0];
				} else if ( $abs_node['is_tax'] ) {
					$abs_tax_data[$abs_node['field']] = (string)$value_array[0];
				} else {
					$abs_post_fields[$abs_node['field']] = (string)$value_array[0];
				}
			} catch (Exception $e) {
				//TODO: catch value not found here and alert for error
				//TODO: catch multiple values returned here and alert for error
				return true;
			}
		}
		$post_position = 0;
		foreach ($xml->xpath($post_root) as $item) {
			$item_fields = array();
			$enclosures = array();
			$meta_data = array();
			$meta_data['is_update'] = current_time('mysql');
			$tax_data = array();
			$value_array = array();

			//save photos as enclosures in meta
			if ((isset($enc_parent) && strlen($enc_parent)) && ! empty( $enc_nodes )) :
				$meta_data['enclosures'] = $this->get_encs($item->xpath($enc_parent), $enc_nodes);
			endif;

			foreach ($item_nodes as $save_location ) {
				try {
					if ('string(' == substr( $save_location['xpath'], 0, 7 ) ) {
						$value_array[0] = substr( $save_location['xpath'], 7, strlen($save_location['xpath'])-8 );
					} else {
 		   				$value_array = $item->xpath( stripslashes( $save_location['xpath'] ) );
					}
					if (isset($save_location['is_meta']) && $save_location['is_meta']) {
						$meta_data[$save_location['field']] = (string)$value_array[0];
					} else if ( isset($save_location['is_tax']) && $save_location['is_tax'] ) {
						//for some taxonomies, multiple values may be supplied in the field
						foreach ( $value_array as $value ) {
							$tax_data[] = array( 'tax_name' => $save_location['field'], 'tax_value'=> ( string ) $value );
						}
					} else {
						$item_fields[$save_location['field']] = (string)$value_array[0];
					}
				} catch (Exception $e) {
					// TODO: catch value not found here and alert for error
					// TODO: catch multiple values returned here and alert for error
					return true;
				}
			}
			$meta_data = array_merge($meta_data, $abs_meta_data);
			$tax_data = array_merge($tax_data, $abs_tax_data);
			if ( !empty( $enc_field ) ) { 
				$meta_data['enc_field'] = $enc_field;
			}
			if ( !isset( $meta_data['position'] ) ) {
				$meta_data['position'] = $post_position;
	 		}
			$item_fields['postmeta'] = $meta_data;
			if ( !empty( $tax_data ) ) {
				$item_fields['tax'] = $tax_data;
			}
			$item_fields['post_category'] = $categories;
			if ( !empty( $meta_data[$this->id_field] ) ) {
				$post_guid = $this->get_guid( $item_fields ); 
				if (!empty( $post_guid ) ) { 
					$item_fields['post_guid'] = $post_guid;
				}
			}
			$posts[] = $item_fields;
			$post_position++;
		} 	
		return $posts;
		 
	}

	/**
	 * @param array $enclosures
	 * @return array
	 */
	private function get_encs($feed_enclosures = array(), $enc_nodes = array()) {
		$enclosures = array();
		foreach($feed_enclosures as $count => $enc) {
			if ( isset( $this->enc_is_photo ) && 1 == $this->enc_is_photo ) {
				$enc_array = array('caption'=>'', 'credit' => '', 'description' => '', 'url' => '', 'width' => '', 'height' => '', 'position' => '');
			} else {
				$enc_array = array();
			}
			$enc_value = array();
			foreach ($enc_nodes as $post_value) {
				try {
					if ('string(' == substr( $post_value['xpath'], 0, 7 ) ) {
						$enc_value[0] = substr( $post_value['xpath'], 7, strlen($post_value['xpath'])-8 );
					} else {
						$enc_value = $enc->xpath(stripslashes($post_value['xpath']));
					}
					$enc_array[$post_value['field']] = esc_attr((string)$enc_value[0]);
				} catch (Exception $e) {
					//TODO: catch value not found here and alert for error or not
					return true;
				}
			}
			// if position is not provided in the feed, use the order in which they appear in the feed
			if ( empty( $enc_array['position'] ) ) {
				$enc_array['position'] = $count;
			}
			$enclosures[] = $enc_array;
		}
		return $enclosures;
	}
	
	/**
	 * Test the connection with the slave site.
	 *
	 * @return  boolean  true on success false on failure.
	*/
	public function test_connection() {
		// TODO: Implement test_connection() method.
		return true;
	}
	
	/**
	 * Checks whether the given post exists in the slave site.
	 *
	 * @param   int  $ext_ID  Slave post ID to check.
	 *
	 * @return  boolean  true on success false on failure.
	*/
	public function is_post_exists( $ext_ID ) {
		// Not supported
		return false;
	}
	
	/**
	 * Get the response message sent from the slave site.
	 *
	 * @return  string  response message.
	*/
	public function get_response() {
		return $this->response;
	}
	
	/**
	 * Get the error code.
	 *
	 * @return  int  error code.
	*/
	public function get_error_code() {
		return $this->error_code;
	}
	
	/**
	 * Get the error message sent from the slave site.
	 *
	 * @return string error message.
	*/
	public function get_error_message() {
		return $this->error_message;
	}
	
	/**
	 * Display the client settings for the slave site.
	 *
	 * @param   object  $site  The site object to display settings.
	*/
	public static function display_settings( $site ) {
		//TODO: MOVE STYLES to appropriate CSS
		//TODO: JS if is_meta show text box, if is_photo show photo select with numbers as values, else show select of post fields
		//TODO: JS Validation
		//TODO: deal with ability to select, i.e. media:group/media:thumbnail[@width="75"]/@url (can't be unserialized as is with quotes around 75)
		$feed_url					= get_post_meta( $site->ID, 'syn_feed_url', true );
		$default_post_type			= get_post_meta( $site->ID, 'syn_default_post_type', true );
		$default_post_status		= get_post_meta( $site->ID, 'syn_default_post_status', true );
		$default_comment_status		= get_post_meta( $site->ID, 'syn_default_comment_status', true );
		$default_ping_status		= get_post_meta( $site->ID, 'syn_default_ping_status', true );
		$node_config				= get_post_meta( $site->ID, 'syn_node_config', true ); 
		$id_field					= get_post_meta( $site->ID, 'syn_id_field', true );
		$enc_field					= get_post_meta( $site->ID, 'syn_enc_field', true );
		$enc_is_photo				= get_post_meta( $site->ID, 'syn_enc_is_photo', true);

		if ( isset( $node_config['namespace'] )) {
			$namespace = $node_config['namespace'];
		}

		//unset is outside of isset() test to remove the item from the array if the key is there with no value
		unset( $node_config['namespace'] );
		if ( isset( $node_config['post_root'] )) {
			$post_root = $node_config['post_root'];
		}
		unset( $node_config['post_root'] );
		if ( isset( $node_config['enc_parent'] )) {
			$enc_parent = $node_config['enc_parent'];
		}
		unset( $node_config['enc_parent'] );

		if ( isset( $node_config['categories'] ) && !empty( $node_config['categories'] ) ) {
			$categories = (array) $node_config['categories'];
		}
		unset( $node_config['categories'] );

		?>
		<style type="text/css">
			ul.feed_mgr_head {
				clear: all;
				width: 100%;
			}
			ul.feed_mgr_head li {
				float: left;
				left: 200px;
				padding-right: 20px;
				top: 0;
				width: 50px;
			}
			ul.feed_mgr_head li:FIRST-CHILD {
				width: 180px;
			}
			ul.feed_mgr_head li:LAST-CHILD {
				width: 300px;
			}
			ul.feed_mgr li:FIRST-CHILD {
				clear:left;
				width: 180px;
			}
			ul.feed_mgr li:LAST-CHILD {
				width: 300px;
			}
			ul.feed_mgr li {
				float: left;
				left: 200px;
				padding-right: 20px;
				top: 0;
				width: 50px; 
			}
			ul.feed_mgr {
				
			}
			.feed_mgr_new_element {
				clear: left;
			}
			ul.feed_log_head {
				font-weight: bold;
				margin-top: 10px;
				width: 100%;
			}
			ul.feed_log_head li {
				float: left;
				left: 200px;
				padding-right: 20px;
				width: 50px;
			}
			ul.feed_log_head li.wide {
				width: 150px;
			}
			ul.feed_log_head li:LAST-CHILD {
				margin-right: 200px;
				}
			ul.feed_log {
				margin-top: 50px;
			}
			ul.feed_log li {
				float: left;
				left: 200px;
				padding-right: 20px;
				width: 50px;
			}
			ul.feed_log li:FIRST-CHILD {
				clear:left;
			}
			ul.feed_log li.wide {
				width: 150px;
			}
			.subtitle {
				font-family: sans-serif;
				font-size: 12px;
				line-height: 1.4em;
				margin-top: 80px;
				width: 100%;
				position: relative;
				left: -35px;
   			}
		</style>
		<p>
			<label for="feed_url"><?php echo esc_html__( 'Enter feed URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
		</p>
		<p>
			<label for="default_post_type"><?php echo esc_html__( 'Select post type', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_type" id="default_post_type" />

			<?php

			$post_types = get_post_types();

			foreach( $post_types as $post_type ) {
				echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_type, $default_post_type ) . '>' . esc_html( $post_type )  . '</option>';
			}

			?>

			</select>
		</p>
		<p>
			<label for="default_post_status"><?php echo esc_html__( 'Select post status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_status" id="default_post_status" />

			<?php

			$post_statuses  = get_post_statuses();

			foreach( $post_statuses as $key => $value ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $default_post_status ) . '>' . esc_html( $key )  . '</option>';
			}

			?>

			</select>
		</p>
		<p>
			<label for="default_comment_status"><?php echo esc_html__( 'Select comment status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_comment_status" id="default_comment_status" />
				<option value="open" <?php selected( 'open', $default_comment_status )  ?>>open</option>
				<option value="closed" <?php selected( 'closed', $default_comment_status )  ?>>closed</option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php echo esc_html__( 'Select ping status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status" />
			<option value="open" <?php selected( 'open', $default_ping_status )  ?> >open</option>
			<option value="closed" <?php selected( 'closed', $default_ping_status )  ?> >closed</option>
			</select>
		</p>

		<p>
			<label for="namespace"><?php echo esc_html__( 'Enter XML namespace', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" size=75 name="namespace" id="namespace" value="<?php echo esc_attr($namespace);?>" />
		</p>

		<p>
			<label for="post_root"><?php echo esc_html__( 'Enter xpath to post root', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="post_root" id="post_root" value="<?php echo esc_attr($post_root); ?>" />
		</p>
		
		<p>
			<label for="id_node"><?php echo esc_html__( 'Enter postmeta key for unique post identifier', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="id_field" id="id_field" value="<?php echo esc_attr($id_field); ?>" />
		</p>

		<p>
			<label for="enc_parent"><?php echo esc_html__( 'Enter parent element for enclosures', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="enc_parent" id="enc_parent" value="<?php echo esc_attr($enc_parent); ?>" />
		</p>

		<p>
			<label for="enc_field"><?php echo esc_html__( 'Enter meta name for enclosures', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="enc_field" id="enc_field" value="<?php echo esc_attr($enc_field); ?>" />
		</p>
		
		<p>
			<label for="enc_is_photo"><?php echo esc_html__( 'Is enclosure an image file', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="checkbox" name="enc_is_photo" id="enc_is_photo" value="1" <?php if ( $enc_is_photo ) { ?>checked="checked" <?php } ?> />
		</p>
		
		<p>
			<label for="categories"><?php echo esc_html__( 'Select category/categories', 'push-syndication' ); ?></label>

		</p>
		<p>
			<select name="categories" multiple="multiple">
		<?php $site_categories = get_categories( array( 'hide_empty' => 0 ) );
			foreach ($site_categories as $category) { 
				if ( isset( $categories ) ) {
					$selected = in_array( $category->cat_ID, $categories ) ? 'selected="selected"' : null;
				}
				$option = '<option value="'.$category->cat_ID.'" ' . $selected . '>';
				$option .= $category->category_nicename;
				$option .= '</option>';
				echo $option;
			}
		?>
			</select>
		</p>
		<p><b>PLEASE NOTE:</b> post_title, post_guid, guid required. if you want a link to another site, is_permalink required. To include a static string, enclose the string as string(your_string_here) -- no quotes.</p>
		
		<ul class='feed_mgr_head'>
			<li>
				<label for="xpath"><?php echo esc_html__( 'Xpath Expression')?></label>
			</li>
			<li>
				<label for="item_node"><?php echo esc_html__( 'Item')?></label>
			</li>
			<li>
				<label for="photo_node"><?php echo esc_html__( 'Enc.')?></label>
			</li>
			<li>
				<label for="meta_node"><?php echo esc_html__( 'Meta')?></label>
			</li>
			<li>
				<label for="tax_node"><?php echo esc_html__( 'Tax')?></label>
			</li>
			<li>
				<label for="item_field"><?php echo esc_html__( 'Field in post')?></label>
			</li>
		</ul>
			
		<?php 
		$rowcount = 0; 
		if ( !empty( $node_config ) ) {
			foreach ($node_config as $key => $storage_locations) {
				foreach ($storage_locations as $storage_location) { ?>
					<ul class='feed_mgr'>
						<li>
							<input type="text" name="node[<?php echo $rowcount; ?>][xpath]" id="node-<?php echo $rowcount; ?>-xpath" size="30" value="<?php echo htmlspecialchars(stripslashes($key)) ; ?>" />
						</li>
						<li>
							<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_item]" id="node-<?php echo $rowcount; ?>-is_item" <?php echo $storage_location['is_item'] ? 'checked="checked"': null ; ?> value="true" />
						</li>
						<li>
							<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_photo]" id="node-<?php echo $rowcount; ?>-is_photo" <?php echo $storage_location['is_photo'] ? 'checked="checked"': null ; ?> value="true" />
						</li>
						<li>
							<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_meta]" id="node-<?php echo $rowcount; ?>-is_meta" <?php echo $storage_location['is_meta'] ? 'checked="checked"': null ; ?> value="true" />
						</li>
						<li>
							<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_tax]" id="node-<?php echo $rowcount; ?>-is_tax" <?php echo $storage_location['is_tax'] ? 'checked="checked"': null ; ?> value="true" />
						</li>
						<li>
							<input type="text" name="node[<?php echo $rowcount; ?>][field]" id="node-<?php echo $rowcount; ?>-field" size="30" value="<?php echo stripcslashes( $storage_location['field'] ); ?>" />
						</li>
			<?php } ?>
					</ul>
				<?php
				++$rowcount;
			}
		}
		?>
			<div class='feed_mgr_new_element'>Add new element:</div> 
				<ul class='feed_mgr'>
					<li>
						<input type="text" name="node[<?php echo $rowcount; ?>][xpath]" id="node-<?php echo $rowcount; ?>-xpath" size="30" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_item]" id="node-<?php echo $rowcount; ?>-is_item" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_photo]" id="node-<?php echo $rowcount; ?>-is_photo" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_meta]" id="node-<?php echo $rowcount; ?>-is_meta" />
					</li>
					<li>
						<input type="checkbox" name="node[<?php echo $rowcount; ?>][is_tax]" id="node-<?php echo $rowcount; ?>-is_tax" />
					</li>
					<li>
						<input type="text" name="node[<?php echo $rowcount; ?>][field]" id="node-<?php echo $rowcount; ?>-field" size="30" />
					</li>
				</ul>
		<p class="subtitle"><em>Last Update</em></p>
		<?php 
			$syn_log = get_post_meta($site->ID, 'syn_log', true);
			if ( empty( $syn_log ) ) {
				return;
			}
		?>
			<ul class='feed_log_head'>
				<li>
					<label for="post_id"><?php echo esc_html__( 'Post ID')?></label>
				</li>
				<li>
					<label for="status"><?php echo esc_html__( 'Status')?></label>
				</li>
				<li class="wide">
					<label for="date_time"><?php echo esc_html__( 'Date/Time')?></label>
				</li>
				<li>
					<label for="view"><?php echo esc_html__( 'VIEW')?></label>
				</li>
			</ul>
		<?php
			foreach($syn_log as $log_row) { 
				$view_link = get_permalink($log_row['post_id']);
		?>
			<ul class='feed_log'>
				<li>
					<?php 
					if ( gettype($log_row['post_id']) == 'integer' ) {
						edit_post_link( $log_row['post_id'], null, null, $log_row['post_id'] ); 
					} else {
						echo "ERROR";
					}
					?>
				</li>
				<li>
					<?php echo $log_row['status']?>
				</li>
				<li class="wide">
					<?php echo $log_row['time']?>
				</li>
				<li>
					<a href="<?php echo $view_link?>">View</a>
				</li>
			</ul>			
	<?php 
			}
	}

	/**
	 * Save the client settings for the slave site.
	 *
	 * @param   int  $site_ID  The site ID to save settings.
	 *
	 * @return  boolean  true on success false on failure.
	*/
	public static function save_settings( $site_ID ) {
		// TODO: adjust to save all settings required by XML feed
		// TODO: validate saved values (e.g. valid post_type? valid status?)

		update_post_meta( $site_ID, 'syn_feed_url', esc_url_raw( $_POST['feed_url'] ) );
		update_post_meta( $site_ID, 'syn_default_post_type', sanitize_text_field( $_POST['default_post_type'] ) );
		update_post_meta( $site_ID, 'syn_default_post_status', sanitize_text_field( $_POST['default_post_status'] ) );
		update_post_meta( $site_ID, 'syn_default_comment_status', sanitize_text_field( $_POST['default_comment_status'] ) );
		update_post_meta( $site_ID, 'syn_default_ping_status', sanitize_text_field( $_POST['default_ping_status'] ) );
		update_post_meta( $site_ID, 'syn_id_field', sanitize_text_field( $_POST['id_field'] ) );
		update_post_meta( $site_ID, 'syn_enc_field', sanitize_text_field( $_POST['enc_field'] ) );
		update_post_meta( $site_ID, 'syn_enc_is_photo', isset( $_POST['enc_is_photo'] ) ? sanitize_text_field( $_POST['enc_is_photo'] ) : null );

		// TODO: sanitize node values
		$node_changes = $_POST['node'];
		$node_config = array();
		if (isset($node_changes)) {
			foreach($node_changes as $row) {
				//if no new has been added to the empty row at the end, ignore it 
				if (isset($row['xpath']) && strlen($row['xpath'])>0) {
					$base_array =array(
						'is_item' => false,
						'is_meta' => false,
						'is_tax'  => false,
						'is_photo'=> false);
					$xpath = html_entity_decode( $row['xpath'] );
					unset($row['xpath']);
					if ( isset( $node_config[$xpath] ) ) {
						$node_config[$xpath][] = array_merge( $base_array, $row ); 
					} else {
						$node_config[$xpath][0] = array_merge($base_array, $row);
					} 
				}
			}
		}
		
		$node_config['namespace'] = sanitize_text_field( $_POST['namespace'] );
		$node_config['post_root'] = sanitize_text_field( $_POST['post_root'] );
		$node_config['enc_parent'] = sanitize_text_field( $_POST['enc_parent'] );
		$node_config['categories'] = sanitize_text_field( $_POST['categories'] );
		update_post_meta( $site_ID, 'syn_node_config', $node_config);
		
		return true;
	}
	
	public static function publish_pulled_post($result, $post, $site, $transport_type, $client) {
		wp_publish_post( $result );
	}

	public static function log_new( $result, $post, $site, $transport_type, $client ) {
		self::log_post( $result, $post, $site, 'new' );
	}

	public static function log_update ($result, $post, $site, $transport_type, $client) {
		self::log_post($result, $post, $site, 'update');
	}

	public static function log_post( $post_id, $post, $site, $status ) {
		// TODO: need to limit how many log entries can be added
		$log_entry = array();
		$log_entry['post_id'] = $post_id;
		$log_entry['status'] = $status;
		if ( !empty( $post ) ) {
		$log_entry['time'] = $post['postmeta']['is_update'];
		} else {
			$log_entry['time'] = current_time('mysql');
		}
		$log = get_post_meta( $site->ID, 'syn_log', true );
		if ( empty( $log ) ) {
			$log[0] = $log_entry;
		} else {
			$log[] = $log_entry;
		}
		update_post_meta( $site->ID, 'syn_log', $log );
	}
	
	public static function save_meta ($result, $post, $site, $transport_type, $client) {
		if ( ! isset( $result ) OR ! isset( $post['postmeta'] ) ) {
			return false;
		}
		$categories = $post['post_category'];
		wp_set_post_terms($result, $categories, 'category', true);
		$metas = $post['postmeta'];
			
		//handle enclosures separately first
		$enc_field = isset( $metas['enc_field'] ) ? $metas['enc_field'] : null;
		$enclosures = isset( $metas[$enc_field] ) ? $metas[$enc_field] : null;
		if ( isset( $enclosures ) && isset ( $enc_field ) ) {
			// first remove all enclosures for the post (for updates) if any
			delete_post_meta( $result, $enc_field);
			foreach( $enclosures as $enclosure ) {
				if (defined('ENCLOSURES_AS_STRINGS') && constant('ENCLOSURES_AS_STRINGS')) {
					$enclosure = implode("\n", $enclosure);
				} else {
					//TODO: is the serialization below necessary? I suspect add_post_meta will do it automatically
					$enclosure = serialize($enclosure);
				}
				add_post_meta($result, $enc_field, $enclosure, false);
			}
	
			// now remove them from the rest of the metadata before saving the rest
			unset($metas['enclosures']);
		}
			
		foreach ($metas as $meta_key => $meta_value) {
			add_post_meta($result, $meta_key, $meta_value, true);
		}
	}
	
	public static function update_meta ($result, $post, $site, $transport_type, $client) {
		if ( !isset($result) OR !isset($post['postmeta']) ) {
			return false;
		}
		$categories = $post['post_category'];
		wp_set_post_terms($result, $categories, 'category', true);
		$metas = $post['postmeta'];
			
		// handle enclosures separately first
		$enc_field = isset( $metas['enc_field'] ) ? $metas['enc_field'] : null;
		$enclosures = isset( $metas[$enc_field] ) ? $metas[$enc_field] : null;
		if ( isset( $enclosures ) && isset( $enc_field ) ) {
			// first remove all enclosures for the post (for updates)
			delete_post_meta( $result, $enc_field);
			foreach( $enclosures as $enclosure ) {
				if (defined('ENCLOSURES_AS_STRINGS') && constant('ENCLOSURES_AS_STRINGS')) {
					$enclosure = implode("\n", $enclosure);
				} else {
					$enclosure = serialize($enclosure);
				}
				add_post_meta($result, $enc_field, $enclosure, false);
			}
	
			// now remove them from the rest of the metadata before saving the rest
			unset($metas['enclosures']);
		}
			
		foreach ($metas as $meta_key => $meta_value) {
			update_post_meta($result, $meta_key, $meta_value);
		}
	}
	
	public static function save_tax ($result, $post, $site, $transport_type, $client) { 
		if ( !isset($result ) OR !isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies = $post['tax'];
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			// post cannot be used to create new taxonomy
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			wp_set_object_terms($result, (string)$tax_value, $tax_name, true);
		}
	}
	
	public static function update_tax ($result, $post, $site, $transport_type, $client) {
		if ( !isset($result ) OR ! isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies = $post['tax'];
		$replace_tax_list = array();
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			//post cannot be used to create new taxonomy
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			if ( !in_array($tax_name, $replace_tax_list ) ) {
				//if we haven't processed this taxonomy before, replace any terms on the post with the first new one
				wp_set_object_terms($result, (string)$tax_value, $tax_name );
				$replace_tax_list[] = $tax_name; 
			} else {
				//if we've already added one term for this taxonomy, append any others
				wp_set_object_terms($result, (string)$tax_value, $tax_name, true);
			}
		}
	}
	public function get_guid( $post ) {
		global $wpdb;

		$metas = $post['postmeta'];
		$post_guid = $metas[$this->id_field];

		$query_string = $wpdb->prepare(
			"SELECT $wpdb->postmeta.post_id
			FROM $wpdb->postmeta
			WHERE $wpdb->postmeta.meta_key = %s
			AND $wpdb->postmeta.meta_value = %s
			LIMIT 10",
			$this->id_field,
			$post_guid
		);
		$existing_posts = $wpdb->get_results( $query_string, OBJECT );

		// TODO: no catch here for more than one post with the same guid
		if ( 0 < count( $existing_posts ) ) {
			$existing_post = $existing_posts[0];
			return $existing_post->post_id;
		} else {
			return null;
		}
	}
}