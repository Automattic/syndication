<?php
/**
 * Hook registrar for WordPress integration.
 *
 * @package Automattic\Syndication\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application;

use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;

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
	 * @todo Implement post type and taxonomy registration.
	 */
	public function on_init(): void {
		// Will register syn_site post type and syn_sitegroup taxonomy.
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
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @todo Implement syndication triggering via PushService.
	 */
	public function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		// Will trigger push syndication when post is published.
		unset( $new_status, $old_status, $post );
	}

	/**
	 * Handle wp_trash_post action.
	 *
	 * @param int $post_id Post ID being trashed.
	 *
	 * @todo Implement remote deletion via PushService.
	 */
	public function on_trash_post( int $post_id ): void {
		// Will trigger deletion from remote sites.
		unset( $post_id );
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
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 *
	 * @todo Implement site settings saving.
	 */
	public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		// Will handle syn_site post saves.
		unset( $post_id, $post, $update );
	}

	/**
	 * Handle delete_post action.
	 *
	 * @param int $post_id Post ID being deleted.
	 *
	 * @todo Implement site deletion handling.
	 */
	public function on_delete_post( int $post_id ): void {
		// Will handle syn_site deletion and scheduled content deletion.
		unset( $post_id );
	}

	/**
	 * Handle create_term action.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @todo Implement site group creation handling.
	 */
	public function on_create_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// Will handle syn_sitegroup creation.
		unset( $term_id, $tt_id, $taxonomy );
	}

	/**
	 * Handle delete_term action.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @todo Implement site group deletion handling.
	 */
	public function on_delete_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// Will handle syn_sitegroup deletion.
		unset( $term_id, $tt_id, $taxonomy );
	}
}
