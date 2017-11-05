<?php
/**
 * The XML pull client for the Syndication plugin
 *
 * Client for the Syndication plugin to pull in XML feeds for consumption.
 * This was added after the initial 1.0.0 release, but no other releases
 * have been tagged since. See {@link http://git.io/WhLkGQ} for details.
 *
 * @link https://wordpress.org/plugins/push-syndication/
 * @link https://github.com/Automattic/syndication/
 * @since 2.1.0
 *
 * @package WordPress
 * @subpackage Syndication
 */

/**
 * Load the {@see Walker_CategoryDropdownMultiple}
 */
include_once( dirname( __FILE__ ) . '/class-walker-category-dropdown-multiple.php' );

/**
 * Load the {@see Syndication_Client} interface.
 */
include_once( dirname( __FILE__ ) . '/interface-syndication-client.php' );

/**
 * Class Syndication_WP_XML_Client
 */
class Syndication_WP_XML_Client implements Syndication_Client {

	/**
	 * @var int
	 */
	private $site_ID;

	/**
	 * @var string
	 */
	private $default_post_type;

	/**
	 * @var string
	 */
	private $default_post_status;

	/**
	 * @var string
	 */
	private $default_comment_status;

	/**
	 * @var string
	 */
	private $default_ping_status;

	/**
	 * @var array
	 */
	private $nodes_to_post;

	/**
	 * @var mixed
	 */
	private $id_field;

	/**
	 * @var mixed
	 */
	private $enc_field;

	/**
	 * @var bool
	 */
	private $enc_is_photo;

	/**
	 * @var string
	 */
	private $feed_url;

	/**
	 * @var string
	 */
	private $error_message = '';

