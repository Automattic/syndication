<?php
/**
 * Pull syndication service.
 *
 * @package Automattic\Syndication\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\Services;

use Automattic\Syndication\Application\DTO\PullResult;
use Automattic\Syndication\Domain\Contracts\PullTransportInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use WP_Post;

/**
 * Service for pulling content from remote sites.
 *
 * Orchestrates the pull syndication workflow including transport creation,
 * post import/update, and result tracking.
 */
final class PullService {

	/**
	 * Transport factory.
	 *
	 * @var TransportFactoryInterface
	 */
	private readonly TransportFactoryInterface $transport_factory;

	/**
	 * Whether to update existing posts.
	 *
	 * @var bool
	 */
	private bool $update_existing = true;

	/**
	 * Constructor.
	 *
	 * @param TransportFactoryInterface $transport_factory Transport factory.
	 */
	public function __construct( TransportFactoryInterface $transport_factory ) {
		$this->transport_factory = $transport_factory;
	}

	/**
	 * Set whether to update existing posts.
	 *
	 * @param bool $update Whether to update.
	 * @return self
	 */
	public function set_update_existing( bool $update ): self {
		$this->update_existing = $update;
		return $this;
	}

	/**
	 * Pull content from multiple sites.
	 *
	 * @param int[] $site_ids Array of site post IDs.
	 * @return PullResult[] Array of results keyed by site ID.
	 */
	public function pull_from_sites( array $site_ids ): array {
		$this->begin_import();

		try {
			$results = array();

			foreach ( $site_ids as $site_id ) {
				$results[ $site_id ] = $this->pull_from_site( $site_id );
			}

			return $results;
		} finally {
			$this->end_import();
		}
	}

	/**
	 * Pull content from a single site.
	 *
	 * @param int $site_id The site post ID.
	 * @return PullResult The pull result.
	 */
	public function pull_from_site( int $site_id ): PullResult {
		$site = get_post( $site_id );

		if ( ! $site instanceof WP_Post || 'syn_site' !== $site->post_type ) {
			return PullResult::failure( $site_id, 'invalid_site', 'Site not found.' );
		}

		// Check if site is enabled.
		$enabled = get_post_meta( $site_id, 'syn_site_enabled', true );
		if ( 'on' !== $enabled ) {
			return PullResult::skipped( $site_id, 'Site is disabled.' );
		}

		$transport = $this->transport_factory->create_pull_transport( $site_id );

		if ( ! $transport instanceof PullTransportInterface ) {
			return PullResult::failure( $site_id, 'invalid_transport', 'Could not create pull transport.' );
		}

		// Pull posts from remote site.
		$posts = $transport->pull();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$posts = apply_filters( 'syn_pre_pull_posts', $posts, $site, null );

		if ( empty( $posts ) ) {
			$this->update_last_pull_time( $site_id );
			return PullResult::success( $site_id, 0, 0 );
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $posts as $post_data ) {
			$result = $this->process_pulled_post( $post_data, $site_id, $site );

			switch ( $result['status'] ) {
				case 'created':
					++$created;
					break;
				case 'updated':
					++$updated;
					break;
				case 'skipped':
					++$skipped;
					break;
				case 'error':
					$errors[] = $result['message'];
					break;
			}
		}

		$this->update_last_pull_time( $site_id );

		if ( ! empty( $errors ) ) {
			return PullResult::partial( $site_id, $created, $updated, $skipped, $errors );
		}

		return PullResult::success( $site_id, $created, $updated );
	}

	/**
	 * Process a single pulled post.
	 *
	 * @param array<string, mixed> $post_data Pulled post data.
	 * @param int                  $site_id   Site post ID.
	 * @param WP_Post              $site      Site post object.
	 * @return array{status: string, message: string, post_id: int}
	 */
	private function process_pulled_post( array $post_data, int $site_id, WP_Post $site ): array {
		// Require a GUID for identification.
		if ( empty( $post_data['post_guid'] ) ) {
			return array(
				'status'  => 'error',
				'message' => 'Post missing GUID.',
				'post_id' => 0,
			);
		}

		$existing_id = $this->find_post_by_guid( $post_data['post_guid'] );

		if ( $existing_id ) {
			return $this->update_existing_post( $existing_id, $post_data, $site_id, $site );
		}

		return $this->create_new_post( $post_data, $site_id, $site );
	}

	/**
	 * Create a new post from pulled data.
	 *
	 * @param array<string, mixed> $post_data Pulled post data.
	 * @param int                  $site_id   Site post ID.
	 * @param WP_Post              $site      Site post object.
	 * @return array{status: string, message: string, post_id: int}
	 */
	private function create_new_post( array $post_data, int $site_id, WP_Post $site ): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$shortcircuit = apply_filters( 'syn_pre_pull_new_post_shortcircuit', false, $post_data, $site, '', null );

