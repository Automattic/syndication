<?php
/**
 * Site configuration value object.
 *
 * @package Automattic\Syndication\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\ValueObjects;

/**
 * Immutable value object representing syndication site configuration.
 *
 * Contains the common configuration for a syndication target site,
 * including its URL, transport type, and enabled status.
 */
final class SiteConfig {

	/**
	 * The site post ID.
	 *
	 * @var int
	 */
	private int $site_id;

	/**
	 * The site URL.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * The remote blog/site identifier.
	 *
	 * @var string
	 */
	private string $remote_site_id;

	/**
	 * The transport type identifier.
	 *
	 * @var string
	 */
	private string $transport_type;

	/**
	 * Whether syndication is enabled for this site.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Constructor.
	 *
	 * @param int    $site_id        The site post ID.
	 * @param string $url            The site URL.
	 * @param string $remote_site_id The remote blog/site identifier.
	 * @param string $transport_type The transport type identifier.
	 * @param bool   $enabled        Whether syndication is enabled.
	 */
	private function __construct(
		int $site_id,
		string $url,
		string $remote_site_id,
		string $transport_type,
		bool $enabled
	) {
		$this->site_id        = $site_id;
		$this->url            = $url;
		$this->remote_site_id = $remote_site_id;
		$this->transport_type = $transport_type;
		$this->enabled        = $enabled;
	}

	/**
	 * Create a site configuration from individual values.
	 *
	 * @param int    $site_id        The site post ID.
	 * @param string $url            The site URL.
	 * @param string $remote_site_id The remote blog/site identifier.
	 * @param string $transport_type The transport type identifier.
	 * @param bool   $enabled        Whether syndication is enabled.
	 * @return self
	 */
	public static function create(
		int $site_id,
		string $url,
		string $remote_site_id,
		string $transport_type,
		bool $enabled = true
	): self {
		return new self( $site_id, $url, $remote_site_id, $transport_type, $enabled );
	}

	/**
	 * Get the site post ID.
	 *
	 * @return int
	 */
	public function get_site_id(): int {
		return $this->site_id;
	}

	/**
	 * Get the site URL.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return $this->url;
	}

	/**
	 * Get the remote site identifier.
	 *
	 * @return string
	 */
	public function get_remote_site_id(): string {
		return $this->remote_site_id;
	}

	/**
	 * Get the transport type.
	 *
	 * @return string
	 */
	public function get_transport_type(): string {
		return $this->transport_type;
	}

	/**
	 * Check if syndication is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Create a new instance with enabled status changed.
	 *
	 * @param bool $enabled The new enabled status.
	 * @return self
	 */
	public function with_enabled( bool $enabled ): self {
		return new self(
			$this->site_id,
			$this->url,
			$this->remote_site_id,
			$this->transport_type,
			$enabled
		);
	}
}
