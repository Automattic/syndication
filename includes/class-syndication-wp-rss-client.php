<?php
/**
 * RSS client for content syndication.
 *
 * @package Syndication
 */

require_once ABSPATH . 'wp-includes/class-simplepie.php';
require_once __DIR__ . '/interface-syndication-client.php';

/**
 * Class Syndication_WP_RSS_Client
 *
 * Implements the Syndication_Client interface using RSS feeds for pulling
 * content from remote sites. Extends SimplePie for feed parsing.
 */
class Syndication_WP_RSS_Client extends SimplePie implements Syndication_Client {

	private $default_post_type;
	private $default_post_status;
	private $default_comment_status;
	private $default_ping_status;
	private $default_cat_status;

	private $site_ID;

	/**
	 * Constructor.
	 *
	 * @param int $site_ID The site post ID.
	 */
	public function __construct( $site_ID ) {

		parent::__construct();

		$this->site_ID = $site_ID;

		$this->set_feed_url( get_post_meta( $site_ID, 'syn_feed_url', true ) );

		// Mirrors core's fetch_feed(). SimplePie 1.3 deprecated set_cache_class()
		// in favour of a registered cache location, and WP_Feed_Cache is itself
		// deprecated in favour of WP_Feed_Cache_Transient. The old call emitted a
		// deprecation notice on every screen that lists a site, because PHP prints
		// it wherever the client happens to be constructed.
		require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
		SimplePie_Cache::register( 'wp_transient', 'WP_Feed_Cache_Transient' );
		$this->set_cache_location( 'wp_transient' );

		// Fetch through the HTTP API, as fetch_feed() does, so proxy settings, the
		// pull user agent and the HTTP API filters apply. The legacy 'File' name
		// works with every SimplePie version core has bundled since 6.4.
		require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';
		$this->registry->register( 'File', 'WP_SimplePie_File', true );

		$this->default_post_type      = get_post_meta( $site_ID, 'syn_default_post_type', true );
		$this->default_post_status    = get_post_meta( $site_ID, 'syn_default_post_status', true );
		$this->default_comment_status = get_post_meta( $site_ID, 'syn_default_comment_status', true );
		$this->default_ping_status    = get_post_meta( $site_ID, 'syn_default_ping_status', true );
		$this->default_cat_status     = get_post_meta( $site_ID, 'syn_default_cat_status', true );

		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_meta' ), 10, 5 );
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_tax' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_meta' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_tax' ), 10, 5 );
	}

	/**
	 * Fetch and parse the feed.
	 *
	 * WP_SimplePie_File uses wp_safe_remote_request(), which refuses private
	 * addresses. Feeds on an intranet have always worked, and the XML client
	 * uses wp_remote_get(), so allow them for the duration of the fetch.
	 *
	 * @return bool True if the feed was fetched and parsed.
	 */
	public function init() {
		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- Only reject_unsafe_urls changes; the timeout is untouched.
		add_filter( 'http_request_args', array( __CLASS__, 'allow_private_feed_hosts' ) );
		$result = parent::init();
		remove_filter( 'http_request_args', array( __CLASS__, 'allow_private_feed_hosts' ) );

		return $result;
	}

	/**
	 * Let a feed request reach a private address, as wp_remote_get() does.
	 *
	 * @param array $args HTTP request arguments.
	 * @return array The arguments, with unsafe URLs allowed.
	 */
	public static function allow_private_feed_hosts( $args ) {
		$args['reject_unsafe_urls'] = false;

		return $args;
	}

	/**
	 * Get the client type data.
	 *
	 * @return array Client ID, supported modes, and display name.
	 */
	public static function get_client_data() {
		return array(
			'id'    => 'WP_RSS',
			'modes' => array( 'pull' ),
			'name'  => 'RSS',
		);
	}

	/**
	 * Create a new post on the remote site.
	 *
	 * @param int $post_ID The local post ID.
	 * @return false Not supported for RSS client.
	 */
	public function new_post( $post_ID ) {
		// Not supported.
		return false;
	}

	/**
	 * Update an existing post on the remote site.
	 *
	 * @param int $post_ID The local post ID.
	 * @param int $ext_ID  The remote post ID.
	 * @return false Not supported for RSS client.
	 */
	public function edit_post( $post_ID, $ext_ID ) {
		// Not supported.
		return false;
	}

