<?php

/**
 * Failed Syndication Auto Retry
 *
 * Watches syndication events and handles site-related failures.
 *
 * There is a cron setup in class-wp-push-syndication-server.php:1266 which will
 * retry a failed pull X times (set in admin settings) and when that limit is met
 * the site becomes 'disabled'. The retry interval could be anything from 5min to 1hr
 * or longer.
 *
 * After each failed retry we don't want to wait the retry interval to try again
 * if the remote server was simply unreachable for a brief spell. This auto retry
 * bypasses the retry interval and retries the pull once a minute up to 3 tries.
 *
 * @uses Syndication_Logger
 */

class Failed_Syndication_Auto_Retry {

	/**
	 * Hook into WordPress
	 */
	public function __construct() {

		// Watch the push_syndication_event action for site pull failures
		add_action( 'push_syndication_after_event_pull_failure', array( $this, 'handle_pull_failure_event' ), 10, 2 );

		// Watch the push_syndication_event action for site pull successes
		add_action( 'push_syndication_after_event_pull_success', array( $this, 'handle_pull_success_event' ), 10, 2 );
	}

	/**
	 * Handle a site pull failure event
	 *
	 * @param $site_id         int    The post id of the site we need to retry
	 * @param $failed_attempts int    The number of pull failures this site has experienced
	 *
	 * @return null
	 */
	public function handle_pull_failure_event( $site_id = 0, $failed_attempts = 0 ) {

		$site_auto_retry_count = 0;
		$site_id         = (int) $site_id;
		$failed_attempts = (int) $failed_attempts;

		// Fetch the allowable number of max pull attempts before the site is marked as 'disabled'
		$max_pull_attempts = (int) get_option( 'push_syndication_max_pull_attempts', 0 );

		// Bail if we've already met the max pull attempt count
		if ( ! $max_pull_attempts ) {
			return;
		}

		// Only proceed if we have a valid site id
		if ( 0 !== $site_id ) {

			// Only proceed if we haven't hit the pull attempt ceiling
			if ( $failed_attempts < $max_pull_attempts ) {

				// Fetch the number of times we've tried to auto-retry
				$site_auto_retry_count = (int) get_post_meta(
					$site_id,
					'syn_failed_auto_retry_attempts',
					true
				);

				// Allow the default auto retry to be filtered
				$auto_retry_limit = apply_filters(
					'pull_syndication_failure_auto_retry_limit',
					3 // By default, only auto retry 3 times
				);

				// Are we still below the auto retry limit?
				if ( $site_auto_retry_count < $auto_retry_limit ) {

					// Yes we are..
					$site = get_post( $site_id );

					// Remove any previous auto retry event calls because we're
					// scheduling these events < 10min apart
					wp_clear_scheduled_hook( 'syndication_failure_auto_retry_interval' );

					// Schedule a pull retry for one minute in the future
					wp_schedule_single_event(
						apply_filters(
							'syndication_failure_auto_retry_interval',
							time() + MINUTE_IN_SECONDS // run in one minute by default
						),
						'syn_pull_content',            // fire the pull content hook
						array( array( $site ) )     // the site which failed to pull
					);

					// Increment our auto retry counter
					$site_auto_retry_count++;

					// And update the post meta auto retry count
					update_post_meta(
						$site_id,
						'syn_failed_auto_retry_attempts',
						$site_auto_retry_count
					);
				} else {
					// Clean the auto retries
					$this->clean_auto_retry( $site_id );
				}
			}
		}
	}


	/**
	 * Handle a site pull success event
	 *
	 * @param $site_id         int    The post id of the site which just successfully pulled
	 * @param $failed_attempts int    The number of pull failures this site has experienced
	 * @return null
	 */
	public function handle_pull_success_event( $site_id = 0, $failed_attempts = 0 ) {

		// Clean the auto retries
		$this->clean_auto_retry( $site_id );
	}


	/**
	 * Cleanup after the auto retry routine
	 *
	 * + Remove any remaining scheduled event hooks
	 * + Remove the retry counter from the site post meta
	 *
	 * @param $site_id int The site id to clean
	 */
	public function clean_auto_retry( $site_id ) {
		// Remove any auto retry event calls
		wp_clear_scheduled_hook( 'syndication_failure_auto_retry_interval' );

		// Remove the auto retry if there was one
		delete_post_meta( $site_id, 'syn_failed_auto_retry_attempts' );
	}
}
