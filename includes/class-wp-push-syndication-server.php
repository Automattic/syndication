<?php
/**
 * Main syndication server class.
 *
 * @package Syndication
 */

require_once __DIR__ . '/class-syndication-client-factory.php';

/**
 * Class WP_Push_Syndication_Server
 *
 * Core syndication server that handles plugin initialization, admin interfaces,
 * settings management, and orchestrates content push/pull operations between sites.
 */
class WP_Push_Syndication_Server {

	const CUSTOM_USER_AGENT = 'WordPress/Syndication Plugin';

	public $push_syndicate_settings;
	public $push_syndicate_default_settings;
	public $push_syndicate_transports;

	private $version;

	public function __construct() {

		// initialization.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// custom columns.
		add_filter( 'manage_edit-syn_site_columns', array( $this, 'add_new_columns' ) );
		add_action( 'manage_syn_site_posts_custom_column', array( $this, 'manage_columns' ), 10, 2 );
		add_filter( 'manage_edit-syn_site_sortable_columns', array( $this, 'add_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );

		// bulk actions.
		add_filter( 'bulk_actions-edit-syn_site', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-syn_site', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );

		// rename Published filter to Enabled.
		add_filter( 'views_edit-syn_site', array( $this, 'rename_views' ) );

		// submenus.
		add_action( 'admin_menu', array( $this, 'register_syndicate_settings' ) );

		// defining sites.
		add_action( 'save_post', array( $this, 'save_site_settings' ) );

		// loading necessary styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );

		// filter admin notices in custom post types.
		add_filter( 'post_updated_messages', array( $this, 'push_syndicate_admin_messages' ) );

		// syndicating content.
		add_action( 'add_meta_boxes', array( $this, 'add_post_metaboxes' ) );
		add_action( 'transition_post_status', array( $this, 'save_syndicate_settings' ) ); // Use transition_post_status instead of save_post because the former is fired earlier which causes race conditions when a site group select and publish happen on the same load.
		add_action( 'wp_trash_post', array( $this, 'delete_content' ) );

		// adding custom time interval.
		add_filter( 'cron_schedules', array( $this, 'cron_add_pull_time_interval' ) );

		// firing a cron job.
		add_action( 'transition_post_status', array( $this, 'pre_schedule_push_content' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'schedule_delete_content' ) );

		// Handle changes to sites and site groups.
		add_action( 'save_post', array( $this, 'handle_site_change' ) );
		add_action( 'delete_post', array( $this, 'handle_site_change' ) );
		add_action( 'create_term', array( $this, 'handle_site_group_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'handle_site_group_change' ), 10, 3 );

		// Generic hook for reprocessing all scheduled pull jobs. This allows
		// for bulk rescheduling of jobs that were scheduled the old way (one job.
		// for many sites).
		add_action( 'syn_refresh_pull_jobs', array( $this, 'refresh_pull_jobs' ) );

		// AJAX handler for testing credentials.
		add_action( 'wp_ajax_syn_test_credentials', array( $this, 'ajax_test_credentials' ) );

		$this->register_syndicate_actions();

		do_action( 'syn_after_setup_server' );
	}

	public function init() {

		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		$post_type_capabilities = array(
			'edit_post'          => $capability,
			'read_post'          => $capability,
			'delete_post'        => $capability,
			'delete_posts'       => $capability, // Only added to address notice caused by https://core.trac.wordpress.org/ticket/30991.
			'edit_posts'         => $capability,
			'edit_others_posts'  => $capability,
			'publish_posts'      => $capability,
			'read_private_posts' => $capability,
		);

		$taxonomy_capabilities = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		register_post_type(
			'syn_site',
			array(
				'labels'               => array(
					'name'               => __( 'Sites', 'push-syndication' ),
					'singular_name'      => __( 'Site', 'push-syndication' ),
					'add_new'            => __( 'Add Site', 'push-syndication' ),
					'add_new_item'       => __( 'Add New Site', 'push-syndication' ),
					'edit_item'          => __( 'Edit Site', 'push-syndication' ),
					'new_item'           => __( 'New Site', 'push-syndication' ),
					'view_item'          => __( 'View Site', 'push-syndication' ),
					'search_items'       => __( 'Search Sites', 'push-syndication' ),
					'not_found'          => __( 'No sites found', 'push-syndication' ),
					'not_found_in_trash' => __( 'No sites found in Trash', 'push-syndication' ),
					'all_items'          => __( 'Sites', 'push-syndication' ),
					'menu_name'          => __( 'Syndication', 'push-syndication' ),
				),
				'description'          => __( 'Syndication target sites', 'push-syndication' ),
				'public'               => false,
				'show_ui'              => true,
				'publicly_queryable'   => false,
				'exclude_from_search'  => true,
				'menu_position'        => 80,
				'menu_icon'            => 'dashicons-rss',
				'hierarchical'         => false,
				'query_var'            => false,
				'rewrite'              => false,
				'supports'             => array( 'title' ),
				'can_export'           => true,
				'register_meta_box_cb' => array( $this, 'site_metaboxes' ),
				'capabilities'         => $post_type_capabilities,
			)
		);

		register_taxonomy(
			'syn_sitegroup',
			'syn_site',
			array(
				'labels'            => array(
					'name'              => __( 'Site Groups', 'push-syndication' ),
					'singular_name'     => __( 'Site Group', 'push-syndication' ),
					'search_items'      => __( 'Search Site Groups', 'push-syndication' ),
					'popular_items'     => __( 'Popular Site Groups', 'push-syndication' ),
					'all_items'         => __( 'All Site Groups', 'push-syndication' ),
					'parent_item'       => __( 'Parent Site Group', 'push-syndication' ),
					'parent_item_colon' => __( 'Parent Site Group:', 'push-syndication' ),
					'edit_item'         => __( 'Edit Site Group', 'push-syndication' ),
					'update_item'       => __( 'Update Site Group', 'push-syndication' ),
					'add_new_item'      => __( 'Add New Site Group', 'push-syndication' ),
					'new_item_name'     => __( 'New Site Group Name', 'push-syndication' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_tagcloud'     => false,
				'show_in_nav_menus' => false,
				'hierarchical'      => true,
				'rewrite'           => false,
				'capabilities'      => $taxonomy_capabilities,
			)
		);

		$this->push_syndicate_default_settings = array(
			'selected_pull_sitegroups'    => array(),
			'selected_post_types'         => array( 'post' ),
			'delete_pushed_posts'         => 'off',
			'pull_time_interval'          => '3600',
			'update_pulled_posts'         => 'off',
			'notification_methods'        => array(),
			'notification_email_address'  => '',
			'notification_email_types'    => array(),
			'notification_slack_webhook'  => '',
			'notification_slack_types'    => array(),
		);

		$this->push_syndicate_settings = wp_parse_args( (array) get_option( 'push_syndicate_settings' ), $this->push_syndicate_default_settings );

		$this->version = get_option( 'syn_version' );

		do_action( 'syn_after_init_server' );
	}

	public function register_syndicate_actions() {
		add_action( 'syn_schedule_push_content', array( $this, 'schedule_push_content' ), 10, 2 );
		add_action( 'syn_schedule_delete_content', array( $this, 'schedule_delete_content' ) );

		add_action( 'syn_push_content', array( $this, 'push_content' ) );
		add_action( 'syn_delete_content', array( $this, 'delete_content' ) );
		add_action( 'syn_pull_content', array( $this, 'pull_content' ), 10, 1 );
	}

	public function add_new_columns( $columns ) {
		$new_columns                  = array();
		$new_columns['cb']            = '<input type="checkbox" />';
		$new_columns['title']         = _x( 'Site Name', 'column name', 'push-syndication' );
		$new_columns['transport']     = _x( 'Transport', 'column name', 'push-syndication' );
		$new_columns['syn_sitegroup'] = _x( 'Groups', 'column name', 'push-syndication' );
		$new_columns['site_status']   = _x( 'Status', 'column name', 'push-syndication' );
		$new_columns['date']          = _x( 'Date', 'column name', 'push-syndication' );
		return $new_columns;
	}

	public function manage_columns( $column_name, $id ) {
		switch ( $column_name ) {
			case 'transport':
				$transport_type = get_post_meta( $id, 'syn_transport_type', true );
				$transport_mode = get_post_meta( $id, 'syn_transport_mode', true );
				$transport_mode = ! empty( $transport_mode ) ? $transport_mode : 'push';
				try {
					$client      = Syndication_Client_Factory::get_client( $transport_type, $id );
					$client_data = $client->get_client_data();
					echo esc_html( sprintf( '%s (%s)', $client_data['name'], $transport_mode ) );
				} catch ( Exception $e ) {
					printf( esc_html__( 'Unknown (%s)', 'push-syndication' ), esc_html( $transport_type ) );
				}
				break;
			case 'syn_sitegroup':
				the_terms( $id, 'syn_sitegroup', '', ', ', '' );
				break;
			case 'site_status':
				$site_status = get_post_meta( $id, 'syn_site_enabled', true );
				if ( 'on' === $site_status ) {
					echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'Enabled', 'push-syndication' ) . '"></span> ';
					esc_html_e( 'Enabled', 'push-syndication' );
				} else {
					echo '<span class="dashicons dashicons-no" style="color: #dc3232;" title="' . esc_attr__( 'Disabled', 'push-syndication' ) . '"></span> ';
					esc_html_e( 'Disabled', 'push-syndication' );
				}
				break;
			default:
				break;
		}
	}

	/**
	 * Add sortable columns.
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function add_sortable_columns( array $columns ): array {
		$columns['title']         = 'title';
		$columns['transport']     = 'transport';
		$columns['syn_sitegroup'] = 'syn_sitegroup';
		$columns['site_status']   = 'site_status';
		return $columns;
	}

	/**
	 * Handle custom column sorting.
	 *
	 * @param \WP_Query $query The query object.
	 */
	public function handle_column_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'syn_site' !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'transport' === $orderby ) {
			$query->set( 'meta_key', 'syn_transport_type' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'syn_sitegroup' === $orderby ) {
			add_filter( 'posts_clauses', array( $this, 'sort_by_taxonomy_clauses' ) );
		}

		if ( 'site_status' === $orderby ) {
			$query->set( 'meta_key', 'syn_site_enabled' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Modify query clauses to sort by taxonomy term name.
	 *
	 * @param array $clauses Query clauses.
	 * @return array Modified clauses.
	 */
	public function sort_by_taxonomy_clauses( array $clauses ): array {
		global $wpdb;

		$clauses['join']   .= " LEFT JOIN {$wpdb->term_relationships} AS tr ON ({$wpdb->posts}.ID = tr.object_id)";
		$clauses['join']   .= " LEFT JOIN {$wpdb->term_taxonomy} AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'syn_sitegroup')";
		$clauses['join']   .= " LEFT JOIN {$wpdb->terms} AS t ON (tt.term_id = t.term_id)";
		$clauses['orderby'] = 't.name ' . ( 'ASC' === strtoupper( get_query_var( 'order' ) ) ? 'ASC' : 'DESC' );

		// Remove filter after use to avoid affecting other queries.
		remove_filter( 'posts_clauses', array( $this, 'sort_by_taxonomy_clauses' ) );

		return $clauses;
	}

	/**
	 * Add bulk actions for sites.
	 *
	 * @param array $actions Current bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_actions( array $actions ): array {
		unset( $actions['edit'] ); // Remove bulk edit (not useful for sites).

		$actions['enable_sites']  = __( 'Enable', 'push-syndication' );
		$actions['disable_sites'] = __( 'Disable', 'push-syndication' );

		return $actions;
	}

	/**
	 * Handle bulk actions for sites.
	 *
	 * @param string $sendback The redirect URL.
	 * @param string $doaction The action being performed.
	 * @param int[]  $post_ids Array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( string $sendback, string $doaction, array $post_ids ): string {
		if ( ! in_array( $doaction, array( 'enable_sites', 'disable_sites' ), true ) ) {
			return $sendback;
		}

		// Check capabilities.
		if ( ! current_user_can( apply_filters( 'syn_syndicate_cap', 'manage_options' ) ) ) {
			return $sendback;
		}

		$updated = 0;
		$status  = 'enable_sites' === $doaction ? 'on' : 'off';

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, 'syn_site_enabled', $status );
			++$updated;
		}

		// Remove previous notification parameters.
		$sendback = remove_query_arg( array( 'bulk_sites_enabled', 'bulk_sites_disabled' ), $sendback );

		$status_label = 'enable_sites' === $doaction ? 'enabled' : 'disabled';

		return add_query_arg( 'bulk_sites_' . $status_label, $updated, $sendback );
	}

	/**
	 * Display admin notices for bulk actions.
	 */
	public function bulk_action_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-syn_site' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just displaying a notice.
		if ( ! empty( $_GET['bulk_sites_enabled'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['bulk_sites_enabled'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of sites */
						_n( '%d site enabled.', '%d sites enabled.', $count, 'push-syndication' ),
						$count
					)
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just displaying a notice.
		if ( ! empty( $_GET['bulk_sites_disabled'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['bulk_sites_disabled'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of sites */
						_n( '%d site disabled.', '%d sites disabled.', $count, 'push-syndication' ),
						$count
					)
				)
			);
		}
	}

	/**
	 * Rename "Published" to "Enabled" in views filter.
	 *
	 * @param array $views Current views.
	 * @return array Modified views.
	 */
	public function rename_views( array $views ): array {
		if ( isset( $views['publish'] ) ) {
			$views['publish'] = str_replace(
				array( '>Published<', '>Published ' ),
				array( '>Enabled<', '>Enabled ' ),
				$views['publish']
			);
		}
		return $views;
	}

	public function admin_init() {
		// Load available transports from the TransportFactory.
		$container = \Automattic\Syndication\Infrastructure\DI\Container::instance();
		$factory   = $container->get( \Automattic\Syndication\Domain\Contracts\TransportFactoryInterface::class );

		if ( $factory instanceof \Automattic\Syndication\Domain\Contracts\TransportFactoryInterface ) {
			foreach ( $factory->get_available_transports() as $id => $data ) {
				$this->push_syndicate_transports[ $id ] = array(
					'name'  => $data['name'],
					'modes' => $data['modes'],
				);
			}
		}

		$this->push_syndicate_transports = apply_filters( 'syn_transports', $this->push_syndicate_transports );

		// register settings.
		register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
		register_setting( 'push_syndicate_settings', 'push_syndication_max_pull_attempts', array( $this, 'validate_max_pull_attempts' ) );

		// Maybe run upgrade.
		$this->upgrade();
	}

	public function load_scripts_and_styles( $hook ) {
		global $typenow;
		if ( 'syn_site' == $typenow ) {
			if ( 'edit.php' === $hook ) {
				wp_enqueue_style( 'syn-edit-sites', plugins_url( 'css/sites.css', __FILE__ ), array(), $this->version );
			} elseif ( in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
				wp_enqueue_style( 'syn-edit-site', plugins_url( 'css/edit-site.css', __FILE__ ), array(), $this->version );

				// Enqueue credential testing script.
				wp_enqueue_script(
					'syn-test-credentials',
					plugins_url( 'js/test-credentials.js', __FILE__ ),
					array( 'jquery' ),
					$this->version,
					true
				);

				wp_localize_script(
					'syn-test-credentials',
					'synTestCredentials',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'syn_test_credentials' ),
						'siteId'  => get_the_ID() ?: 0,
						'testing' => __( 'Testing...', 'push-syndication' ),
						'test'    => __( 'Test Credentials', 'push-syndication' ),
					)
				);
			}
		}
	}

	public function push_syndicate_settings_validate( $raw_settings ) {

		$settings                                = array();
		$settings['selected_post_types']         = ! empty( $raw_settings['selected_post_types'] ) ? array_map( 'sanitize_text_field', $raw_settings['selected_post_types'] ) : array();
		$settings['delete_pushed_posts']         = ! empty( $raw_settings['delete_pushed_posts'] ) ? sanitize_text_field( $raw_settings['delete_pushed_posts'] ) : 'off';
		$settings['selected_pull_sitegroups']    = ! empty( $raw_settings['selected_pull_sitegroups'] ) ? array_map( 'sanitize_text_field', $raw_settings['selected_pull_sitegroups'] ) : array();
		$settings['pull_time_interval']          = ! empty( $raw_settings['pull_time_interval'] ) ? max( (int) $raw_settings['pull_time_interval'], 300 ) : 3600;
		$settings['update_pulled_posts']         = ! empty( $raw_settings['update_pulled_posts'] ) ? sanitize_text_field( $raw_settings['update_pulled_posts'] ) : 'off';
		$settings['notification_methods']        = ! empty( $raw_settings['notification_methods'] ) ? array_map( 'sanitize_text_field', $raw_settings['notification_methods'] ) : array();
		$settings['notification_email_address']  = ! empty( $raw_settings['notification_email_address'] ) ? sanitize_email( $raw_settings['notification_email_address'] ) : '';
		$settings['notification_email_types']    = ! empty( $raw_settings['notification_email_types'] ) ? array_map( 'sanitize_text_field', $raw_settings['notification_email_types'] ) : array();
		$settings['notification_slack_webhook']  = ! empty( $raw_settings['notification_slack_webhook'] ) ? esc_url_raw( $raw_settings['notification_slack_webhook'] ) : '';
		$settings['notification_slack_types']    = ! empty( $raw_settings['notification_slack_types'] ) ? array_map( 'sanitize_text_field', $raw_settings['notification_slack_types'] ) : array();

		$this->pre_schedule_pull_content( $settings['selected_pull_sitegroups'] );

		return $settings;
	}

	public function register_syndicate_settings() {
		add_submenu_page(
			'edit.php?post_type=syn_site',
			esc_html__( 'Syndication Settings', 'push-syndication' ),
			esc_html__( 'Settings', 'push-syndication' ),
			apply_filters( 'syn_syndicate_cap', 'manage_options' ),
			'syndication-settings',
			array( $this, 'display_syndicate_settings' )
		);
	}

	public function display_syndicate_settings() {

		// Pull Settings sections.
		add_settings_section( 'pull_sitegroups', '', '__return_false', 'syn_pull_settings' );
		add_settings_field( 'pull_sitegroups_selection', esc_html__( 'Site Groups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_selection' ), 'syn_pull_settings', 'pull_sitegroups' );

		add_settings_section( 'pull_options', '', '__return_false', 'syn_pull_settings' );
		add_settings_field( 'pull_time_interval', esc_html__( 'Time interval', 'push-syndication' ), array( $this, 'display_time_interval_selection' ), 'syn_pull_settings', 'pull_options', array( 'label_for' => 'syn_pull_time_interval' ) );
		add_settings_field( 'max_pull_attempts', esc_html__( 'Maximum pull attempts', 'push-syndication' ), array( $this, 'display_max_pull_attempts' ), 'syn_pull_settings', 'pull_options', array( 'label_for' => 'syn_max_pull_attempts' ) );
		add_settings_field( 'update_pulled_posts', esc_html__( 'Update pulled posts', 'push-syndication' ), array( $this, 'display_update_pulled_posts_selection' ), 'syn_pull_settings', 'pull_options', array( 'label_for' => 'syn_update_pulled_posts' ) );

		// Push Settings section.
		add_settings_section( 'push_options', '', '__return_false', 'syn_push_settings' );
		add_settings_field( 'post_type_selection', esc_html__( 'Post types', 'push-syndication' ), array( $this, 'display_post_types_selection' ), 'syn_push_settings', 'push_options' );
		add_settings_field( 'delete_pushed_posts', esc_html__( 'Delete pushed posts', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_selection' ), 'syn_push_settings', 'push_options', array( 'label_for' => 'syn_delete_pushed_posts' ) );

		// Notifications section.
		add_settings_section( 'notification_options', '', '__return_false', 'syn_notifications' );
		add_settings_field( 'notification_email_enabled', esc_html__( 'Email', 'push-syndication' ), array( $this, 'display_notification_email_settings' ), 'syn_notifications', 'notification_options' );
		add_settings_field( 'notification_slack_enabled', esc_html__( 'Slack', 'push-syndication' ), array( $this, 'display_notification_slack_settings' ), 'syn_notifications', 'notification_options' );

		?>

		<div class="wrap">

			<h1><?php esc_html_e( 'Syndication Settings', 'push-syndication' ); ?></h1>

			<form action="options.php" method="post">

				<?php settings_fields( 'push_syndicate_settings' ); ?>

				<h2><?php esc_html_e( 'Pull Settings', 'push-syndication' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure how content is pulled from remote sites.', 'push-syndication' ); ?></p>

				<?php $this->display_pull_cron_status(); ?>

				<?php do_settings_sections( 'syn_pull_settings' ); ?>

				<h2><?php esc_html_e( 'Push Settings', 'push-syndication' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure how content is pushed to remote sites.', 'push-syndication' ); ?></p>

				<?php do_settings_sections( 'syn_push_settings' ); ?>

				<h2><?php esc_html_e( 'Notifications', 'push-syndication' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Receive notifications when content is pushed or pulled.', 'push-syndication' ); ?></p>

				<?php do_settings_sections( 'syn_notifications' ); ?>

				<?php submit_button(); ?>

			</form>

		</div>

		<?php
	}

	/**
	 * Display the pull cron status.
	 */
	public function display_pull_cron_status() {
		$next_scheduled = wp_next_scheduled( 'syn_pull_content' );

		if ( ! $next_scheduled ) {
			// Check if there are any individual site pull jobs scheduled.
			$cron_jobs      = _get_cron_array();
			$next_scheduled = null;

			if ( is_array( $cron_jobs ) ) {
				foreach ( $cron_jobs as $timestamp => $cron ) {
					if ( isset( $cron['syn_pull_content'] ) ) {
						$next_scheduled = $timestamp;
						break;
					}
				}
			}
		}

		echo '<div class="syn-cron-status notice notice-info inline" style="margin: 1em 0; padding: 10px;">';

		if ( $next_scheduled ) {
			$time_diff = $next_scheduled - time();

			if ( $time_diff > 0 ) {
				printf(
					'<p><strong>%s</strong> %s</p>',
					esc_html__( 'Next scheduled pull:', 'push-syndication' ),
					esc_html( human_time_diff( time(), $next_scheduled ) . ' ' . __( 'from now', 'push-syndication' ) )
				);
			} else {
				printf(
					'<p><strong>%s</strong> %s</p>',
					esc_html__( 'Next scheduled pull:', 'push-syndication' ),
					esc_html__( 'Pending (overdue)', 'push-syndication' )
				);
			}

			printf(
				'<p class="description">%s %s</p>',
				esc_html__( 'Scheduled for:', 'push-syndication' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_scheduled ) )
			);
		} else {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Status:', 'push-syndication' ),
				esc_html__( 'No pull jobs scheduled. Select Site Groups and save to schedule pulls.', 'push-syndication' )
			);
		}

		echo '</div>';
	}

	public function display_pull_sitegroups_selection() {
		$sitegroups = get_terms(
			array(
				'taxonomy'   => 'syn_sitegroup',
				'fields'     => 'all',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		// If there are no Site Groups defined, return.
		if ( empty( $sitegroups ) || is_wp_error( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No Site Groups defined yet.', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) ) . '">' . esc_html__( 'Create a Site Group', 'push-syndication' ) . '</a></p>';
			return;
		}

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Site Groups', 'push-syndication' ) . '</legend>';

		foreach ( $sitegroups as $sitegroup ) {
			?>
			<p>
				<label>
					<input type="checkbox" name="push_syndicate_settings[selected_pull_sitegroups][]" value="<?php echo esc_attr( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $this->push_syndicate_settings['selected_pull_sitegroups'] ); ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<?php
				if ( ! empty( $sitegroup->description ) ) {
					echo ' <span class="description">— ' . esc_html( $sitegroup->description ) . '</span>';
				}
				?>
			</p>
			<?php
		}

		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Select which Site Groups to pull content from on schedule.', 'push-syndication' ) . '</p>';
	}

	public function display_time_interval_selection() {
		$intervals = array(
			300   => __( '5 minutes', 'push-syndication' ),
			900   => __( '15 minutes', 'push-syndication' ),
			1800  => __( '30 minutes', 'push-syndication' ),
			3600  => __( '1 hour', 'push-syndication' ),
			7200  => __( '2 hours', 'push-syndication' ),
			21600 => __( '6 hours', 'push-syndication' ),
			43200 => __( '12 hours', 'push-syndication' ),
			86400 => __( '24 hours', 'push-syndication' ),
		);

		$current_value = (int) $this->push_syndicate_settings['pull_time_interval'];

		// Fall back to 1 hour if saved value doesn't match any option.
		if ( ! array_key_exists( $current_value, $intervals ) ) {
			$current_value = 3600;
		}
		?>
		<select id="syn_pull_time_interval" name="push_syndicate_settings[pull_time_interval]">
			<?php foreach ( $intervals as $seconds => $label ) : ?>
				<option value="<?php echo esc_attr( $seconds ); ?>" <?php selected( $current_value, $seconds ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Display the form field for the push_syndication_max_pull_attempts option.
	 */
	public function display_max_pull_attempts() {
		?>
		<input type="number" id="syn_max_pull_attempts" size="10" min="0" name="push_syndication_max_pull_attempts" value="<?php echo esc_attr( get_option( 'push_syndication_max_pull_attempts', 0 ) ); ?>" />
		<p class="description"><?php esc_html_e( 'Site will be disabled after failure threshold is reached. Set to 0 to disable.', 'push-syndication' ); ?></p>
		<?php
	}

	/**
	 * Validate the push_syndication_max_pull_attempts option.
	 *
	 * @param mixed $val The option value to validate.
	 *
	 * @return int Validated value.
	 */
	public function validate_max_pull_attempts( $val ) {
		/**
		 * Filter the maximum value that can be used for the
		 * push_syndication_max_pull_attempts option. This only takes effect when the
		 * option is set. Use the pre_option_push_syndication_max_pull_attempts or
		 * option_push_syndication_max_pull_attempts filters to modify values that
		 * have already been set.
		 *
		 * @param int $upper_limit Maximum value that can be used. Defaults to 100.
		 */
		$upper_limit = apply_filters( 'push_syndication_max_pull_attempts_upper_limit', 100 );

		// Ensure a value between zero and the upper limit.
		return min( $upper_limit, max( 0, (int) $val ) );
	}

	public function display_update_pulled_posts_selection() {
		?>
		<input type="checkbox" id="syn_update_pulled_posts" name="push_syndicate_settings[update_pulled_posts]" value="on" <?php checked( $this->push_syndicate_settings['update_pulled_posts'], 'on' ); ?> />
		<p class="description"><?php esc_html_e( 'When enabled, existing local posts will be updated if the remote content changes.', 'push-syndication' ); ?></p>
		<?php
	}

	public function display_post_types_selection() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Post types', 'push-syndication' ) . '</legend>';

		foreach ( $post_types as $post_type ) {
			?>
			<p>
				<label>
					<input type="checkbox" name="push_syndicate_settings[selected_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php $this->checked_array( $post_type->name, $this->push_syndicate_settings['selected_post_types'] ); ?> />
					<?php echo esc_html( $post_type->labels->name ); ?>
				</label>
			</p>
			<?php
		}

		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Select which post types can be pushed to remote sites.', 'push-syndication' ) . '</p>';
	}

	public function display_delete_pushed_posts_selection() {
		?>
		<input type="checkbox" id="syn_delete_pushed_posts" name="push_syndicate_settings[delete_pushed_posts]" value="on" <?php checked( $this->push_syndicate_settings['delete_pushed_posts'], 'on' ); ?> />
		<p class="description"><?php esc_html_e( 'When the source post is deleted, also delete pushed copies on remote sites.', 'push-syndication' ); ?></p>
		<?php
	}

	/**
	 * Display email notification settings.
	 */
	public function display_notification_email_settings() {
		$settings      = $this->push_syndicate_settings;
		$email_enabled = ! empty( $settings['notification_methods'] ) && in_array( 'email', (array) $settings['notification_methods'], true );
		$email_address = $settings['notification_email_address'] ?? '';
		$email_types   = $settings['notification_email_types'] ?? array();
		?>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_methods][]" value="email" <?php checked( $email_enabled ); ?> />
				<?php esc_html_e( 'Enable email notifications', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label for="syn_notification_email_address" class="screen-reader-text"><?php esc_html_e( 'Notification email address', 'push-syndication' ); ?></label>
			<input type="email" id="syn_notification_email_address" name="push_syndicate_settings[notification_email_address]" value="<?php echo esc_attr( $email_address ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Email address', 'push-syndication' ); ?>" aria-describedby="syn_notification_email_description" />
		</p>
		<p class="description" id="syn_notification_email_description"><?php esc_html_e( 'The email address where notifications should be sent.', 'push-syndication' ); ?></p>
		<p><strong><?php esc_html_e( 'Send notification when:', 'push-syndication' ); ?></strong></p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_email_types][]" value="processed" <?php $this->checked_array( 'processed', $email_types ); ?> />
				<?php esc_html_e( 'Site processed', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_email_types][]" value="create" <?php $this->checked_array( 'create', $email_types ); ?> />
				<?php esc_html_e( 'New post created', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_email_types][]" value="update" <?php $this->checked_array( 'update', $email_types ); ?> />
				<?php esc_html_e( 'Existing post updated', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_email_types][]" value="delete" <?php $this->checked_array( 'delete', $email_types ); ?> />
				<?php esc_html_e( 'Existing post deleted', 'push-syndication' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Display Slack notification settings.
	 */
	public function display_notification_slack_settings() {
		$settings      = $this->push_syndicate_settings;
		$slack_enabled = ! empty( $settings['notification_methods'] ) && in_array( 'slack', (array) $settings['notification_methods'], true );
		$slack_webhook = $settings['notification_slack_webhook'] ?? '';
		$slack_types   = $settings['notification_slack_types'] ?? array();
		?>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_methods][]" value="slack" <?php checked( $slack_enabled ); ?> />
				<?php esc_html_e( 'Enable Slack notifications', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label for="syn_notification_slack_webhook" class="screen-reader-text"><?php esc_html_e( 'Slack Webhook URL', 'push-syndication' ); ?></label>
			<input type="url" id="syn_notification_slack_webhook" name="push_syndicate_settings[notification_slack_webhook]" value="<?php echo esc_attr( $slack_webhook ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Slack Webhook URL', 'push-syndication' ); ?>" aria-describedby="syn_notification_slack_description" />
		</p>
		<p class="description" id="syn_notification_slack_description">
			<?php
			printf(
				/* translators: %s: link to Slack webhooks setup page */
				esc_html__( 'Set up a new Slack webhook URL %s.', 'push-syndication' ),
				'<a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">' . esc_html__( 'here', 'push-syndication' ) . '</a>'
			);
			?>
		</p>
		<p><strong><?php esc_html_e( 'Send notification when:', 'push-syndication' ); ?></strong></p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_slack_types][]" value="processed" <?php $this->checked_array( 'processed', $slack_types ); ?> />
				<?php esc_html_e( 'Site processed', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_slack_types][]" value="create" <?php $this->checked_array( 'create', $slack_types ); ?> />
				<?php esc_html_e( 'New post created', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_slack_types][]" value="update" <?php $this->checked_array( 'update', $slack_types ); ?> />
				<?php esc_html_e( 'Existing post updated', 'push-syndication' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="push_syndicate_settings[notification_slack_types][]" value="delete" <?php $this->checked_array( 'delete', $slack_types ); ?> />
				<?php esc_html_e( 'Existing post deleted', 'push-syndication' ); ?>
			</label>
		</p>
		<?php
	}

	public function display_sitegroups_selection() {

		echo '<h3>' . esc_html__( 'Select Sitegroups', 'push-syndication' ) . '</h3>';

		$selected_sitegroups = get_option( 'syn_selected_sitegroups' );
		$selected_sitegroups = ! empty( $selected_sitegroups ) ? $selected_sitegroups : array();

		// get all sitegroups.
		$sitegroups = get_terms(
			'syn_sitegroup',
			array(
				'fields'     => 'all',
				'hide_empty' => false,
				'orderby'    => 'name',
			) 
		);

		// if there are no sitegroups defined return.
		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		foreach ( $sitegroups as $sitegroup ) {

			?>

			<p>
				<label>
					<input type="checkbox" name="syn_selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ); ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<?php echo esc_html( $sitegroup->description ); ?>
			</p>

			<?php
		}
	}

	public function site_metaboxes() {
		add_meta_box( 'sitediv', __( ' Site Settings ' ), array( $this, 'add_site_settings_metabox' ), 'syn_site', 'normal', 'high' );
		add_meta_box( 'syn_pull_settings', __( ' Pull Settings ', 'push-syndication' ), array( $this, 'add_pull_settings_metabox' ), 'syn_site', 'normal', 'default' );
		remove_meta_box( 'submitdiv', 'syn_site', 'side' );
		add_meta_box( 'submitdiv', __( ' Site Status ' ), array( $this, 'add_site_status_metabox' ), 'syn_site', 'side', 'high' );
	}

	public function add_site_status_metabox( $site ) {
		$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
		$site_enabled = ! empty( $site_enabled ) ? $site_enabled : 'off';
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section">
						<span><?php esc_html_e( 'Status:', 'push-syndication' ); ?></span>
						<input type="hidden" name="post_status" value="publish" />
						<fieldset style="margin-top: 8px;">
							<label style="display: block; margin-bottom: 4px;">
								<input type="radio" name="site_enabled" value="on" <?php checked( $site_enabled, 'on' ); ?> />
								<?php esc_html_e( 'Enabled', 'push-syndication' ); ?>
							</label>
							<label style="display: block;">
								<input type="radio" name="site_enabled" value="off" <?php checked( $site_enabled, 'off' ); ?> />
								<?php esc_html_e( 'Disabled', 'push-syndication' ); ?>
							</label>
						</fieldset>
					</div>
				</div>
				<div class="clear"></div>
			</div>

			<div id="major-publishing-actions">

				<div id="delete-action">
					<a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $site->ID ) ); ?>"><?php esc_html_e( 'Move to Trash', 'push-syndication' ); ?></a>
				</div>

				<div id="publishing-action">
					<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="ajax-loading" alt="" />
					<?php
					if ( ! in_array( $site_enabled, array( 'on', 'off' ) ) || 0 == $site->ID ) { 
						?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Add Site', 'push-syndication' ); ?>" />
						<?php 
						submit_button(
							__( 'Add Site', 'push-syndication' ),
							'primary',
							'enabled',
							false,
							array(
								'tabindex'  => '5',
								'accesskey' => 'p',
							) 
						); 
						?>
					<?php } else { ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Update', 'push-syndication' ); ?>" />
						<input name="save" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e( 'Update', 'push-syndication' ); ?>" />
					<?php } ?>
				</div>

				<div class="clear"></div>
			</div>
		</div>

		<?php
	}

	/**
	 * Display the pull settings metabox.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public function add_pull_settings_metabox( $site ) {
		$pull_post_status = get_post_meta( $site->ID, 'syn_pull_post_status', true );
		$log_limit        = get_post_meta( $site->ID, 'syn_log_limit', true );

		// Default values.
		$pull_post_status = ! empty( $pull_post_status ) ? $pull_post_status : '';
		$log_limit        = '' !== $log_limit ? (int) $log_limit : 100;

		?>
		<p class="description">
			<?php esc_html_e( 'These settings only apply when this site is configured for pull syndication.', 'push-syndication' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="syn_pull_post_status"><?php esc_html_e( 'Default Post Status', 'push-syndication' ); ?></label>
				</th>
				<td>
					<select name="syn_pull_post_status" id="syn_pull_post_status">
						<option value="" <?php selected( $pull_post_status, '' ); ?>>
							<?php esc_html_e( 'Use remote status', 'push-syndication' ); ?>
						</option>
						<option value="draft" <?php selected( $pull_post_status, 'draft' ); ?>>
							<?php esc_html_e( 'Draft', 'push-syndication' ); ?>
						</option>
						<option value="pending" <?php selected( $pull_post_status, 'pending' ); ?>>
							<?php esc_html_e( 'Pending Review', 'push-syndication' ); ?>
						</option>
						<option value="publish" <?php selected( $pull_post_status, 'publish' ); ?>>
							<?php esc_html_e( 'Published', 'push-syndication' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Override the post status for pulled content. Useful for review workflows.', 'push-syndication' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="syn_log_limit"><?php esc_html_e( 'Log Entry Limit', 'push-syndication' ); ?></label>
				</th>
				<td>
					<input type="number" name="syn_log_limit" id="syn_log_limit" value="<?php echo esc_attr( (string) $log_limit ); ?>" min="1" max="1000" step="1" class="small-text" />
					<p class="description">
						<?php esc_html_e( 'Maximum number of log entries to keep for this site (1-1000). Default is 100.', 'push-syndication' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function add_site_settings_metabox( $post ) {

		global $post;

		$transport_type = get_post_meta( $post->ID, 'syn_transport_type', true );
		$transport_mode = get_post_meta( $post->ID, 'syn_transport_mode', true );
		$site_enabled   = get_post_meta( $post->ID, 'syn_site_enabled', true );

		// default values.
		$transport_type = ! empty( $transport_type ) ? $transport_type : 'WP_XMLRPC';
		$transport_mode = ! empty( $transport_mode ) ? $transport_mode : 'push';
		$site_enabled   = ! empty( $site_enabled ) ? $site_enabled : 'off';

		// nonce for verification when saving.
		wp_nonce_field( plugin_basename( __FILE__ ), 'site_settings_noncename' );

		$this->display_transports( $transport_type, $transport_mode );

		try {
			Syndication_Client_Factory::display_client_settings( $post, $transport_type );
		} catch ( Exception $e ) {
			echo esc_html( $e->getMessage() );
		}

		?>

		<div class="syn-test-credentials-wrapper" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
			<button type="button" id="syn-test-credentials-btn" class="button button-secondary">
				<?php esc_html_e( 'Test Credentials', 'push-syndication' ); ?>
			</button>
			<div id="syn-test-credentials-result" style="display: none; margin-top: 10px;"></div>
		</div>

		<div class="clear"></div>

		<?php
	}

	public function display_transports( $transport_type, $transport_mode ) {
		// Build lists of push and pull transports.
		$push_transports = array();
		$pull_transports = array();

		foreach ( $this->push_syndicate_transports as $key => $value ) {
			if ( in_array( 'push', $value['modes'], true ) ) {
				$push_transports[ $key ] = $value['name'];
			}
			if ( in_array( 'pull', $value['modes'], true ) ) {
				$pull_transports[ $key ] = $value['name'];
			}
		}

		// Current composite value for selection.
		$current_value = $transport_type . '|' . $transport_mode;

		echo '<p>' . esc_html__( 'Select a transport type and direction', 'push-syndication' ) . '</p>';
		echo '<select name="transport_type_mode" onchange="this.form.submit()">';

		// Push optgroup.
		if ( ! empty( $push_transports ) ) {
			echo '<optgroup label="' . esc_attr__( 'Push (send content to remote site)', 'push-syndication' ) . '">';
			foreach ( $push_transports as $key => $name ) {
				$value = $key . '|push';
				/* translators: %s: transport name */
				$label = sprintf( __( '%s (push)', 'push-syndication' ), $name );
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $current_value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</optgroup>';
		}

		// Pull optgroup.
		if ( ! empty( $pull_transports ) ) {
			echo '<optgroup label="' . esc_attr__( 'Pull (import content from remote site)', 'push-syndication' ) . '">';
			foreach ( $pull_transports as $key => $name ) {
				$value = $key . '|pull';
				/* translators: %s: transport name */
				$label = sprintf( __( '%s (pull)', 'push-syndication' ), $name );
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $current_value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</optgroup>';
		}

		echo '</select>';
	}

	public function save_site_settings() {

		global $post;

		// autosave verification.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it return.
		if ( ! isset( $_POST['site_settings_noncename'] ) || ! wp_verify_nonce( $_POST['site_settings_noncename'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		// Parse composite transport_type_mode value (e.g., "WP_REST_API|push").
		$transport_type_mode = isset( $_POST['transport_type_mode'] ) ? sanitize_text_field( $_POST['transport_type_mode'] ) : '';
		$parts               = explode( '|', $transport_type_mode );
		$transport_type      = $parts[0] ?? 'WP_XMLRPC';
		$transport_mode      = $parts[1] ?? 'push';

		// Validate transport type exists.
		if ( ! isset( $this->push_syndicate_transports[ $transport_type ] ) ) {
			$transport_type = 'WP_XMLRPC';
		}

		// Validate mode is valid for this transport.
		$valid_modes = $this->push_syndicate_transports[ $transport_type ]['modes'] ?? array( 'push' );
		if ( ! in_array( $transport_mode, $valid_modes, true ) ) {
			$transport_mode = $valid_modes[0] ?? 'push';
		}

		update_post_meta( $post->ID, 'syn_transport_type', $transport_type );
		update_post_meta( $post->ID, 'syn_transport_mode', $transport_mode );

		$site_enabled = sanitize_text_field( $_POST['site_enabled'] );

		try {
			$save = Syndication_Client_Factory::save_client_settings( $post->ID, $transport_type );
			if ( ! $save ) {
				return;
			}
			$client = Syndication_Client_Factory::get_client( $transport_type, $post->ID );

			if ( $client->test_connection() ) {
				add_filter(
					'redirect_post_location',
					function ( $location ) {
						return add_query_arg( 'message', 251, $location );
					}
				);
			} else {
				add_filter(
					'redirect_post_location',
					function ( $location ) {
						return add_query_arg( 'message', 252, $location );
					}
				);
				$site_enabled = 'off';
			}
		} catch ( Exception $e ) {
			add_filter(
				'redirect_post_location',
				function ( $location ) {
					return add_query_arg( 'message', 250, $location );
				}
			);
		}

		update_post_meta( $post->ID, 'syn_site_enabled', $site_enabled );

		// Save pull settings.
		if ( isset( $_POST['syn_pull_post_status'] ) ) {
			$pull_post_status = sanitize_text_field( $_POST['syn_pull_post_status'] );
			// Only allow valid statuses.
			if ( in_array( $pull_post_status, array( '', 'draft', 'pending', 'publish' ), true ) ) {
				update_post_meta( $post->ID, 'syn_pull_post_status', $pull_post_status );
			}
		}

		if ( isset( $_POST['syn_log_limit'] ) ) {
			$log_limit = (int) $_POST['syn_log_limit'];
			// Clamp to valid range (1-1000).
			$log_limit = max( 1, min( 1000, $log_limit ) );
			update_post_meta( $post->ID, 'syn_log_limit', $log_limit );
		}
	}

	/**
	 * AJAX handler for testing site credentials.
	 *
	 * Tests the connection without saving, providing immediate feedback.
	 * For existing sites, falls back to stored credentials if form fields are empty.
	 */
	public function ajax_test_credentials() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'syn_test_credentials' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'push-syndication' ) ) );
		}

		// Check capabilities.
		if ( ! current_user_can( apply_filters( 'syn_syndicate_cap', 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'push-syndication' ) ) );
		}

		// Get and validate transport type.
		$transport_type_mode = isset( $_POST['transport_type_mode'] ) ? sanitize_text_field( $_POST['transport_type_mode'] ) : '';
		$parts               = explode( '|', $transport_type_mode );
		$transport_type      = $parts[0] ?? '';

		if ( empty( $transport_type ) || ! isset( $this->push_syndicate_transports[ $transport_type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid transport type.', 'push-syndication' ) ) );
		}

		// Get site ID for existing sites (to fall back to stored credentials).
		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

		// Merge form data with stored credentials for empty fields.
		$credentials = $this->get_test_credentials( $transport_type, $_POST, $site_id );

		// Build transport instance based on type.
		$transport = $this->create_transport_for_testing( $transport_type, $credentials );

		if ( null === $transport ) {
			wp_send_json_error( array( 'message' => __( 'Could not create transport. Please check all fields are filled.', 'push-syndication' ) ) );
		}

		// Test the connection.
		try {
			$result = $transport->test_connection();

			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'Connection successful! Credentials are valid.', 'push-syndication' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Connection failed. Please check your credentials.', 'push-syndication' ) ) );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Connection error: %s', 'push-syndication' ), $e->getMessage() ) ) );
		}
	}

	/**
	 * Get credentials for testing, merging form data with stored values.
	 *
	 * @param string $transport_type The transport type.
	 * @param array  $post_data      The POST data from the form.
	 * @param int    $site_id        The site post ID (0 for new sites).
	 * @return array Merged credentials.
	 */
	private function get_test_credentials( string $transport_type, array $post_data, int $site_id ): array {
		$credentials = $post_data;

		// If no site ID, return form data as-is.
		if ( $site_id <= 0 ) {
			return $credentials;
		}

		// Get stored values for empty form fields.
		$container = \Automattic\Syndication\Infrastructure\DI\Container::instance();
		$encryptor = $container->get( \Automattic\Syndication\Domain\Contracts\EncryptorInterface::class );

		// Site URL - use stored if form is empty.
		if ( empty( $credentials['site_url'] ) ) {
			$credentials['site_url'] = get_post_meta( $site_id, 'syn_site_url', true );
		}

		// Username - use stored if form is empty.
		if ( empty( $credentials['site_username'] ) ) {
			$credentials['site_username'] = get_post_meta( $site_id, 'syn_site_username', true );
		}

		// Password - use stored (decrypted) if form is empty.
		if ( empty( $credentials['site_password'] ) ) {
			$encrypted = get_post_meta( $site_id, 'syn_site_password', true );
			if ( ! empty( $encrypted ) && $encryptor instanceof \Automattic\Syndication\Domain\Contracts\EncryptorInterface ) {
				$decrypted = $encryptor->decrypt( $encrypted );
				$credentials['site_password'] = is_string( $decrypted ) ? $decrypted : '';
			}
		}

		// Token (for WordPress.com) - use stored (decrypted) if form is empty.
		if ( empty( $credentials['site_token'] ) ) {
			$encrypted = get_post_meta( $site_id, 'syn_site_token', true );
			if ( ! empty( $encrypted ) && $encryptor instanceof \Automattic\Syndication\Domain\Contracts\EncryptorInterface ) {
				$decrypted = $encryptor->decrypt( $encrypted );
				$credentials['site_token'] = is_string( $decrypted ) ? $decrypted : '';
			}
		}

		// Blog ID (for WordPress.com) - use stored if form is empty.
		if ( empty( $credentials['blog_id'] ) ) {
			$credentials['blog_id'] = get_post_meta( $site_id, 'syn_site_id', true );
		}

		// Feed URL (for RSS) - use stored if form is empty.
		if ( empty( $credentials['feed_url'] ) ) {
			$credentials['feed_url'] = get_post_meta( $site_id, 'syn_feed_url', true );
		}

		return $credentials;
	}

	/**
	 * Create a transport instance for credential testing.
	 *
	 * @param string $transport_type The transport type ID.
	 * @param array  $post_data      The POST data with credentials.
	 * @return \Automattic\Syndication\Domain\Contracts\TransportInterface|null
	 */
	private function create_transport_for_testing( string $transport_type, array $post_data ) {
		$site_url = isset( $post_data['site_url'] ) ? esc_url_raw( $post_data['site_url'] ) : '';
		$username = isset( $post_data['site_username'] ) ? sanitize_text_field( $post_data['site_username'] ) : '';
		$password = isset( $post_data['site_password'] ) ? $post_data['site_password'] : '';

		switch ( $transport_type ) {
			case 'WP_REST_API':
				if ( empty( $site_url ) || empty( $username ) || empty( $password ) ) {
					return null;
				}
				return new \Automattic\Syndication\Infrastructure\Transport\REST\WordPressRestTransport(
					0, // No site ID for testing.
					$site_url,
					$username,
					$password
				);

			case 'WP_XMLRPC':
				if ( empty( $site_url ) || empty( $username ) || empty( $password ) ) {
					return null;
				}
				return new \Automattic\Syndication\Infrastructure\Transport\XMLRPC\XMLRPCTransport(
					0,
					$site_url,
					$username,
					$password
				);

			case 'WP_REST':
				// WordPress.com REST requires token and blog_id.
				$token   = isset( $post_data['site_token'] ) ? $post_data['site_token'] : $password;
				$blog_id = isset( $post_data['site_id'] ) ? sanitize_text_field( $post_data['site_id'] ) : '';
				if ( empty( $token ) || empty( $blog_id ) ) {
					return null;
				}
				return new \Automattic\Syndication\Infrastructure\Transport\REST\WordPressComTransport(
					0,
					$token,
					$blog_id
				);

			case 'WP_RSS':
				// RSS doesn't need authentication testing in the same way.
				$feed_url = isset( $post_data['feed_url'] ) ? esc_url_raw( $post_data['feed_url'] ) : $site_url;
				if ( empty( $feed_url ) ) {
					return null;
				}
				return new \Automattic\Syndication\Infrastructure\Transport\Feed\RSSFeedTransport(
					0,
					$feed_url,
					'post',
					'draft',
					'closed',
					'closed',
					false
				);

			default:
				return null;
		}
	}

	public function push_syndicate_admin_messages( $messages ) {

		// general error messages.
		$messages['syn_site'][250] = __( 'Transport class not found!', 'push-syndication' );
		$messages['syn_site'][251] = __( 'Connection Successful!', 'push-syndication' );
		$messages['syn_site'][252] = __( 'Something went wrong when connecting to the site. Site disabled.', 'push-syndication' );

		// xmlrpc error messages.
		$messages['syn_site'][301] = __( 'Invalid URL.', 'push-syndication' );
		$messages['syn_site'][302] = __( 'You do not have sufficient capability to perform this action.', 'push-syndication' );
		$messages['syn_site'][303] = __( 'Bad login/pass combination.', 'push-syndication' );
		$messages['syn_site'][304] = __( 'XML-RPC services are disabled on this site.', 'push-syndication' );
		$messages['syn_site'][305] = __( 'Transport error. Invalid endpoint', 'push-syndication' );
		$messages['syn_site'][306] = __( 'Something went wrong when connecting to the site.', 'push-syndication' );

		// WordPress.com REST error messages.
		$messages['site'][301] = __( 'Invalid URL', 'push-syndication' );

		// RSS error messages.

		return $messages;
	}

	public function add_post_metaboxes() {

		// return if no post types supports push syndication.
		if ( empty( $this->push_syndicate_settings['selected_post_types'] ) ) {
			return;
		}

		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$selected_post_types = $this->push_syndicate_settings['selected_post_types'];
		foreach ( $selected_post_types as $selected_post_type ) {
			add_meta_box( 'syndicatediv', __( ' Syndicate ' ), array( $this, 'add_syndicate_metabox' ), $selected_post_type, 'side', 'high' );
			// add_meta_box( 'syndicationstatusdiv', __( ' Syndication Status ' ), array( $this, 'add_syndication_status_metabox' ), $selected_post_type, 'normal', 'high' ); // phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- Commented out code.
		}
	}

	public function add_syndicate_metabox() {

		global $post;

		// nonce for verification when saving.
		wp_nonce_field( plugin_basename( __FILE__ ), 'syndicate_noncename' );

		// get all sitegroups.
		$sitegroups = get_terms(
			'syn_sitegroup',
			array(
				'fields'     => 'all',
				'hide_empty' => false,
				'orderby'    => 'name',
			) 
		);

		// if there are no sitegroups defined return.
		if ( empty( $sitegroups ) ) {
			echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
			echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=syn_sitegroup&post_type=syn_site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
			return;
		}

		$selected_sitegroups = get_post_meta( $post->ID, '_syn_selected_sitegroups', true );
		$selected_sitegroups = ! empty( $selected_sitegroups ) ? $selected_sitegroups : array();

		echo '<ul>';

		foreach ( $sitegroups as $sitegroup ) {

			?>
			<li>
				<label>
					<input type="checkbox" name="selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ); ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<p> <?php echo esc_html( $sitegroup->description ); ?> </p>
			</li>
			<?php
		}

		echo '</ul>';

		$post_source = get_post_meta( $post->ID, 'syn_source_url', true );
		if ( ! empty( $post_source ) ) {
			$source_host = wp_parse_url( $post_source, PHP_URL_HOST );
			if ( $source_host ) {
				printf(
					'<div class="syn-post-source"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: source site hostname */
							__( 'Source: %s', 'push-syndication' ),
							$source_host
						)
					)
				);
			}
		}
	}

	public function checked_array( $value, $group ) {
		if ( ! empty( $group ) ) {
			if ( in_array( $value, $group ) ) {
				echo 'checked="checked"';
			}
		}
	}

	public function save_syndicate_settings() {

		global $post;

		// autosave verification.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it return.
		if ( ! isset( $_POST['syndicate_noncename'] ) || ! wp_verify_nonce( $_POST['syndicate_noncename'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$selected_sitegroups = ! empty( $_POST['selected_sitegroups'] ) ? array_map( 'sanitize_key', $_POST['selected_sitegroups'] ) : '';
		update_post_meta( $post->ID, '_syn_selected_sitegroups', $selected_sitegroups );

		if ( '' === get_post_meta( $post->ID, 'post_uniqueid', true ) ) {
			update_post_meta( $post->ID, 'post_uniqueid', uniqid() );
		}
	}

	public function add_syndication_status_metabox() {
		// @TODO retrieve syndication status and display.
	}

	public function pre_schedule_push_content( $new_status, $old_status, $post ) {

		// autosave verification.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it return.
		if ( ! isset( $_POST['syndicate_noncename'] ) || ! wp_verify_nonce( $_POST['syndicate_noncename'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$sites = $this->get_sites_by_post_ID( $post->ID );

		if ( empty( $sites['selected_sites'] ) && empty( $sites['removed_sites'] ) ) {
			return;
		}

		do_action( 'syn_schedule_push_content', $post->ID, $sites );
	}

	public function schedule_push_content( $post_id, $sites ) {
		wp_schedule_single_event(
			time() - 1,
			'syn_push_content',
			array( $sites )
		);
	}

	/**
	 * Cron job function to syndicate content.
	 *
	 * @param array $sites Array of sites data to syndicate content to.
	 */
	public function push_content( $sites ) {

		// if another process running on it return.
		if ( get_transient( 'syn_syndicate_lock' ) == 'locked' ) {
			return;
		}

		// set value as locked, valid for 5 mins.
		set_transient( 'syn_syndicate_lock', 'locked', 60 * 5 );

		/** Start of critical section. */

		$post_ID = $sites['post_ID'];

		// an array containing states of sites.
		$slave_post_states = get_post_meta( $post_ID, '_syn_slave_post_states', true );
		$slave_post_states = ! empty( $slave_post_states ) ? $slave_post_states : array();

		$sites = apply_filters( 'syn_pre_push_post_sites', $sites, $post_ID, $slave_post_states );

		if ( ! empty( $sites['selected_sites'] ) ) {
			foreach ( $sites['selected_sites'] as $site ) {
				$transport_type = get_post_meta( $site->ID, 'syn_transport_type', true );
				$client         = Syndication_Client_Factory::get_client( $transport_type, $site->ID );
				$info           = $this->get_site_info( $site->ID, $slave_post_states, $client );

				// Check if post already exists on target to prevent syndication loops.
				if ( in_array( $transport_type, array( 'WP_REST', 'WP_XMLRPC' ), true ) ) {
					$unique_id = get_post_meta( $post_ID, 'post_uniqueid', true );

					if ( ! empty( $unique_id ) && $client->is_source_site_post( 'post_uniqueid', $unique_id ) ) {
						continue;
					}
				}

				if ( 'new' === $info['state'] || 'new-error' === $info['state'] ) { // States 'new' and 'new-error'.

					$push_new_shortcircuit = apply_filters( 'syn_pre_push_new_post_shortcircuit', false, $post_ID, $site, $transport_type, $client, $info );
					if ( true === $push_new_shortcircuit ) {
						continue;
					}

					$result = $client->new_post( $post_ID );

					$this->validate_result_new_post( $result, $slave_post_states, $site->ID, $client );
					$this->update_slave_post_states( $post_ID, $slave_post_states );

					do_action( 'syn_post_push_new_post', $result, $post_ID, $site, $transport_type, $client, $info );
				} else { // States 'success', 'edit-error' and 'remove-error'.
					$push_edit_shortcircuit = apply_filters( 'syn_pre_push_edit_post_shortcircuit', false, $post_ID, $site, $transport_type, $client, $info );
					if ( true === $push_edit_shortcircuit ) {
						continue;
					}

					$result = $client->edit_post( $post_ID, $info['ext_ID'] );

					$this->validate_result_edit_post( $result, $info['ext_ID'], $slave_post_states, $site->ID, $client );
					$this->update_slave_post_states( $post_ID, $slave_post_states );

					do_action( 'syn_post_push_edit_post', $result, $post_ID, $site, $transport_type, $client, $info );
				}
			}
		}

		if ( ! empty( $sites['removed_sites'] ) ) {
			foreach ( $sites['removed_sites'] as $site ) {
				$transport_type = get_post_meta( $site->ID, 'syn_transport_type', true );
				$client         = Syndication_Client_Factory::get_client( $transport_type, $site->ID );
				$info           = $this->get_site_info( $site->ID, $slave_post_states, $client );

				// if the post is not pushed we do not need to delete them.
				if ( 'success' === $info['state'] || 'edit-error' === $info['state'] || 'remove-error' === $info['state'] ) {
					$result = $client->delete_post( $info['ext_ID'] );
					if ( is_wp_error( $result ) ) {
						$slave_post_states['remove-error'][ $site->ID ] = $result;
						$this->update_slave_post_states( $post_ID, $slave_post_states );
					}
				}
			}
		}


		/** End of critical section. */

		// release the lock.
		delete_transient( 'syn_syndicate_lock' );
	}

	public function get_sites_by_post_ID( $post_ID ) {

		$selected_sitegroups = get_post_meta( $post_ID, '_syn_selected_sitegroups', true );
		$selected_sitegroups = ! empty( $selected_sitegroups ) ? $selected_sitegroups : array();
		$old_sitegroups      = get_post_meta( $post_ID, '_syn_old_sitegroups', true );
		$old_sitegroups      = ! empty( $old_sitegroups ) ? $old_sitegroups : array();
		$removed_sitegroups  = array_diff( $old_sitegroups, $selected_sitegroups );

		// initialization.
		$data = array(
			'post_ID'        => $post_ID,
			'selected_sites' => array(),
			'removed_sites'  => array(),
		);

		if ( ! empty( $selected_sitegroups ) ) {
			foreach ( $selected_sitegroups as $selected_sitegroup ) {

				// get all the sites in the sitegroup.
				$sites = $this->get_sites_by_sitegroup( $selected_sitegroup );
				if ( empty( $sites ) ) {
					continue;
				}

				foreach ( $sites as $site ) {
					$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
					if ( 'on' === $site_enabled ) {
						$data['selected_sites'][] = $site;
					}
				}
			}
		}

		if ( ! empty( $removed_sitegroups ) ) {
			foreach ( $removed_sitegroups as $removed_sitegroup ) {

				// get all the sites in the sitegroup.
				$sites = $this->get_sites_by_sitegroup( $removed_sitegroup );
				if ( empty( $sites ) ) {
					continue;
				}

				foreach ( $sites as $site ) {
					$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
					if ( 'on' === $site_enabled ) {
						$data['removed_sites'][] = $site;
					}
				}
			}
		}

		update_post_meta( $post_ID, '_syn_old_sitegroups', $selected_sitegroups );

		return $data;
	}

	/**
	 * Return an array of sites as objects based on sitegroup.
	 *
	 * @param object $sitegroup The sitegroup term object.
	 *
	 * @return array Array of site post objects.
	 */
	public function get_sites_by_sitegroup( $sitegroup ) {

		// @TODO if sitegroup is deleted?

		$results = new WP_Query(
			array(
				'post_type'      => 'syn_site',
				'posts_per_page' => 100,
				'tax_query'      => array(
					array(
						'taxonomy' => 'syn_sitegroup',
						'field'    => 'slug',
						'terms'    => $sitegroup,
					),
				),
			)
		);

		return (array) $results->posts;
	}

	/**
	 * Gets the site info relative to a post state.
	 *
	 * The states are:
	 *  - success - the post was pushed successfully.
	 *  - new-error - error when creating the post.
	 *  - edit-error - error when editing the post.
	 *  - remove-error - error when removing the post in a slave site.
	 *  - new - if the state is not found or the post is deleted in the slave site.
	 *
	 * @param int                          $site_ID           The site ID.
	 * @param array                        $slave_post_states Array of slave post states, passed by reference.
	 * @param Syndication_Client_Interface $client            The syndication client.
	 *
	 * @return array Site info with state and optional ext_ID.
	 */
	public function get_site_info( $site_ID, &$slave_post_states, $client ) {

		if ( empty( $slave_post_states ) ) {
			return array( 'state' => 'new' );
		}

		foreach ( $slave_post_states as $state => $sites ) {
			if ( isset( $sites[ $site_ID ] ) && is_array( $sites[ $site_ID ] ) && ! empty( $sites[ $site_ID ]['ext_ID'] ) ) {
				if ( $client->is_post_exists( $sites[ $site_ID ]['ext_ID'] ) ) {
					$info = array(
						'state'  => $state,
						'ext_ID' => $sites[ $site_ID ]['ext_ID'],
					);
					unset( $slave_post_states[ $state ] [ $site_ID ] );
					return $info;
				} else {
					return array( 'state' => 'new' );
				}
			}
		}

		return array( 'state' => 'new' );
	}

	/**
	 * Validates result of creating a new post.
	 *
	 * State transitions if the result is error:
	 * new          -> new-error
	 * new-error    -> new-error
	 * remove-error -> new-error
	 *
	 * @param int|WP_Error                 $result            The result from creating the post.
	 * @param array                        $slave_post_states Array of slave post states, passed by reference.
	 * @param int                          $site_ID           The site ID.
	 * @param Syndication_Client_Interface $client            The syndication client.
	 *
	 * @return int|WP_Error The result.
	 */
	public function validate_result_new_post( $result, &$slave_post_states, $site_ID, $client ) {

		if ( is_wp_error( $result ) ) {
			$slave_post_states['new-error'][ $site_ID ] = $result;
		} else {
			$slave_post_states['success'][ $site_ID ] = array(
				'ext_ID' => (int) $result,
			);
		}

		return $result;
	}

	/**
	 * Validates result of editing a post.
	 *
	 * State transitions if the result is error:
	 * edit-error   -> edit-error
	 * success      -> edit-error
	 *
	 * @param bool|WP_Error                $result            The result from editing the post.
	 * @param int                          $ext_ID            The external post ID.
	 * @param array                        $slave_post_states Array of slave post states, passed by reference.
	 * @param int                          $site_ID           The site ID.
	 * @param Syndication_Client_Interface $client            The syndication client.
	 *
	 * @return bool|WP_Error The result.
	 */
	public function validate_result_edit_post( $result, $ext_ID, &$slave_post_states, $site_ID, $client ) {
		if ( is_wp_error( $result ) ) {
			$slave_post_states['edit-error'][ $site_ID ] = array(
				'error'  => $result,
				'ext_ID' => (int) $ext_ID,
			);
		} else {
			$slave_post_states['success'][ $site_ID ] = array(
				'ext_ID' => (int) $ext_ID,
			);
		}

		return $result;
	}

	private function update_slave_post_states( $post_id, $slave_post_states ) {
		update_post_meta( $post_id, '_syn_slave_post_states', $slave_post_states );
	}

	public function pre_schedule_delete_content( $post_id ) {

		// if slave post deletion is not enabled return.
		$delete_pushed_posts = ! empty( $this->push_syndicate_settings['delete_pushed_posts'] ) ? $this->push_syndicate_settings['delete_pushed_posts'] : 'off';
		if ( 'on' !== $delete_pushed_posts ) {
			return;
		}

		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		do_action( 'syn_schedule_delete_content', $post_id );
	}

	public function schedule_delete_content( $post_ID ) {
		wp_schedule_single_event(
			time() - 1,
			'syn_delete_content',
			array( $post_ID )
		);
	}

	public function delete_content( $post_ID ) {

		$delete_error_sites = get_option( 'syn_delete_error_sites' );
		$delete_error_sites = ! empty( $delete_error_sites ) ? $delete_error_sites : array();
		$slave_posts        = $this->get_slave_posts( $post_ID );

		if ( empty( $slave_posts ) ) {
			return;
		}

		foreach ( $slave_posts as $site_ID => $ext_ID ) {
			$site_enabled = get_post_meta( $site_ID, 'syn_site_enabled', true );

			// check whether the site is enabled.
			if ( 'on' === $site_enabled ) {
				$transport_type = get_post_meta( $site_ID, 'syn_transport_type', true );
				$client         = Syndication_Client_Factory::get_client( $transport_type, $site_ID );

				if ( $client->is_post_exists( $ext_ID ) ) {
					$push_delete_shortcircuit = apply_filters( 'syn_pre_push_delete_post_shortcircuit', false, $ext_ID, $post_ID, $site_ID, $transport_type, $client );
					if ( true === $push_delete_shortcircuit ) {
						continue;
					}

					$result = $client->delete_post( $ext_ID );

					do_action( 'syn_post_push_delete_post', $result, $ext_ID, $post_ID, $site_ID, $transport_type, $client );

					if ( ! $result ) {
						$delete_error_sites[ $site_ID ] = array( $ext_ID );
					}
				}
			}
		}

		update_option( 'syn_delete_error_sites', $delete_error_sites );
		// all post metadata will be automatically deleted including slave_post_states.
	}

	/**
	 * Get the slave posts as $site_ID => $ext_ID.
	 *
	 * @param int $post_ID The post ID to get slave posts for.
	 *
	 * @return array|void Array of slave posts or void if none exist.
	 */
	public function get_slave_posts( $post_ID ) {

		// array containing states of sites.
		$slave_post_states = get_post_meta( $post_ID, '_syn_slave_post_states', true );
		if ( empty( $slave_post_states ) ) {
			return;
		}

		// array containing slave posts as $site_ID => $ext_ID.
		$slave_posts = array();

		foreach ( $slave_post_states as $state ) {
			foreach ( $state as $site_ID => $info ) {
				if ( ! is_wp_error( $info ) && ! empty( $info['ext_ID'] ) ) {
					$slave_posts[ $site_ID ] = $info['ext_ID'];
				}
			}
		}

		return $slave_posts;
	}

	/**
	 * Check if the current user has syndication capability.
	 *
	 * @return bool Whether the current user can syndicate.
	 */
	public function current_user_can_syndicate() {
		$syndicate_cap = apply_filters( 'syn_syndicate_cap', 'manage_options' );
		return current_user_can( $syndicate_cap );
	}

	public function cron_add_pull_time_interval( $schedules ) {

		// Only add custom interval if syndication settings are defined.
		if (
			empty( $this->push_syndicate_settings )
			|| ! array_key_exists( 'pull_time_interval', $this->push_syndicate_settings )
		) {
			return $schedules;
		}

		// Adds the custom time interval to the existing schedules.
		$schedules['syn_pull_time_interval'] = array(
			'interval' => intval( $this->push_syndicate_settings['pull_time_interval'] ),
			'display'  => esc_html__( 'Pull Time Interval', 'push-syndication' ),
		);

		return $schedules;
	}

	public function pre_schedule_pull_content( $selected_sitegroups ) {

		if ( ! $this->current_user_can_syndicate() ) {
			return;
		}

		$sites = array();
		foreach ( $selected_sitegroups as $selected_sitegroup ) {
			$sites = array_merge( $sites, $this->get_sites_by_sitegroup( $selected_sitegroup ) );
		}


		$this->schedule_pull_content( $sites );
	}

	public function schedule_pull_content( $sites ) {

		// to unschedule a cron we need the original arguements passed to schedule the cron.
		// we are saving it as a siteoption.
		$old_pull_sites = get_option( 'syn_old_pull_sites' );


		// Clear all previously scheduled jobs.
		if ( ! empty( $old_pull_sites ) ) {
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

	public function pull_get_selected_sites() {
		$selected_sitegroups = $this->push_syndicate_settings['selected_pull_sitegroups'];

		$sites = array();
		foreach ( $selected_sitegroups as $selected_sitegroup ) {
			$sites = array_merge( $sites, $this->get_sites_by_sitegroup( $selected_sitegroup ) );
		}

		// Order by last update date.
		usort( $sites, array( $this, 'sort_sites_by_last_pull_date' ) );

		return $sites;
	}

	private function sort_sites_by_last_pull_date( $site_a, $site_b ) {
		$site_a_pull_date = (int) get_post_meta( $site_a->ID, 'syn_last_pull_time', true );
		$site_b_pull_date = (int) get_post_meta( $site_b->ID, 'syn_last_pull_time', true );

		if ( $site_a_pull_date == $site_b_pull_date ) {
			return 0;
		}

		return ( $site_a_pull_date < $site_b_pull_date ) ? -1 : 1;
	}

	public function pull_content( $sites = array() ) {
		add_filter( 'http_headers_useragent', array( $this, 'syndication_user_agent' ) );

		if ( empty( $sites ) ) {
			$sites = $this->pull_get_selected_sites();
		}

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

		foreach ( $sites as $site ) {
			$site_id = $site->ID;

			$site_enabled = get_post_meta( $site_id, 'syn_site_enabled', true );
			if ( 'on' !== $site_enabled ) {
				continue;
			}

			$transport_type = get_post_meta( $site_id, 'syn_transport_type', true );
			$client         = Syndication_Client_Factory::get_client( $transport_type, $site_id );
			$posts          = apply_filters( 'syn_pre_pull_posts', $client->get_posts(), $site, $client );

			$post_types_processed = array();

			if ( is_array( $posts ) && count( $posts ) > 0 ) {
				Syndication_Logger::log_post_info( $site_id, $status = 'start_import', $message = sprintf( __( 'starting import for site id %1$d with %2$d posts', 'push-syndication' ), $site_id, count( $posts ) ), $log_time = null, $extra = array() );
			} else {
				Syndication_Logger::log_post_info( $site_id, $status = 'no_posts', $message = sprintf( __( 'no posts for site id %d', 'push-syndication' ), $site_id ), $log_time = null, $extra = array() );
			}

			if ( is_array( $posts ) && ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					if ( ! in_array( $post['post_type'], $post_types_processed ) ) {
						remove_post_type_support( $post['post_type'], 'revisions' );
						$post_types_processed[] = $post['post_type'];
					}

					if ( empty( $post['post_guid'] ) ) {
						Syndication_Logger::log_post_error( $site_id, $status = 'no_post_guid', $message = sprintf( __( 'skipping post no guid', 'push-syndication' ) ), $log_time = null, $extra = array( 'post' => $post ) );
						continue;
					}
					$post_id = $this->find_post_by_guid( $post['post_guid'], $post, $site );

					if ( $post_id ) {
						$pull_edit_shortcircuit = apply_filters( 'syn_pre_pull_edit_post_shortcircuit', false, $post, $site, $transport_type, $client );
						if ( true === $pull_edit_shortcircuit ) {
							Syndication_Logger::log_post_info( $site_id, $status = 'skip_pre_pull_edit_post', $message = sprintf( __( 'skipping post per syn_pre_pull_edit_post_shortcircuit', 'push-syndication' ) ), $log_time = null, $extra = array( 'post' => $post ) );
							continue;
						}
						// if updation is disabled continue.
						if ( 'on' !== $this->push_syndicate_settings['update_pulled_posts'] ) {
							Syndication_Logger::log_post_info( $site_id, $status = 'skip_update_pulled_posts', $message = sprintf( __( 'skipping post update per update_pulled_posts setting', 'push-syndication' ) ), $log_time = null, $extra = array( 'post' => $post ) );
							continue;
						}
						$post['ID'] = $post_id;

						$post = apply_filters( 'syn_pull_edit_post', $post, $site, $client );

						$result = wp_update_post( $post, true );

						do_action( 'syn_post_pull_edit_post', $result, $post, $site, $transport_type, $client );

						$updated_post_ids[] = (int) $result;
					} else {
						$pull_new_shortcircuit = apply_filters( 'syn_pre_pull_new_post_shortcircuit', false, $post, $site, $transport_type, $client );
						if ( true === $pull_new_shortcircuit ) {
							Syndication_Logger::log_post_info( $site_id, $status = 'syn_pre_pull_new_post_shortcircuit', $message = sprintf( __( 'skipping post per syn_pre_pull_edit_post_shortcircuit', 'push-syndication' ) ), $log_time = null, $extra = array( 'post' => $post ) );
							continue;
						}
						$post = apply_filters( 'syn_pull_new_post', $post, $site, $client );

						$result = wp_insert_post( $post, true );

						do_action( 'syn_post_pull_new_post', $result, $post, $site, $transport_type, $client );

						if ( ! is_wp_error( $result ) ) {
							update_post_meta( $result, 'syn_post_guid', $post['post_guid'] );
							update_post_meta( $result, 'syn_source_site_id', $site_id );
						}

						$updated_post_ids[] = (int) $result;
					}
				}

				foreach ( $post_types_processed as $post_type ) {
					add_post_type_support( $post_type, 'revisions' );
				}
			}

			update_post_meta( $site_id, 'syn_last_pull_time', time() );
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

	public function syndication_user_agent( $user_agent ) {
		return apply_filters( 'syn_pull_user_agent', self::CUSTOM_USER_AGENT );
	}

	public function find_post_by_guid( $guid, $post, $site ) {
		global $wpdb;

		$post_id = apply_filters( 'syn_pre_find_post_by_guid', false, $guid, $post, $site );
		if ( false !== $post_id ) {
			return $post_id;
		}

		// A direct query here is way more efficient than WP_Query, because we don't have to do all the extra processing, filters, and JOIN.
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'syn_post_guid' AND meta_value = %s LIMIT 1", $guid ) );

		if ( $post_id ) {
			return $post_id;
		}

		return false;
	}

	/**
	 * Reschedule all scheduled pull jobs.
	 */
	public function refresh_pull_jobs() {
		$sites = $this->pull_get_selected_sites();

		$this->schedule_pull_content( $sites );
	}

	/**
	 * Handle save_post and delete_post for syn_site posts.
	 *
	 * If a syn_site post is updated or deleted we should reprocess any scheduled pull jobs.
	 *
	 * @param int $post_id The post ID.
	 */
	public function handle_site_change( $post_id ) {
		if ( 'syn_site' === get_post_type( $post_id ) ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Handle create_term and delete_term for syn_sitegroup terms.
	 *
	 * If a site group is created or deleted we should reprocess any scheduled pull jobs.
	 *
	 * @param int    $term     The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy slug.
	 */
	public function handle_site_group_change( $term, $tt_id, $taxonomy ) {
		if ( 'syn_sitegroup' === $taxonomy ) {
			$this->schedule_deferred_pull_jobs_refresh();
		}
	}

	/**
	 * Schedule a deferred refresh of pull jobs.
	 *
	 * This prevents timeout issues when many sites are configured by deferring
	 * the refresh to a background cron event instead of running synchronously.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	private function schedule_deferred_pull_jobs_refresh() {
		// Use a transient to debounce multiple requests within a short time window.
		$debounce_key = 'syn_pull_jobs_refresh_pending';

		if ( get_transient( $debounce_key ) ) {
			// Already scheduled, don't schedule again.
			return;
		}

		// Set transient for 2 minutes to prevent duplicate scheduling.
		set_transient( $debounce_key, '1', 2 * MINUTE_IN_SECONDS );

		// Clear any existing scheduled refresh and schedule a new one.
		wp_clear_scheduled_hook( 'syn_refresh_pull_jobs' );
		wp_schedule_single_event( time() + 60, 'syn_refresh_pull_jobs' );
	}

	private function upgrade() {
		global $wpdb;

		if ( version_compare( $this->version, SYNDICATION_VERSION, '>=' ) ) {
			return;
		}

		// Upgrade to 2.1.
		if ( version_compare( $this->version, '2.0', '<=' ) ) {
			$inserted_posts_by_site = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'syn_inserted_posts'" );
			foreach ( $inserted_posts_by_site as $site_id ) {
				$inserted_posts = get_post_meta( $site_id, 'syn_inserted_posts', true );

				foreach ( $inserted_posts as $inserted_post_id => $inserted_post_guid ) {
					update_post_meta( $inserted_post_id, 'syn_post_guid', $inserted_post_guid );
					update_post_meta( $inserted_post_id, 'syn_source_site_id', $site_id );
				}
			}

			update_option( 'syn_version', '2.1' );
		}

		update_option( 'syn_version', SYNDICATION_VERSION );
	}
}