	/**
	 * Delete a post on the remote site.
	 *
	 * @param int $ext_ID The remote post ID.
	 * @return false Not supported for RSS client.
	 */
	public function delete_post( $ext_ID ) {
		// Not supported.
		return false;
	}

	/**
	 * Test the connection to the remote site.
	 *
	 * @return bool True if the feed was fetched and parsed; false otherwise.
	 */
	public function test_connection() {
		return true === $this->init();
	}

	/**
	 * Check if a post exists on the remote site.
	 *
	 * @param int $ext_ID The remote post ID.
	 * @return false Not supported for RSS client.
	 */
	public function is_post_exists( $ext_ID ) {
		// Not supported.
		return false;
	}

	/**
	 * Display the site settings form fields.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( $site ) {

		$feed_url               = get_post_meta( $site->ID, 'syn_feed_url', true );
		$default_post_type      = get_post_meta( $site->ID, 'syn_default_post_type', true );
		$default_post_status    = get_post_meta( $site->ID, 'syn_default_post_status', true );
		$default_comment_status = get_post_meta( $site->ID, 'syn_default_comment_status', true );
		$default_ping_status    = get_post_meta( $site->ID, 'syn_default_ping_status', true );
		$default_cat_status     = get_post_meta( $site->ID, 'syn_default_cat_status', true );

		?>

		<p>
			<label for="feed_url"><?php echo esc_html__( 'Enter feed URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
		</p>
		<p>
			<label for="default_post_type"><?php echo esc_html__( 'Select post type', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_type" id="default_post_type">

			<?php

			$post_types = get_post_types();

			foreach ( $post_types as $post_type ) {
				echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_type, $default_post_type ) . '>' . esc_html( $post_type ) . '</option>';
			}

			?>

			</select>
		</p>
		<p>
			<label for="default_post_status"><?php echo esc_html__( 'Select post status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_post_status" id="default_post_status">

			<?php

			$post_statuses = get_post_statuses();

			foreach ( $post_statuses as $key => $value ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $default_post_status ) . '>' . esc_html( $key ) . '</option>';
			}

			?>

			</select>
		</p>
		<p>
			<label for="default_comment_status"><?php echo esc_html__( 'Select comment status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_comment_status" id="default_comment_status">
				<option value="open" <?php selected( 'open', $default_comment_status ); ?> ><?php esc_html_e( 'Open', 'push-syndication' ); ?></option>
				<option value="closed" <?php selected( 'closed', $default_comment_status ); ?> ><?php esc_html_e( 'Closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_ping_status"><?php echo esc_html__( 'Select ping status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_ping_status" id="default_ping_status">
			<option value="open" <?php selected( 'open', $default_ping_status ); ?> ><?php esc_html_e( 'Open', 'push-syndication' ); ?></option>
			<option value="closed" <?php selected( 'closed', $default_ping_status ); ?> ><?php esc_html_e( 'Closed', 'push-syndication' ); ?></option>
			</select>
		</p>
		<p>
			<label for="default_cat_status"><?php echo esc_html__( 'Select category status', 'push-syndication' ); ?></label>
		</p>
		<p>
			<select name="default_cat_status" id="default_cat_status">
			<option value="yes" <?php selected( 'yes', $default_cat_status ); ?> ><?php echo esc_html__( 'Import categories', 'push-syndication' ); ?></option>
			<option value="no" <?php selected( 'no', $default_cat_status ); ?> ><?php echo esc_html__( 'Ignore categories', 'push-syndication' ); ?></option>
			</select>
		</p>

		<?php

		do_action( 'syn_after_site_form', $site );
	}

	/**
	 * Save the site settings from POST data.
	 *
	 * @param int $site_ID The site post ID.
	 * @return bool True on success.
	 */
	public static function save_settings( $site_ID ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WP_Push_Syndication_Server::save_site_settings() verifies the nonce before calling this.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- $site_ID is the parameter name set by Syndication_Client.
		update_post_meta( $site_ID, 'syn_feed_url', isset( $_POST['feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['feed_url'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_post_type', isset( $_POST['default_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['default_post_type'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_post_status', isset( $_POST['default_post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_post_status'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_comment_status', isset( $_POST['default_comment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_comment_status'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_ping_status', isset( $_POST['default_ping_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_ping_status'] ) ) : '' );
		update_post_meta( $site_ID, 'syn_default_cat_status', isset( $_POST['default_cat_status'] ) ? sanitize_text_field( wp_unslash( $_POST['default_cat_status'] ) ) : '' );
		// phpcs:enable
		return true;
	}

	/**
	 * Get a post from the remote site.
	 *
	 * @param int $ext_ID The remote post ID.
	 */
	public function get_post( $ext_ID ) {
		// TODO: Implement get_post() method.
	}

	/**
	 * Get posts from the RSS feed.
	 *
	 * @param array $args Optional arguments.
	 * @return array Array of post data arrays.
	 */
	public function get_posts( $args = array() ) {

		$rss_init = $this->init();

		if ( false === $rss_init ) {
			// translators: %s: Feed URL.
			Syndication_Logger::log_post_error( $this->site_ID, $status = 'error', $message = sprintf( __( 'Failed to parse feed at: %s', 'push-syndication' ), $this->feed_url ), $log_time = null, $extra = array( 'error' => $this->error() ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- site_ID predates the naming rule.

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_ID );
		} else {
			// translators: %d: Size of the fetched feed, in bytes.
			Syndication_Logger::log_post_info( $this->site_ID, $status = 'fetch_feed', $message = sprintf( __( 'fetched feed with %d bytes', 'push-syndication' ), strlen( $this->get_raw_data() ) ), $log_time = null, $extra = array() );

			// Track the event.
			do_action( 'push_syndication_event', 'pull_success', $this->site_ID );
		}

		$this->handle_content_type();

		// hold all the posts.
		$posts    = array();
		$taxonomy = array(
			'cats' => array(),
			'tags' => array(),
		);

		foreach ( $this->get_items() as $item ) {
			if ( 'yes' == $this->default_cat_status ) {
				$taxonomy = $this->set_taxonomy( $item );
			}

			$post = array(
				'post_title'     => $item->get_title(),
				'post_content'   => $item->get_content(),
				'post_excerpt'   => $item->get_description(),
				'post_type'      => $this->default_post_type,
				'post_status'    => $this->default_post_status,
				'comment_status' => $this->default_comment_status,
				'ping_status'    => $this->default_ping_status,
				'post_guid'      => $item->get_id(),
				'post_category'  => $taxonomy['cats'],
				'tags_input'     => $taxonomy['tags'],
			);

			// Without a date, leave it to WordPress: the current time for a new post,
			// and the existing date for an update.
			$post_date_gmt = $item->get_gmdate( 'Y-m-d H:i:s' );
			if ( null !== $post_date_gmt ) {
				$post['post_date_gmt'] = $post_date_gmt;
				$post['post_date']     = get_date_from_gmt( $post_date_gmt );
			}

			/**
			 * Exclude or alter posts during an RSS syndication pull.
			 *
			 * Return an array of post data to save this post. Return false to exclude
			 * this post.
			 *
			 * @param array          $post    The post data, as would be passed to wp_insert_post.
			 * @param array          $args    The arguments passed to get_posts().
			 * @param SimplePie_Item $item    A SimplePie item object.
			 * @param int            $site_id The ID of the site post holding the feed data.
			 */
			$post = apply_filters( 'syn_rss_pull_filter_post', $post, $args, $item, $this->site_ID );
			if ( false === $post ) {
				continue;
			}
			$posts[] = $post;
		}

		return $posts;
	}

	/**
	 * Set taxonomy terms from a feed item.
	 *
	 * @param SimplePie_Item $item The feed item.
	 * @return array Array of category and tag IDs.
	 */
	public function set_taxonomy( $item ) {
		// SimplePie returns null, not an empty array, for an item with no categories.
		$cats = (array) $item->get_categories();
		$ids  = array(
			'cats' => array(),
			'tags' => array(),
		);

		foreach ( $cats as $cat ) {
			// checks if term exists.
			if ( $result = get_term_by( 'name', $cat->term, 'category' ) ) {
				if ( isset( $result->term_id ) ) {
					$ids['cats'][] = $result->term_id;
				}
			} elseif ( $result = get_term_by( 'name', $cat->term, 'post_tag' ) ) {
				if ( isset( $result->term_id ) ) {
					$ids['tags'][] = $result->term_id;
				}
			} else {
				// creates if not.
				$result = wp_insert_term( $cat->term, 'category' );
				if ( ! is_wp_error( $result ) ) {
					$ids['cats'][] = $result['term_id'];
				} elseif ( 'term_exists' === $result->get_error_code() ) {
					// Created since the lookup above; the error data is its ID.
					$ids['cats'][] = (int) $result->get_error_data();
				}
			}
		}

		// returns array ready for post creation.
		return $ids;
	}

	/**
	 * Save post meta for a newly created post.
	 *
	 * @param int|WP_Error $result         The post ID or WP_Error.
	 * @param array        $post           The post data array.
	 * @param WP_Post      $site           The site post object.
	 * @param string       $transport_type The transport type.
	 * @param object       $client         The client instance.
	 * @return false|void False if conditions not met.
	 */
	public static function save_meta( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
			return false;
		}
		if ( ! empty( $post['post_category'] ) ) {
			wp_set_post_terms( $result, $post['post_category'], 'category', true );
		}
		$metas = array_filter( $post['postmeta'], array( 'Syndication_WP_XML_Client', 'is_allowed_meta_key' ), ARRAY_FILTER_USE_KEY );

		// handle enclosures separately first.
		$enc_field  = isset( $metas['enc_field'] ) && Syndication_WP_XML_Client::is_allowed_meta_key( $metas['enc_field'] ) ? $metas['enc_field'] : null;
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
	 * Update post meta for an existing post.
	 *
	 * @param int|WP_Error $result         The post ID or WP_Error.
	 * @param array        $post           The post data array.
	 * @param WP_Post      $site           The site post object.
	 * @param string       $transport_type The transport type.
	 * @param object       $client         The client instance.
	 * @return false|void False if conditions not met.
	 */
	public static function update_meta( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
			return false;
		}
		if ( ! empty( $post['post_category'] ) ) {
			wp_set_post_terms( $result, $post['post_category'], 'category', true );
		}
		$metas = array_filter( $post['postmeta'], array( 'Syndication_WP_XML_Client', 'is_allowed_meta_key' ), ARRAY_FILTER_USE_KEY );

		// handle enclosures separately first.
		$enc_field  = isset( $metas['enc_field'] ) && Syndication_WP_XML_Client::is_allowed_meta_key( $metas['enc_field'] ) ? $metas['enc_field'] : null;
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
	 * Save taxonomy terms for a newly created post.
	 *
	 * @param int|WP_Error $result         The post ID or WP_Error.
	 * @param array        $post           The post data array.
	 * @param WP_Post      $site           The site post object.
	 * @param string       $transport_type The transport type.
	 * @param object       $client         The client instance.
	 * @return false|void False if conditions not met.
	 */
	public static function save_tax( $result, $post, $site, $transport_type, $client ) {
		if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
			return false;
		}
		$taxonomies = $post['tax'];
		foreach ( $taxonomies as $tax_name => $tax_value ) {
			// post cannot be used to create new taxonomy.
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			wp_set_object_terms( $result, (string) $tax_value, $tax_name, true );
		}
	}

	/**
	 * Update taxonomy terms for an existing post.
	 *
	 * @param int|WP_Error $result         The post ID or WP_Error.
	 * @param array        $post           The post data array.
	 * @param WP_Post      $site           The site post object.
	 * @param string       $transport_type The transport type.
	 * @param object       $client         The client instance.
	 * @return false|void False if conditions not met.
	 */
	public static function update_tax( $result, $post, $site, $transport_type, $client ) {
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
				wp_set_object_terms( $result, (string) $tax_value, $tax_name );
				$replace_tax_list[] = $tax_name;
			} else {
				// if we've already added one term for this taxonomy, append any others.
				wp_set_object_terms( $result, (string) $tax_value, $tax_name, true );
			}
		}
	}
}
