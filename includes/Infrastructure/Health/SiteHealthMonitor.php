<?php
/**
 * Site health monitoring for syndication failures.
 *
 * @package Automattic\Syndication\Infrastructure\Health
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Health;

/**
 * Monitors site health and handles pull failures.
 *
 * Consolidates functionality from legacy classes:
 * - Syndication_Event_Counter (failure counting)
 * - Syndication_Site_Failure_Monitor (site disabling)
 * - Failed_Syndication_Auto_Retry (immediate retries)
 */
final class SiteHealthMonitor {

	/**
	 * Option prefix for failure counts.
	 */
	private const FAILURE_COUNT_OPTION_PREFIX = 'syn_pull_failure_count_';

	/**
	 * Meta key for auto-retry attempts.
	 */
	private const AUTO_RETRY_META_KEY = 'syn_auto_retry_attempts';

	/**
	 * Default auto-retry limit.
	 */
	private const DEFAULT_AUTO_RETRY_LIMIT = 3;

	/**
	 * Default auto-retry interval in seconds.
	 */
	private const DEFAULT_AUTO_RETRY_INTERVAL = MINUTE_IN_SECONDS;

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Listen to legacy event hooks fired by transports.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
		add_action( 'push_syndication_event', array( $this, 'handle_legacy_event' ), 10, 2 );

