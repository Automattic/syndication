<?php
/**
 * Hook registrar for WordPress integration.
 *
 * @package Automattic\Syndication\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application;

use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;
use Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar;

/**
 * Registers WordPress hooks for the new architecture.
 *
 * This class will replace the hook registration in WP_Push_Syndication_Server
 * once the migration is complete. Handlers are currently empty placeholders
 * that will be implemented to use the new services.
 *
 * @see WP_Push_Syndication_Server::__construct() for the legacy equivalent.
 */
final class HookRegistrar {

	/**
	 * DI container.
	 *
	 * @var Container
	 */
	private readonly Container $container;

	/**
	 * Hook manager.
	 *
	 * @var HookManager
	 */
	private readonly HookManager $hooks;

	/**
	 * Constructor.
	 *
	 * @param Container   $container DI container.
	 * @param HookManager $hooks     Hook manager.
	 */
	public function __construct( Container $container, HookManager $hooks ) {
		$this->container = $container;
		$this->hooks     = $hooks;
	}

	/**
	 * Register all hooks.
	 *
	 * Call this method to register all WordPress hooks. This mirrors
	 * the hook registration in WP_Push_Syndication_Server::__construct().
	 */
	public function register(): void {
		$this->register_initialisation_hooks();
		$this->register_admin_hooks();
		$this->register_syndication_hooks();
		$this->register_cron_hooks();
		$this->register_site_management_hooks();
	}

	/**
	 * Register initialisation hooks.
	 *
	 * @see WP_Push_Syndication_Server::init()
	 */
	private function register_initialisation_hooks(): void {
		// Post type and taxonomy registration will move here from legacy code.
		$this->hooks->add_action( 'init', array( $this, 'on_init' ), 10, 0 );
	}

	/**
	 * Register admin hooks.
	 *
	 * @see WP_Push_Syndication_Server::admin_init()
	 */
	private function register_admin_hooks(): void {
		$this->hooks->add_action( 'admin_init', array( $this, 'on_admin_init' ), 10, 0 );
	}

	/**
	 * Register content syndication hooks.
	 *
	 * @see WP_Push_Syndication_Server - transition_post_status, wp_trash_post
	 */
	private function register_syndication_hooks(): void {
		$this->hooks->add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
		$this->hooks->add_action( 'wp_trash_post', array( $this, 'on_trash_post' ), 10, 1 );
	}

	/**
	 * Register cron-related hooks.
	 *
	 * @see WP_Push_Syndication_Server::cron_add_pull_time_interval()
	 */
	private function register_cron_hooks(): void {
		$this->hooks->add_filter( 'cron_schedules', array( $this, 'on_cron_schedules' ), 10, 1 );
	}

	/**
	 * Register site management hooks.
	 *
	 * @see WP_Push_Syndication_Server - save_post, delete_post, create_term, delete_term
	 */
	private function register_site_management_hooks(): void {
		$this->hooks->add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
		$this->hooks->add_action( 'delete_post', array( $this, 'on_delete_post' ), 10, 1 );
		$this->hooks->add_action( 'create_term', array( $this, 'on_create_term' ), 10, 3 );
		$this->hooks->add_action( 'delete_term', array( $this, 'on_delete_term' ), 10, 3 );
	}

