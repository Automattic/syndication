<?php
/**
 * Plugin bootstrapper - wires services to WordPress hooks.
 *
 * @package Automattic\Syndication\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\WordPress;

use Automattic\Syndication\Application\Contracts\PullServiceInterface;
use Automattic\Syndication\Application\Contracts\PushServiceInterface;
use Automattic\Syndication\Application\Services\CredentialTestingService;
use Automattic\Syndication\Application\Services\PullService;
use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use Automattic\Syndication\Infrastructure\Admin\AdminAssets;
use Automattic\Syndication\Infrastructure\Admin\AdminMessages;
use Automattic\Syndication\Infrastructure\Admin\PostSyndicationMetabox;
use Automattic\Syndication\Infrastructure\Admin\SettingsPage;
use Automattic\Syndication\Infrastructure\Admin\SiteListTable;
use Automattic\Syndication\Infrastructure\Admin\SiteMetaboxes;
use Automattic\Syndication\Infrastructure\CLI\ListSitegroupsCommand;
use Automattic\Syndication\Infrastructure\CLI\ListSitesCommand;
use Automattic\Syndication\Infrastructure\CLI\PullSiteCommand;
use Automattic\Syndication\Infrastructure\CLI\PullSitegroupCommand;
use Automattic\Syndication\Infrastructure\CLI\PushAllPostsCommand;
use Automattic\Syndication\Infrastructure\CLI\PushPostCommand;
use Automattic\Syndication\Infrastructure\DI\Container;

/**
 * Plugin bootstrapper.
 *
 * Wires up the DI container and registers WordPress hooks for the
 * new DDD-based architecture. This class serves as the entry point
 * for the refactored syndication functionality.
 */
final class PluginBootstrapper {

	/**
	 * DI container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether the bootstrapper has been initialised.
	 *
	 * @var bool
	 */
	private bool $initialised = false;

	/**
	 * Constructor.
	 *
	 * @param Container $container DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialise the plugin.
	 */
	public function init(): void {
		if ( $this->initialised ) {
			return;
		}

		$this->register_services();
		$this->register_hooks();
		$this->register_admin_services();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}

