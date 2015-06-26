<?php

namespace Automattic\Syndication\Clients\XML_Pull;

use Automattic\Syndication\Puller;
use Automattic\Syndication\Types;

/**
 * Syndication Client: XML Pull
 *
 * Create 'syndication sites' to pull external content into your
 * WordPress install via XML. Includes XPath mapping to map incoming
 * XML data to specific post data.
 *
 * @package Automattic\Syndication\Clients\XML
 */
class Pull_Client extends Puller {

	/**
	 * @var int
	 */
	private $site_id;

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
	 * Build initial pull client object
	 *
	 * @param int $site_id
	 */
	public function __construct( $site_id ) {
		$this->site_id = $site_id;
		$this->set_feed_url( get_post_meta( $site_id, 'syn_feed_url', true ) );

		$this->default_post_type      = get_post_meta( $site_id, 'syn_default_post_type', true );
		$this->default_post_status    = get_post_meta( $site_id, 'syn_default_post_status', true );
		$this->default_comment_status = get_post_meta( $site_id, 'syn_default_comment_status', true );
		$this->default_ping_status    = get_post_meta( $site_id, 'syn_default_ping_status', true );
		$this->nodes_to_post          = get_post_meta( $site_id, 'syn_node_config', false );
		$this->id_field               = get_post_meta( $site_id, 'syn_id_field', true );
		$this->enc_field              = get_post_meta( $site_id, 'syn_enc_field', true );
		$this->enc_is_photo           = get_post_meta( $site_id, 'syn_enc_is_photo', true );
	}

	/**
	 * Retrieves a list of posts from a remote site.
	 *
	 * @param   int $site_id The ID of the site to get posts for
	 * @return  array|bool   Array of posts on success, false on failure.
	 */
	public function get_posts( $site_id = 0 ) {
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


		// The instance construct here is fubar..
		// we're not instantiating the class for each site
		// using $this->var isn't accurate..
		$feed = $this->fetch_feed();

		// TODO: kill feed client if too many failures

		$site_post = get_post( $site_id );

		if ( is_wp_error_and_throw( $feed ) ) {
			Syndication_Logger::log_post_error( $this->site_id, $status = 'error', $message = sprintf( __( 'Could not reach feed at: %s | Error: %s', 'push-syndication' ), $this->feed_url, $feed->get_error_message() ), $log_time = $site_post->postmeta['is_update'], $extra = array() );

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_id );

			return array();
		} else {
			Syndication_Logger::log_post_info( $this->site_id, $status = 'fetch_feed', $message = sprintf( __( 'fetched feed with %d bytes', 'push-syndication' ), strlen( $feed ) ), $log_time = null, $extra = array() );
		}

		/** @var SimpleXMLElement $xml */
		$xml = simplexml_load_string( $feed, null, 0, $namespace, false );

		if ( false === $xml ) {
			Syndication_Logger::log_post_error( $this->site_id, $status = 'error', $message = sprintf( __( 'Failed to parse feed at: %s', 'push-syndication' ), $this->feed_url ), $log_time = $site_post->postmeta['is_update'], $extra = array() );

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $this->site_id );

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
				error_log( $e );

				return array();
			}
		}

		$post_position = 0;
		$items         = $xml->xpath( $post_root );

		if ( empty( $items ) ) {
			Syndication_Logger::log_post_error( $this->site_id, $status = 'error', $message = printf( __( 'No post nodes found using XPath "%s" in feed', 'push-syndication' ), $post_root ), $log_time = $site_post->postmeta['is_update'], $extra = array() );
			return array();
		} else {
			Syndication_Logger::log_post_info( $this->site_id, $status = 'simplexml_load_string', $message = sprintf( __( 'parsed feed, received %d items', 'push-syndication' ), count( $items ) ), $log_time = null, $extra = array() );
		}

		foreach ( $items as $item ) {
			$post_object = new Import_Post;

			$enclosures             = array();
			$meta_data              = array();
			$meta_data['is_update'] = current_time( 'mysql' );
			$tax_data               = array();
			$value_array            = array();
			// @todo flush out how the post is actually created
			$post_object = new Types\Post();

			$post_object->post_data['post_type'] = $this->default_post_type;

			//save photos as enclosures in meta
			if ( ( isset( $enc_parent ) && strlen( $enc_parent ) ) && ! empty( $enc_nodes ) ) {
				$meta_data['enclosures'] = $this->get_enclosures( $item->xpath( $enc_parent ), $enc_nodes );
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
						$post_object->post_data[ $save_location['field'] ] = (string) $value_array[0];
					}
				}
				catch ( Exception $e ) {
					error_log( $e );

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

			$post_object->post_meta = $meta_data;

			if ( ! empty( $tax_data ) ) {
				$post_object->post_terms = $tax_data;
			}

			$post_object->post_data['post_category'] = $categories;

			if ( ! empty( $meta_data[ $this->id_field ] ) ) {
				$post_object->post_data['post_guid'] = $meta_data[ $this->id_field ];
				$post_object->remote_id = $meta_data[ $this->id_field ];
			}

			$post_object->site_id = $this->site_id;

			$posts[] = $post_object;
			$post_position++;
		}

		Syndication_Logger::log_post_info( $this->site_id, $status = 'posts_received', $message = sprintf( __( '%d posts were prepared', 'push-syndication' ), count( $posts ) ), $log_time = null, $extra = array() );

		return $posts;

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
			$this->error_message = sprintf( __( 'Feed URL not set for this feed: %s', 'push-syndication' ), $this->site_ID );
		}
	}

	/**
	 * Get enclosures (images/attachments) from a feed.
	 *
	 * @param array $feed_enclosures Optional.
	 * @param array $enc_nodes Optional.
	 * @return array The list of enclosures in the feed.
	 */
	private function get_enclosures( $feed_enclosures = array(), $enc_nodes = array() ) {
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
					error_log( $e );

					return;
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
	 * Update post meta for the specified post.
	 *
	 * @param $post_meta
	 * $param $post
	 * $param $site_id
	 * @return mixed False if an error of if the data to save isn't passed.
	 */
	public static function update_meta( $post_meta, $post, $site_id ) {
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
}
