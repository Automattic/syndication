<?php
/**
 * Site Failure Monitur
 *
 * Watches syndication events and handles site-related failures.
 *
 * @uses Syndication_Logger
 */

namespace Automattic\Syndication;

class Syndication_Site_Failure_Monitor {

	/**
	 * Setup
	 */
	public function __construct() {
		add_action( 'push_syndication_after_event_pull_failure', array( $this, 'handle_pull_failure_event' ), 10, 2 );
	}

	/**
	 * Handle the pull failure event. If the number of failures exceeds the maximum attempts set in the options, then disable the site.
	 *
	 * @param $site_id
	 * @param $count
	 */
	public function handle_pull_failure_event( $site_id, $count ) {
		global $settings_manager;
		$site_id = (int) $site_id;

		$max_pull_attempts = (int) $settings_manager->get_setting( 'push_syndication_max_pull_attempts', 0 );

		if ( ! $max_pull_attempts ) {
			return;
		}

		if ( $count >= $max_pull_attempts ) {
			// Disable the site.
			update_post_meta( $site_id, 'syn_site_enabled', false );

			// Reset the event counter.
			do_action( 'push_syndication_reset_event', 'pull_failure', $site_id );

			// Log what happened.
			Syndication_Logger::log_post_error( $site_id, 'error', sprintf( __( 'Site disabled after %d pull failure(s).', 'push-syndication' ), $count ) );
		}
	}
}
