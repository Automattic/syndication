<?php
/**
 * Transport factory interface.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

/**
 * Interface for transport factory implementations.
 */
interface TransportFactoryInterface {

	/**
	 * Create a transport for the given site.
	 *
	 * @param int $site_id Site post ID.
	 * @return TransportInterface|null The transport or null if creation failed.
	 */
	public function create( int $site_id ): ?TransportInterface;

	/**
	 * Create a push transport for the given site.
	 *
	 * @param int $site_id Site post ID.
	 * @return PushTransportInterface|null The push transport or null.
	 */
	public function create_push_transport( int $site_id ): ?PushTransportInterface;

	/**
	 * Create a pull transport for the given site.
	 *
	 * @param int $site_id Site post ID.
	 * @return PullTransportInterface|null The pull transport or null.
	 */
	public function create_pull_transport( int $site_id ): ?PullTransportInterface;

	/**
	 * Get available transports.
	 *
	 * @return array<string, array{id: string, modes: array<string>, name: string}>
	 */
	public function get_available_transports(): array;

	/**
	 * Check if a transport type supports push.
	 *
	 * @param string $transport_type Transport type identifier.
	 * @return bool True if push is supported.
	 */
	public function supports_push( string $transport_type ): bool;

	/**
	 * Check if a transport type supports pull.
	 *
	 * @param string $transport_type Transport type identifier.
	 * @return bool True if pull is supported.
	 */
	public function supports_pull( string $transport_type ): bool;
}
