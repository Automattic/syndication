<?php
/**
 * Site repository implementation.
 *
 * @package Automattic\Syndication\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Repositories;

use Automattic\Syndication\Domain\Contracts\EncryptorInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use Automattic\Syndication\Domain\ValueObjects\SiteConfig;
use Automattic\Syndication\Domain\ValueObjects\SiteCredentials;
use WP_Post;
use WP_Query;

/**
 * WordPress-based site repository.
 *
 * Stores sites as the syn_site custom post type with meta data.
 */
final class SiteRepository implements SiteRepositoryInterface {

	/**
	 * Post type for sites.
	 */
	private const POST_TYPE = 'syn_site';

	/**
	 * Meta key prefix.
	 */
	private const META_PREFIX = 'syn_site_';

	/**
	 * The encryptor for sensitive data.
	 *
	 * @var EncryptorInterface
	 */
	private readonly EncryptorInterface $encryptor;

	/**
	 * Constructor.
	 *
	 * @param EncryptorInterface $encryptor Encryptor for credentials.
	 */
	public function __construct( EncryptorInterface $encryptor ) {
		$this->encryptor = $encryptor;
	}

	/**
	 * Get a site by its ID.
	 *
	 * @param int $site_id The site post ID.
	 * @return SiteConfig|null The site configuration, or null if not found.
	 */
	public function get( int $site_id ): ?SiteConfig {
		$post = get_post( $site_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $this->build_config_from_post( $post );
	}

	/**
	 * Get all enabled sites.
	 *
	 * @return array<SiteConfig> Array of site configurations.
	 */
	public function get_enabled(): array {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_PREFIX . 'enabled',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		return array_map(
			fn( WP_Post $post ) => $this->build_config_from_post( $post ),
			$query->posts
		);
	}

	/**
	 * Get sites in a specific group.
	 *
	 * @param int $group_id The sitegroup term ID.
	 * @return array<SiteConfig> Array of site configurations.
	 */
	public function get_by_group( int $group_id ): array {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'syn_sitegroup',
						'field'    => 'term_id',
						'terms'    => $group_id,
					),
				),
			)
		);

		return array_map(
			fn( WP_Post $post ) => $this->build_config_from_post( $post ),
			$query->posts
		);
	}

	/**
	 * Get credentials for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return SiteCredentials The site credentials.
	 */
	public function get_credentials( int $site_id ): SiteCredentials {
		$token    = get_post_meta( $site_id, self::META_PREFIX . 'token', true );
		$username = get_post_meta( $site_id, self::META_PREFIX . 'username', true );
		$password = get_post_meta( $site_id, self::META_PREFIX . 'password', true );

		// Decrypt sensitive fields.
		if ( is_string( $token ) && '' !== $token ) {
			$decrypted = $this->encryptor->decrypt( $token );
			$token     = is_string( $decrypted ) ? $decrypted : '';
		}

		if ( is_string( $password ) && '' !== $password ) {
			$decrypted = $this->encryptor->decrypt( $password );
			$password  = is_string( $decrypted ) ? $decrypted : '';
		}

		if ( is_string( $token ) && '' !== $token ) {
			return SiteCredentials::from_token( $token );
		}

		if ( is_string( $username ) && '' !== $username && is_string( $password ) ) {
			return SiteCredentials::from_username_password( $username, $password );
		}

		return SiteCredentials::empty();
	}

	/**
	 * Save site configuration.
	 *
	 * @param SiteConfig $config The site configuration to save.
	 * @return bool True on success, false on failure.
	 */
	public function save( SiteConfig $config ): bool {
		$site_id = $config->get_site_id();

		if ( ! $this->exists( $site_id ) ) {
			return false;
		}

		update_post_meta( $site_id, self::META_PREFIX . 'url', $config->get_url() );
		update_post_meta( $site_id, self::META_PREFIX . 'id', $config->get_remote_site_id() );
		update_post_meta( $site_id, self::META_PREFIX . 'enabled', $config->is_enabled() ? '1' : '0' );

		return true;
	}

	/**
	 * Save site credentials.
	 *
	 * @param int             $site_id     The site post ID.
	 * @param SiteCredentials $credentials The credentials to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_credentials( int $site_id, SiteCredentials $credentials ): bool {
		if ( ! $this->exists( $site_id ) ) {
			return false;
		}

		if ( $credentials->has_token() ) {
			$token = $credentials->get_token();
			if ( null !== $token ) {
				$encrypted = $this->encryptor->encrypt( $token );
				update_post_meta( $site_id, self::META_PREFIX . 'token', $encrypted );
			}
		}

		if ( $credentials->has_username_password() ) {
			$username = $credentials->get_username();
			$password = $credentials->get_password();

			if ( null !== $username ) {
				update_post_meta( $site_id, self::META_PREFIX . 'username', $username );
			}

			if ( null !== $password ) {
				$encrypted = $this->encryptor->encrypt( $password );
				update_post_meta( $site_id, self::META_PREFIX . 'password', $encrypted );
			}
		}

		return true;
	}

	/**
	 * Enable a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success, false on failure.
	 */
	public function enable( int $site_id ): bool {
		if ( ! $this->exists( $site_id ) ) {
			return false;
		}

		return (bool) update_post_meta( $site_id, self::META_PREFIX . 'enabled', '1' );
	}

	/**
	 * Disable a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success, false on failure.
	 */
	public function disable( int $site_id ): bool {
		if ( ! $this->exists( $site_id ) ) {
			return false;
		}

		return (bool) update_post_meta( $site_id, self::META_PREFIX . 'enabled', '0' );
	}

	/**
	 * Check if a site exists.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True if the site exists, false otherwise.
	 */
	public function exists( int $site_id ): bool {
		$post = get_post( $site_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * Build a SiteConfig from a WP_Post.
	 *
	 * @param WP_Post $post The post object.
	 * @return SiteConfig The site configuration.
	 */
	private function build_config_from_post( WP_Post $post ): SiteConfig {
		$url            = get_post_meta( $post->ID, self::META_PREFIX . 'url', true );
		$remote_site_id = get_post_meta( $post->ID, self::META_PREFIX . 'id', true );
		$transport_type = get_post_meta( $post->ID, 'syn_transport_type', true );
		$enabled        = get_post_meta( $post->ID, self::META_PREFIX . 'enabled', true );

		return SiteConfig::create(
			$post->ID,
			is_string( $url ) ? $url : '',
			is_string( $remote_site_id ) ? $remote_site_id : '',
			is_string( $transport_type ) ? $transport_type : '',
			'1' === $enabled || true === $enabled
		);
	}
}