	/**
	 * Class constructor.
	 *
	 * @param int $site_ID The ID of the site to pull feeds from.
	 */
	function __construct( $site_ID ) {
		$this->site_ID = $site_ID;
		$this->set_feed_url( get_post_meta( $site_ID, 'syn_feed_url', true ) );

		$this->default_post_type      = get_post_meta( $site_ID, 'syn_default_post_type', true );
		$this->default_post_status    = get_post_meta( $site_ID, 'syn_default_post_status', true );
		$this->default_comment_status = get_post_meta( $site_ID, 'syn_default_comment_status', true );
		$this->default_ping_status    = get_post_meta( $site_ID, 'syn_default_ping_status', true );
		$this->nodes_to_post          = get_post_meta( $site_ID, 'syn_node_config', false );
		$this->id_field               = get_post_meta( $site_ID, 'syn_id_field', true );
		$this->enc_field              = get_post_meta( $site_ID, 'syn_enc_field', true );
		$this->enc_is_photo           = get_post_meta( $site_ID, 'syn_enc_is_photo', true );

		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_meta' ), 10, 5 );
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_tax' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_meta' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_tax' ), 10, 5 );
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'publish_pulled_post' ), 10, 5 );
	}

	/**
	 * Sets the URL of the external feed to pull from.
	 *
	 * @param string $url The URL of the feed to pull.
	 */
	private function set_feed_url( $url ) {

		$url = apply_filters( 'syn_feed_url', $url );

		if ( parse_url( $url ) ) {
			$this->feed_url = $url;
		} else {
			$this->error_message = sprintf( esc_html__( 'Feed URL not set for this feed: %s', 'push-syndication' ), $this->site_ID );
		}
	}

	/**
	 * Gets the client data to pass along.
	 *
	 * @return array
	 */
	public static function get_client_data() {
		return array( 'id' => 'WP_XML', 'modes' => array( 'pull' ), 'name' => 'XML' );
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $post_ID
	 * @return bool
	 */
	public function new_post( $post_ID ) {
		return false; // Not supported
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $post_ID
	 * @param int $ext_ID
	 * @return bool
	 */
	public function edit_post( $post_ID, $ext_ID ) {
		return false; // Not supported
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $ext_ID
	 * @return bool
	 */
	public function delete_post( $ext_ID ) {
		return false; // Not supported
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $ext_ID
	 * @return bool
	 */
	public function get_post( $ext_ID ) {
		return false; // Not supported
	}

	/**
	 * Retrieves a list of posts from a remote site.
	 *
	 * @param   array $args Arguments when retrieving posts.
	 * @return  boolean true on success false on failure.
	 */
	public function get_posts( $args = array() ) {
		// create $post with values from $this::node_to_post
		// create $post_meta with values from $this::node_to_meta

		//TODO: required fields for post
		//TODO: handle categories

		$abs_nodes       = array();
		$item_nodes      = array();
		$enc_nodes       = array();
		$tax_nodes       = array();
		$abs_post_fields = array();
		$abs_meta_data   = array();
		$abs_tax_data    = array();
		$posts           = array();

		$nodes     = $this->nodes_to_post[0];
		$post_root = $nodes['post_root'];
		unset( $nodes['post_root'] );

		$namespace = isset( $nodes['namespace'] ) ? $nodes['namespace'] : null;
		unset( $nodes['namespace'] );

		$enc_parent = $nodes['enc_parent'];
		unset( $nodes['enc_parent'] );

		$enc_field = isset( $this->enc_field ) ? $this->enc_field : null;

		$categories = (array) $nodes['categories'];
		unset( $nodes['categories'] );

		$enclosures_as_strings = isset( $nodes['enclosures_as_strings'] ) ? true : false;
		unset( $nodes['enclosures_as_strings'] );

		//TODO: add checkbox on feed config to allow enclosures to be saved as strings as SI does
		//TODO: add tags here and in feed set up UI
		foreach ( $nodes['nodes'] as $key => $storage_locations ) {
			foreach ( $storage_locations as $storage_location ) {
				$storage_location['xpath'] = $key;
				if ( $storage_location['is_item'] ) {
					$item_nodes[] = $storage_location;
				} else if ( $storage_location['is_photo'] ) {
					$enc_nodes[] = $storage_location;
				} else if ( $storage_location['is_tax'] && $storage_location['is_item'] ) {
					$tax_nodes[] = $storage_location;
				} else {
					$abs_nodes[] = $storage_location;
				}
			}
		}

		$feed = $this->fetch_feed();

		// Catch attempts to pull content from a file which doesn't exist.
		// TODO: kill feed client if too many failures
		$site_post = get_post( $this->site_ID );
		if ( is_wp_error( $feed ) ) {
			Syndication_Logger::log_post_error(
				$this->site_ID,
				$status = 'error',
				$message = sprintf( esc_html__( 'Could not reach feed at: %s | Error: %s', 'push-syndication' ),
				$this->feed_url,
				$feed->get_error_message() ),
				$log_time = null,
				$extra = array()
			);

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_ID );

			return array();
		} else {
			Syndication_Logger::log_post_info(
				$this->site_ID,
				$status = 'fetch_feed',
				$message = sprintf( esc_html__( 'fetched feed with %d bytes', 'push-syndication' ), strlen( $feed ) ),
				$log_time = null,
				$extra = array()
			);

			// Track the event.
			do_action( 'push_syndication_event', 'pull_success', $this->site_ID );
		}

		$old_setting = libxml_use_internal_errors( true );
		/** @var SimpleXMLElement $xml */
		$xml = simplexml_load_string( $feed, null, 0, $namespace, false );
		libxml_use_internal_errors( $old_setting );

		if ( false === $xml ) {
			Syndication_Logger::log_post_error(
				$this->site_ID,
				$status = 'error',
				$message = sprintf( esc_html__( 'Failed to parse feed at: %s', 'push-syndication' ), $this->feed_url ),
				$log_time = $site_post->postmeta['is_update'],
				$extra = array()
			);

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_ID );

			return array();
		}

		$abs_post_fields['enclosures_as_strings'] = $enclosures_as_strings;

		// TODO: handle constant strings in XML
		foreach ( $abs_nodes as $abs_node ) {
			$value_array = array();
			try {
				if ( 'string(' == substr( $abs_node['xpath'], 0, 7 ) ) {
					$value_array[0] = substr( $abs_node['xpath'], 7, strlen( $abs_node['xpath'] ) - 8 );
				} else {
					$value_array = $xml->xpath( stripslashes( $abs_node['xpath'] ) );
				}

				if ( $abs_node['is_meta'] ) {
					$abs_meta_data[ $abs_node['field'] ] = (string) $value_array[0];
				} else if ( $abs_node['is_tax'] ) {
					$abs_tax_data[ $abs_node['field'] ] = (string) $value_array[0];
				} else {
					$abs_post_fields[ $abs_node['field'] ] = (string) $value_array[0];
				}
			}
			catch ( Exception $e ) {
				//TODO: catch value not found here and alert for error
				//TODO: catch multiple values returned here and alert for error
				return array();
			}
		}

		$post_position = 0;
		$items         = $xml->xpath( $post_root );

		if ( empty( $items ) ) {
			Syndication_Logger::log_post_error(
				$this->site_ID,
				$status = 'error',
				$message = printf( esc_html__( 'No post nodes found using XPath "%s" in feed', 'push-syndication' ), $post_root ),
				$log_time = $site_post->postmeta['is_update'],
				$extra = array()
			);
			return array();
		} else {
			Syndication_Logger::log_post_info(
				$this->site_ID,
				$status = 'simplexml_load_string',
				$message = sprintf( esc_html__( 'parsed feed, received %d items', 'push-syndication' ), count( $items ) ),
				$log_time = null,
				$extra = array()
			);
		}

		foreach ( $items as $item ) {
			$item_fields            = array();
			$enclosures             = array();
			$meta_data              = array();
			$meta_data['is_update'] = current_time( 'mysql' );
			$tax_data               = array();
			$value_array            = array();

			$item_fields['post_type'] = $this->default_post_type;

			//save photos as enclosures in meta
			if ( ( isset( $enc_parent ) && strlen( $enc_parent ) ) && ! empty( $enc_nodes ) ) {
				$meta_data['enclosures'] = $this->get_encs( $item->xpath( $enc_parent ), $enc_nodes );
			}

			foreach ( $item_nodes as $save_location ) {
				try {
					if ( 'string(' == substr( $save_location['xpath'], 0, 7 ) ) {
						$value_array[0] = substr( $save_location['xpath'], 7, strlen( $save_location['xpath'] ) - 8 );
					} else {
						$value_array = $item->xpath( stripslashes( $save_location['xpath'] ) );
					}
					if ( isset( $save_location['is_meta'] ) && $save_location['is_meta'] ) {
						//SimpleXMLElement::xpath returns either an array or false if an element isn't returned
						//checking $value_array first avoids the warning we get if the field isn't found
						if ( $value_array && ( count( $value_array ) > 1 ) ) {
							$value_array = array_map( 'strval', $value_array );
							$meta_data[ $save_location['field'] ] = $value_array;
						} else if ( $value_array ) {
							//return a string if $value_array contains only a single element
							$meta_data[ $save_location['field'] ] = (string) $value_array[0];
						}
					} else if ( isset( $save_location['is_tax'] ) && $save_location['is_tax'] ) {
						//for some taxonomies, multiple values may be supplied in the field
						foreach ( $value_array as $value ) {
							$tax_data[ $save_location['field'] ] = (string) $value;
						}
					} else {
						$item_fields[ $save_location['field'] ] = (string) $value_array[0];
					}
				}
				catch ( Exception $e ) {
					// TODO: catch value not found here and alert for error
					// TODO: catch multiple values returned here and alert for error
					return array();
				}
			}

			$meta_data = array_merge( $meta_data, $abs_meta_data );
			$tax_data  = array_merge( $tax_data, $abs_tax_data );

			if ( ! empty( $enc_field ) ) {
				$meta_data['enc_field'] = $enc_field;
			}

			if ( ! isset( $meta_data['position'] ) ) {
				$meta_data['position'] = $post_position;
			}

			$item_fields['postmeta'] = $meta_data;

			if ( ! empty( $tax_data ) ) {
				$item_fields['tax'] = $tax_data;
			}

			$item_fields['post_category'] = $categories;

			if ( ! empty( $meta_data[ $this->id_field ] ) ) {
				$item_fields['post_guid'] = $meta_data[ $this->id_field ];
			}

			$posts[] = $item_fields;
			$post_position++;
		}

		Syndication_Logger::log_post_info(
			$this->site_ID,
			$status = 'posts_received',
			$message = sprintf( esc_html__( '%d posts were prepared', 'push-syndication' ), count( $posts ) ),
			$log_time = null,
			$extra = array()
		);

		return $posts;

	}

	/**
	 * Get enclosures (images/attachments) from a feed.
	 *
	 * @param array $feed_enclosures Optional.
	 * @param array $enc_nodes Optional.
	 * @return array The list of enclosures in the feed.
	 */
	private function get_encs( $feed_enclosures = array(), $enc_nodes = array() ) {
		$enclosures = array();
		foreach ( $feed_enclosures as $count => $enc ) {
			if ( isset( $this->enc_is_photo ) && 1 == $this->enc_is_photo ) {
				$enc_array = array(
					'caption'     => '',
					'credit'      => '',
					'description' => '',
					'url'         => '',
					'width'       => '',
					'height'      => '',
					'position'    => '',
				);
			} else {
				$enc_array = array();
			}

			$enc_value = array();

			foreach ( $enc_nodes as $post_value ) {
				try {
					if ( 'string(' == substr( $post_value['xpath'], 0, 7 ) ) {
						$enc_value[0] = substr( $post_value['xpath'], 7, strlen( $post_value['xpath'] ) - 8 );
					} else {
						$enc_value = $enc->xpath( stripslashes( $post_value['xpath'] ) );
					}
					$enc_array[ $post_value['field'] ] = esc_attr( (string) $enc_value[0] );
				}
				catch ( Exception $e ) {
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
	 * @return bool True on success; false on failure.
	 */
	public function test_connection() {
		return ! is_wp_error( $this->fetch_feed() );
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $ext_ID External post ID to check.
	 * @return bool
	 */
	public function is_post_exists( $ext_ID ) {
		return false; // Not supported
	}

	/**
	 * Display the client settings for the slave site.
	 *
	 * @param   object $site The site object to display settings.
	 */
	public static function display_settings( $site ) {
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
			<input type="text" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
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
				<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( $post_type, $default_post_type ); ?>><?php echo esc_html( $post_type ); ?></option>
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
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $default_post_status ); ?>><?php echo esc_html( $key ); ?></option>
			<?php } ?>
			</select>
		</p>
		<p>
			<label for="default_comment_status"><?php esc_html_e( 'Select comment status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_comment_status" id="default_comment_status">
			<option value="open" <?php selected( 'open', $default_comment_status ); ?>><?php esc_html_e( 'open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_comment_status ); ?>><?php esc_html_e( 'closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php esc_html_e( 'Select ping status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status">
			<option value="open" <?php selected( 'open', $default_ping_status ); ?>><?php esc_html_e( 'open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_ping_status ); ?>><?php esc_html_e( 'closed', 'push-syndication' ); ?></option>
			</select>
		</p>

		<p>
			<label for="namespace"><?php esc_html_e( 'Enter XML namespace', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" size="75" name="namespace" id="namespace" value="<?php echo esc_attr( $namespace ); ?>" />
		</p>

		<p>
			<label for="post_root"><?php esc_html_e( 'Enter XPath to post root', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="post_root" id="post_root" value="<?php echo esc_attr( $post_root ); ?>" />
		</p>

		<p>
			<label for="id_field"><?php esc_html_e( 'Enter post meta key for unique post identifier', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="id_field" id="id_field" value="<?php echo esc_attr( $id_field ); ?>" />
		</p>

		<p>
			<label for="enc_parent"><?php esc_html_e( 'Enter parent element for enclosures', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="enc_parent" id="enc_parent" value="<?php echo esc_attr( $enc_parent ); ?>" />
		</p>

		<p>
			<label for="enc_field"><?php esc_html_e( 'Enter meta name for enclosures', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" name="enc_field" id="enc_field" value="<?php echo esc_attr( $enc_field ); ?>" />
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

		<h2><?php esc_html_e( 'XPath-to-Data Mapping', 'push-syndication' ); ?></h2>

		<p><?php printf( esc_html__( '<strong>PLEASE NOTE:</strong> %s are required. If you want a link to another site, %s is required. To include a static string, enclose the string as "%s(your_string_here)" &mdash; no quotes.', 'push-syndication' ), 'post_title, post_guid, guid', 'is_permalink', 'string' ); ?></p>

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
						<input type="text" name="node[<?php echo (int) $rowcount; ?>][xpath]" id="node-<?php echo (int) $rowcount; ?>-xpath" value="<?php echo esc_attr( $key ); ?>" />
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
					<a href="#" class="syn-delete syn-pull-xpath-delete"><?php esc_html_e( 'Delete', 'push-syndication' ); ?></a>
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
			<a href="#" class="syn-delete syn-pull-xpath-delete"><?php esc_html_e( 'Delete', 'push-syndication' ); ?></a>
		</ul>

		<a href="#" class="syn-pull-xpath-add-new button"><?php esc_html_e( 'Add new', 'push-syndication' ); ?></a>

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

		do_action( 'syn_after_site_form', $site );
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

	/**
	 * Save the client settings for the remote site.
	 *
	 * @param   int $site_ID The site ID to save settings.
	 * @return  bool True on success; false on failure.
	 */
	public static function save_settings( $site_ID ) {
		// TODO: adjust to save all settings required by XML feed
		// TODO: validate saved values (e.g. valid post_type? valid status?)
		// TODO: actually check if saving was successful or not and return a proper bool

		update_post_meta( $site_ID, 'syn_feed_url', esc_url_raw( $_POST['feed_url'] ) );
		update_post_meta( $site_ID, 'syn_default_post_type', sanitize_text_field( $_POST['default_post_type'] ) );
		update_post_meta( $site_ID, 'syn_default_post_status', sanitize_text_field( $_POST['default_post_status'] ) );
		update_post_meta( $site_ID, 'syn_default_comment_status', sanitize_text_field( $_POST['default_comment_status'] ) );
		update_post_meta( $site_ID, 'syn_default_ping_status', sanitize_text_field( $_POST['default_ping_status'] ) );
		update_post_meta( $site_ID, 'syn_id_field', sanitize_text_field( $_POST['id_field'] ) );
		update_post_meta( $site_ID, 'syn_enc_field', sanitize_text_field( $_POST['enc_field'] ) );
		update_post_meta( $site_ID, 'syn_enc_is_photo', isset( $_POST['enc_is_photo'] ) ? sanitize_text_field( $_POST['enc_is_photo'] ) : null );


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
		$node_config['categories'] = is_array( $_POST['categories'] ) ? array_map( 'intval', $_POST['categories'] ) : array();
		$node_config['nodes']      = $custom_nodes;
		update_post_meta( $site_ID, 'syn_node_config', $node_config );
		return true;
	}

	/**
	 * Publish the remote post to the local site.
	 *
	 * @param $result
	 * @param $post
	 * @param $site
	 * @param $transport_type
	 * @param $client
	 */
	public static function publish_pulled_post( $result, $post, $site, $transport_type, $client ) {
		wp_publish_post( $result );
	}

	/**
	 * Save post meta for the specified post.
	 *
	 * @param $result
	 * @param $post
	 * @param $site
	 * @param $transport_type
	 * @param $client
	 * @return mixed False if an error of if the data to save isn't passed.
	 */
	public static function save_meta( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
			return false;
		}
		$categories = $post['post_category'];
		wp_set_post_terms( $result, $categories, 'category', true );
		$metas = $post['postmeta'];

		//handle enclosures separately first
		$enc_field  = isset( $metas['enc_field'] ) ? $metas['enc_field'] : null;
		$enclosures = isset( $metas['enclosures'] ) ? $metas['enclosures'] : null;
		if ( isset( $enclosures ) && isset ( $enc_field ) ) {
			// first remove all enclosures for the post (for updates) if any
			delete_post_meta( $result, $enc_field );
			foreach ( $enclosures as $enclosure ) {
				if ( defined( 'ENCLOSURES_AS_STRINGS' ) && constant( 'ENCLOSURES_AS_STRINGS' ) ) {
					$enclosure = implode( "\n", $enclosure );
				}
				add_post_meta( $result, $enc_field, $enclosure, false );
			}

			// now remove them from the rest of the metadata before saving the rest
			unset( $metas['enclosures'] );
		}

		foreach ( $metas as $meta_key => $meta_value ) {
			add_post_meta( $result, $meta_key, $meta_value, true );
		}
	}

	/**
	 * Update post meta for the specified post.
	 *
	 * @param $result
	 * @param $post
	 * @param $site
	 * @param $transport_type
	 * @param $client
	 * @return mixed False if an error of if the data to save isn't passed.
	 */
	public static function update_meta( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
			return false;
		}
		$categories = $post['post_category'];
		wp_set_post_terms( $result, $categories, 'category', true );
		$metas = $post['postmeta'];

		// handle enclosures separately first
		$enc_field  = isset( $metas['enc_field'] ) ? $metas['enc_field'] : null;
		$enclosures = isset( $metas['enclosures'] ) ? $metas['enclosures'] : null;
		if ( isset( $enclosures ) && isset( $enc_field ) ) {
			// first remove all enclosures for the post (for updates)
			delete_post_meta( $result, $enc_field );
			foreach ( $enclosures as $enclosure ) {
				if ( defined( 'ENCLOSURES_AS_STRINGS' ) && constant( 'ENCLOSURES_AS_STRINGS' ) ) {
					$enclosure = implode( "\n", $enclosure );
				}
				add_post_meta( $result, $enc_field, $enclosure, false );
			}

			// now remove them from the rest of the metadata before saving the rest
			unset( $metas['enclosures'] );
		}

		foreach ( $metas as $meta_key => $meta_value ) {
			update_post_meta( $result, $meta_key, $meta_value );
		}
	}

	/**
	 * @param $result
	 * @param $post
	 * @param $site
	 * @param $transport_type
	 * @param $client
	 * @return mixed False if an error of if the data to save isn't passed.
	 */
	public static function save_tax( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies = $post['tax'];
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			// post cannot be used to create new taxonomy
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			wp_set_object_terms( $result, (string) $tax_value, $tax_name, true );
		}
	}

	/**
	 * @param $result
	 * @param $post
	 * @param $site
	 * @param $transport_type
	 * @param $client
	 * @return mixed False if an error of if the data to save isn't passed.
	 */
	public static function update_tax( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies       = $post['tax'];
		$replace_tax_list = array();
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			//post cannot be used to create new taxonomy
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			if ( ! in_array( $tax_name, $replace_tax_list ) ) {
				//if we haven't processed this taxonomy before, replace any terms on the post with the first new one
				wp_set_object_terms( $result, (string) $tax_value, $tax_name );
				$replace_tax_list[] = $tax_name;
			} else {
				//if we've already added one term for this taxonomy, append any others
				wp_set_object_terms( $result, (string) $tax_value, $tax_name, true );
			}
		}
	}

	/**
	 * Fetch a remote feed.
	 *
	 * @return string|WP_Error The content of the remote feed, or error if there's a problem.
	 */
	public function fetch_feed() {
		$request = wp_remote_get( $this->feed_url );
		if ( is_wp_error( $request ) ) {
			return $request;
		} elseif ( 200 != wp_remote_retrieve_response_code( $request ) ) {
			return new WP_Error( 'syndication-fetch-failure', 'Failed to fetch XML Feed; HTTP code: ' . wp_remote_retrieve_response_code( $request ) );
		}

		return wp_remote_retrieve_body( $request );
	}

}
