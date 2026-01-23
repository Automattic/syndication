<?php
/**
 * Consolidated syndication logging system.
 *
 * @package Automattic\Syndication\Infrastructure\Logging
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Logging;

/**
 * Handles consolidated logging for syndication events.
 *
 * Pull logs: One entry per sync run per site.
 * Push logs: One entry per push event showing all target sites.
 */
final class SyndicationLog {

	/**
	 * Log entry statuses.
	 */
	public const STATUS_SUCCESS = 'success';
	public const STATUS_PARTIAL = 'partial';
	public const STATUS_ERROR   = 'error';
	public const STATUS_SKIPPED = 'skipped';

	/**
	 * Post action types.
	 */
	public const ACTION_CREATED = 'created';
	public const ACTION_UPDATED = 'updated';
	public const ACTION_DELETED = 'deleted';
	public const ACTION_SKIPPED = 'skipped';
	public const ACTION_FAILED  = 'failed';

	/**
	 * Default maximum log entries per site.
	 */
	private const DEFAULT_LOG_ENTRY_LIMIT = 100;

	/**
	 * Meta key for per-site log limit.
	 */
	private const LOG_LIMIT_META_KEY = 'syn_log_limit';

	/**
	 * Meta key for pull logs.
	 */
	private const PULL_LOG_META_KEY = 'syn_pull_log';

	/**
	 * Option key for push logs.
	 */
	private const PUSH_LOG_OPTION_KEY = 'syn_push_log';

