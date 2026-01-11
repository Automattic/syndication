<?php
/**
 * Base transport interface for syndication clients.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

/**
 * Base interface for all syndication transport implementations.
 *
 * All transport clients must implement this interface which provides
 * the common functionality for connection testing and client metadata.
 */
interface TransportInterface {

	/**
	 * Get client metadata.
	 *
	 * Returns information about this transport implementation including
	 * its identifier, supported modes, and display name.
	 *
	 * @return array{id: string, modes: array<string>, name: string} Client metadata.
	 */
	public static function get_client_data(): array;

	/**
	 * Test the connection to the remote site.
	 *
	 * @return bool True if connection is successful, false otherwise.
	 */
	public function test_connection(): bool;

	/**
	 * Check if a post exists on the remote site.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool True if the post exists, false otherwise.
	 */
	public function is_post_exists( int $remote_id ): bool;
}
