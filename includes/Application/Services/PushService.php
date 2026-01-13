<?php
/**
 * Push syndication service.
 *
 * @package Automattic\Syndication\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\Services;

use Automattic\Syndication\Application\DTO\PushResult;
use Automattic\Syndication\Domain\Contracts\PushTransportInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use WP_Error;
use WP_Post;

/**
 * Service for pushing content to remote sites.
 *
 * Orchestrates the push syndication workflow including transport creation,
 * state management, and result tracking.
 */
final class PushService {

	/**
	 * Lock transient name.
	 */
	private const LOCK_TRANSIENT = 'syn_syndicate_lock';

	/**
	 * Lock duration in seconds.
	 */
	private const LOCK_DURATION = 300;

	/**
	 * Transport factory.
	 *
	 * @var TransportFactoryInterface
	 */
	private readonly TransportFactoryInterface $transport_factory;

	/**
	 * Constructor.
	 *
	 * @param TransportFactoryInterface $transport_factory Transport factory.
	 */
	public function __construct( TransportFactoryInterface $transport_factory ) {
		$this->transport_factory = $transport_factory;
	}

	/**
	 * Push a post to multiple sites.
	 *
	 * @param int   $post_id  The local post ID.
	 * @param int[] $site_ids Array of site post IDs to push to.
	 * @return PushResult[] Array of results keyed by site ID.
	 */
	public function push_to_sites( int $post_id, array $site_ids ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		if ( ! $this->acquire_lock() ) {
			return array();
		}

		try {
			$results      = array();
			$slave_states = $this->get_slave_post_states( $post_id );

			foreach ( $site_ids as $site_id ) {
				$results[ $site_id ] = $this->push_to_site( $post_id, $site_id, $slave_states );
			}

			$this->save_slave_post_states( $post_id, $slave_states );

			return $results;
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Push a post to a single site.
	 *
	 * @param int                       $post_id      The local post ID.
	 * @param int                       $site_id      The site post ID.
	 * @param array<string, mixed>|null $slave_states Optional slave states array (modified by reference).
	 * @return PushResult The push result.
	 */
	public function push_to_site( int $post_id, int $site_id, ?array &$slave_states = null ): PushResult {
		$transport = $this->transport_factory->create_push_transport( $site_id );

		if ( ! $transport instanceof PushTransportInterface ) {
			return PushResult::failure( $site_id, 'invalid_transport', 'Could not create push transport.' );
		}

		if ( null === $slave_states ) {
			$slave_states = $this->get_slave_post_states( $post_id );
		}

		$state     = $this->get_site_state( $site_id, $slave_states );
		$remote_id = $this->get_remote_post_id( $site_id, $slave_states );

		// Determine if this is a new push or update.
		if ( $this->is_new_push( $state ) ) {
			$result = $this->do_new_push( $transport, $post_id, $site_id, $slave_states );
		} else {
			$result = $this->do_update_push( $transport, $post_id, $site_id, $remote_id, $slave_states );
		}

		return $result;
	}

	/**
	 * Delete a post from a site.
	 *
	 * @param int $post_id The local post ID.
	 * @param int $site_id The site post ID.
	 * @return PushResult The delete result.
	 */
	public function delete_from_site( int $post_id, int $site_id ): PushResult {
		$transport = $this->transport_factory->create_push_transport( $site_id );

		if ( ! $transport instanceof PushTransportInterface ) {
			return PushResult::failure( $site_id, 'invalid_transport', 'Could not create push transport.' );
		}

		$slave_states = $this->get_slave_post_states( $post_id );
		$remote_id    = $this->get_remote_post_id( $site_id, $slave_states );

		if ( 0 === $remote_id ) {
			return PushResult::failure( $site_id, 'no_remote_post', 'Post does not exist on remote site.' );
		}

		$result = $transport->delete( $remote_id );

		if ( is_wp_error( $result ) ) {
			$slave_states['remove-error'][ $site_id ] = $result;
			$this->save_slave_post_states( $post_id, $slave_states );

			return PushResult::failure( $site_id, $result->get_error_code(), $result->get_error_message() );
		}

		// Remove from success tracking.
		unset( $slave_states['success'][ $site_id ] );
		$this->save_slave_post_states( $post_id, $slave_states );

		return PushResult::success( $site_id, $remote_id, 'deleted' );
	}

	/**
	 * Perform a new push operation.
	 *
	 * @param PushTransportInterface $transport    The transport.
	 * @param int                    $post_id      Local post ID.
	 * @param int                    $site_id      Site post ID.
	 * @param array<string, mixed>   $slave_states Slave states (modified by reference).
	 * @return PushResult The result.
	 */
	private function do_new_push(
		PushTransportInterface $transport,
		int $post_id,
		int $site_id,
		array &$slave_states
	): PushResult {
		$result = $transport->push( $post_id );

		if ( true === $result ) {
			// Filtered out - not an error.
			return PushResult::skipped( $site_id, 'Filtered out by pre-push filter.' );
		}

		if ( is_wp_error( $result ) ) {
			$slave_states['new-error'][ $site_id ] = $result;

			return PushResult::failure( $site_id, $result->get_error_code(), $result->get_error_message() );
		}

		// Success - store the remote ID.
		$remote_id                           = (int) $result;
		$slave_states['success'][ $site_id ] = $remote_id;

		// Clear any previous errors.
		unset( $slave_states['new-error'][ $site_id ] );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'syn_post_push_new_post', $remote_id, $post_id, get_post( $site_id ), '', null, array() );

		return PushResult::success( $site_id, $remote_id, 'created' );
	}

	/**
	 * Perform an update push operation.
	 *
	 * @param PushTransportInterface $transport    The transport.
	 * @param int                    $post_id      Local post ID.
	 * @param int                    $site_id      Site post ID.
	 * @param int                    $remote_id    Remote post ID.
	 * @param array<string, mixed>   $slave_states Slave states (modified by reference).
	 * @return PushResult The result.
	 */
	private function do_update_push(
		PushTransportInterface $transport,
		int $post_id,
		int $site_id,
		int $remote_id,
		array &$slave_states
	): PushResult {
		$result = $transport->update( $post_id, $remote_id );

		if ( true === $result ) {
			// Filtered out - not an error.
			return PushResult::skipped( $site_id, 'Filtered out by pre-update filter.' );
		}

		if ( is_wp_error( $result ) ) {
			$slave_states['edit-error'][ $site_id ] = $result;

			return PushResult::failure( $site_id, $result->get_error_code(), $result->get_error_message() );
		}

		// Clear any previous errors.
		unset( $slave_states['edit-error'][ $site_id ] );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'syn_post_push_edit_post', $result, $post_id, get_post( $site_id ), '', null, array() );

		return PushResult::success( $site_id, $remote_id, 'updated' );
	}