	/**
	 * Current pull log entry being built.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $current_pull_entry = null;

	/**
	 * Current push log entry being built.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $current_push_entry = null;

	/**
	 * Site ID for current pull operation.
	 *
	 * @var int|null
	 */
	private ?int $current_site_id = null;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Start a new pull sync run.
	 *
	 * @param int    $site_id   The site ID being pulled from.
	 * @param string $site_name The site name.
	 */
	public function start_pull( int $site_id, string $site_name ): void {
		$this->current_site_id    = $site_id;
		$this->current_pull_entry = array(
			'time'      => current_time( 'mysql' ),
			'timestamp' => time(),
			'site_id'   => $site_id,
			'site_name' => $site_name,
			'status'    => self::STATUS_SUCCESS,
			'summary'   => array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'failed'  => 0,
			),
			'posts'     => array(),
		);
	}

	/**
	 * Log a pulled post result.
	 *
	 * @param string      $action    The action taken (created, updated, skipped, failed).
	 * @param int         $local_id  Local post ID (0 if failed).
	 * @param int         $remote_id Remote post ID.
	 * @param string      $title     Post title.
	 * @param string|null $error     Error message if failed.
	 */
	public function log_pulled_post(
		string $action,
		int $local_id,
		int $remote_id,
		string $title,
		?string $error = null
	): void {
		if ( null === $this->current_pull_entry ) {
			return;
		}

		$this->current_pull_entry['posts'][] = array(
			'action'    => $action,
			'local_id'  => $local_id,
			'remote_id' => $remote_id,
			'title'     => $title,
			'error'     => $error,
		);

		// Update summary counts.
		if ( isset( $this->current_pull_entry['summary'][ $action ] ) ) {
			++$this->current_pull_entry['summary'][ $action ];
		}

		// Update overall status based on results.
		if ( self::ACTION_FAILED === $action ) {
			if ( self::STATUS_SUCCESS === $this->current_pull_entry['status'] ) {
				$this->current_pull_entry['status'] = self::STATUS_PARTIAL;
			}
		}
	}

	/**
	 * End the current pull sync run and save the log.
	 *
	 * @param string|null $error Overall error message if the entire pull failed.
	 */
	public function end_pull( ?string $error = null ): void {
		if ( null === $this->current_pull_entry || null === $this->current_site_id ) {
			return;
		}

		// If there's an overall error, mark as error status.
		if ( null !== $error ) {
			$this->current_pull_entry['status'] = self::STATUS_ERROR;
			$this->current_pull_entry['error']  = $error;
		}

		// If all posts failed, mark as error.
		$summary = $this->current_pull_entry['summary'];
		if ( $summary['failed'] > 0 && 0 === $summary['created'] && 0 === $summary['updated'] ) {
			$this->current_pull_entry['status'] = self::STATUS_ERROR;
		}

		// If no posts were processed at all, mark as skipped.
		$total = $summary['created'] + $summary['updated'] + $summary['skipped'] + $summary['failed'];
		if ( 0 === $total && null === $error ) {
			$this->current_pull_entry['status'] = self::STATUS_SKIPPED;
		}

		$this->save_pull_log( $this->current_site_id, $this->current_pull_entry );

		$this->current_pull_entry = null;
		$this->current_site_id    = null;
	}

	/**
	 * Start a new push event.
	 *
	 * @param int    $post_id    Local post ID.
	 * @param string $post_title Local post title.
	 */
	public function start_push( int $post_id, string $post_title ): void {
		$this->current_push_entry = array(
			'time'       => current_time( 'mysql' ),
			'timestamp'  => time(),
			'post_id'    => $post_id,
			'post_title' => $post_title,
			'status'     => self::STATUS_SUCCESS,
			'sites'      => array(),
		);
	}

	/**
	 * Log a push to a site.
	 *
	 * @param int         $site_id   Target site ID.
	 * @param string      $site_name Target site name.
	 * @param string      $action    The action taken (created, updated, deleted, failed).
	 * @param int         $remote_id Remote post ID (0 if failed).
	 * @param string|null $error     Error message if failed.
	 */
	public function log_pushed_site(
		int $site_id,
		string $site_name,
		string $action,
		int $remote_id,
		?string $error = null
	): void {
		if ( null === $this->current_push_entry ) {
			return;
		}

		$this->current_push_entry['sites'][] = array(
			'site_id'   => $site_id,
			'site_name' => $site_name,
			'action'    => $action,
			'remote_id' => $remote_id,
			'error'     => $error,
		);

		// Update overall status based on results.
		if ( self::ACTION_FAILED === $action ) {
			if ( self::STATUS_SUCCESS === $this->current_push_entry['status'] ) {
				$this->current_push_entry['status'] = self::STATUS_PARTIAL;
			}
		}
	}

	/**
	 * End the current push event and save the log.
	 */
	public function end_push(): void {
		if ( null === $this->current_push_entry ) {
			return;
		}

		// Check if all sites failed.
		$failed_count  = 0;
		$success_count = 0;
		foreach ( $this->current_push_entry['sites'] as $site ) {
			if ( self::ACTION_FAILED === $site['action'] ) {
				++$failed_count;
			} else {
				++$success_count;
			}
		}

		if ( $failed_count > 0 && 0 === $success_count ) {
			$this->current_push_entry['status'] = self::STATUS_ERROR;
		}

		$this->save_push_log( $this->current_push_entry );

		$this->current_push_entry = null;
	}

	/**
	 * Save a pull log entry.
	 *
	 * @param int                  $site_id The site ID.
	 * @param array<string, mixed> $entry   The log entry.
	 */
	private function save_pull_log( int $site_id, array $entry ): void {
		$log = get_post_meta( $site_id, self::PULL_LOG_META_KEY, true );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Get per-site limit or use default.
		$limit = $this->get_site_log_limit( $site_id );

		// Trim old entries if over limit.
		if ( count( $log ) >= $limit ) {
			$log = array_slice( $log, -( $limit - 1 ) );
		}

		$log[] = $entry;

		update_post_meta( $site_id, self::PULL_LOG_META_KEY, $log );
	}

	/**
	 * Get the log limit for a site.
	 *
	 * @param int $site_id The site ID.
	 * @return int The log entry limit.
	 */
	private function get_site_log_limit( int $site_id ): int {
		$limit = get_post_meta( $site_id, self::LOG_LIMIT_META_KEY, true );

		if ( '' === $limit || ! is_numeric( $limit ) ) {
			$limit = self::DEFAULT_LOG_ENTRY_LIMIT;
		}

		$limit = (int) $limit;

		// Ensure reasonable bounds (1-1000).
		return max( 1, min( 1000, $limit ) );
	}

	/**
	 * Save a push log entry.
	 *
	 * @param array<string, mixed> $entry The log entry.
	 */
	private function save_push_log( array $entry ): void {
		$log = get_option( self::PUSH_LOG_OPTION_KEY, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		/**
		 * Filter the push log entry limit.
		 *
		 * @param int $limit The maximum number of push log entries to keep.
		 */
		$limit = (int) apply_filters( 'syn_push_log_limit', self::DEFAULT_LOG_ENTRY_LIMIT );
		$limit = max( 1, min( 1000, $limit ) );

		// Trim old entries if over limit.
		if ( count( $log ) >= $limit ) {
			$log = array_slice( $log, -( $limit - 1 ) );
		}

		$log[] = $entry;

		update_option( self::PUSH_LOG_OPTION_KEY, $log, false );
	}

	/**
	 * Get pull logs for a site or all sites.
	 *
	 * @param int|null    $site_id   Site ID to filter by, or null for all.
	 * @param int|null    $timestamp Filter to a specific timestamp.
	 * @param string|null $status    Filter by status.
	 * @param string|null $search    Search term for post titles.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_pull_logs(
		?int $site_id = null,
		?int $timestamp = null,
		?string $status = null,
		?string $search = null
	): array {
		$all_logs = array();

		if ( null !== $site_id ) {
			$site_logs = get_post_meta( $site_id, self::PULL_LOG_META_KEY, true );
			if ( is_array( $site_logs ) ) {
				$all_logs = $site_logs;
			}
		} else {
			// Get logs from all sites.
			$sites = get_posts(
				array(
					'post_type'      => 'syn_site',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
				)
			);

			foreach ( $sites as $id ) {
				$site_logs = get_post_meta( $id, self::PULL_LOG_META_KEY, true );
				if ( is_array( $site_logs ) ) {
					$all_logs = array_merge( $all_logs, $site_logs );
				}
			}
		}

		// Apply filters.
		$all_logs = array_filter(
			$all_logs,
			function ( $entry ) use ( $timestamp, $status, $search ) {
				// Filter by timestamp.
				if ( null !== $timestamp && ( $entry['timestamp'] ?? 0 ) !== $timestamp ) {
					return false;
				}

				// Filter by status.
				if ( null !== $status && ( $entry['status'] ?? '' ) !== $status ) {
					return false;
				}

				// Filter by search term in post titles.
				if ( null !== $search && '' !== $search ) {
					$found = false;
					foreach ( $entry['posts'] ?? array() as $post ) {
						if ( stripos( $post['title'] ?? '', $search ) !== false ) {
							$found = true;
							break;
						}
					}
					if ( ! $found ) {
						return false;
					}
				}

				return true;
			}
		);

		// Sort by timestamp descending (newest first).
		usort(
			$all_logs,
			function ( $a, $b ) {
				return ( $b['timestamp'] ?? 0 ) - ( $a['timestamp'] ?? 0 );
			}
		);

		return $all_logs;
	}

	/**
	 * Get push logs.
	 *
	 * @param int|null    $post_id   Filter by local post ID.
	 * @param int|null    $timestamp Filter to a specific timestamp.
	 * @param string|null $status    Filter by status.
	 * @param string|null $search    Search term.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_push_logs(
		?int $post_id = null,
		?int $timestamp = null,
		?string $status = null,
		?string $search = null
	): array {
		$all_logs = get_option( self::PUSH_LOG_OPTION_KEY, array() );

		if ( ! is_array( $all_logs ) ) {
			$all_logs = array();
		}

		// Apply filters.
		$all_logs = array_filter(
			$all_logs,
			function ( $entry ) use ( $post_id, $timestamp, $status, $search ) {
				// Filter by post ID.
				if ( null !== $post_id && ( $entry['post_id'] ?? 0 ) !== $post_id ) {
					return false;
				}

				// Filter by timestamp.
				if ( null !== $timestamp && ( $entry['timestamp'] ?? 0 ) !== $timestamp ) {
					return false;
				}

				// Filter by status.
				if ( null !== $status && ( $entry['status'] ?? '' ) !== $status ) {
					return false;
				}

				// Filter by search term.
				if ( null !== $search && '' !== $search ) {
					$found = false;
					// Search in post title.
					if ( stripos( $entry['post_title'] ?? '', $search ) !== false ) {
						$found = true;
					}
					// Search in site names.
					if ( ! $found ) {
						foreach ( $entry['sites'] ?? array() as $site ) {
							if ( stripos( $site['site_name'] ?? '', $search ) !== false ) {
								$found = true;
								break;
							}
						}
					}
					if ( ! $found ) {
						return false;
					}
				}

				return true;
			}
		);

		// Sort by timestamp descending (newest first).
		usort(
			$all_logs,
			function ( $a, $b ) {
				return ( $b['timestamp'] ?? 0 ) - ( $a['timestamp'] ?? 0 );
			}
		);

		return $all_logs;
	}

	/**
	 * Get all unique sites that have pull logs.
	 *
	 * @return array<int, array{id: int, name: string}>
	 */
	public function get_sites_with_logs(): array {
		$sites = get_posts(
			array(
				'post_type'      => 'syn_site',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => self::PULL_LOG_META_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$result = array();
		foreach ( $sites as $site ) {
			$result[] = array(
				'id'   => $site->ID,
				'name' => $site->post_title,
			);
		}

		return $result;
	}

	/**
	 * Clear all logs for a site.
	 *
	 * @param int $site_id The site ID.
	 */
	public function clear_pull_logs( int $site_id ): void {
		delete_post_meta( $site_id, self::PULL_LOG_META_KEY );
	}

	/**
	 * Clear all push logs.
	 */
	public function clear_push_logs(): void {
		delete_option( self::PUSH_LOG_OPTION_KEY );
	}
}
