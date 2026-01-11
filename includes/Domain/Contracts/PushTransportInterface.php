<?php
/**
 * Push transport interface for syndication clients.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

use WP_Error;

/**
 * Interface for transport implementations that support pushing content.
 *
 * Push transports can create, update, and delete content on remote sites.
 * This includes WordPress.com REST API and XML-RPC clients.
 */
interface PushTransportInterface extends TransportInterface {

	/**
	 * Create a new post on the remote site.
	 *
	 * @param int $post_id The local post ID to push.
	 * @return int|WP_Error|true The remote post ID on success, WP_Error on failure,
	 *                           or true if the post was filtered out.
	 */
	public function push( int $post_id ): int|WP_Error|bool;

	/**
	 * Update an existing post on the remote site.
	 *
	 * @param int $post_id   The local post ID.
	 * @param int $remote_id The remote post ID to update.
	 * @return int|WP_Error|true The local post ID on success, WP_Error on failure,
	 *                           or true if the post was filtered out.
	 */
	public function update( int $post_id, int $remote_id ): int|WP_Error|bool;

	/**
	 * Delete a post from the remote site.
	 *
	 * @param int $remote_id The remote post ID to delete.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( int $remote_id ): bool|WP_Error;
}