		// Admin notice for disabled sites.
		add_action( 'admin_notices', array( $this, 'display_disabled_site_notices' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss_notice' ) );
	}

	/**
	 * Handle legacy syndication events from transports.
	 *
	 * @param string     $event_type The event type (pull_failure, pull_success).
	 * @param string|int $site_id    The site post ID.
	 */
	public function handle_legacy_event( string $event_type, $site_id ): void {
		$site_id = (int) $site_id;

		if ( 'pull_success' === $event_type ) {
			$this->handle_pull_completed( $site_id, true );
		} elseif ( 'pull_failure' === $event_type ) {
			$this->handle_pull_completed( $site_id, false );
		}
	}

	/**
	 * Handle a completed pull operation.
	 *
	 * @param int  $site_id The site post ID.
	 * @param bool $success Whether the pull was successful.
	 */
	public function handle_pull_completed( int $site_id, bool $success ): void {
		if ( $success ) {
			$this->handle_pull_success( $site_id );
		} else {
			$this->handle_pull_failure( $site_id );
		}
	}

	/**
	 * Handle a successful pull.
	 *
	 * @param int $site_id The site post ID.
	 */
	private function handle_pull_success( int $site_id ): void {
		// Reset failure count on success.
		$this->reset_failure_count( $site_id );

		// Clear any pending auto-retry attempts.
		delete_post_meta( $site_id, self::AUTO_RETRY_META_KEY );
	}

	/**
	 * Handle a failed pull.
	 *
	 * @param int $site_id The site post ID.
	 */
	private function handle_pull_failure( int $site_id ): void {
		$failure_count = $this->increment_failure_count( $site_id );
		$max_attempts  = $this->get_max_pull_attempts();

		// If no max attempts configured, just track failures but don't disable.
		if ( 0 === $max_attempts ) {
			return;
		}

		// Check if we should disable the site.
		if ( $failure_count >= $max_attempts ) {
			$this->disable_site( $site_id, $failure_count );
			return;
		}

		// Try auto-retry before the next scheduled pull.
		$this->schedule_auto_retry( $site_id );
	}

	/**
	 * Schedule an auto-retry for a failed site.
	 *
	 * @param int $site_id The site post ID.
	 */
	private function schedule_auto_retry( int $site_id ): void {
		$site = get_post( $site_id );
		if ( ! $site instanceof \WP_Post || 'syn_site' !== $site->post_type ) {
			return;
		}

		// Get current auto-retry count.
		$auto_retry_count = (int) get_post_meta( $site_id, self::AUTO_RETRY_META_KEY, true );

		/**
		 * Filter the auto-retry limit.
		 *
		 * @param int $limit   The maximum number of auto-retries.
		 * @param int $site_id The site post ID.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uses established syn_ prefix.
		$auto_retry_limit = (int) apply_filters( 'syn_auto_retry_limit', self::DEFAULT_AUTO_RETRY_LIMIT, $site_id );

		// Check if we've exceeded the auto-retry limit.
		if ( $auto_retry_count >= $auto_retry_limit ) {
			// Reset auto-retry count - will try again on next scheduled pull.
			delete_post_meta( $site_id, self::AUTO_RETRY_META_KEY );
			return;
		}

		/**
		 * Filter the auto-retry interval.
		 *
		 * @param int $interval The interval in seconds.
		 * @param int $site_id  The site post ID.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uses established syn_ prefix.
		$interval = (int) apply_filters( 'syn_auto_retry_interval', self::DEFAULT_AUTO_RETRY_INTERVAL, $site_id );

		// Schedule the retry.
		$retry_time = time() + $interval;

		wp_schedule_single_event(
			$retry_time,
			'syn_pull_content',
			array( array( $site ) )
		);

		// Increment auto-retry count.
		update_post_meta( $site_id, self::AUTO_RETRY_META_KEY, $auto_retry_count + 1 );
	}

	/**
	 * Disable a site after too many failures.
	 *
	 * @param int $site_id       The site post ID.
	 * @param int $failure_count The number of failures.
	 */
	private function disable_site( int $site_id, int $failure_count ): void {
		// Disable the site.
		update_post_meta( $site_id, 'syn_site_enabled', 'off' );

		// Reset counters.
		$this->reset_failure_count( $site_id );
		delete_post_meta( $site_id, self::AUTO_RETRY_META_KEY );

		// Store notice for admin display.
		$this->add_disabled_site_notice( $site_id, $failure_count );

		/**
		 * Fires when a site is disabled due to pull failures.
		 *
		 * @param int $site_id       The site post ID.
		 * @param int $failure_count The number of failures that triggered the disable.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uses established syn_ prefix.
		do_action( 'syn_site_disabled', $site_id, $failure_count );
	}

	/**
	 * Get the maximum pull attempts before disabling.
	 *
	 * @return int Maximum attempts (0 = no limit).
	 */
	private function get_max_pull_attempts(): int {
		return (int) get_option( 'push_syndication_max_pull_attempts', 0 );
	}

	/**
	 * Get the current failure count for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return int The failure count.
	 */
	private function get_failure_count( int $site_id ): int {
		return (int) get_option( self::FAILURE_COUNT_OPTION_PREFIX . $site_id, 0 );
	}

	/**
	 * Increment the failure count for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return int The new failure count.
	 */
	private function increment_failure_count( int $site_id ): int {
		$count = $this->get_failure_count( $site_id ) + 1;
		update_option( self::FAILURE_COUNT_OPTION_PREFIX . $site_id, $count, false );
		return $count;
	}

	/**
	 * Reset the failure count for a site.
	 *
	 * @param int $site_id The site post ID.
	 */
	private function reset_failure_count( int $site_id ): void {
		delete_option( self::FAILURE_COUNT_OPTION_PREFIX . $site_id );
	}

	/**
	 * Add a notice about a disabled site.
	 *
	 * @param int $site_id       The site post ID.
	 * @param int $failure_count The number of failures.
	 */
	private function add_disabled_site_notice( int $site_id, int $failure_count ): void {
		$notices   = get_option( 'syn_disabled_site_notices', array() );
		$notices[] = array(
			'site_id'       => $site_id,
			'failure_count' => $failure_count,
			'time'          => time(),
		);
		update_option( 'syn_disabled_site_notices', $notices, false );
	}

	/**
	 * Display admin notices for disabled sites.
	 */
	public function display_disabled_site_notices(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uses established syn_ prefix.
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$notices = get_option( 'syn_disabled_site_notices', array() );

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $index => $notice ) {
			$site = get_post( $notice['site_id'] );
			if ( ! $site instanceof \WP_Post ) {
				continue;
			}

			$dismiss_url = add_query_arg(
				array(
					'syn_dismiss_notice' => $index,
					'_wpnonce'           => wp_create_nonce( 'syn_dismiss_notice_' . $index ),
				)
			);

			printf(
				'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
				sprintf(
					/* translators: 1: site title, 2: failure count */
					esc_html__( 'Syndication: Site "%1$s" was disabled after %2$d pull failures.', 'push-syndication' ),
					esc_html( $site->post_title ),
					(int) $notice['failure_count']
				),
				esc_url( $dismiss_url ),
				esc_html__( 'Dismiss', 'push-syndication' )
			);
		}
	}

	/**
	 * Handle dismissing a notice.
	 */
	public function handle_dismiss_notice(): void {
		if ( ! isset( $_GET['syn_dismiss_notice'] ) ) {
			return;
		}

		$index = (int) $_GET['syn_dismiss_notice'];

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'syn_dismiss_notice_' . $index ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uses established syn_ prefix.
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$notices = get_option( 'syn_disabled_site_notices', array() );

		if ( isset( $notices[ $index ] ) ) {
			unset( $notices[ $index ] );
			$notices = array_values( $notices ); // Re-index.
			update_option( 'syn_disabled_site_notices', $notices, false );
		}

		// Redirect to remove query args.
		wp_safe_redirect( remove_query_arg( array( 'syn_dismiss_notice', '_wpnonce' ) ) );
		exit;
	}
}