	/**
	 * Handle init action.
	 *
	 * Registers the syn_site post type and syn_sitegroup taxonomy.
	 * Safe to call even when legacy code also registers - will skip if already registered.
	 */
	public function on_init(): void {
		$registrar = $this->container->get( PostTypeRegistrar::class );
		\assert( $registrar instanceof PostTypeRegistrar );
		$registrar->register();

		/**
		 * Fires after syndication server initialisation.
		 *
		 * @since 2.0.0
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		do_action( 'syn_after_init_server' );
	}

	/**
	 * Handle admin_init action.
	 *
	 * @todo Implement admin initialisation.
	 */
	public function on_admin_init(): void {
		// Will handle admin setup.
	}

	/**
	 * Handle transition_post_status action.
	 *
	 * Saves syndication settings and schedules push content when a post
	 * status changes. Mirrors legacy save_syndicate_settings() and
	 * pre_schedule_push_content().
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		unset( $new_status, $old_status );

		// Skip autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip if no nonce or invalid nonce (form not submitted).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens here.
		if ( ! isset( $_POST['syndicate_noncename'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value used only for verification.
		if ( ! wp_verify_nonce( $_POST['syndicate_noncename'], 'syndicate_nonce' ) ) {
			return;
		}

		// Check capability.
		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		// Save selected site groups.
		$this->save_syndicate_settings( $post->ID );

		// Get sites for syndication.
		$sites = $this->get_sites_by_post_id( $post->ID );

		if ( empty( $sites['selected_sites'] ) && empty( $sites['removed_sites'] ) ) {
			return;
		}

		// Schedule push content via action (allows legacy code to hook in).
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		do_action( 'syn_schedule_push_content', $post->ID, $sites );
	}

	/**
	 * Save syndicate settings for a post.
	 *
	 * @param int $post_id The post ID.
	 */
	private function save_syndicate_settings( int $post_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified in caller.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array values sanitized with sanitize_key.
		$selected_sitegroups = ! empty( $_POST['selected_sitegroups'] )
			? array_map( 'sanitize_key', (array) $_POST['selected_sitegroups'] )
			: array();
		// phpcs:enable

		update_post_meta( $post_id, '_syn_selected_sitegroups', $selected_sitegroups );

		// Generate unique post ID if not exists (prevents syndication loops).
		if ( '' === get_post_meta( $post_id, 'post_uniqueid', true ) ) {
			update_post_meta( $post_id, 'post_uniqueid', uniqid() );
		}
	}

	/**
	 * Get sites for syndication by post ID.
	 *
	 * Returns selected sites (to push to) and removed sites (to delete from).
	 *
	 * @param int $post_id The post ID.
	 * @return array{post_ID: int, selected_sites: array<int>, removed_sites: array<int>} Sites data.
	 */
	private function get_sites_by_post_id( int $post_id ): array {
		$selected_sitegroups = get_post_meta( $post_id, '_syn_selected_sitegroups', true );
		$selected_sitegroups = is_array( $selected_sitegroups ) ? $selected_sitegroups : array();

		$old_sitegroups = get_post_meta( $post_id, '_syn_old_sitegroups', true );
		$old_sitegroups = is_array( $old_sitegroups ) ? $old_sitegroups : array();

		$removed_sitegroups = array_diff( $old_sitegroups, $selected_sitegroups );

		$data = array(
			'post_ID'        => $post_id,
			'selected_sites' => array(),
			'removed_sites'  => array(),
		);

		$repository = $this->container->get( SiteRepositoryInterface::class );
		\assert( $repository instanceof SiteRepositoryInterface );

		// Get selected sites.
		foreach ( $selected_sitegroups as $sitegroup ) {
			$term = get_term_by( 'slug', $sitegroup, 'syn_sitegroup' );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$configs = $repository->get_by_group( $term->term_id );
			foreach ( $configs as $config ) {
				if ( $config->is_enabled() ) {
					$data['selected_sites'][] = $config->get_site_id();
				}
			}
		}

		// Get removed sites.
		foreach ( $removed_sitegroups as $sitegroup ) {
			$term = get_term_by( 'slug', $sitegroup, 'syn_sitegroup' );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$configs = $repository->get_by_group( $term->term_id );
			foreach ( $configs as $config ) {
				if ( $config->is_enabled() ) {
					$data['removed_sites'][] = $config->get_site_id();
				}
			}
		}

		// Update old sitegroups for next comparison.
		update_post_meta( $post_id, '_syn_old_sitegroups', $selected_sitegroups );

		return $data;
	}

	/**
	 * Check if the current user can syndicate.
	 *
	 * @return bool True if user can syndicate.
	 */
	private function current_user_can_syndicate(): bool {
		/**
		 * Filters the capability required for syndication.
		 *
		 * @param string $capability Default capability.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		return current_user_can( $capability );
	}

	/**
	 * Handle wp_trash_post action.
	 *
	 * Deletes syndicated content from remote sites when a post is trashed.
	 * Mirrors legacy delete_content().
	 *
	 * @param int $post_id Post ID being trashed.
	 */
	public function on_trash_post( int $post_id ): void {
		// Check if delete on trash is enabled.
		$settings = get_option( 'push_syndicate_settings' );
		if ( empty( $settings['delete_pushed_posts'] ) ) {
			return;
		}

		// Get slave posts (remote posts that were syndicated).
		$slave_posts = $this->get_slave_posts( $post_id );
		if ( empty( $slave_posts ) ) {
			return;
		}

		$push_service = $this->container->get( PushService::class );
		\assert( $push_service instanceof PushService );

		$delete_errors = get_option( 'syn_delete_error_sites', array() );

		foreach ( $slave_posts as $site_id => $remote_id ) {
			// Check if site is enabled.
			if ( 'on' !== get_post_meta( $site_id, 'syn_site_enabled', true ) ) {
				continue;
			}

			$result = $push_service->delete_from_site( $post_id, $site_id );

			if ( ! $result->is_success() ) {
				$delete_errors[ $site_id ] = array( $remote_id );
			}

			/**
			 * Fires after a post is deleted from a remote site.
			 *
			 * @param bool   $success       Whether deletion was successful.
			 * @param int    $remote_id     Remote post ID.
			 * @param int    $post_id       Local post ID.
			 * @param int    $site_id       Site post ID.
			 * @param string $transport_type Transport type.
			 * @param object $client        Transport client (null for new architecture).
			 */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
			do_action( 'syn_post_push_delete_post', $result->is_success(), $remote_id, $post_id, $site_id, '', null );
		}

		update_option( 'syn_delete_error_sites', $delete_errors );
	}

	/**
	 * Get slave posts (remote posts that were syndicated).
	 *
	 * @param int $post_id The local post ID.
	 * @return array<int, int> Array of site_id => remote_id pairs.
	 */
	private function get_slave_posts( int $post_id ): array {
		$slave_post_states = get_post_meta( $post_id, '_syn_slave_post_states', true );
		if ( empty( $slave_post_states ) || ! is_array( $slave_post_states ) ) {
			return array();
		}

		$slave_posts = array();

		// The state structure is: $state_name => array( $site_id => $info ).
		// We only care about successful syncs which have ext_ID.
		if ( ! empty( $slave_post_states['success'] ) && is_array( $slave_post_states['success'] ) ) {
			foreach ( $slave_post_states['success'] as $site_id => $remote_id ) {
				if ( is_numeric( $remote_id ) && $remote_id > 0 ) {
					$slave_posts[ (int) $site_id ] = (int) $remote_id;
				}
			}
		}

		return $slave_posts;
	}

	/**
	 * Handle cron_schedules filter.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Existing schedules.
	 * @return array<string, array{interval: int, display: string}> Modified schedules.
	 */
	public function on_cron_schedules( array $schedules ): array {
		$settings           = get_option( 'push_syndicate_settings' );
		$pull_time_interval = $settings['pull_time_interval'] ?? 3600;

		$schedules['syn_pull_time_interval'] = array(
			'interval' => (int) $pull_time_interval,
			'display'  => __( 'Pull Time Interval', 'push-syndication' ),
		);

		return $schedules;
	}

	/**
	 * Handle save_post action.
	 *
	 * Handles syn_site post saves and triggers pull job refresh.
	 * Mirrors legacy save_site_settings() and handle_site_change().
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );

		// Handle site changes (for pull job refresh).
		if ( PostTypeRegistrar::POST_TYPE === $post->post_type ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Handle delete_post action.
	 *
	 * Triggers pull job refresh when a syn_site is deleted.
	 * Mirrors legacy handle_site_change().
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public function on_delete_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post && PostTypeRegistrar::POST_TYPE === $post->post_type ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Handle create_term action.
	 *
	 * Triggers pull job refresh when a syn_sitegroup is created.
	 * Mirrors legacy handle_site_group_change().
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_create_term( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $term_id, $tt_id );

		if ( PostTypeRegistrar::TAXONOMY === $taxonomy ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Handle delete_term action.
	 *
	 * Triggers pull job refresh when a syn_sitegroup is deleted.
	 * Mirrors legacy handle_site_group_change().
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_delete_term( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $term_id, $tt_id );

		if ( PostTypeRegistrar::TAXONOMY === $taxonomy ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Schedule a deferred refresh of pull jobs.
	 *
	 * Uses debouncing to prevent multiple refreshes in quick succession.
	 * Mirrors legacy schedule_deferred_pull_jobs_refresh().
	 */
	private function schedule_deferred_pull_jobs_refresh(): void {
		$debounce_key = 'syn_pull_jobs_refresh_pending';

		// Skip if already scheduled (debounce).
		if ( get_transient( $debounce_key ) ) {
			return;
		}

		// Set debounce transient (5 second window).
		set_transient( $debounce_key, '1', 5 );

		// Schedule the refresh action.
		if ( ! wp_next_scheduled( 'syn_refresh_pull_jobs' ) ) {
			wp_schedule_single_event( time() + 5, 'syn_refresh_pull_jobs' );
		}
	}
}
