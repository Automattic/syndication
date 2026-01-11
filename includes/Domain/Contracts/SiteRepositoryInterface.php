<?php
/**
 * Site repository interface.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

use Automattic\Syndication\Domain\ValueObjects\SiteConfig;
use Automattic\Syndication\Domain\ValueObjects\SiteCredentials;

/**
 * Interface for site storage operations.
 *
 * Sites are stored as a custom post type (syn_site) with meta data
 * for credentials and configuration.
 */
interface SiteRepositoryInterface {

	/**
	 * Get a site by its ID.
	 *
	 * @param int $site_id The site post ID.
	 * @return SiteConfig|null The site configuration, or null if not found.
	 */
	public function get( int $site_id ): ?SiteConfig;

	/**
	 * Get all enabled sites.
	 *
	 * @return array<SiteConfig> Array of site configurations.
	 */
	public function get_enabled(): array;

	/**
	 * Get sites in a specific group.
	 *
	 * @param int $group_id The sitegroup term ID.
	 * @return array<SiteConfig> Array of site configurations.
	 */
	public function get_by_group( int $group_id ): array;

	/**
	 * Get credentials for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return SiteCredentials The site credentials.
	 */
	public function get_credentials( int $site_id ): SiteCredentials;

	/**
	 * Save site configuration.
	 *
	 * @param SiteConfig $config The site configuration to save.
	 * @return bool True on success, false on failure.
	 */
	public function save( SiteConfig $config ): bool;

	/**
	 * Save site credentials.
	 *
	 * @param int             $site_id     The site post ID.
	 * @param SiteCredentials $credentials The credentials to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_credentials( int $site_id, SiteCredentials $credentials ): bool;

	/**
	 * Enable a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success, false on failure.
	 */
	public function enable( int $site_id ): bool;

	/**
	 * Disable a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success, false on failure.
	 */
	public function disable( int $site_id ): bool;

	/**
	 * Check if a site exists.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True if the site exists, false otherwise.
	 */
	public function exists( int $site_id ): bool;
}