	/**
	 * Get slave post states from post meta.
	 *
	 * @param int $post_id The post ID.
	 * @return array<string, mixed> Slave states.
	 */
	private function get_slave_post_states( int $post_id ): array {
		$states = get_post_meta( $post_id, '_syn_slave_post_states', true );
		return is_array( $states ) ? $states : array();
	}

	/**
	 * Save slave post states to post meta.
	 *
	 * @param int                  $post_id The post ID.
	 * @param array<string, mixed> $states  Slave states.
	 */
	private function save_slave_post_states( int $post_id, array $states ): void {
		update_post_meta( $post_id, '_syn_slave_post_states', $states );
	}

	/**
	 * Get the state for a specific site.
	 *
	 * @param int                  $site_id      Site post ID.
	 * @param array<string, mixed> $slave_states Slave states.
	 * @return string State identifier.
	 */
	private function get_site_state( int $site_id, array $slave_states ): string {
		if ( isset( $slave_states['success'][ $site_id ] ) ) {
			return 'success';
		}

		if ( isset( $slave_states['edit-error'][ $site_id ] ) ) {
			return 'edit-error';
		}

		if ( isset( $slave_states['remove-error'][ $site_id ] ) ) {
			return 'remove-error';
		}

		if ( isset( $slave_states['new-error'][ $site_id ] ) ) {
			return 'new-error';
		}

		return 'new';
	}

	/**
	 * Get the remote post ID for a site.
	 *
	 * @param int                  $site_id      Site post ID.
	 * @param array<string, mixed> $slave_states Slave states.
	 * @return int Remote post ID or 0.
	 */
	private function get_remote_post_id( int $site_id, array $slave_states ): int {
		return isset( $slave_states['success'][ $site_id ] )
			? (int) $slave_states['success'][ $site_id ]
			: 0;
	}

	/**
	 * Check if this should be a new push.
	 *
	 * @param string $state Current state.
	 * @return bool True if new push, false for update.
	 */
	private function is_new_push( string $state ): bool {
		return in_array( $state, array( 'new', 'new-error' ), true );
	}

	/**
	 * Acquire the syndication lock.
	 *
	 * @return bool True if lock acquired.
	 */
	private function acquire_lock(): bool {
		if ( 'locked' === get_transient( self::LOCK_TRANSIENT ) ) {
			return false;
		}

		set_transient( self::LOCK_TRANSIENT, 'locked', self::LOCK_DURATION );
		return true;
	}

	/**
	 * Release the syndication lock.
	 */
	private function release_lock(): void {
		delete_transient( self::LOCK_TRANSIENT );
	}
}
