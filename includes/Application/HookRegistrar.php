<?php
/**
 * Hook registrar for WordPress integration.
 *
 * @package Automattic\Syndication\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application;

use Automattic\Syndication\Application\Services\PullService;
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

		// Push content scheduling and execution.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$this->hooks->add_action( 'syn_schedule_push_content', array( $this, 'on_schedule_push_content' ), 10, 2 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$this->hooks->add_action( 'syn_push_content', array( $this, 'on_push_content' ), 10, 1 );

		// Pull content execution and job management.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$this->hooks->add_action( 'syn_pull_content', array( $this, 'on_pull_content' ), 10, 1 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$this->hooks->add_action( 'syn_refresh_pull_jobs', array( $this, 'on_refresh_pull_jobs' ), 10, 0 );
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
	 * Handle syn_schedule_push_content action.
	 *
	 * Schedules a cron event to push content in the background.
	 * This ensures pushing to many sites doesn't block the request.
	 *
	 * @param int                                                              $post_id Post ID to push.
	 * @param array{post_ID: int, selected_sites: array, removed_sites: array} $sites   Sites data.
	 */
	public function on_schedule_push_content( int $post_id, array $sites ): void {
		unset( $post_id );

		// Schedule push to run immediately in the background.
		// Using time() - 1 ensures it runs on the next cron tick.
		wp_schedule_single_event(
			time() - 1,
			'syn_push_content',
			array( $sites )
		);

		// Spawn cron immediately if possible (non-blocking).
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Handle syn_push_content cron action.
	 *
	 * Executes the actual push operation in a background context.
	 * Uses PushService for the new architecture.
	 *
	 * @param array{post_ID: int, selected_sites: array, removed_sites: array} $sites Sites data.
	 */
	public function on_push_content( array $sites ): void {
		$post_id = $sites['post_ID'] ?? 0;

		if ( 0 === $post_id ) {
			return;
		}

		$push_service = $this->container->get( PushService::class );
		\assert( $push_service instanceof PushService );

		// Push to selected sites.
		$selected_site_ids = $this->extract_site_ids( $sites['selected_sites'] ?? array() );
		if ( ! empty( $selected_site_ids ) ) {
			$push_service->push_to_sites( $post_id, $selected_site_ids );
		}

		// Delete from removed sites.
		$removed_site_ids = $this->extract_site_ids( $sites['removed_sites'] ?? array() );
		foreach ( $removed_site_ids as $site_id ) {
			$push_service->delete_from_site( $post_id, $site_id );
		}
	}

	/**
	 * Extract site IDs from sites array.
	 *
	 * The sites array can contain either WP_Post objects (legacy) or site IDs (new).
	 *
	 * @param array<int|\WP_Post> $sites Array of sites.
	 * @return array<int> Array of site IDs.
	 */
	private function extract_site_ids( array $sites ): array {
		$ids = array();

		foreach ( $sites as $site ) {
			if ( $site instanceof \WP_Post ) {
				$ids[] = $site->ID;
			} elseif ( is_object( $site ) && isset( $site->ID ) ) {
				$ids[] = (int) $site->ID;
			} elseif ( is_numeric( $site ) ) {
				$ids[] = (int) $site;
			}
		}

		return array_unique( $ids );
	}

	/**
	 * Handle syn_pull_content cron action.
	 *
	 * Pulls content from remote sites using PullService.
	 * Legacy code passes an array of WP_Post site objects or a single site in an array.
	 *
	 * @param array<\WP_Post|int> $sites Array of sites to pull from.
	 */
	public function on_pull_content( array $sites ): void {
		if ( empty( $sites ) ) {
			$sites = $this->get_selected_pull_sites();
		}

		$site_ids = $this->extract_site_ids( $sites );

		if ( empty( $site_ids ) ) {
			return;
		}

		// Configure update behavior from settings.
		$settings        = get_option( 'push_syndicate_settings' );
		$update_existing = ! empty( $settings['update_pulled_posts'] ) && 'on' === $settings['update_pulled_posts'];

		$pull_service = $this->container->get( PullService::class );
		\assert( $pull_service instanceof PullService );
		$pull_service->set_update_existing( $update_existing );

		// Pull from all sites.
		$pull_service->pull_from_sites( $site_ids );
	}

	/**
	 * Handle syn_refresh_pull_jobs cron action.
	 *
	 * Reschedules all pull jobs based on current site configuration.
	 * Called when sites or sitegroups are modified.
	 */
	public function on_refresh_pull_jobs(): void {
		$sites = $this->get_selected_pull_sites();

		$this->schedule_pull_jobs( $sites );
	}

	/**
	 * Get sites selected for pulling.
	 *
	 * Returns sites from the selected pull sitegroups, ordered by last pull time.
	 *
	 * @return array<\WP_Post> Array of site post objects.
	 */
	private function get_selected_pull_sites(): array {
		$settings = get_option( 'push_syndicate_settings' );

		if ( empty( $settings['selected_pull_sitegroups'] ) ) {
			return array();
		}

		$selected_sitegroups = $settings['selected_pull_sitegroups'];
		$sites               = array();

		foreach ( $selected_sitegroups as $sitegroup ) {
			$term = get_term_by( 'slug', $sitegroup, 'syn_sitegroup' );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$query = new \WP_Query(
				array(
					'post_type'      => PostTypeRegistrar::POST_TYPE,
					'posts_per_page' => 100,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for sitegroup filtering.
						array(
							'taxonomy' => PostTypeRegistrar::TAXONOMY,
							'field'    => 'slug',
							'terms'    => $sitegroup,
						),
					),
				)
			);

			$sites = array_merge( $sites, $query->posts );
		}

		// Sort by last pull time (oldest first).
		usort(
			$sites,
			static function ( \WP_Post $a, \WP_Post $b ): int {
				$a_time = (int) get_post_meta( $a->ID, 'syn_last_pull_time', true );
				$b_time = (int) get_post_meta( $b->ID, 'syn_last_pull_time', true );
				return $a_time <=> $b_time;
			}
		);

		return $sites;
	}

	/**
	 * Schedule pull jobs for sites.
	 *
	 * Clears existing pull cron jobs and schedules new ones (one per site).
	 *
	 * @param array<\WP_Post> $sites Array of site post objects.
	 */
	private function schedule_pull_jobs( array $sites ): void {
		// Get old sites to clear their scheduled jobs.
		$old_sites = get_option( 'syn_old_pull_sites', array() );

		// Clear old scheduled jobs.
		if ( ! empty( $old_sites ) ) {
			// Clear jobs scheduled the old way (one job for many sites).
			wp_clear_scheduled_hook( 'syn_pull_content', array( $old_sites ) );

			// Clear jobs scheduled the new way (one job per site).
			foreach ( $old_sites as $old_site ) {
				wp_clear_scheduled_hook( 'syn_pull_content', array( $old_site ) );
				// Also clear single-site array format.
				wp_clear_scheduled_hook( 'syn_pull_content', array( array( $old_site ) ) );
			}

			// Clear any generic scheduled hook.
			wp_clear_scheduled_hook( 'syn_pull_content' );
		}

		// Schedule new jobs: one job per site.
		foreach ( $sites as $site ) {
			wp_schedule_event(
				time() - 1,
				'syn_pull_time_interval',
				'syn_pull_content',
				array( array( $site ) )
			);
		}

		// Save sites for next refresh.
		update_option( 'syn_old_pull_sites', $sites );
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