		$this->initialised = true;
	}

	/**
	 * Register additional services in the container.
	 */
	private function register_services(): void {
		// Register PushService - both interface and concrete class.
		$push_service_factory = function ( Container $container ): PushService {
			$factory = $container->get( TransportFactoryInterface::class );
			\assert( $factory instanceof TransportFactoryInterface );
			return new PushService( $factory );
		};
		$this->container->register( PushService::class, $push_service_factory );
		$this->container->register( PushServiceInterface::class, $push_service_factory );

		// Register PullService - both interface and concrete class.
		$pull_service_factory = function ( Container $container ): PullService {
			$factory = $container->get( TransportFactoryInterface::class );
			\assert( $factory instanceof TransportFactoryInterface );
			return new PullService( $factory );
		};
		$this->container->register( PullService::class, $pull_service_factory );
		$this->container->register( PullServiceInterface::class, $pull_service_factory );
	}

	/**
	 * Register admin services.
	 */
	private function register_admin_services(): void {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		// Site list table customisation.
		$site_list = $this->container->get( SiteListTable::class );
		\assert( $site_list instanceof SiteListTable );
		$site_list->register();

		// Settings page.
		$settings_page = $this->container->get( SettingsPage::class );
		\assert( $settings_page instanceof SettingsPage );
		$settings_page->register();

		// Site metaboxes - stored for use in PostTypeRegistrar callback.
		$site_metaboxes = $this->container->get( SiteMetaboxes::class );
		\assert( $site_metaboxes instanceof SiteMetaboxes );
		$site_metaboxes->register();

		// Post syndication metabox.
		$post_metabox = $this->container->get( PostSyndicationMetabox::class );
		\assert( $post_metabox instanceof PostSyndicationMetabox );
		$post_metabox->register();

		// Admin assets.
		$admin_assets = $this->container->get( AdminAssets::class );
		\assert( $admin_assets instanceof AdminAssets );
		$admin_assets->register();

		// Admin messages.
		$admin_messages = $this->container->get( AdminMessages::class );
		\assert( $admin_messages instanceof AdminMessages );
		$admin_messages->register();

		// Credential testing service.
		$credential_testing = $this->container->get( CredentialTestingService::class );
		\assert( $credential_testing instanceof CredentialTestingService );
		$credential_testing->register();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		// Initialisation hooks.
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		// Content syndication hooks.
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'on_trash_post' ) );

		// Cron hooks.
		add_filter( 'cron_schedules', array( $this, 'on_cron_schedules' ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		add_action( 'syn_schedule_push_content', array( $this, 'on_schedule_push_content' ), 10, 2 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		add_action( 'syn_push_content', array( $this, 'on_push_content' ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		add_action( 'syn_pull_content', array( $this, 'on_pull_content' ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		add_action( 'syn_refresh_pull_jobs', array( $this, 'on_refresh_pull_jobs' ) );

		// Site management hooks.
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'create_term', array( $this, 'on_create_term' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_delete_term' ), 10, 3 );
	}

	/**
	 * Register WP-CLI commands.
	 */
	private function register_cli_commands(): void {
		$push_service = $this->container->get( PushServiceInterface::class );
		\assert( $push_service instanceof PushServiceInterface );

		$pull_service = $this->container->get( PullServiceInterface::class );
		\assert( $pull_service instanceof PullServiceInterface );

		$site_repository = $this->container->get( SiteRepositoryInterface::class );
		\assert( $site_repository instanceof SiteRepositoryInterface );

		\WP_CLI::add_command(
			'syndication push-post',
			new PushPostCommand( $push_service, $site_repository )
		);

		\WP_CLI::add_command(
			'syndication push-all-posts',
			new PushAllPostsCommand( $push_service, $site_repository )
		);

		\WP_CLI::add_command(
			'syndication pull-site',
			new PullSiteCommand( $pull_service, $site_repository )
		);

		\WP_CLI::add_command(
			'syndication pull-sitegroup',
			new PullSitegroupCommand( $pull_service, $site_repository )
		);

		\WP_CLI::add_command(
			'syndication sites-list',
			new ListSitesCommand( $site_repository )
		);

		\WP_CLI::add_command(
			'syndication sitegroups-list',
			new ListSitegroupsCommand()
		);
	}

	/**
	 * Handle init action.
	 */
	public function on_init(): void {
		$registrar = $this->container->get( PostTypeRegistrar::class );
		\assert( $registrar instanceof PostTypeRegistrar );
		$registrar->register();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		do_action( 'syn_after_init_server' );
	}

	/**
	 * Handle admin_init action.
	 */
	public function on_admin_init(): void {
		// Placeholder for admin setup.
	}

	/**
	 * Handle transition_post_status action.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		unset( $new_status, $old_status );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens here.
		if ( ! isset( $_POST['syndicate_noncename'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value used only for verification.
		if ( ! wp_verify_nonce( $_POST['syndicate_noncename'], 'syndicate_nonce' ) ) {
			return;
		}

		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$this->save_syndicate_settings( $post->ID );

		$sites = $this->get_sites_by_post_id( $post->ID );

		if ( empty( $sites['selected_sites'] ) && empty( $sites['removed_sites'] ) ) {
			return;
		}

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

		if ( '' === get_post_meta( $post_id, 'post_uniqueid', true ) ) {
			update_post_meta( $post_id, 'post_uniqueid', uniqid() );
		}
	}

	/**
	 * Get sites for syndication by post ID.
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

		update_post_meta( $post_id, '_syn_old_sitegroups', $selected_sitegroups );

		return $data;
	}

	/**
	 * Check if the current user can syndicate.
	 *
	 * @return bool True if user can syndicate.
	 */
	private function current_user_can_syndicate(): bool {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		return current_user_can( $capability );
	}

	/**
	 * Handle wp_trash_post action.
	 *
	 * @param int $post_id Post ID being trashed.
	 */
	public function on_trash_post( int $post_id ): void {
		$settings = get_option( 'push_syndicate_settings' );
		if ( empty( $settings['delete_pushed_posts'] ) ) {
			return;
		}

		$slave_posts = $this->get_slave_posts( $post_id );
		if ( empty( $slave_posts ) ) {
			return;
		}

		$push_service = $this->container->get( PushService::class );
		\assert( $push_service instanceof PushService );

		$delete_errors = get_option( 'syn_delete_error_sites', array() );

		foreach ( $slave_posts as $site_id => $remote_id ) {
			if ( 'on' !== get_post_meta( $site_id, 'syn_site_enabled', true ) ) {
				continue;
			}

			$result = $push_service->delete_from_site( $post_id, $site_id );

			if ( ! $result->is_success() ) {
				$delete_errors[ $site_id ] = array( $remote_id );
			}

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
	 * @param int                                                              $post_id Post ID to push.
	 * @param array{post_ID: int, selected_sites: array, removed_sites: array} $sites   Sites data.
	 */
	public function on_schedule_push_content( int $post_id, array $sites ): void {
		unset( $post_id );

		wp_schedule_single_event(
			time() - 1,
			'syn_push_content',
			array( $sites )
		);

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Handle syn_push_content cron action.
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

		$selected_site_ids = $this->extract_site_ids( $sites['selected_sites'] ?? array() );
		if ( ! empty( $selected_site_ids ) ) {
			$push_service->push_to_sites( $post_id, $selected_site_ids );
		}

		$removed_site_ids = $this->extract_site_ids( $sites['removed_sites'] ?? array() );
		foreach ( $removed_site_ids as $site_id ) {
			$push_service->delete_from_site( $post_id, $site_id );
		}
	}

	/**
	 * Extract site IDs from sites array.
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

		$settings        = get_option( 'push_syndicate_settings' );
		$update_existing = ! empty( $settings['update_pulled_posts'] ) && 'on' === $settings['update_pulled_posts'];

		$pull_service = $this->container->get( PullService::class );
		\assert( $pull_service instanceof PullService );
		$pull_service->set_update_existing( $update_existing );

		$pull_service->pull_from_sites( $site_ids );
	}

	/**
	 * Handle syn_refresh_pull_jobs cron action.
	 */
	public function on_refresh_pull_jobs(): void {
		$sites = $this->get_selected_pull_sites();

		$this->schedule_pull_jobs( $sites );
	}

	/**
	 * Get sites selected for pulling.
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
	 * @param array<\WP_Post> $sites Array of site post objects.
	 */
	private function schedule_pull_jobs( array $sites ): void {
		$old_sites = get_option( 'syn_old_pull_sites', array() );

		if ( ! empty( $old_sites ) ) {
			wp_clear_scheduled_hook( 'syn_pull_content', array( $old_sites ) );

			foreach ( $old_sites as $old_site ) {
				wp_clear_scheduled_hook( 'syn_pull_content', array( $old_site ) );
				wp_clear_scheduled_hook( 'syn_pull_content', array( array( $old_site ) ) );
			}

			wp_clear_scheduled_hook( 'syn_pull_content' );
		}

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
	 * Handle save_post action.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $post_id, $update );

		if ( PostTypeRegistrar::POST_TYPE === $post->post_type ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Handle delete_post action.
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
	 */
	private function schedule_deferred_pull_jobs_refresh(): void {
		$debounce_key = 'syn_pull_jobs_refresh_pending';

		if ( get_transient( $debounce_key ) ) {
			return;
		}

		set_transient( $debounce_key, '1', 5 );

		if ( ! wp_next_scheduled( 'syn_refresh_pull_jobs' ) ) {
			wp_schedule_single_event( time() + 5, 'syn_refresh_pull_jobs' );
		}
	}
}
