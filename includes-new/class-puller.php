<?php

namespace Automattic\Syndication;

/**
 * Syndication Puller
 *
 * The role of this class is to be a base/parent for all pull client classes.
 * This parent class contains methods to process a site using this client
 *
 * @package Automattic\Syndication
 */
abstract class Puller {

	public function __construct(){

		//@todo make the following hooks work again
		// these were in version 2.0, however we've restructured so much that these methods should no longer be called individually. now, instead they are called in succession in process_post below

		/*
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_meta' ), 10, 5 );
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_tax' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_meta' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_tax' ), 10, 5 );
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'publish_pulled_post', 10, 5 );
		*/
	}

	/**
	 * Process a site and pull all it's posts
	 *
	 * @param int $site_id The ID of the site for which to pull it's posts
	 * @param obj $client  The syndication client class instance
	 * @return array|bool  Array of posts on success, false on failure
	 */
	public function process_site( $site_id, $client ) {
		global $site_manager, $client_manager;

		// Fetch the site status
		if ( ! in_array( $site_manager->get_site_status( $site_id ), array( 'idle', '' ) ) ) {
			return false;
		}

		// Get the required client.
		$client_transport_type = get_post_meta( $site_id, 'syn_transport_type', true );
		if ( ! $client_transport_type ) {
			return false;
		}

		// Fetch the client so we may pull it's posts
		$client_details = $client_manager->get_pull_client( $client_transport_type );
		if ( ! $client_details ) {
			return false;
		}

		// Mark site as in progress
		$site_manager->update_site_status( 'pulling' );

		try {
			// Fetch the site's posts by calling the class located at the
			// namespace given during registration
			$posts = $client->get_posts( $site_id );

			// Process the posts we fetched
			$this->process_posts( $posts, $site_id );

		} catch ( \Exception $e ) {
			error_log( $e );
		}

		// Update site status
		$site_manager->update_site_status( 'idle' );

		if ( is_array( $posts ) && ! empty( $posts ) ) {
			return $posts;
		} else {
			return false;
		}
	}

	/**
	 * Process new posts fetched for a feed
	 *
	 * @param array $posts An array of new posts fetched
	 * @throws \Exception
	 * @return mixed False on failure
	 */
	public function process_posts( $posts, $site_id ) {
		// @todo perform actions to improve performance

		if ( ! is_array( $posts ) && ! empty( $posts ) ) {
			throw new \Exception( '$posts must be array' );

			return false;
		}

		$inserted_posts = 0;

		foreach ( $posts as $post ) {
			$post_id = $this->process_post( $post );

			if ( false !== $post_id ) {
				$inserted_posts++;
			}
		};

		Syndication_Logger::log_post_info( $site_id, $status = 'posts_processed', $message = sprintf( __( '%d posts were successfully processed', 'push-syndication' ), $inserted_posts ), $log_time = null, $extra = array() );
		
		// @todo remove actions to improve performance
	}

	/**
	 * Process a new post
	 *
	 * Insert the post into WP, then if successful,
	 * insert the posts meta and terms
	 *
	 * @param Types\Post $post A prepared
	 * @return int $post_id The ID of the newly inserted post
	 */
	public function process_post( Types\Post $post ) {

		// @todo hooks
		// @todo Validate the post.
		// @todo Bail if post exists and in-progress marker is set.
		// @todo Mark post as in-progress (if post exists).

		// Consume the post
		$post = apply_filters( 'syn_before_insert_post', $post );

		// Generate a unique `syndicated_guid` combining the site id and the item guid.
		$syndicated_guid = md5( $post->post_meta['site_id'] . '_' . $post->post_data['post_guid'] );

		// Query for posts with a matching syndicated_guid
		$query_args = array(
			'meta_key'      => 'syndicated_guid',
			'meta_value'    => $syndicated_guid,
			'post_status'   => 'any',
			'post_per_page' => 1
		);
		$existing_post_query = new \WP_Query( $query_args );
		// If the post has already been consumed, update it; otherwise insert it.
		// @todo Only update if updates enabled in settings
		if ( $existing_post_query->have_posts() ) {
			// Existing post, set the post ID for update.
			$existing_post_query->the_post();
			$post->post_data['ID'] = get_the_ID();

			// Maintain the post's status.
			$post->post_data['post_status'] = get_post_status();

			// Update the existing post.
			$post_id = wp_update_post( $post->post_data, true );
		} else {
			//  Include the syndicated_guid so we can update this post later.
			$post->post_meta['syndicated_guid'] = $syndicated_guid;

			// The post is new, insert it.
			$post_id = wp_insert_post( $post->post_data, true );
		}
		wp_reset_postdata();

		if ( ! is_wp_error_do_throw( $post_id ) ) {
			$this->process_post_meta( $post_id, $post->post_meta );
			$this->process_post_terms( $post_id, $post->post_terms );
		}

		return $post_id;

		// @todo Mark post as done.
	}

