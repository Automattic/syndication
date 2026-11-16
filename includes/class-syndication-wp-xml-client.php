<?php
/**
 * XML feed client for content syndication.
 *
 * Client for the Syndication plugin to pull in XML feeds for consumption.
 * This was added after the initial 1.0.0 release, but no other releases
 * have been tagged since. See {@link http://git.io/WhLkGQ} for details.
 *
 * @link https://wordpress.org/plugins/push-syndication/
 * @link https://github.com/Automattic/syndication/
 * @since 2.2.0
 *
 * @package Syndication
 */

require_once __DIR__ . '/class-walker-category-dropdown-multiple.php';
require_once __DIR__ . '/interface-syndication-client.php';

/**
 * Class Syndication_WP_XML_Client
 *
 * Implements the Syndication_Client interface for pulling content from
 * generic XML feeds. Supports configurable node mappings for flexible
 * feed parsing.
 */
class Syndication_WP_XML_Client implements Syndication_Client {

	/**
	 * Prefix that XPath expressions use for elements in the site's XML namespace.
	 *
	 * XPath 1.0 has no default namespace, so in a feed declaring xmlns="..." an
	 * unprefixed name such as //item matches nothing; //syn:item does.
	 *
	 * @var string
	 */
	const XPATH_NAMESPACE_PREFIX = 'syn';

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
	public function __construct( $site_ID ) {
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
			// translators: %s: Site post ID.
			$this->error_message = sprintf( __( 'Feed URL not set for this feed: %s', 'push-syndication' ), $this->site_ID );
		}
	}

	/**
	 * Gets the client data to pass along.
	 *
	 * @return array
	 */
	public static function get_client_data() {
		return array(
			'id'    => 'WP_XML',
			'modes' => array( 'pull' ),
			'name'  => 'XML',
		);
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $post_ID The post ID.
	 *
	 * @return bool Always false as not supported.
	 */
	public function new_post( $post_ID ) {
		return false; // Not supported.
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $post_ID The local post ID.
	 * @param int $ext_ID  The external post ID.
	 *
	 * @return bool Always false as not supported.
	 */
	public function edit_post( $post_ID, $ext_ID ) {
		return false; // Not supported.
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $ext_ID The external post ID.
	 *
	 * @return bool Always false as not supported.
	 */
	public function delete_post( $ext_ID ) {
		return false; // Not supported.
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $ext_ID The external post ID.
	 *
	 * @return bool Always false as not supported.
	 */
	public function get_post( $ext_ID ) {
		return false; // Not supported.
	}

	/**
	 * Retrieves a list of posts from a remote site.
	 *
	 * @param   array $args Arguments when retrieving posts.
	 * @return  boolean true on success false on failure.
	 */
	public function get_posts( $args = array() ) {
		// create $post with values from $this::node_to_post.
		// create $post_meta with values from $this::node_to_meta.

		// TODO: required fields for post.
		// TODO: handle categories.

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

		// TODO: add checkbox on feed config to allow enclosures to be saved as strings as SI does.
		// TODO: add tags here and in feed set up UI.
		foreach ( $nodes['nodes'] as $key => $storage_locations ) {
			foreach ( $storage_locations as $storage_location ) {
				$storage_location['xpath'] = $key;
				if ( $storage_location['is_item'] ) {
					$item_nodes[] = $storage_location;
				} elseif ( $storage_location['is_photo'] ) {
					$enc_nodes[] = $storage_location;
				} elseif ( $storage_location['is_tax'] && $storage_location['is_item'] ) {
					$tax_nodes[] = $storage_location;
				} else {
					$abs_nodes[] = $storage_location;
				}
			}
		}

		$feed = $this->fetch_feed();

		// Catch attempts to pull content from a file which doesn't exist.
		// TODO: kill feed client if too many failures.
		if ( is_wp_error( $feed ) ) {
			// translators: 1: Feed URL, 2: Error message.
			Syndication_Logger::log_post_error( $this->site_ID, $status = 'error', $message = sprintf( __( 'Could not reach feed at: %1$s | Error: %2$s', 'push-syndication' ), $this->feed_url, $feed->get_error_message() ), $log_time = null, $extra = array() );

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_ID );

			return array();
		} else {
			$feed_size = strlen( $feed );
			Syndication_Logger::log_post_info(
				$this->site_ID, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Existing property name.
				'fetch_feed',
				sprintf(
					/* translators: %s: Size of the fetched feed, in bytes. */
					_n( 'fetched feed with %s byte', 'fetched feed with %s bytes', $feed_size, 'push-syndication' ),
					number_format_i18n( $feed_size )
				)
			);
		}

		// Enable libxml to retrieve xml errors.
		libxml_use_internal_errors( true );

		/** @var SimpleXMLElement $xml */
		$xml = simplexml_load_string( $feed, null, LIBXML_NONET, $namespace, false );

		if ( false === $xml ) {
			$xml_errors = implode( '; ', array_map( 'trim', wp_list_pluck( libxml_get_errors(), 'message' ) ) );

			// Clear libxml error buffer.
			libxml_clear_errors();

			Syndication_Logger::log_post_error(
				$this->site_ID, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Existing property name.
				'error',
				/* translators: 1: Feed URL, 2: Semicolon-separated list of XML parser errors. */
				sprintf( __( 'Failed to parse feed at %1$s. Errors: %2$s', 'push-syndication' ), $this->feed_url, $xml_errors )
			);

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_ID );

			return array();
		}

		// Track the event only once the feed has parsed, as success resets the failure count.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Existing hook and property names.
		do_action( 'push_syndication_event', 'pull_success', $this->site_ID );

		self::register_xpath_namespace( $xml, $namespace );

		// TODO: handle constant strings in XML.
		foreach ( $abs_nodes as $abs_node ) {
			$values = self::evaluate_xpath( $xml, $abs_node['xpath'] );

			if ( false === $values ) {
				$this->log_invalid_xpath( $abs_node['xpath'] );
				return array();
			}

			// A field the feed doesn't supply is left unset rather than blank.
			if ( empty( $values ) ) {
				continue;
			}

			if ( $abs_node['is_meta'] ) {
				$abs_meta_data[ $abs_node['field'] ] = $values[0];
			} elseif ( $abs_node['is_tax'] ) {
				$abs_tax_data[ $abs_node['field'] ] = $values;
			} else {
				$abs_post_fields[ $abs_node['field'] ] = $values[0];
			}
		}

		$post_position = 0;
		$items         = $xml->xpath( $post_root );

		if ( empty( $items ) ) {
			Syndication_Logger::log_post_error(
				$this->site_ID, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Existing property name.
				'error',
				/* translators: %s: XPath expression used to locate the post root. */
				sprintf( __( 'No post nodes found using XPath "%s" in feed', 'push-syndication' ), $post_root )
			);
			return array();
		} else {
			// translators: %d: Number of items found in the feed.
			Syndication_Logger::log_post_info( $this->site_ID, $status = 'simplexml_load_string', $message = sprintf( __( 'parsed feed, received %d items', 'push-syndication' ), count( $items ) ), $log_time = null, $extra = array() );
		}

		foreach ( $items as $item ) {
			self::register_xpath_namespace( $item, $namespace );

			$item_fields            = array();
			$enclosures             = array();
			$meta_data              = array();
			$meta_data['is_update'] = current_time( 'mysql' );
			$tax_data               = array();

			$item_fields['post_type'] = $this->default_post_type;

			// Pulled posts used to be force-published, so a site with no saved status keeps publishing.
			$item_fields['post_status'] = empty( $this->default_post_status ) ? 'publish' : $this->default_post_status;

			// An empty value would close comments and pings on update, so only pass saved settings.
			$item_fields += array_filter(
				array(
					'comment_status' => $this->default_comment_status,
					'ping_status'    => $this->default_ping_status,
				)
			);

			// save photos as enclosures in meta.
			if ( ( isset( $enc_parent ) && strlen( $enc_parent ) ) && ! empty( $enc_nodes ) ) {
				$feed_enclosures = $item->xpath( $enc_parent );

				if ( false === $feed_enclosures ) {
					$this->log_invalid_xpath( $enc_parent );
					return array();
				}

				$meta_data['enclosures'] = $this->get_encs( $feed_enclosures, $enc_nodes, $namespace );

				if ( false === $meta_data['enclosures'] ) {
					return array();
				}
			}

			foreach ( $item_nodes as $save_location ) {
				$values = self::evaluate_xpath( $item, $save_location['xpath'] );

				if ( false === $values ) {
					$this->log_invalid_xpath( $save_location['xpath'] );
					return array();
				}

				// A field this item doesn't supply is left unset rather than blank.
				if ( empty( $values ) ) {
					continue;
				}

				if ( isset( $save_location['is_meta'] ) && $save_location['is_meta'] ) {
					// Return a string if there's only a single value.
					$meta_data[ $save_location['field'] ] = 1 === count( $values ) ? $values[0] : $values;
				} elseif ( isset( $save_location['is_tax'] ) && $save_location['is_tax'] ) {
					// For some taxonomies, multiple values may be supplied in the field.
					$tax_data[ $save_location['field'] ] = $values;
				} else {
					$item_fields[ $save_location['field'] ] = $values[0];
				}
			}

			// Feed-level post fields apply to every item, unless the item supplies its own.
			$item_fields += $abs_post_fields;

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
			++$post_position;
		}

		// translators: %d: Number of posts prepared for import.
		Syndication_Logger::log_post_info( $this->site_ID, $status = 'posts_received', $message = sprintf( __( '%d posts were prepared', 'push-syndication' ), count( $posts ) ), $log_time = null, $extra = array() );

		return $posts;
	}

	/**
	 * Get enclosures (images/attachments) from a feed.
	 *
	 * @param array       $feed_enclosures Optional.
	 * @param array       $enc_nodes       Optional.
	 * @param string|null $xml_namespace   Optional. The site's XML namespace.
	 * @return array|false The list of enclosures in the feed, or false if an XPath expression is invalid.
	 */
	private function get_encs( $feed_enclosures = array(), $enc_nodes = array(), $xml_namespace = null ) {
		$enclosures = array();
		foreach ( $feed_enclosures as $count => $enc ) {
			self::register_xpath_namespace( $enc, $xml_namespace );

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

			foreach ( $enc_nodes as $post_value ) {
				$enc_value = self::evaluate_xpath( $enc, $post_value['xpath'] );

				if ( false === $enc_value ) {
					$this->log_invalid_xpath( $post_value['xpath'] );
					return false;
				}

				$enc_array[ $post_value['field'] ] = esc_attr( empty( $enc_value ) ? '' : $enc_value[0] );
			}
			// if position is not provided in the feed, use the order in which they appear in the feed.
			if ( empty( $enc_array['position'] ) ) {
				$enc_array['position'] = $count;
			}
			$enclosures[] = $enc_array;
		}

		return $enclosures;
	}

	/**
	 * Evaluate a node mapping's XPath expression, or unwrap its string(...) constant.
	 *
	 * SimpleXMLElement::xpath() doesn't throw: it returns false for an invalid
	 * expression and an empty array when nothing matches.
	 *
	 * @param SimpleXMLElement $context The element to evaluate the expression against.
	 * @param string           $xpath   The XPath expression, or a string(...) constant.
	 * @return string[]|false The matched values, or false if the expression is invalid.
	 */
	private static function evaluate_xpath( $context, $xpath ) {
		if ( 'string(' === substr( $xpath, 0, 7 ) ) {
			return array( substr( $xpath, 7, -1 ) );
		}

		$values = $context->xpath( stripslashes( $xpath ) );

		return false === $values ? false : array_map( 'strval', $values );
	}

	/**
	 * Log an XPath expression in the node mapping that couldn't be evaluated.
	 *
	 * @param string $xpath The invalid XPath expression.
	 */
	private function log_invalid_xpath( $xpath ) {
		// translators: %s: XPath expression from the site's node mapping.
		$message = sprintf( __( 'Invalid XPath expression "%s" in the node mapping', 'push-syndication' ), $xpath );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Existing property name.
		Syndication_Logger::log_post_error( $this->site_ID, 'error', $message );
	}

	/**
	 * Register the site's XML namespace for XPath on an element.
	 *
	 * Registrations don't carry over to the elements xpath() returns, so every
	 * context an expression is evaluated against needs its own.
	 *
	 * @param SimpleXMLElement $element       The element XPath will be evaluated against.
	 * @param string|null      $xml_namespace The site's XML namespace, if any.
	 */
	private static function register_xpath_namespace( $element, $xml_namespace ) {
		if ( ! empty( $xml_namespace ) ) {
			$element->registerXPathNamespace( self::XPATH_NAMESPACE_PREFIX, $xml_namespace );
		}
	}

	/**
	 * Test the connection with the slave site.
	 *
	 * @return bool True on success; false on failure.
	 */
	public function test_connection() {
		$feed = $this->fetch_feed();
		if ( is_wp_error( $feed ) ) {
			return false;
		}

		$use_errors = libxml_use_internal_errors( true );
		$xml        = simplexml_load_string( $feed, null, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );

		return false !== $xml;
	}

	/**
	 * Required by the interface, but not used here.
	 *
	 * @param int $ext_ID External post ID to check.
	 * @return bool
	 */
	public function is_post_exists( $ext_ID ) {
		return false; // Not supported.
	}

	/**
	 * Display the client settings for the slave site.
	 *
	 * @param   object $site The site object to display settings.
	 */
	public static function display_settings( $site ) {
		// TODO: JS if is_meta show text box, if is_photo show photo select with numbers as values, else show select of post fields.
		// TODO: JS Validation.
		// TODO: deal with ability to select, i.e. media:group/media:thumbnail[@width="75"]/@url (can't be unserialized as is with quotes around 75).
		$feed_url               = get_post_meta( $site->ID, 'syn_feed_url', true );
		$default_post_type      = get_post_meta( $site->ID, 'syn_default_post_type', true );
		$default_post_status    = get_post_meta( $site->ID, 'syn_default_post_status', true );
		$default_comment_status = get_post_meta( $site->ID, 'syn_default_comment_status', true );
		$default_ping_status    = get_post_meta( $site->ID, 'syn_default_ping_status', true );
		$node_config            = get_post_meta( $site->ID, 'syn_node_config', true );
		$id_field               = get_post_meta( $site->ID, 'syn_id_field', true );
		$enc_field              = get_post_meta( $site->ID, 'syn_enc_field', true );
		$enc_is_photo           = get_post_meta( $site->ID, 'syn_enc_is_photo', true );

		// A site with no saved node config gets '', which unset() below can't handle.
		if ( ! is_array( $node_config ) ) {
			$node_config = array();
		}

		if ( isset( $node_config['namespace'] ) ) {
			$namespace = $node_config['namespace'];
		}

		// Unset is outside of isset() test to remove the item from the array if the key is there with no value.
		$namespace = isset( $node_config['namespace'] ) ? $node_config['namespace'] : null;
		unset( $node_config['namespace'] );

		$post_root = isset( $node_config['post_root'] ) ? $node_config['post_root'] : null;
		unset( $node_config['post_root'] );

		$enc_parent = isset( $node_config['enc_parent'] ) ? $node_config['enc_parent'] : null;
		unset( $node_config['enc_parent'] );

		$categories = isset( $node_config['categories'] ) && ! empty( $node_config['categories'] ) ? (array) $node_config['categories'] : null;
		unset( $node_config['categories'] );

		$custom_nodes = isset( $node_config['nodes'] ) ? $node_config['nodes'] : array();
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
			$post_types = WP_Push_Syndication_Server::get_pull_post_types();

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
			<option value="open" <?php selected( 'open', $default_comment_status ); ?>><?php esc_html_e( 'Open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_comment_status ); ?>><?php esc_html_e( 'Closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php esc_html_e( 'Select ping status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status">
			<option value="open" <?php selected( 'open', $default_ping_status ); ?>><?php esc_html_e( 'Open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_ping_status ); ?>><?php esc_html_e( 'Closed', 'push-syndication' ); ?></option>
			</select>
		</p>

		<p>
			<label for="namespace"><?php esc_html_e( 'Enter XML namespace', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" size="75" name="namespace" id="namespace" aria-describedby="namespace-description" value="<?php echo esc_attr( $namespace ); ?>" />
		</p>
		<p class="description" id="namespace-description">
			<?php
			// translators: 1: XPath namespace prefix, 2: Example XPath expression.
			echo esc_html( sprintf( __( 'Use the %1$s: prefix for elements in this namespace, for example %2$s.', 'push-syndication' ), self::XPATH_NAMESPACE_PREFIX, '//' . self::XPATH_NAMESPACE_PREFIX . ':item' ) );
			?>
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
				wp_dropdown_categories(
					array(
						'hide_empty'     => false,
						'hierarchical'   => true,
						'selected_array' => $categories,
						'walker'         => new Walker_CategoryDropdownMultiple(),
						'name'           => 'categories[]',
						'id'             => 'categories',
					) 
				);
				remove_filter( 'wp_dropdown_cats', array( __CLASS__, 'make_multiple_categories_dropdown' ) );
			?>
		</p>

		<h3><?php esc_html_e( 'XPath-to-Data Mapping', 'push-syndication' ); ?></h3>

		<?php
		$rejected_keys = get_transient( 'syn_rejected_meta_keys_' . $site->ID );
		if ( $rejected_keys ) :
			delete_transient( 'syn_rejected_meta_keys_' . $site->ID );
			?>
			<div class="notice notice-error inline">
				<p>
					<?php
					// translators: %s: Comma-separated list of post fields and meta keys.
					echo esc_html( sprintf( __( 'These fields were not saved, because pulled content may not be written to them: %s', 'push-syndication' ), implode( ', ', $rejected_keys ) ) );
					?>
				</p>
			</div>
		<?php endif; ?>

		<p>
			<?php
			// translators: 1: Comma-separated list of required field names, 2: The is_permalink field name, 3: The string keyword used to wrap a static value.
			echo wp_kses( sprintf( __( '<strong>PLEASE NOTE:</strong> %1$s are required. If you want a link to another site, %2$s is required. To include a static string, enclose the string as "%3$s(your_string_here)" &mdash; no quotes.', 'push-syndication' ), 'post_title, post_guid, guid', 'is_permalink', 'string' ), array( 'strong' => array() ) );
			?>
		</p>

		<?php
		// One row per saved mapping, then an empty row to add a new one.
		$rows = array();
		foreach ( $custom_nodes as $key => $storage_locations ) {
			foreach ( $storage_locations as $storage_location ) {
				$rows[] = array(
					'xpath'    => wp_unslash( $key ),
					'is_item'  => $storage_location['is_item'],
					'is_photo' => $storage_location['is_photo'],
					'is_meta'  => $storage_location['is_meta'],
					'is_tax'   => $storage_location['is_tax'],
					'field'    => stripcslashes( $storage_location['field'] ),
				);
			}
		}
		$rows[] = array(
			'xpath'    => '',
			'is_item'  => false,
			'is_photo' => false,
			'is_meta'  => false,
			'is_tax'   => false,
			'field'    => '',
		);

		$checkboxes = array(
			'is_item'  => _x( 'Item', 'XPath mapping column: the XPath is relative to each feed item', 'push-syndication' ),
			'is_photo' => __( 'Enclosure', 'push-syndication' ),
			'is_meta'  => _x( 'Meta', 'XPath mapping column: store the value as a custom field', 'push-syndication' ),
			'is_tax'   => __( 'Taxonomy', 'push-syndication' ),
		);
		?>

		<table class="widefat syn-xml-client-xpath-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'XPath expression', 'push-syndication' ); ?></th>
					<?php foreach ( $checkboxes as $label ) : ?>
						<th scope="col" class="syn-xml-client-xpath-check"><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
					<th scope="col"><?php esc_html_e( 'Field in post', 'push-syndication' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'push-syndication' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $rowcount => $row ) : ?>
					<tr class="syn-xml-client-xpath-form">
						<td>
							<input type="text" class="widefat" name="node[<?php echo (int) $rowcount; ?>][xpath]" id="node-<?php echo (int) $rowcount; ?>-xpath" aria-label="<?php esc_attr_e( 'XPath expression', 'push-syndication' ); ?>" value="<?php echo esc_attr( $row['xpath'] ); ?>" />
						</td>
						<?php foreach ( $checkboxes as $name => $label ) : ?>
							<td class="syn-xml-client-xpath-check">
								<input type="checkbox" name="node[<?php echo (int) $rowcount; ?>][<?php echo esc_attr( $name ); ?>]" id="node-<?php echo (int) $rowcount; ?>-<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" value="true" <?php checked( $row[ $name ] ); ?> />
							</td>
						<?php endforeach; ?>
						<td>
							<input type="text" class="widefat" name="node[<?php echo (int) $rowcount; ?>][field]" id="node-<?php echo (int) $rowcount; ?>-field" aria-label="<?php esc_attr_e( 'Field in post', 'push-syndication' ); ?>" value="<?php echo esc_attr( $row['field'] ); ?>" />
						</td>
						<td>
							<?php // The button is named after the row's XPath, so each Delete says which mapping it removes. ?>
							<button type="button" class="button-link button-link-delete syn-pull-xpath-delete" id="node-<?php echo (int) $rowcount; ?>-delete" aria-labelledby="node-<?php echo (int) $rowcount; ?>-delete node-<?php echo (int) $rowcount; ?>-xpath"><?php esc_html_e( 'Delete', 'push-syndication' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p>
			<button type="button" class="button syn-pull-xpath-add-new"><?php esc_html_e( 'Add new', 'push-syndication' ); ?></button>
		</p>

		<script>
			jQuery( function ( $ ) {
				var $rows = $( '.syn-xml-client-xpath-table tbody' ),
					nextRow = $rows.children().length;

				// Delegated, so rows added with Add new can be deleted too.
				$rows.on( 'click', '.syn-pull-xpath-delete', function () {
					var $row = $( this ).closest( 'tr' ),
						$focus = $row.next().length ? $row.next() : $row.prev();

					// Add new copies the last row, so always keep one: empty it instead.
					if ( 1 === $rows.children().length ) {
						$row.find( 'input[type="text"]' ).val( '' );
						$row.find( 'input[type="checkbox"]' ).prop( 'checked', false );
						$focus = $row;
					} else {
						$row.remove();
					}

					// The focused button may be gone, so move focus to a row that's still there.
					$focus.find( 'input' ).first().trigger( 'focus' );
					wp.a11y.speak( '<?php echo esc_js( __( 'Mapping deleted.', 'push-syndication' ) ); ?>' );
				} );

				$( '.syn-pull-xpath-add-new' ).on( 'click', function () {
					var $row = $rows.children().last().clone(),
						index = nextRow++;

					$row.find( 'input, button' ).each( function () {
						var labelledby = this.getAttribute( 'aria-labelledby' );

						this.id = this.id.replace( /^node-\d+-/, 'node-' + index + '-' );
						if ( labelledby ) {
							this.setAttribute( 'aria-labelledby', labelledby.replace( /node-\d+-/g, 'node-' + index + '-' ) );
						}
						if ( this.name ) {
							this.name = this.name.replace( /^node\[\d+\]/, 'node[' + index + ']' );
						}
						if ( 'checkbox' === this.type ) {
							this.checked = false;
						} else if ( 'text' === this.type ) {
							this.value = '';
						}
					} );

					$rows.append( $row );
					$row.find( 'input' ).first().trigger( 'focus' );
					wp.a11y.speak( '<?php echo esc_js( __( 'Mapping added.', 'push-syndication' ) ); ?>' );
				} );
			} );
		</script>

		<?php

		do_action( 'syn_after_site_form', $site );
	}

	/**
	 * Rewrite wp_dropdown_categories output to enable a multiple select
	 *
	 * @param  string $result Rendered category dropdown list.
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
		// TODO: adjust to save all settings required by XML feed.
		// TODO: validate saved values (e.g. valid post_type? valid status?)
		// TODO: actually check if saving was successful or not and return a proper bool.

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in WP_Push_Syndication_Server::save_site_settings().
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- $site_ID is the parameter name set by Syndication_Client.
		update_post_meta( $site_ID, 'syn_feed_url', isset( $_POST['feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['feed_url'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_post_type', isset( $_POST['default_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['default_post_type'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_post_status', isset( $_POST['default_post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_post_status'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_comment_status', isset( $_POST['default_comment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_comment_status'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_ping_status', isset( $_POST['default_ping_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_ping_status'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_id_field', isset( $_POST['id_field'] ) ? sanitize_text_field( wp_unslash( $_POST['id_field'] ) ) : '' );

		// Fields that the config names but pulled data may not be written to.
		$rejected_keys = array();

		$enc_field = isset( $_POST['enc_field'] ) ? sanitize_text_field( wp_unslash( $_POST['enc_field'] ) ) : '';
		if ( '' !== $enc_field && ! self::is_allowed_meta_key( $enc_field ) ) {
			$rejected_keys[] = $enc_field;
			$enc_field       = '';
		}
		update_post_meta( $site_ID, 'syn_enc_field', $enc_field );
		update_post_meta( $site_ID, 'syn_enc_is_photo', isset( $_POST['enc_is_photo'] ) ? sanitize_text_field( wp_unslash( $_POST['enc_is_photo'] ) ) : null );

		// Each row is sanitized field by field below.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$node_changes = isset( $_POST['node'] ) && is_array( $_POST['node'] ) ? wp_unslash( $_POST['node'] ) : array();
		$node_config  = array();
		$custom_nodes = array();
		if ( ! empty( $node_changes ) ) {
			foreach ( $node_changes as $row ) {
				$row_data = array();

				// if no new has been added to the empty row at the end, ignore it.
				if ( ! empty( $row['xpath'] ) ) {
					foreach ( array( 'is_item', 'is_meta', 'is_tax', 'is_photo' ) as $field ) {
						$row_data[ $field ] = isset( $row[ $field ] ) && in_array(
							$row[ $field ],
							array(
								'true',
								'on',
							) 
						) ? 1 : 0;
					}
					$xpath = html_entity_decode( $row['xpath'] );

					unset( $row['xpath'] );

					$row_data['field'] = sanitize_text_field( $row['field'] );

					// pull_content() also strips ID and import_id, which would pick the local post written to.
					$is_disallowed = $row_data['is_meta']
						? '' !== $row_data['field'] && ! self::is_allowed_meta_key( $row_data['field'] )
						: in_array( $row_data['field'], array( 'ID', 'import_id' ), true );

					if ( $is_disallowed ) {
						$rejected_keys[] = $row_data['field'];
						continue;
					}

					if ( ! isset( $custom_nodes[ $xpath ] ) ) {
						$custom_nodes[ $xpath ] = array();
					}

					$custom_nodes[ $xpath ][] = $row_data;
				}
			}
		}

		$node_config['namespace']  = isset( $_POST['namespace'] ) ? sanitize_text_field( wp_unslash( $_POST['namespace'] ) ) : '';
		$node_config['post_root']  = isset( $_POST['post_root'] ) ? sanitize_text_field( wp_unslash( $_POST['post_root'] ) ) : '';
		$node_config['enc_parent'] = isset( $_POST['enc_parent'] ) ? sanitize_text_field( wp_unslash( $_POST['enc_parent'] ) ) : '';
		$node_config['categories'] = isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'intval', $_POST['categories'] ) : array();
		$node_config['nodes']      = $custom_nodes;
		update_post_meta( $site_ID, 'syn_node_config', $node_config );

		if ( $rejected_keys ) {
			set_transient( 'syn_rejected_meta_keys_' . $site_ID, $rejected_keys, 5 * MINUTE_IN_SECONDS );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		return true;
	}

	/**
	 * Save post meta for the specified post.
	 *
	 * @param int|WP_Error                 $result         The local post ID or error.
	 * @param array                        $post           The post data.
	 * @param WP_Post                      $site           The site post object.
	 * @param string                       $transport_type The transport type identifier.
	 * @param Syndication_Client_Interface $client         The syndication client.
	 *
	 * @return mixed|false False if an error or if the data to save isn't passed.
	 */
	public static function save_meta( $result, $post, $site, $transport_type, $client ) {
		// The pull hooks are shared, so ignore posts pulled by another client.
		if ( ! $client instanceof self ) {
			return false;
		}
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
			return false;
		}
		$categories = $post['post_category'];
		wp_set_post_terms( $result, $categories, 'category', true );
		$metas = array_filter( $post['postmeta'], array( __CLASS__, 'is_allowed_meta_key' ), ARRAY_FILTER_USE_KEY );

		// handle enclosures separately first.
		$enc_field  = isset( $metas['enc_field'] ) && self::is_allowed_meta_key( $metas['enc_field'] ) ? $metas['enc_field'] : null;
		$enclosures = isset( $metas['enclosures'] ) ? $metas['enclosures'] : null;
		if ( isset( $enclosures ) && isset( $enc_field ) ) {
			// First remove all enclosures for the post (for updates) if any.
			delete_post_meta( $result, $enc_field );
			foreach ( $enclosures as $enclosure ) {
				if ( defined( 'ENCLOSURES_AS_STRINGS' ) && constant( 'ENCLOSURES_AS_STRINGS' ) ) {
					$enclosure = implode( "\n", $enclosure );
				}
				add_post_meta( $result, $enc_field, $enclosure, false );
			}

			// now remove them from the rest of the metadata before saving the rest.
			unset( $metas['enclosures'] );
		}

		foreach ( $metas as $meta_key => $meta_value ) {
			add_post_meta( $result, $meta_key, $meta_value, true );
		}
	}

	/**
	 * Update post meta for the specified post.
	 *
	 * @param int|WP_Error                 $result         The local post ID or error.
	 * @param array                        $post           The post data.
	 * @param WP_Post                      $site           The site post object.
	 * @param string                       $transport_type The transport type identifier.
	 * @param Syndication_Client_Interface $client         The syndication client.
	 *
	 * @return mixed|false False if an error or if the data to save isn't passed.
	 */
	public static function update_meta( $result, $post, $site, $transport_type, $client ) {
		// The pull hooks are shared, so ignore posts pulled by another client.
		if ( ! $client instanceof self ) {
			return false;
		}
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
			return false;
		}
		$categories = $post['post_category'];
		wp_set_post_terms( $result, $categories, 'category', true );
		$metas = array_filter( $post['postmeta'], array( __CLASS__, 'is_allowed_meta_key' ), ARRAY_FILTER_USE_KEY );

		// handle enclosures separately first.
		$enc_field  = isset( $metas['enc_field'] ) && self::is_allowed_meta_key( $metas['enc_field'] ) ? $metas['enc_field'] : null;
		$enclosures = isset( $metas['enclosures'] ) ? $metas['enclosures'] : null;
		if ( isset( $enclosures ) && isset( $enc_field ) ) {
			// First remove all enclosures for the post (for updates).
			delete_post_meta( $result, $enc_field );
			foreach ( $enclosures as $enclosure ) {
				if ( defined( 'ENCLOSURES_AS_STRINGS' ) && constant( 'ENCLOSURES_AS_STRINGS' ) ) {
					$enclosure = implode( "\n", $enclosure );
				}
				add_post_meta( $result, $enc_field, $enclosure, false );
			}

			// now remove them from the rest of the metadata before saving the rest.
			unset( $metas['enclosures'] );
		}

		foreach ( $metas as $meta_key => $meta_value ) {
			update_post_meta( $result, $meta_key, $meta_value );
		}
	}

	/**
	 * Whether pulled feed data may be written to a post meta key.
	 *
	 * Refuses protected keys and the plugin's own syn_ and _syn_ keys. The key is
	 * checked as add_post_meta() will store it, unslashed, and keys outside printable
	 * ASCII are refused because MySQL's default collations can match them to one of
	 * those keys.
	 *
	 * @param string $meta_key Meta key named by the site's configuration.
	 * @return bool True if the key may be written.
	 */
	public static function is_allowed_meta_key( $meta_key ) {
		$meta_key = wp_unslash( (string) $meta_key );

		return '' !== $meta_key
			&& ! preg_match( '/[^\x20-\x7E]/', $meta_key )
			&& ! is_protected_meta( $meta_key, 'post' )
			&& ! preg_match( '/^_?syn_/i', $meta_key );
	}

	/**
	 * Save taxonomy terms for the specified post.
	 *
	 * @param int|WP_Error                 $result         The local post ID or error.
	 * @param array                        $post           The post data.
	 * @param WP_Post                      $site           The site post object.
	 * @param string                       $transport_type The transport type identifier.
	 * @param Syndication_Client_Interface $client         The syndication client.
	 *
	 * @return mixed|false False if an error or if the data to save isn't passed.
	 */
	public static function save_tax( $result, $post, $site, $transport_type, $client ) {
		// The pull hooks are shared, so ignore posts pulled by another client.
		if ( ! $client instanceof self ) {
			return false;
		}
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies = $post['tax'];
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			// post cannot be used to create new taxonomy.
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			wp_set_object_terms( $result, array_map( 'strval', (array) $tax_value ), $tax_name, true );
		}
	}

	/**
	 * Update taxonomy terms for the specified post.
	 *
	 * @param int|WP_Error                 $result         The local post ID or error.
	 * @param array                        $post           The post data.
	 * @param WP_Post                      $site           The site post object.
	 * @param string                       $transport_type The transport type identifier.
	 * @param Syndication_Client_Interface $client         The syndication client.
	 *
	 * @return mixed|false False if an error or if the data to save isn't passed.
	 */
	public static function update_tax( $result, $post, $site, $transport_type, $client ) {
		// The pull hooks are shared, so ignore posts pulled by another client.
		if ( ! $client instanceof self ) {
			return false;
		}
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies       = $post['tax'];
		$replace_tax_list = array();
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			// post cannot be used to create new taxonomy.
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			if ( ! in_array( $tax_name, $replace_tax_list ) ) {
				// if we haven't processed this taxonomy before, replace any terms on the post with the first new one.
				wp_set_object_terms( $result, array_map( 'strval', (array) $tax_value ), $tax_name );
				$replace_tax_list[] = $tax_name;
			} else {
				// if we've already added one term for this taxonomy, append any others.
				wp_set_object_terms( $result, array_map( 'strval', (array) $tax_value ), $tax_name, true );
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
			/* translators: %d: HTTP status code. */
			return new WP_Error( 'syndication-fetch-failure', sprintf( __( 'Failed to fetch XML feed; HTTP status %d.', 'push-syndication' ), wp_remote_retrieve_response_code( $request ) ) );
		}

		$body = wp_remote_retrieve_body( $request );

		// Reject any feed carrying a doctype. No syndication feed needs one, and on
		// older libxml builds an external entity declaration is resolved at parse
		// time, turning a MITM'd http:// feed into local file disclosure or SSRF.
		if ( preg_match( '/<!\s*(DOCTYPE|ENTITY)/i', $body ) ) {
			return new WP_Error( 'syndication-fetch-failure', __( 'Refused XML feed: it declares a document type.', 'push-syndication' ) );
		}

		return $body;
	}
}
