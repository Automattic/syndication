<?php

namespace Automattic\Syndication\Clients\REST_Push;

use Automattic\Syndication;
use Automattic\Syndication\Pusher;
use Automattic\Syndication\Types;

/**
 * Syndication Client: REST Push
 *
 * Create 'syndication sites' to push site content to an external
 * WordPress install via REST-RPC. Includes XPath mapping to map incoming
 * REST data to specific post data.
 *
 * @package Automattic\Syndication\Clients\REST
 */

class Push_Client extends Pusher {

	private $access_token;
	private $blog_ID;

	private $port;
	private $useragent;
	private $timeout;

	function __construct() {}

	public function init( $site_ID = 0, $port = 80, $timeout = 45 ) {
		global $settings_manager;

		$this->access_token = $settings_manager->syndicate_decrypt( get_post_meta( $site_ID, 'syn_site_token', true ) );
		$this->blog_ID      = get_post_meta( $site_ID, 'syn_site_id', true );
		$this->timeout      = $timeout;
		$this->useragent    = 'push-syndication-plugin';
		$this->port         = $port;

	}

	public function get_posts( $site_ID = 0 ) {
	}

	/**
	 * Process this site, setting up callbacks.
	 *
	 * @param int $site_ID The id of the site to set up.
	 */
	public function process_site( $site_ID = 0 ) {

	}

	private function get_thumbnail_meta_keys( $post_id ) {

		/**
		 * Filter the meta keys used used for thumbnail meta.
		 *
		 * Enables support for non-core images, like from the Multiple Post Thumbnail plugin.
		 *
		 * @param array $meta_keys Array of meta keys to use for thumbnail meta.
		 */
		return apply_filters( 'syn_xmlrpc_push_thumbnail_metas', array( '_thumbnail_id' ), $post_id );
	}

	public static function get_client_data() {
		return array( 'id' => 'WP_REST', 'modes' => array( 'push' ), 'name' => 'WordPress.com REST' );
	}

	/**
	 * Push a new post to the remote.
	 */
	public function new_post( $post_ID ) {

		$post = (array)get_post( $post_ID );

		/**
		 * Filter the post used by the REST push client when pushing a new post to a remote.
		 *
		 * This filter can be used to exclude or alter posts during a content push. Return false
		 * to short circuit the post push.
		 *
		 * @param WP_Post $post    The post the be pushed.
		 * @param int     $post_ID The id of the post originating this request.
		 */
		$post = apply_filters( 'syn_rest_push_filter_new_post', $post, $post_ID );
		if ( false === $post )
			return true;

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $post['post_date_gmt'],
			'categories' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'category', array( 'fields' => 'names', ) ) ),
			'tags'       => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'post_tag', array( 'fields' => 'names', ) ) ),
		);

		/**
		 * Filter the REST push client body before pushing a new post.
		 *
		 * @param string $body    The body to send to the REST API endpoint.
		 * @param int    $post_ID The id of the post being pushed.
		 */
		$body = apply_filters( 'syn_rest_push_filter_new_post_body', $body, $post_ID );

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/new/',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body' => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return $response->ID;
		} else {
			return new \WP_Error( 'rest-push-new-fail', $response->message );
		}

	}

	/**
	 * Update an existing post on the remote.
	 */
	public function edit_post( $post_ID, $ext_ID ) {

		$post = (array)get_post( $post_ID );

		/**
		 * Filter the post used by the REST push client when pushing an update.
		 *
		 * This filter can be used to exclude or alter posts during a content update to an
		 * existing post on the remote. Return false to short circuit the post push update.
		 *
		 * @param WP_Post $post    The post the be pushed.
		 * @param int     $post_ID The id of the post originating this request.
		 */
		$post = apply_filters( 'syn_rest_push_filter_edit_post', $post, $post_ID );
		if ( false === $post )
			return true;

		$body = array(
			'title'      => $post['post_title'],
			'content'    => $post['post_content'],
			'excerpt'    => $post['post_excerpt'],
			'status'     => $post['post_status'],
			'password'   => $post['post_password'],
			'date'       => $post['post_date_gmt'],
			'categories' => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'category', array( 'fields' => 'names' ) ) ),
			'tags'       => $this->_prepare_terms( wp_get_object_terms( $post_ID, 'post_tag', array( 'fields' => 'names' ) ) )
		);

		/**
		 * Filter the REST push client body before pushing an existing post update.
		 *
		 * @param string $body    The body to send to the REST API endpoint.
		 * @param int    $post_ID The id of the post being pushed.
		 */
		$body = apply_filters( 'syn_rest_push_filter_edit_post_body', $body, $post_ID );

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $ext_ID . '/',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body' => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return $post_ID;
		} else {
			return new \WP_Error( 'rest-push-edit-fail', $response->message );
		}

	}

	/**
	 * Utility method to Syndicate [gallery] shortcode images
	 * It needs to upload images and inject new IDs into the post_content
	 * @access private
	 * @uses $shortcode_tags global variable
	 * @param string $post_content - post to be syndicated
	 * @return string $post_content - post content with replaced gallery shortcodes
	 */
	private function syndicate_gallery_images( $post_content ) {

	}

	/**
	 * When we delete a local post, delete the remote as well.
	 */
	public function delete_post( $ext_ID ) {

		$response = wp_remote_post(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $ext_ID . '/delete',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return true;
		} else {
			return new \WP_Error( 'rest-push-delete-fail', $response->message );
		}

	}


	/**
	 * Retrieve a remote post by ID.
	 */
	function get_remote_post( $remote_post_id ) {

	}


	// get an array of values and convert it to CSV
	function _prepare_terms( $terms ) {

		$terms_csv = '';

		foreach ( $terms as $term ) {
			$terms_csv .= $term . ',';
		}

		return $terms_csv;

	}
	/**
	 * Check if a posts exists via the WordPress TEST API.
	 *
	 * @param  int  $post_ID The post id to check
	 *
	 * @return boolean       True if the post exists, otherwise false.
	 *
	 */
	public function is_post_exists( $post_ID ) {

		$response = wp_remote_get(
			'https://public-api.wordpress.com/rest/v1/sites/' . $this->blog_ID . '/posts/' . $post_ID . '/?pretty=1',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Test the connection to the remote server.
	 *
	 * @return bool
	 */
	public function test_connection( $site_ID ) {
				// @TODo find a better method
		$response = wp_remote_get(
			'https://public-api.wordpress.com/rest/v1/me/?pretty=1',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->useragent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		// TODO: return WP_Error
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response->error ) ) {
			return true;
		} else {
			return false;
		}
	}

}
