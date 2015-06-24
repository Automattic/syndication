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

	protected $_client_manager;

	public $current_site_id = null;

	public function __construct( Client_Manager $client_manager ) {

		$this->_client_manager = $client_manager;
	}

	/**
	 * Process a site and pull all it's posts
	 *
	 * @param int $site_id The ID of the site for which to pull it's posts
	 * @return array|bool  Array of posts on success, false on failure
	 */
	public function process_site( $site_id ) {

		$this->current_site_id = $site_id;

		// @todo check site status

		// Load the required client.
		$client_slug = get_post_meta( $site_id, 'syn_transport_type', true );
		if ( ! $client_slug ) {
			// @todo log that this site was skipped because no client set.
			throw new \Exception( 'No client selected.' );
		}

		$client = $this->_client_manager->get_pull_client( $client_slug );
		if ( ! $client ) {
			// @todo log that selected client does not exist.
		}

		// @todo mark site as in progress

		try {
			$syn_posts = $client->get_posts();
		} catch ( \Exception $e ) {
			// @todo log and bail.
		}

		// @todo update site status
	}

	/**
	 *
	 * @param $posts array|\Traversable
	 * @throws \Exception
	 */
	public function process_posts( $posts ) {
		// @todo perform actions to improve performance

		if ( ! is_array( $posts ) || ! $posts instanceof \Traversable ) {
			throw new \InvalidArgumentException( '$posts must be array or Traversable.' );
		}

		foreach ( $posts as $post ) {
			$this->process_post( $post );
		};

		// @todo remove actions to improve performance
	}

	/**
	 * @param Types\Post $post
	 * @throws \Exception
	 */
	public function process_post( Types\Post $post ) {

		// @todo hooks
		// @todo Validate the post.

		// Find local ID.
		if ( ! $post->local_id ) {
			$local_id = $this->get_local_id( $post->remote_id );

			if ( $local_id ) {
				$post->local_id = $local_id;
			}
		}

		// Make sure post exists.
		if ( $post->local_id && ! get_post( $post->local_id ) ) {
			throw new \Exception( 'Post does not exist.' );
		}

		// @todo Bail if post exists and in-progress marker is set.
		// @todo Mark post as in-progress (if post exists).

		// Consume the post.
		$this->process_post_data( $post );
		$this->process_post_meta( $post );
		$this->process_post_terms( $post );

		// @todo Mark post as done.
	}

	public function process_post_data( Types\Post $post ) {

		// @todo Validate again if this method remains public.

		$new_post = $post->post_data;

		// @todo Date/time futzing.

		if ( $post->local_id ) {
			$new_post['ID'] = $post->local_id;
		}

		$new_post = apply_filters( 'syn_before_insert_post', $new_post, $this->current_site_id );

		$res = wp_insert_post( $new_post, true );

		is_wp_error_do_throw( $res );
	}

	public function process_post_meta( Types\Post $post ) {

		// @todo Validate again if this method remains public.
		$post_meta = apply_filters( 'syn_before_update_post_meta', $post->post_meta, $post, $this->current_site_id );

		foreach ( $post->post_meta as $key => $value ) {
			$res = update_post_meta( $post->local_id, $key, $value );

			if ( ! $res ) {
				throw new \Exception( 'Could not insert post meta.' );
			}
		}
	}

	public function process_post_terms( Types\Post $post ) {

		// @todo Validate again if this method remains public.
		$post_terms = apply_filters( 'syn_before_set_object_terms', $post->post_terms, $post, $this->current_site_id );


		foreach ( $post_terms as $taxonomy => $terms ) {

			$res = wp_set_object_terms( $post->local_id, $terms, $taxonomy );

			is_wp_error_do_throw( $res );
		}
	}


	/**
	 * @param $identifier
	 * @return int|void
	 */
	public function get_local_id( $identifier ) {

		$identifier = (string) $identifier;

		if ( empty( $identifier ) ) {
			return;
		}

		$posts = get_posts( [
			'meta_key' => 'syn_identifier',
			'meta_value' => $identifier,
			'posts_per_page' => 1,
			'fields' => 'ids',
		] );

		if ( ! $posts ) {
			return;
		}

		return (int) $posts[0];
	}
}

