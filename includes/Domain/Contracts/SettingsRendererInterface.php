<?php
/**
 * Settings renderer interface for transport configuration.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

use WP_Post;

/**
 * Interface for rendering and saving transport-specific settings.
 *
 * Transport implementations that need custom configuration fields
 * in the admin interface should implement this interface.
 */
interface SettingsRendererInterface {

	/**
	 * Display the settings form fields for this transport.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( WP_Post $site ): void;

	/**
	 * Save the settings from POST data.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function save_settings( int $site_id ): bool;
}
