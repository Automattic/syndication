<?php
/**
 * Abstract push transport base class.
 *
 * @package Automattic\Syndication\Infrastructure\Transport
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Transport;

use Automattic\Syndication\Domain\Contracts\PushTransportInterface;
use WP_Error;
use WP_Post;

/**
 * Abstract base class for push transport implementations.
 *
 * Provides common functionality for pushing content to remote sites,
 * including filter hook handling and post data preparation.
 */
abstract class AbstractPushTransport implements PushTransportInterface {

	/**
	 * The site post ID.
	 *
	 * @var int
	 */
	protected readonly int $site_id;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	protected readonly int $timeout;

	/**
	 * User agent string.
	 *
	 * @var string
	 */
	protected readonly string $user_agent;

	/**
	 * Constructor.
	 *
	 * @param int $site_id The site post ID.
	 * @param int $timeout Request timeout in seconds.
	 */
	public function __construct( int $site_id, int $timeout = 45 ) {
		$this->site_id    = $site_id;
		$this->timeout    = $timeout;
		$this->user_agent = 'push-syndication-plugin';
	}

	/**
	 * Push a new post to the remote site.
	 *
	 * @param int $post_id The local post ID to push.
	 * @return int|WP_Error|bool Remote post ID on success, WP_Error on failure, true if filtered out.
	 */
	public function push( int $post_id ): int|WP_Error|bool {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'invalid_post', 'Post not found.' );
		}

		$post_data = $this->prepare_post_data( $post );

		// Apply pre-push filter - allows exclusion or modification.
		$post_data = $this->apply_pre_push_filter( $post_data, $post_id );

		if ( false === $post_data ) {
			return true; // Filtered out.
		}

		// Apply body filter for transport-specific modifications.
		$post_data = $this->apply_body_filter( 'push', $post_data, $post_id );

		return $this->do_push( $post_data, $post_id );
	}

	/**
	 * Update an existing post on the remote site.
	 *
	 * @param int $post_id   The local post ID.
	 * @param int $remote_id The remote post ID to update.
	 * @return int|WP_Error|bool Local post ID on success, WP_Error on failure, true if filtered out.
	 */
	public function update( int $post_id, int $remote_id ): int|WP_Error|bool {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'invalid_post', 'Post not found.' );
		}

		$post_data = $this->prepare_post_data( $post );

		// Apply pre-update filter - allows exclusion or modification.
		$post_data = $this->apply_pre_update_filter( $post_data, $post_id );

		if ( false === $post_data ) {
			return true; // Filtered out.
		}

		// Apply body filter for transport-specific modifications.
		$post_data = $this->apply_body_filter( 'update', $post_data, $post_id );

		return $this->do_update( $post_data, $post_id, $remote_id );
	}

	/**
	 * Delete a post from the remote site.
	 *
	 * @param int $remote_id The remote post ID to delete.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( int $remote_id ): bool|WP_Error {
		return $this->do_delete( $remote_id );
	}

	/**
	 * Prepare post data for syndication.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> Prepared post data.
	 */
	protected function prepare_post_data( WP_Post $post ): array {
		return array(
			'post_title'    => $post->post_title,
			'post_content'  => $post->post_content,
			'post_excerpt'  => $post->post_excerpt,
			'post_status'   => $post->post_status,
			'post_password' => $post->post_password,
			'post_date'     => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'post_type'     => $post->post_type,
			'post_id'       => $post->ID,
		);
	}

	/**
	 * Apply pre-push filter.
	 *
	 * Override in subclasses to provide transport-specific filter names.
	 *
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed>|false Filtered data or false to skip.
	 */
	protected function apply_pre_push_filter( array $post_data, int $post_id ): array|false {
		return $post_data;
	}

	/**
	 * Apply pre-update filter.
	 *
	 * Override in subclasses to provide transport-specific filter names.
	 *
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed>|false Filtered data or false to skip.
	 */
	protected function apply_pre_update_filter( array $post_data, int $post_id ): array|false {
		return $post_data;
	}

	/**
	 * Apply body filter for transport-specific modifications.
	 *
	 * Override in subclasses to provide transport-specific filter names.
	 *
	 * @param string               $action    The action (push or update).
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed> Filtered data.
	 */
	protected function apply_body_filter( string $action, array $post_data, int $post_id ): array {
		return $post_data;
	}

	/**
	 * Perform the actual push operation.
	 *
	 * @param array<string, mixed> $post_data Prepared post data.
	 * @param int                  $post_id   The local post ID.
	 * @return int|WP_Error Remote post ID on success, WP_Error on failure.
	 */
	abstract protected function do_push( array $post_data, int $post_id ): int|WP_Error;

	/**
	 * Perform the actual update operation.
	 *
	 * @param array<string, mixed> $post_data Prepared post data.
	 * @param int                  $post_id   The local post ID.
	 * @param int                  $remote_id The remote post ID.
	 * @return int|WP_Error Local post ID on success, WP_Error on failure.
	 */
	abstract protected function do_update( array $post_data, int $post_id, int $remote_id ): int|WP_Error;

	/**
	 * Perform the actual delete operation.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	abstract protected function do_delete( int $remote_id ): bool|WP_Error;
}
