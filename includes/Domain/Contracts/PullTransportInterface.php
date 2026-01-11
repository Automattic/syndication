<?php
/**
 * Pull transport interface for syndication clients.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

/**
 * Interface for transport implementations that support pulling content.
 *
 * Pull transports can retrieve content from remote sites.
 * This includes RSS feed, XML feed, and XML-RPC clients.
 */
interface PullTransportInterface extends TransportInterface {

	/**
	 * Retrieve posts from the remote site.
	 *
	 * @param array $args Optional arguments for filtering posts.
	 * @return array Array of post data from the remote site.
	 */
	public function pull( array $args = array() ): array;

	/**
	 * Retrieve a single post from the remote site.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return array|null Post data array or null if not found.
	 */
	public function get_post( int $remote_id ): ?array;
}
