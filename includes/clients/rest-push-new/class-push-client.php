<?php
/**
 * Syndication Client: REST Push
 *
 * Create 'syndication sites' to push site content to an external WordPress
 * install via the WP REST API. Includes XPath mapping to map incoming
 * REST data to specific post data.
 *
 * @package Automattic\Syndication\Clients\REST
 */

namespace Automattic\Syndication\Clients\REST_Push_New;

use Automattic\Syndication\Pusher;

class Push_Client extends Pusher {
	/**
	 * Access token.
	 *
	 * @since 2.1
	 * @var string
	 */
	private $access_token;

	/**
	 * Endpoint URL.
	 *
	 * @since 2.1
	 * @var string
	 */
	private $endpoint_url;

	/**
	 * Port.
	 *
	 * @since 2.1
	 * @var int
	 */
	private $port;

	/**
	 * User Agent.
	 *
	 * @since 2.1
	 * @var string
	 */
	private $useragent;

	/**
	 * Timeout.
	 *
	 * @since 2.1
	 * @var integer
	 */
	private $timeout;

	/**
	 * Push_Client constructor.
	 *
	 * @since 2.1
	 */
	public function __construct() {}

	/**
	 * Init
	 *
	 * @since 2.1
	 * @param int $site_id The Site ID (Syndication Endpoint) to Push to.
	 * @param int $port The port number of the receiving REST API.
	 * @param int $timeout How long to wait before killing the request.
	 */
	public function init( $site_id = 0, $port = 80, $timeout = 45 ) {
		global $settings_manager;

		$this->access_token = $settings_manager->syndicate_decrypt( get_post_meta( $site_id, 'syn_site_token', true ) );
		$this->endpoint_url = untrailingslashit( get_post_meta( $site_id, 'syn_site_url', true ) );
		$this->timeout      = $timeout;
		$this->useragent    = 'push-syndication-plugin';
		$this->port         = $port;

	}

	/**
	 * Push a new post to the remote endpoint.
	 *
	 * @since 2.1
	 * @param int $post_id The ID of the post to be pushed.
	 * @return int|\WP_Error The ID of the remote post or error.
	 */
	public function new_post( $post_id ) {
		$post = (array) get_post( $post_id );

		/**
		 * Filter the post used by the REST push client when pushing a new post to a remote.
		 *
		 * This filter can be used to exclude or alter posts during a content push. Return false
		 * to short circuit the post push.
		 *
		 * @since 2.1
		 * @param WP_Post $post    The post the be pushed.
		 * @param int     $post_id The id of the post originating this request.
		 */
		$post = apply_filters( 'syn_rest_push_filter_new_post', $post, $post_id );

		if ( false === $post ) {
			return true;
		}

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $post['post_date_gmt'],
		);

		/**
		 * Filter the REST push client body before pushing a new post.
		 *
		 * @since 2.1
		 * @param string $body    The body to send to the REST API endpoint.
		 * @param int    $post_id The id of the post being pushed.
		 */
		$body = apply_filters( 'syn_rest_push_filter_new_post_body', $body, $post_id );

