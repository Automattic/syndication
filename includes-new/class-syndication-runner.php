<?php

namespace Automattic\Syndication;

/**
 * Syndication
 *
 * The role of the syndication runner is to manage the site pull/push processes.
 * Sets up cron schedule whenever pull sites are added
 * or removed and handles management of individual cron jobs per site.
 * Automatically disables feed with multiple failures.
 *
 * @package Automattic\Syndication
 */
class Syndication_Runner {
	const CUSTOM_USER_AGENT = 'WordPress/Syndication Plugin';

	public  $push_syndicate_transports;


	/**
	 * Set up the Syndication Runner.
	 */
	function __construct() {

		// adding custom time interval
		add_filter( 'cron_schedules', array( $this, 'cron_add_pull_time_interval' ) );

		// Post saved changed or deleted, firing a cron jobs.
		add_action( 'transition_post_status', array( $this, 'pre_schedule_push_content' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'schedule_delete_content' ) );

		// Handle changes to sites and site groups, reset cron jobs.
		add_action( 'save_post',   array( $this, 'handle_site_change' ) );
		add_action( 'delete_post', array( $this, 'handle_site_change' ) );
		add_action( 'create_term', array( $this, 'handle_site_group_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'handle_site_group_change' ), 10, 3 );


		// Generic hook for reprocessing all scheduled pull jobs. This allows
		// for bulk rescheduling of jobs that were scheduled the old way (one job
		// for many sites).
		add_action( 'syn_refresh_pull_jobs', array( $this, 'refresh_pull_jobs' ) );

		$this->register_syndicate_actions();

		// Legacy action.
		do_action( 'syn_after_setup_server' );

	}

	public function init() {
		$this->version = get_option( 'syn_version' );
	}

	/**
	 * Set up syndication callback hooks.
	 */
	public function register_syndicate_actions() {
		add_action( 'syn_schedule_push_content', array( $this, 'schedule_push_content' ), 10, 2 );
		add_action( 'syn_schedule_delete_content', array( $this, 'schedule_delete_content' ) );

		add_action( 'syn_push_content', array( $this, 'push_content' ) );
		add_action( 'syn_delete_content', array( $this, 'delete_content' ) );
		add_action( 'syn_pull_content', array( $this, 'pull_content' ), 10, 1 );
	}

	public function syndication_user_agent( $user_agent ) {
		return apply_filters( 'syn_pull_user_agent', self::CUSTOM_USER_AGENT );
	}

	/**
	 * Pull a single site.
	 *
	 * @param  site_id string The site to pull.
	 *
	 * @return Array          An array of updated and added post ids.
	 */
	function pull_site( $site_id ) {
		global $client_manager;

		// Fetch the site's client/transport type name
		$client_transport_type = get_post_meta( $site_id, 'syn_transport_type', true );

		// Fetch the site's client by name
		$client_details = $client_manager->get_pull_client( $client_transport_type );

		// Run the client's process_site method
		$client            = new $client_details['class'];
		$updated_post_ids  = array();

		$processed_posts   = $client->process_site( $site_id, $client );

		/**
		 * Always return only the processed post IDs.
		 */
		if ( $processed_posts ) {

			$updated_post_data = wp_list_pluck( $processed_posts, 'post_data' );
			$updated_post_ids  = wp_list_pluck( $updated_post_data, 'ID' );
		}

		return ( $updated_post_ids );
	}

	public function pull_content( $sites = array() ) {
		global $site_manager;

		add_filter( 'http_headers_useragent', array( $this, 'syndication_user_agent' ) );

		if ( empty( $sites ) ) {
			$sites = $site_manager->pull_get_selected_sites();
		}

		$enabled_sites = $site_manager->get_sites_by_status( 'enabled' );
		$sites         = array_intersect( $sites, $enabled_sites );

		// Treat this process as an import.
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		// Temporarily suspend comment and term counting and cache invalidation.
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );

		// Keep track of posts that are added or changed.
		$updated_post_ids = array();
		//error_log( 'pull' );
		//error_log( json_encode( $sites ) );

		foreach( $sites as $site_id ) {

			$site_updated_post_ids = $this->pull_site( $site_id );
			$updated_post_ids      = array_merge( $updated_post_ids, $site_updated_post_ids );

			update_post_meta( $site_id, 'syn_last_pull_time', current_time( 'timestamp', 1 ) );
		}

		// Resume comment and term counting and cache invalidation.
		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		// Clear the caches for any posts that were updated.
		foreach ( $updated_post_ids as $updated_post_id ) {
			clean_post_cache( $updated_post_id );
		}

		remove_filter( 'http_headers_useragent', array( $this, 'syndication_user_agent' ) );
	}


	/**
	 * Handle save_post and delete_post for syn_site posts. If a syn_site post
	 * is updated or deleted we should reprocess any scheduled pull jobs.
	 *
	 * @param $post_id
	 */
	public function handle_site_change( $post_id ) {
		if ( 'syn_site' === get_post_type( $post_id ) ) {
			$this->refresh_pull_jobs();
		}
	}
	/**
	 * Reschedule all scheduled pull jobs.
	 */
	public function refresh_pull_jobs()	{
		global $site_manager;
		$sites         = $site_manager->pull_get_selected_sites();
		$enabled_sites = $site_manager->get_sites_by_status( 'enabled' );
		$sites         = array_intersect( $sites, $enabled_sites );
		$this->schedule_pull_content( $sites );
	}

	/**
	 * Schedule the pull content cron jobs.
	 *
	 * @param $sites Array Sites that need to be scheduled
	 */
	public function schedule_pull_content( $sites ) {

		// to unschedule a cron we need the original arguments passed to schedule the cron
		// we are saving it as a site option
		$old_pull_sites = get_option( 'syn_old_pull_sites' );


		// Clear all previously scheduled jobs.
		if( ! empty( $old_pull_sites ) ) {
			// Clear any jobs that were scheduled the old way: one job to pull many sites.
			wp_clear_scheduled_hook( 'syn_pull_content', array( $old_pull_sites ) );

			// Clear any jobs that were scheduled the new way: one job to pull one site.
			foreach ( $old_pull_sites as $old_pull_site ) {
				wp_clear_scheduled_hook( 'syn_pull_content', array( $old_pull_site ) );
			}

			wp_clear_scheduled_hook( 'syn_pull_content' );
		}

		// Schedule new jobs: one job for each site.
		foreach ( $sites as $site ) {
			wp_schedule_event(
				time() - 1,
				'syn_pull_time_interval',
				'syn_pull_content',
				array( array( $site ) )
			);
		}

		update_option( 'syn_old_pull_sites', $sites );
	}

	/**
	 * Fire events right before scheduling push content.
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 */
	public function pre_schedule_push_content( $new_status, $old_status, $post ) {

		// Don't fire on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it return
		if( ! isset( $_POST['syndicate_noncename'] ) || ! wp_verify_nonce( $_POST['syndicate_noncename'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		// Varify user capabilities.
		if( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$sites = $this->get_sites_by_post_ID( $post->ID );

		if ( empty( $sites['selected_sites'] ) && empty( $sites['removed_sites'] ) ) {
			return;
		}

		do_action( 'syn_schedule_push_content', $post->ID, $sites );
	}

	/**
	 * Handle create_term and delete_term for syn_sitegroup terms. If a site
	 * group is created or deleted we should reprocess any scheduled pull jobs.
	 *
	 * @param $term
	 * @param $tt_id
	 * @param $taxonomy
	 */
	public function handle_site_group_change ( $term, $tt_id, $taxonomy ) {
		if ( 'syn_sitegroup' === $taxonomy ) {
			$this->refresh_pull_jobs();
		}
	}

	public function cron_add_pull_time_interval( $schedules ) {
		global $settings_manager;

		// Adds the custom time interval to the existing schedules.
		$schedules['syn_pull_time_interval'] = array(
			'interval' => intval( $settings_manager->get_setting( 'pull_time_interval' ) ),
			'display'  => esc_html__( 'Pull Time Interval', 'push-syndication' )
		);

		return $schedules;

	}

}