	/**
	 * Add meta to a post
	 *
	 * @param int   $post_id   The ID of the post for which to insert the given meta
	 * @param array $post_meta Associative array of meta to add to the post
	 * @throws \Exception
	 * @return mixed           False on failure
	 */
	public function process_post_meta( $post_id, $post_meta ) {
		// @todo Validate again if this method remains public.
		// @todo Ensure works correctly for updates.
		$post_meta = apply_filters( 'syn_before_update_post_meta', $post_meta, $post_id );
		//handle enclosures separately first
		$enc_field = isset( $post_meta['enc_field'] ) ? $post_meta['enc_field'] : null;
		$enclosures = isset( $post_meta['enclosures'] ) ? $post_meta['enclosures'] : null;
		if ( isset( $enclosures ) && isset ( $enc_field ) ) {
			// first remove all enclosures for the post (for updates) if any
			delete_post_meta( $post_id, $enc_field);
			foreach( $enclosures as $enclosure ) {
				if (defined('ENCLOSURES_AS_STRINGS') && constant('ENCLOSURES_AS_STRINGS')) {
					$enclosure = implode("\n", $enclosure);
				}
				error_log( sprintf( 'adding enclosure meta to %s field %s enclosure %s', $post_id, $enc_field, json_encode( $enclosure ) ) );

				add_post_meta( $post_id, $enc_field, $enclosure, false );
			}

			// now remove them from the rest of the metadata before saving the rest
			unset($post_meta['enclosures']);
		}

		if ( is_array( $post_meta ) && ! empty( $post_meta ) ) {

			foreach ( $post_meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		} else {
			return false;
		}
	}

	/**
	 * Add taxonomy terms to a post
	 *
	 * @param int   $post_id    The ID of the post for which to insert the given meta
	 * @param array $post_terms Associative array of taxonomy|terms to add to the post
	 * @throws \Exception
	 */
	public function process_post_terms( $post_id, $post_terms ) {

		// @todo Validate again if this method remains public.
		$post_terms = apply_filters( 'syn_before_set_object_terms', $post_id, $post_terms );

		if ( is_array( $post_terms ) && ! empty( $post_terms ) ) {
			foreach ( $post_terms as $taxonomy => $terms ) {

				$res = wp_set_object_terms( $post_id, $terms, $taxonomy );

				is_wp_error_do_throw( $res );
			}
		}
	}

	/**
	 * Test the connection with the slave site.
	 *
	 * @param string $remote_url The remote URL
	 * @return bool True on success; false on failure.
	 */
	public function test_connection( $remote_url = '' ) {
		return ! is_wp_error( $this->remote_get( $remote_url = '' ) );
	}

	/**
	 * Fetch a remote url.
	 *
	 * @param string $remote_url The remote URL
	 * @return string|WP_Error The content of the remote feed, or error if there's a problem.
	 */
	public function remote_get( $remote_url = '' ) {

		// Only proceed if we have a valid remote url
		if ( isset( $remote_url ) && ! empty( $remote_url ) ) {

			$request = wp_remote_get( esc_url_raw( $remote_url ) );

			if ( is_wp_error_do_throw( $request ) ) {
				return $request;
			} elseif ( 200 != wp_remote_retrieve_response_code( $request ) ) {
				return new \WP_Error( 'syndication-fetch-failure', 'Failed to fetch Remote URL; HTTP code: ' . wp_remote_retrieve_response_code( $request ) );
			}

			return wp_remote_retrieve_body( $request );
		} else {
			return false;
		}
	}

	/**
	 * Get enclosures (images/attachments) from a feed.
	 *
	 * @param array $feed_enclosures Optional.
	 * @param array $enc_nodes Optional.
	 * @param bool  $enc_is_photo
	 * @return array The list of enclosures in the feed.
	 */
	public function get_enclosures( $feed_enclosures = array(), $enc_nodes = array(), $enc_is_photo = false ) {
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
}