		$response = wp_remote_post(
			$this->endpoint_url . '/wp-json/wp/v2/posts',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
			// Add Categories.
			$this->sync_taxonomy( 'category', $post_id, $body->id );

			// Add tags.
			$this->sync_taxonomy( 'post_tag', $post_id, $body->id );

			return $body->id;
		} else {
			if ( ! empty( $body->message ) ) {
				$message = $body->message;
			} else {
				$message = __( 'Failed to push new post', 'push-syndication' );
			}

			return new \WP_Error( 'rest-push-new-fail', $message );
		}
	}

	/**
	 * Update an existing post on the remote endpoint.
	 *
	 * @since 2.1
	 * @param int $post_id        The ID of the post to be pushed.
	 * @param int $remote_post_id The ID of the remote post.
	 * @return int|\WP_Error The ID of the remote post or error.
	 */
	public function edit_post( $post_id, $remote_post_id ) {
		$post = (array) get_post( $post_id );

		/**
		 * Filter the post used by the REST push client when pushing an update.
		 *
		 * This filter can be used to exclude or alter posts during a content update to an
		 * existing post on the remote. Return false to short circuit the post push update.
		 *
		 * @since 2.1
		 * @param \WP_Post $post    The post the be pushed.
		 * @param int      $post_ID The id of the post originating this request.
		 */
		$post = apply_filters( 'syn_rest_push_filter_edit_post', $post, $post_id );

		if ( false === $post ) {
			return true;
		}

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $post['post_date_gmt'],
		);

		/**
		 * Filter the REST push client body before pushing a new post.
		 *
		 * @since 2.1
		 * @param string $body    The body to send to the REST API endpoint.
		 * @param int    $post_id The id of the post being pushed.
		 */
		$body = apply_filters( 'syn_rest_push_filter_new_post_body', $body, $post_id );

		$response = wp_remote_post(
			$this->endpoint_url . '/wp-json/wp/v2/posts/' . $remote_post_id,
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
			// Add Categories.
			$this->sync_taxonomy( 'category', $post_id, $body->id );

			// Add tags.
			$this->sync_taxonomy( 'post_tag', $post_id, $body->id );

			return $body->id;
		} else {
			if ( ! empty( $body->message ) ) {
				$message = $body->message;
			} else {
				$message = __( 'Failed to push update post', 'push-syndication' );
			}

			return new \WP_Error( 'rest-push-edit-fail', $message );
		}
	}

	/**
	 * When we delete a local post, delete the remote as well.
	 *
	 * @since 2.1
	 * @param int $remote_post_id The ID of the remote post to delete.
	 * @return int|\WP_Error The ID of the remote post or error.
	 */
	public function delete_post( $remote_post_id ) {
		$response = wp_remote_post(
			$this->endpoint_url . '/wp-json/wp/v2/posts/' . $remote_post_id,
			array(
				'method'     => 'DELETE',
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
			return true;
		} else {
			if ( ! empty( $body->message ) ) {
				$message = $body->message;
			} else {
				$message = __( 'Failed to push update post', 'push-syndication' );
			}

			return new \WP_Error( 'rest-push-delete-fail', $message );
		}

	}

	/**
	 * Check if a posts exists via the WordPress REST API.
	 *
	 * @since 2.1
	 * @param  int  $remote_post_id The remote post ID to check
	 * @return boolean              True if the post exists, otherwise false.
	 */
	public function is_post_exists( $remote_post_id ) {
		$response = wp_remote_post(
			$this->endpoint_url . '/wp-json/wp/v2/posts/' . $remote_post_id,
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			)
		);

		if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
			$post = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! empty( $post ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Syncs the taxonomies between the local post and remote post. Adds
	 * taxonomies where need be.
	 *
	 * @since 2.1
	 * @param string $taxonomy The taxonomy name to sync.
	 * @param int    $post_id The ID of the local post.
	 * @param int    $remote_post_id The ID of the remote post.
	 * @return bool
	 */
	public function sync_taxonomy( $taxonomy, $post_id, $remote_post_id ) {
		// Make sure we support the taxonomy being called.
		switch ( $taxonomy ) {
			case 'category':
				$slug_key = 'categories';
				break;
			case 'post_tag':
				$slug_key = 'tags';
				break;
		}

		if ( empty( $slug_key ) ) {
			return false;
		}

		$terms      = array();
		$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );

		if ( empty( $post_terms ) ) {
			return true;
		}

		/**
		 * Filter the terms.
		 *
		 * @since 2.1
		 * @param array  $post_terms List of terms in taxonomy assigned to post.
		 * @param string $taxonomy   The name of the taxonomy.
		 * @param string $post_id    The id of the post being pushed.
		 */
		$post_terms = apply_filters( 'syn_rest_push_filter_post_taxonomies', $post_terms, $taxonomy, $post_id );

		// Get a list of terms that exist.
		$response = wp_remote_get(
			$this->endpoint_url . '/wp-json/wp/v2/' . $slug_key . '?slug=' . implode( ',', $post_terms ),
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
			$remote_terms = json_decode( wp_remote_retrieve_body( $response ) );

			/*
			 * Go through any remote terms that match the post terms and add
			 * them to the list of terms to assign to the post. Also remove them
			 * for the list of terms to add that don't exist.
			 */
			if ( count( $remote_terms ) ) {
				foreach ( $remote_terms as $term ) {
					if ( false !== ( $key = array_search( $term->name, $post_terms, true ) ) ) {
						$terms[] = $term->id;
						unset( $post_terms[ $key ] );
					}
				}
			}
		} else {
			return false;
		}

		// Create the remaining categories that don't exist remotely.
		if ( count( $post_terms ) ) {
			foreach ( $post_terms as $post_term ) {
				$body = array(
					'name' => $post_term,
				);

				$response = wp_remote_post(
					$this->endpoint_url . '/wp-json/wp/v2/' . $slug_key,
					array(
						'timeout'    => $this->timeout,
						'user-agent' => $this->useragent,
						'sslverify'  => false,
						'headers'    => array(
							'authorization' => 'Bearer ' . $this->access_token,
							'Content-Type'  => 'application/json',
						),
						'body'       => wp_json_encode( $body ),
					)
				);

				if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
					$term    = json_decode( wp_remote_retrieve_body( $response ) );
					$terms[] = $term->id;
				} else {
					return false;
				}
			}
		}

		// Assign categories to post.
		if ( ! empty( $terms ) ) {
			$body = array(
				$slug_key => $terms,
			);

			$response = wp_remote_post(
				$this->endpoint_url . '/wp-json/wp/v2/posts/' . $remote_post_id,
				array(
					'timeout' => $this->timeout,
					'user-agent' => $this->useragent,
					'sslverify' => false,
					'headers' => array(
						'authorization' => 'Bearer ' . $this->access_token,
						'Content-Type'  => 'application/json',
					),
					'body' => wp_json_encode( $body ),
				)
			);

			if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test the connection to the remote server.
	 *
	 * @since 2.1
	 * @param int $site_id The ID of the Syndication Endpoint.
	 * @return bool
	 */
	public function test_connection( $site_id ) {
		$response = wp_remote_get(
			$this->endpoint_url . '/wp-json/wp/v2/posts',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( 200, 201 ), true ) ) {
			return true;
		} else {
			return false;
		}
	}
}
