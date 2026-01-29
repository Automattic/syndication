<?php
/**
 * Pull service interface.
 *
 * @package Automattic\Syndication\Application\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\Contracts;

use Automattic\Syndication\Application\DTO\PullResult;

/**
 * Interface for pull syndication service.
 */
interface PullServiceInterface {

	/**
	 * Set whether to update existing posts.
	 *
	 * @param bool $update Whether to update.
	 * @return self
	 */
	public function set_update_existing( bool $update ): self;

	/**
	 * Pull content from multiple sites.
	 *
	 * @param int[] $site_ids Array of site post IDs.
	 * @return PullResult[] Array of results keyed by site ID.
	 */
	public function pull_from_sites( array $site_ids ): array;

	/**
	 * Pull content from a single site.
	 *
	 * @param int $site_id The site post ID.
	 * @return PullResult The pull result.
	 */
	public function pull_from_site( int $site_id ): PullResult;
}
