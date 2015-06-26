<?php

namespace Automattic\Syndication\Clients\XML_Pull;

use Automattic\Syndication;
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
	 * Hook into WordPress
	 */
	public function __construct() {}

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

		$abs_nodes = $item_nodes = $enc_nodes = $tax_nodes = $abs_post_fields = $abs_meta_data = $abs_tax_data = $posts = [];

		$site = get_post( $site_id );
		$node_config = get_post_meta( $site->ID, 'syn_node_config', true );
		$enc_field = get_post_meta( $site->ID, 'syn_enc_field', true );
		$feed_url = apply_filters( 'syn_feed_url', get_post_meta( $site->ID, 'syn_feed_url', true ) );
		$enclosures_as_strings = isset( $node_config['enclosures_as_strings'] ) ? true : false;
		$id_field = get_post_meta( $site_id, 'syn_id_field', true );
		$enc_is_photo = get_post_meta( $site_id, 'syn_enc_is_photo', true );

		//TODO: add checkbox on feed config to allow enclosures to be saved as strings as SI does
		//TODO: add tags here and in feed set up UI
		foreach ( $node_config['nodes'] as $key => $storage_locations ) {
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

		// Fetch the XML feed
		$feed = $this->remote_get( $feed_url );

		if ( Syndication\is_wp_error_do_throw( $feed ) ) {
			Syndication\Syndication_Logger::log_post_error( $site->ID, $status = 'error', $message = sprintf( __( 'Could not reach feed at: %s | Error: %s', 'push-syndication' ), $feed_url, $feed->get_error_message() ), $log_time = null, $extra = array() );

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $site->ID );

			return array();
		} else {
			Syndication\Syndication_Logger::log_post_info( $site->ID, $status = 'fetch_feed', $message = sprintf( __( 'fetched feed with %d bytes', 'push-syndication' ), strlen( $feed ) ), $log_time = null, $extra = array() );
		}

		/**
		 * @var SimpleXMLElement $xml
		 */
		$xml = simplexml_load_string( $feed, null, 0, $node_config['namespace'], false );

		if ( false === $xml ) {
			Syndication\Syndication_Logger::log_post_error( $site->ID, $status = 'error', $message = sprintf( __( 'Failed to parse feed at: %s', 'push-syndication' ), $feed_url ), $log_time = null, $extra = array() );

			// Track the event.
			do_action( 'push_syndication_event', 'pull_failure', $site->ID );

			return array();
		}

		$abs_post_fields['enclosures_as_strings'] = $enclosures_as_strings;

		// TODO: handle constant strings in XML
		foreach ( $abs_nodes as $abs_node ) {
			$value_array = array();

			if ( 'string(' == substr( $abs_node['xpath'], 0, 7 ) ) {
				$value_array[0] = substr( $abs_node['xpath'], 7, strlen( $abs_node['xpath'] ) - 8 );
			} else {
				$value_array = $xml->xpath( stripslashes( $abs_node['xpath'] ) );
			}

			if ( $abs_node['is_meta'] ) {
				if ( isset( $value_array[0] ) && ! empty( $value_array[0] ) ) {
					$abs_meta_data[ $abs_node['field'] ] = (string) $value_array[0];
				}
			} else if ( $abs_node['is_tax'] ) {
				if ( isset( $value_array[0] ) && ! empty( $value_array[0] ) ) {
					$abs_tax_data[ $abs_node['field'] ] = (string) $value_array[0];
				}
			} else {
				if ( isset( $value_array[0] ) && ! empty( $value_array[0] ) ) {
					$abs_post_fields[ $abs_node['field'] ] = (string) $value_array[0];
				}
			}
		}

		$items = $xml->xpath( $node_config['post_root'] );

		if ( empty( $items ) ) {
			Syndication\Syndication_Logger::log_post_error( $site->ID, $status = 'error', $message = printf( __( 'No post nodes found using XPath "%s" in feed', 'push-syndication' ), $node_config['post_root'] ), $log_time = $site->postmeta['is_update'], $extra = array() );
			return array();
		} else {
			Syndication\Syndication_Logger::log_post_info( $site->ID, $status = 'simplexml_load_string', $message = sprintf( __( 'parsed feed, received %d items', 'push-syndication' ), count( $items ) ), $log_time = null, $extra = array() );
		}

		foreach ( $items as $item_index => $item ) {

			// @todo flush out how the post is actually created
			$new_post = new Types\Post();

			$enclosures = $meta_data = $tax_data = $value_array = [];
			$meta_data['is_update'] = current_time( 'mysql' );
			$new_post->post_data['post_type'] = get_post_meta( $site->ID, 'syn_default_post_type', true );;

			//save photos as enclosures in meta
			if ( ( isset( $node_config['enc_parent'] ) && strlen( $node_config['enc_parent'] ) ) && ! empty( $enc_nodes ) ) {
				$meta_data['enclosures'] = $this->get_enclosures( $item->xpath( $node_config['enc_parent'] ), $enc_nodes, $enc_is_photo );
			}

			foreach ( $item_nodes as $save_location ) {
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
					$new_post->post_data[ $save_location['field'] ] = (string) $value_array[0];
				}
			}

			// get this into the tax data
			$new_post->post_data['post_category'] = $node_config['categories'];

			// get this into the meta data
			$meta_data['site_id'] = $site->ID;

			$meta_data = array_merge( $meta_data, $abs_meta_data );
			$tax_data  = array_merge( $tax_data, $abs_tax_data );

			if ( ! empty( $enc_field ) ) {
				$meta_data['enc_field'] = $enc_field;
			}

			if ( ! isset( $meta_data['position'] ) ) {
				$meta_data['position'] = $item_index;
			}

			if ( ! empty( $meta_data ) ) {
				$new_post->post_meta = $meta_data;
			}

			if ( ! empty( $tax_data ) ) {
				$new_post->post_terms = $tax_data;
			}

			if ( ! empty( $meta_data[ $id_field ] ) ) {
				$new_post->post_data['guid'] = $meta_data[ $id_field ];
			}

			$new_posts[] = $new_post;
		}

		Syndication\Syndication_Logger::log_post_info( $site->ID, $status = 'posts_received', $message = sprintf( __( '%d posts were prepared', 'push-syndication' ), count( $new_posts ) ), $log_time = null, $extra = array() );

	/**
	 * Get enclosures (images/attachments) from a feed.
	 *
	 * @param array $feed_enclosures Optional.
	 * @param array $enc_nodes Optional.
	 * @param bool  $enc_is_photo
	 * @return array The list of enclosures in the feed.
	 */
	private function get_enclosures( $feed_enclosures = array(), $enc_nodes = array(), $enc_is_photo = false ) {
		$enclosures = array();
		foreach ( $feed_enclosures as $count => $enc ) {
			if ( isset( $enc_is_photo ) && 1 == $enc_is_photo ) {
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
		return $new_posts;
	}
}