		if ( true === $shortcircuit ) {
			return array(
				'status'  => 'skipped',
				'message' => 'Filtered out.',
				'post_id' => 0,
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$post_data = apply_filters( 'syn_pull_new_post', $post_data, $site, null );

		// Prepare data for wp_insert_post.
		$insert_data = $this->prepare_insert_data( $post_data );
		$result      = wp_insert_post( $insert_data, true );

		if ( is_wp_error( $result ) ) {
			return array(
				'status'  => 'error',
				'message' => $result->get_error_message(),
				'post_id' => 0,
			);
		}

		$post_id = (int) $result;

		// Store syndication metadata.
		update_post_meta( $post_id, 'syn_post_guid', $post_data['post_guid'] );
		update_post_meta( $post_id, 'syn_source_site_id', $site_id );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'syn_post_pull_new_post', $post_id, $post_data, $site, '', null );

		return array(
			'status'  => 'created',
			'message' => '',
			'post_id' => $post_id,
		);
	}

	/**
	 * Update an existing post from pulled data.
	 *
	 * @param int                  $post_id   Existing post ID.
	 * @param array<string, mixed> $post_data Pulled post data.
	 * @param int                  $site_id   Site post ID.
	 * @param WP_Post              $site      Site post object.
	 * @return array{status: string, message: string, post_id: int}
	 */
	private function update_existing_post( int $post_id, array $post_data, int $site_id, WP_Post $site ): array {
		if ( ! $this->update_existing ) {
			return array(
				'status'  => 'skipped',
				'message' => 'Update disabled.',
				'post_id' => $post_id,
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$shortcircuit = apply_filters( 'syn_pre_pull_edit_post_shortcircuit', false, $post_data, $site, '', null );

		if ( true === $shortcircuit ) {
			return array(
				'status'  => 'skipped',
				'message' => 'Filtered out.',
				'post_id' => $post_id,
			);
		}

		$post_data['ID'] = $post_id;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$post_data = apply_filters( 'syn_pull_edit_post', $post_data, $site, null );

		// Prepare data for wp_update_post.
		$update_data       = $this->prepare_insert_data( $post_data );
		$update_data['ID'] = $post_id;

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return array(
				'status'  => 'error',
				'message' => $result->get_error_message(),
				'post_id' => $post_id,
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'syn_post_pull_edit_post', $post_id, $post_data, $site, '', null );

		return array(
			'status'  => 'updated',
			'message' => '',
			'post_id' => $post_id,
		);
	}

	/**
	 * Prepare data for wp_insert_post/wp_update_post.
	 *
	 * @param array<string, mixed> $post_data Pulled post data.
	 * @return array<string, mixed> Data for WordPress post functions.
	 */
	private function prepare_insert_data( array $post_data ): array {
		$data = array(
			'post_title'   => $post_data['post_title'] ?? '',
			'post_content' => $post_data['post_content'] ?? '',
			'post_excerpt' => $post_data['post_excerpt'] ?? '',
			'post_status'  => $post_data['post_status'] ?? 'draft',
			'post_type'    => $post_data['post_type'] ?? 'post',
		);

		if ( ! empty( $post_data['post_date'] ) ) {
			$data['post_date'] = $post_data['post_date'];
		}

		if ( ! empty( $post_data['post_date_gmt'] ) ) {
			$data['post_date_gmt'] = $post_data['post_date_gmt'];
		}

		if ( isset( $post_data['ID'] ) ) {
			$data['ID'] = $post_data['ID'];
		}

		return $data;
	}

	/**
	 * Find a post by its syndication GUID.
	 *
	 * @param string $guid The syndication GUID.
	 * @return int|null Post ID or null.
	 */
	private function find_post_by_guid( string $guid ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom meta lookup, caching not beneficial here.
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'syn_post_guid' AND meta_value = %s LIMIT 1",
				$guid
			)
		);

		return $post_id ? (int) $post_id : null;
	}

	/**
	 * Update the last pull time for a site.
	 *
	 * @param int $site_id Site post ID.
	 */
	private function update_last_pull_time( int $site_id ): void {
		update_post_meta( $site_id, 'syn_last_pull_time', time() );
	}

	/**
	 * Begin import mode.
	 */
	private function begin_import(): void {
		if ( ! defined( 'WP_IMPORTING' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant.
			define( 'WP_IMPORTING', true );
		}

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );
	}

	/**
	 * End import mode.
	 */
	private function end_import(): void {
		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
	}
}
