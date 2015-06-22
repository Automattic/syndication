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
		$site_id               = (int) $site_id;
		$failed_attempts       = (int) $failed_attempts;
		$cleanup               = false;

		// Fetch the allowable number of max pull attempts before the site is marked as 'disabled'
		$max_pull_attempts = (int) get_option( 'push_syndication_max_pull_attempts', 0 );

		// Bail if we've already met the max pull attempt count
		if ( ! $max_pull_attempts ) {
			return;
		}

		// Only proceed if we have a valid site id
		if ( 0 !== $site_id ) {

			// Fetch the site post
			$site = get_post( $site_id );

			// Fetch the site url
			$site_url = get_post_meta( $site->ID, 'syn_feed_url', true );

			// Fetch the number of times we've tried to auto-retry
			$site_auto_retry_count = (int) get_post_meta( $site_id, 'syn_failed_auto_retry_attempts', true );

			// Only proceed if we haven't hit the pull attempt ceiling
			if ( $failed_attempts < $max_pull_attempts ) {

				// Allow the default auto retry to be filtered
				// By default, only auto retry 3 times
				$auto_retry_limit = apply_filters( 'pull_syndication_failure_auto_retry_limit', 3 );

				// Store the current time for repeated use below
				$time_now = time();

				// Create a string time to be sent to the logger
				// Add 1 so our log items appear to occur a second later
				// and hence order better in the log viewer
				// without this, sometimes when the pull occurs quickly
				// these log items appear to occur at the same time as the failure
				$log_time = date( 'Y-m-d H:i:s', $time_now + 1 );

				// Are we still below the auto retry limit?
				if ( $site_auto_retry_count < $auto_retry_limit ) {

					// Yes we are..

					// Run in one minute by default
					$auto_retry_interval = apply_filters( 'syndication_failure_auto_retry_interval', $time_now + MINUTE_IN_SECONDS );

					Syndication_Logger::log_post_info( $site->ID, $status = 'start_auto_retry', $message = sprintf( __( 'Connection retry %d of %d to %s in %s..', 'push-syndication' ), $site_auto_retry_count + 1, $auto_retry_limit, $site_url, human_time_diff( $time_now, $auto_retry_interval ) ), $log_time, $extra = array() );

					// Schedule a pull retry for one minute in the future
					wp_schedule_single_event(
						$auto_retry_interval,     // retry in X time
						'syn_pull_content',       // fire the syndication_auto_retry hook
						array( array( $site ) )   // the site which failed to pull
					);

					// Increment our auto retry counter
					$site_auto_retry_count++;

					// And update the post meta auto retry count
					update_post_meta( $site->ID, 'syn_failed_auto_retry_attempts', $site_auto_retry_count );

				} else {

					// Auto Retry limit met
					// Let's cleanup after ourselves
					$cleanup = true ;
				}
			} else {

				// Retry attempt limit met
				// The site has been disabled, let's cleanup after ourselves
				$cleanup = true;
			}

			// Should we cleanup after ourselves?
			if ( $cleanup ) {

				// Remove the auto retry if there was one
				delete_post_meta( $site->ID, 'syn_failed_auto_retry_attempts' );

				Syndication_Logger::log_post_error( $site->ID, $status = 'end_auto_retry', $message = sprintf( __( 'Failed %d times to reconnect to %s', 'push-syndication' ), $site_auto_retry_count, $site_url ), $log_time, $extra = array() );
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

		// Remove the auto retry if there was one
		delete_post_meta( $site_id, 'syn_failed_auto_retry_attempts' );
	}
}