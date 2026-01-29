<?php
/**
 * Push service interface.
 *
 * @package Automattic\Syndication\Application\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\Contracts;

use Automattic\Syndication\Application\DTO\PushResult;

/**
 * Interface for push syndication service.
 */
interface PushServiceInterface {

	/**
	 * Push a post to multiple sites.
	 *
	 * @param int   $post_id  The local post ID.
	 * @param int[] $site_ids Array of site post IDs to push to.
	 * @return PushResult[] Array of results keyed by site ID.
	 */
	public function push_to_sites( int $post_id, array $site_ids ): array;

	/**
	 * Push a post to a single site.
	 *
	 * @param int                       $post_id      The local post ID.
	 * @param int                       $site_id      The site post ID.
	 * @param array<string, mixed>|null $slave_states Optional slave states array (modified by reference).
	 * @return PushResult The push result.
	 */
	public function push_to_site( int $post_id, int $site_id, ?array &$slave_states = null ): PushResult;

	/**
	 * Delete a post from a site.
	 *
	 * @param int $post_id The local post ID.
	 * @param int $site_id The site post ID.
	 * @return PushResult The delete result.
	 */
	public function delete_from_site( int $post_id, int $site_id ): PushResult;
}
