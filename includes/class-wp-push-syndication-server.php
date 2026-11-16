<?php
/**
 * Main syndication server class.
 *
 * @package Syndication
 */

require_once __DIR__ . '/class-syndication-client-factory.php';
require_once __DIR__ . '/push-syndicate-encryption.php';

/**
 * Class WP_Push_Syndication_Server
 *
 * Core syndication server that handles plugin initialization, admin interfaces,
 * settings management, and orchestrates content push/pull operations between sites.
 */
class WP_Push_Syndication_Server {

	const CUSTOM_USER_AGENT = 'WordPress/Syndication Plugin';

	/**
	 * Lowest pull interval, in seconds, that may be scheduled.
	 */
	const MIN_PULL_TIME_INTERVAL = 300;

	public $push_syndicate_settings;
	public $push_syndicate_default_settings;
	public $push_syndicate_transports;

	private $version;

	public function __construct() {

		// initialization.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 11 ); // After init(), which loads the stored version.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// custom columns.
		add_filter( 'manage_edit-syn_site_columns', array( $this, 'add_new_columns' ) );
		add_action( 'manage_syn_site_posts_custom_column', array( $this, 'manage_columns' ), 10, 2 );

		// submenus.
		add_action( 'admin_menu', array( $this, 'register_syndicate_settings' ) );
		add_filter( 'option_page_capability_push_syndicate_settings', array( $this, 'settings_capability' ) );

		// defining sites.
		add_action( 'save_post', array( $this, 'save_site_settings' ) );

		// loading necessary styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );

		// filter admin notices in custom post types.
		add_filter( 'post_updated_messages', array( $this, 'push_syndicate_admin_messages' ) );

		// syndicating content.
		add_action( 'add_meta_boxes', array( $this, 'add_post_metaboxes' ) );
		add_action( 'transition_post_status', array( $this, 'save_syndicate_settings' ), 10, 3 ); // Use transition_post_status instead of save_post because the former is fired earlier which causes race conditions when a site group select and publish happen on the same load.
		add_action( 'wp_trash_post', array( $this, 'pre_schedule_delete_content' ) );

		// adding custom time interval.
		add_filter( 'cron_schedules', array( $this, 'cron_add_pull_time_interval' ) );

		// firing a cron job.
		add_action( 'transition_post_status', array( $this, 'pre_schedule_push_content' ), 10, 3 );
		// Before the post meta that records the remote copies is deleted.
		add_action( 'before_delete_post', array( $this, 'pre_schedule_delete_content' ) );

		// Handle changes to sites and site groups.
		add_action( 'save_post', array( $this, 'handle_site_change' ) );
		add_action( 'delete_post', array( $this, 'handle_site_change' ) );
		add_action( 'create_term', array( $this, 'handle_site_group_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'handle_site_group_change' ), 10, 3 );

		// Generic hook for reprocessing all scheduled pull jobs. This allows
		// for bulk rescheduling of jobs that were scheduled the old way (one job.
		// for many sites).
		add_action( 'syn_refresh_pull_jobs', array( $this, 'refresh_pull_jobs' ) );


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
					'name'          => __( 'Sites', 'push-syndication' ),
					'singular_name' => __( 'Site', 'push-syndication' ),
					'add_new'       => __( 'Add Site', 'push-syndication' ),
					'add_new_item'  => __( 'Add New Site', 'push-syndication' ),
					'edit_item'     => __( 'Edit Site', 'push-syndication' ),
					'new_item'      => __( 'New Site', 'push-syndication' ),
					'view_item'     => __( 'View Site', 'push-syndication' ),
					'search_items'  => __( 'Search Sites', 'push-syndication' ),
				),
				'description'          => __( 'Sites in the network', 'push-syndication' ),
				'public'               => false,
				'show_ui'              => true,
				'publicly_queryable'   => false,
				'exclude_from_search'  => true,
				'menu_position'        => 100,
				// @TODO we need a menu icon here.
				'hierarchical'         => false, // @TODO check this
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
					'parent_item_colon' => __( 'Parent Site Group', 'push-syndication' ),
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
			'selected_pull_sitegroups' => array(),
			'selected_post_types'      => array( 'post' ),
			'delete_pushed_posts'      => 'off',
			'pull_time_interval'       => '3600',
			'update_pulled_posts'      => 'off',
			'client_id'                => '',
			'client_secret'            => '',
		);

		$this->push_syndicate_settings = wp_parse_args( (array) get_option( 'push_syndicate_settings' ), $this->push_syndicate_default_settings );

		$this->version = get_option( 'syn_version' );

		do_action( 'syn_after_init_server' );
	}

	public function register_syndicate_actions() {
		add_action( 'syn_schedule_push_content', array( $this, 'schedule_push_content' ), 10, 2 );
		add_action( 'syn_schedule_delete_content', array( $this, 'schedule_delete_content' ) );

		add_action( 'syn_push_content', array( $this, 'push_content' ) );
		add_action( 'syn_delete_content', array( $this, 'delete_content' ), 10, 2 );
		add_action( 'syn_pull_content', array( $this, 'pull_content' ), 10, 1 );
	}

	public function add_new_columns( $columns ) {
		$new_columns                  = array();
		$new_columns['cb']            = '<input type="checkbox" />';
		$new_columns['title']         = _x( 'Site Name', 'column name', 'push-syndication' );
		$new_columns['client-type']   = _x( 'Client Type', 'column name', 'push-syndication' );
		$new_columns['syn_sitegroup'] = _x( 'Groups', 'column name', 'push-syndication' );
		$new_columns['site_status']   = _x( 'Status', 'column name', 'push-syndication' );
		$new_columns['date']          = _x( 'Date', 'column name', 'push-syndication' );
		return $new_columns;
	}

	public function manage_columns( $column_name, $id ) {
		global $wpdb;
		switch ( $column_name ) {
			case 'client-type':
				$transport_type = get_post_meta( $id, 'syn_transport_type', true );
				try {
					$client      = Syndication_Client_Factory::get_client( $transport_type, $id );
					$client_data = $client->get_client_data();
					echo esc_html( sprintf( '%s (%s)', $client_data['name'], array_shift( $client_data['modes'] ) ) );
				} catch ( Exception $e ) {
					// translators: %s: Transport type of the site.
					printf( esc_html__( 'Unknown (%s)', 'push-syndication' ), esc_html( $transport_type ) );
				}
				break;
			case 'syn_sitegroup':
				the_terms( $id, 'syn_sitegroup', '', ', ', '' );
				break;
			case 'site_status':
				$site_status = get_post_meta( $id, 'syn_site_enabled', true );
				if ( ! filter_var( $site_status, FILTER_VALIDATE_BOOLEAN ) ) {
					esc_html_e( 'disabled', 'push-syndication' );
				} else {
					esc_html_e( 'enabled', 'push-syndication' );
				}
				break;
			default:
				break;
		}
	}

	public function admin_init() {
		// @TODO define more parameters.
		$name_match = '#class-syndication-(.+)-client\.php$#';

		$full_path = __DIR__ . '/';
		if ( $handle = opendir( $full_path ) ) {
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( ! preg_match( $name_match, $entry, $matches ) ) {
					continue;
				}
				require_once $full_path . $entry;
				$class_name = 'Syndication_' . strtoupper( str_replace( '-', '_', $matches[1] ) ) . '_Client';

				if ( ! class_exists( $class_name ) ) {
					continue;
				}
				$client_data = call_user_func( array( $class_name, 'get_client_data' ) );
				if ( is_array( $client_data ) && ! empty( $client_data ) ) {
					$this->push_syndicate_transports[ $client_data['id'] ] = array(
						'name'  => $client_data['name'],
						'modes' => $client_data['modes'],
					);
				}
			}
		}
		$this->push_syndicate_transports = apply_filters( 'syn_transports', $this->push_syndicate_transports );

		// register settings.
		register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
		register_setting( 'push_syndicate_settings', 'push_syndication_max_pull_attempts', array( $this, 'validate_max_pull_attempts' ) );
	}

	public function load_scripts_and_styles( $hook ) {
		global $typenow;
		if ( 'syn_site' == $typenow ) {
			if ( 'edit.php' === $hook ) {
				wp_enqueue_style( 'syn-edit-sites', plugins_url( 'css/sites.css', __FILE__ ), array(), $this->version );
			} elseif ( in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
				wp_enqueue_style( 'syn-edit-site', plugins_url( 'css/edit-site.css', __FILE__ ), array(), $this->version );
			}
		}
	}

	/**
	 * Validates the plugin settings before they are stored.
	 *
	 * Selections are intersected with the values actually offered by the
	 * settings screen, so only registered post types and existing sitegroup
	 * slugs can ever reach the option. A newly submitted client secret is
	 * encrypted before it is stored.
	 *
	 * @param array $raw_settings Unvalidated settings, as submitted.
	 *
	 * @return array Validated settings.
	 */
	public function push_syndicate_settings_validate( $raw_settings ) {

		$raw_settings = (array) $raw_settings;

		$settings                             = array();
		$settings['client_id']                = sanitize_text_field( $raw_settings['client_id'] ?? '' );
		$settings['selected_post_types']      = $this->validate_selection( $raw_settings['selected_post_types'] ?? array(), get_post_types() );
		$settings['delete_pushed_posts']      = isset( $raw_settings['delete_pushed_posts'] ) && 'on' === $raw_settings['delete_pushed_posts'] ? 'on' : 'off';
		$settings['selected_pull_sitegroups'] = $this->validate_selection( $raw_settings['selected_pull_sitegroups'] ?? array(), $this->get_sitegroup_slugs() );
		$settings['pull_time_interval']       = ! empty( $raw_settings['pull_time_interval'] ) ? max( self::MIN_PULL_TIME_INTERVAL, absint( $raw_settings['pull_time_interval'] ) ) : 3600;
		$settings['update_pulled_posts']      = isset( $raw_settings['update_pulled_posts'] ) && 'on' === $raw_settings['update_pulled_posts'] ? 'on' : 'off';

		// The client secret field is write-only: a blank submission keeps the stored secret.
		$submitted_secret          = sanitize_text_field( $raw_settings['client_secret'] ?? '' );
		$settings['client_secret'] = '' !== $submitted_secret
			? push_syndicate_encrypt( $submitted_secret )
			: ( $this->push_syndicate_settings['client_secret'] ?? '' );

		$this->pre_schedule_pull_content( $settings['selected_pull_sitegroups'] );

		return $settings;
	}

	/**
	 * Reduces a submitted selection to the values that were actually offered.
	 *
	 * @param mixed $submitted Submitted value, expected to be an array of strings.
	 * @param array $allowed   Permitted values.
	 *
	 * @return array Validated selection, re-indexed.
	 */
	private function validate_selection( $submitted, $allowed ) {
		$submitted = array_filter( (array) $submitted, 'is_string' );

		return array_values( array_intersect( $submitted, $allowed ) );
	}

	/**
	 * Gets the slugs of every existing sitegroup.
	 *
	 * @return array Sitegroup slugs.
	 */
	private function get_sitegroup_slugs() {
		$slugs = get_terms(
			array(
				'taxonomy'   => 'syn_sitegroup',
				'fields'     => 'slugs',
				'hide_empty' => false,
			)
		);

		return is_array( $slugs ) ? $slugs : array();
	}

	/**
	 * Gets the client secret, decrypted for use.
	 *
	 * Falls back to the stored value for secrets saved before they were
	 * encrypted at rest.
	 *
	 * @return string The client secret.
	 */
	private function get_client_secret() {
		$stored = $this->push_syndicate_settings['client_secret'] ?? '';

		if ( '' === $stored ) {
			return '';
		}

		$decrypted = push_syndicate_decrypt( $stored );

		return is_string( $decrypted ) ? $decrypted : $stored;
	}

	public function register_syndicate_settings() {
		add_submenu_page( 'options-general.php', esc_html__( 'Push Syndication Settings', 'push-syndication' ), esc_html__( 'Push Syndication', 'push-syndication' ), apply_filters( 'syn_syndicate_cap', 'manage_options' ), 'push-syndicate-settings', array( $this, 'display_syndicate_settings' ) );
	}

	/**
	 * Capability options.php requires to save the settings screen.
	 *
	 * The same capability that shows the screen, rather than the default manage_options.
	 *
	 * @return string Capability name.
	 */
	public function settings_capability() {
		return apply_filters( 'syn_syndicate_cap', 'manage_options' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook.
	}

	public function display_syndicate_settings() {

		add_settings_section( 'push_syndicate_pull_sitegroups', esc_html__( 'Site Groups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_description' ), 'push_syndicate_pull_sitegroups' );
		add_settings_field( 'pull_sitegroups_selection', esc_html__( 'Select sitegroups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_selection' ), 'push_syndicate_pull_sitegroups', 'push_syndicate_pull_sitegroups' );

		add_settings_section( 'push_syndicate_pull_options', esc_html__( 'Pull Options', 'push-syndication' ), array( $this, 'display_pull_options_description' ), 'push_syndicate_pull_options' );
		add_settings_field( 'pull_time_interval', esc_html__( 'Specify time interval in seconds', 'push-syndication' ), array( $this, 'display_time_interval_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options', array( 'label_for' => 'pull_time_interval' ) );
		add_settings_field( 'max_pull_attempts', esc_html__( 'Maximum pull attempts', 'push-syndication' ), array( $this, 'display_max_pull_attempts' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options', array( 'label_for' => 'push_syndication_max_pull_attempts' ) );
		add_settings_field( 'update_pulled_posts', esc_html__( 'Update pulled posts', 'push-syndication' ), array( $this, 'display_update_pulled_posts_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options', array( 'label_for' => 'update_pulled_posts' ) );

		add_settings_section( 'push_syndicate_post_types', esc_html__( 'Post Types', 'push-syndication' ), array( $this, 'display_push_post_types_description' ), 'push_syndicate_post_types' );
		add_settings_field( 'post_type_selection', esc_html__( 'Select post types', 'push-syndication' ), array( $this, 'display_post_types_selection' ), 'push_syndicate_post_types', 'push_syndicate_post_types' );

		add_settings_section( 'delete_pushed_posts', esc_html__( 'Delete Pushed Posts', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_description' ), 'delete_pushed_posts' );
		add_settings_field( 'delete_post_check', esc_html__( 'Delete pushed posts', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_selection' ), 'delete_pushed_posts', 'delete_pushed_posts', array( 'label_for' => 'delete_pushed_posts' ) );

		add_settings_section( 'api_token', esc_html__( 'API Token Configuration', 'push-syndication' ), array( $this, 'display_apitoken_description' ), 'api_token' );
		add_settings_field( 'client_id', esc_html__( 'Enter your client ID', 'push-syndication' ), array( $this, 'display_client_id' ), 'api_token', 'api_token', array( 'label_for' => 'client_id' ) );
		add_settings_field( 'client_secret', esc_html__( 'Enter your client secret', 'push-syndication' ), array( $this, 'display_client_secret' ), 'api_token', 'api_token', array( 'label_for' => 'client_secret' ) );

		?>

		<div class="wrap">

			<h1><?php esc_html_e( 'Push Syndicate Settings', 'push-syndication' ); ?></h1>

			<form action="options.php" method="post">

				<?php settings_fields( 'push_syndicate_settings' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_sitegroups' ); ?>

				<?php do_settings_sections( 'push_syndicate_pull_options' ); ?>

				<?php submit_button( __( 'Pull Now', 'push-syndication' ) ); ?>

				<?php do_settings_sections( 'push_syndicate_post_types' ); ?>

				<?php do_settings_sections( 'delete_pushed_posts' ); ?>

				<?php do_settings_sections( 'api_token' ); ?>

				<?php submit_button(); ?>

			</form>

			<?php $this->get_api_token(); ?>

		</div>

		<?php
	}

	public function display_pull_sitegroups_description() {
		echo esc_html__( 'Select the sitegroups to pull content', 'push-syndication' );
	}

	public function display_pull_sitegroups_selection() {

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
					<input type="checkbox" name="push_syndicate_settings[selected_pull_sitegroups][]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $this->push_syndicate_settings['selected_pull_sitegroups'] ); ?> />
					<?php echo esc_html( $sitegroup->name ); ?>
				</label>
				<?php echo esc_html( $sitegroup->description ); ?>
			</p>

			<?php
		}
	}

	public function display_pull_options_description() {
		echo esc_html__( 'Configure options for pulling content', 'push-syndication' );
	}

	public function display_time_interval_selection() {
		echo '<input type="text" size="10" id="pull_time_interval" name="push_syndicate_settings[pull_time_interval]" value="' . esc_attr( $this->push_syndicate_settings['pull_time_interval'] ) . '"/>';
	}

	/**
	 * Display the form field for the push_syndication_max_pull_attempts option.
	 */
	public function display_max_pull_attempts() {
		?>
		<input type="text" size="10" id="push_syndication_max_pull_attempts" name="push_syndication_max_pull_attempts" value="<?php echo esc_attr( get_option( 'push_syndication_max_pull_attempts', 0 ) ); ?>" />
		<p><?php echo esc_html__( 'Site will be disabled after failure threshold is reached. Set to 0 to disable.', 'push-syndication' ); ?></p>
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
		// @TODO refractor this.
		echo '<input type="checkbox" id="update_pulled_posts" name="push_syndicate_settings[update_pulled_posts]" value="on" ';
		echo checked( $this->push_syndicate_settings['update_pulled_posts'], 'on' ) . ' />';
	}

	public function display_push_post_types_description() {
		echo esc_html__( 'Select the post types to add support for pushing content', 'push-syndication' );
	}

	public function display_post_types_selection() {

		// @TODO add more suitable filters.
		$post_types = get_post_types( array( 'public' => true ) );

		echo '<ul>';

		foreach ( $post_types as $post_type ) {

			?>

			<li>
				<label>
					<input type="checkbox" name="push_syndicate_settings[selected_post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php $this->checked_array( $post_type, $this->push_syndicate_settings['selected_post_types'] ); ?> />
					<?php echo esc_html( $post_type ); ?>
				</label>
			</li>

			<?php
		}

		echo '</ul>';
	}

	public function display_delete_pushed_posts_description() {
		echo esc_html__( 'Tick the box to delete all the pushed posts when the master post is deleted', 'push-syndication' );
	}

	public function display_delete_pushed_posts_selection() {
		// @TODO refractor this.
		echo '<input type="checkbox" id="delete_pushed_posts" name="push_syndicate_settings[delete_pushed_posts]" value="on" ';
		echo checked( $this->push_syndicate_settings['delete_pushed_posts'], 'on' ) . ' />';
	}

	public function display_apitoken_description() {
		// @TODO add client type information.
		echo '<p>' . esc_html__( 'To syndicate content to WordPress.com you must ', 'push-syndication' ) . '<a href="https://developer.wordpress.com/apps/new/">' . esc_html__( 'create a new application', 'push-syndication' ) . '</a></p>';
		echo '<p>' . esc_html__( 'Enter the Redirect URI as follows', 'push-syndication' ) . '</p>';
		echo '<p><b>' . esc_html( menu_page_url( 'push-syndicate-settings', false ) ) . '</p></b>';
	}

	public function display_client_id() {
		echo '<input type="text" size="100" id="client_id" name="push_syndicate_settings[client_id]" value="' . esc_attr( $this->push_syndicate_settings['client_id'] ) . '"/>';
	}

	public function display_client_secret() {
		$placeholder = empty( $this->push_syndicate_settings['client_secret'] ) ? '' : __( 'Leave blank to keep the saved value', 'push-syndication' );
		echo '<input type="password" size="100" id="client_secret" autocomplete="off" name="push_syndicate_settings[client_secret]" value="" placeholder="' . esc_attr( $placeholder ) . '"/>';
	}

	public function get_api_token() {

		$redirect_uri           = menu_page_url( 'push-syndicate-settings', false );
		$authorization_endpoint = add_query_arg(
			array(
				'client_id'     => rawurlencode( $this->push_syndicate_settings['client_id'] ),
				'redirect_uri'  => rawurlencode( $redirect_uri ),
				'response_type' => 'code',
			),
			'https://public-api.wordpress.com/oauth2/authorize'
		);

		echo '<h3>' . esc_html__( 'Authorization', 'push-syndication' ) . '</h3>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth redirect from WordPress.com, which cannot carry a nonce.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		// if code is not found return or settings updated return.
		if ( '' === $code || ! empty( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
			echo '<p>' . esc_html__( 'Click the authorize button to generate api token', 'push-syndication' ) . '</p>';

			?>

			<a class="button button-primary" href="<?php echo esc_url( $authorization_endpoint ); ?>"><?php esc_html_e( 'Authorize', 'push-syndication' ); ?></a>

			<?php

			return;
		}

		$response = wp_remote_post(
			'https://public-api.wordpress.com/oauth2/token',
			array(
				'body' => array(
					'client_id'     => $this->push_syndicate_settings['client_id'],
					'redirect_uri'  => $redirect_uri,
					'client_secret' => $this->get_client_secret(),
					'code'          => $code,
					'grant_type'    => 'authorization_code',
				),
			) 
		);

		$result = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $result->access_token, $result->blog_id, $result->blog_url ) ) {
			if ( is_wp_error( $response ) ) {
				$reason = $response->get_error_message();
			} elseif ( ! empty( $result->error_description ) ) {
				$reason = $result->error_description;
			} elseif ( ! empty( $result->error ) ) {
				$reason = $result->error;
			} else {
				/* translators: %d: HTTP status code. */
				$reason = sprintf( __( 'Unexpected response from WordPress.com (HTTP status %d).', 'push-syndication' ), wp_remote_retrieve_response_code( $response ) );
			}

			/* translators: %s: Reason the token request failed. */
			echo '<p>' . esc_html( sprintf( __( 'Error retrieving API token: %s', 'push-syndication' ), $reason ) ) . '</p>';
			echo '<p>' . esc_html__( 'Please authorize again.', 'push-syndication' ) . '</p>';

			?>

			<a class="button button-primary" href="<?php echo esc_url( $authorization_endpoint ); ?>"><?php esc_html_e( 'Authorize', 'push-syndication' ); ?></a>

			<?php

			return;
		}

		?>

		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row">Access token</th>
				<td><?php echo esc_html( $result->access_token ); ?></td>
			</tr>
			<tr valign="top">
				<th scope="row">Blog ID</th>
				<td><?php echo esc_html( $result->blog_id ); ?></td>
			</tr>
			<tr valign="top">
				<th scope="row">Blog URL</th>
				<td><?php echo esc_html( $result->blog_url ); ?></td>
			</tr>
			</tbody>
		</table>

		<?php

		echo '<p>' . esc_html__( 'Enter the above details in relevant fields when registering a ', 'push-syndication' ) . '<a href="http://wordpress.com" target="_blank">WordPress.com</a>' . esc_html__( 'site', 'push-syndication' ) . '</p>';
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
		add_meta_box( 'sitediv', __( 'Site Settings', 'push-syndication' ), array( $this, 'add_site_settings_metabox' ), 'syn_site', 'normal', 'high' );
		remove_meta_box( 'submitdiv', 'syn_site', 'side' );
		add_meta_box( 'submitdiv', __( 'Site Status', 'push-syndication' ), array( $this, 'add_site_status_metabox' ), 'syn_site', 'side', 'high' );
	}

	public function add_site_status_metabox( $site ) {
		$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div id="misc-publishing-actions">
					<div class="misc-pub-section">
						<label for="post_status"><?php esc_html_e( 'Status:', 'push-syndication' ); ?></label>
						<span id="post-status-display">
						<?php
						switch ( $site_enabled ) {
							case 'on':
								esc_html_e( 'Enabled', 'push-syndication' );
								break;
							case 'off':
							default:
								esc_html_e( 'Disabled', 'push-syndication' );
								break;
						}
						?>
						</span>

						<a href="#post_status" class="edit-post-status hide-if-no-js" tabindex='4'><?php esc_html_e( 'Edit', 'push-syndication' ); ?></a>

						<div id="post-status-select" class="hide-if-js">
							<input type="hidden" name="post_status" value="publish" />
							<select name='site_enabled' id='post_status' tabindex='4'>
								<option<?php selected( $site_enabled, 'on' ); ?> value='on'><?php esc_html_e( 'Enabled', 'push-syndication' ); ?></option>
								<option<?php selected( $site_enabled, 'off' ); ?> value='off'><?php esc_html_e( 'Disabled', 'push-syndication' ); ?></option>
							</select>
							<a href="#post_status" class="save-post-status hide-if-no-js button"><?php esc_html_e( 'OK', 'push-syndication' ); ?></a>
						</div>

					</div>

					<div id="timestampdiv" class="hide-if-js"><?php touch_time( 0, 1, 4 ); ?></div>
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

	public function add_site_settings_metabox( $post ) {

		global $post;

		$transport_type = get_post_meta( $post->ID, 'syn_transport_type', true );
		$site_enabled   = get_post_meta( $post->ID, 'syn_site_enabled', true );

		// default values.
		$transport_type = ! empty( $transport_type ) ? $transport_type : 'WP_XMLRPC';
		$transport_mode = ! empty( $transport_mode ) ? $transport_mode : 'pull';
		$site_enabled   = ! empty( $site_enabled ) ? $site_enabled : 'off';

		// nonce for verification when saving. Distinct from the syndicate metabox nonce so that
		// a nonce for one save path is not valid for the other.
		wp_nonce_field( 'syn_save_site_settings', 'site_settings_noncename' );

		$this->display_transports( $transport_type, $transport_mode );

		try {
			Syndication_Client_Factory::display_client_settings( $post, $transport_type );
		} catch ( Exception $e ) {
			echo esc_html( $e->getMessage() );
		}

		?>

		<div class="clear"></div>

		<?php
	}

	public function display_transports( $transport_type, $mode ) {

		echo '<p><label for="transport_type">' . esc_html__( 'Select a transport type', 'push-syndication' ) . '</label></p>';
		// TODO: add direction.
		echo '<select name="transport_type" id="transport_type" onchange="this.form.submit()">';

		$values  = array();
		$max_len = 0;
		foreach ( $this->push_syndicate_transports as $key => $value ) {
			$mode = array_shift( $value['modes'] );
			// translators: 1: Client name, 2: Client mode.
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $transport_type, false ) . '>' . sprintf( esc_html__( '%1$s (%2$s)', 'push-syndication' ), esc_html( $value['name'] ), esc_html( $mode ) ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Persist the site settings metabox on a syn_site save.
	 *
	 * @param int $post_id ID of the post being saved.
	 * @return void
	 */
	public function save_site_settings( $post_id ) {

		// autosave verification.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it return.
		if ( ! isset( $_POST['site_settings_noncename'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['site_settings_noncename'] ) ), 'syn_save_site_settings' ) ) {
			return;
		}

		// Site settings only ever belong to a site, and only to a user who can syndicate.
		if ( 'syn_site' !== get_post_type( $post_id ) || ! $this->current_user_can_syndicate() ) {
			return;
		}

		$transport_type = isset( $_POST['transport_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transport_type'] ) ) : '';

		// Only registered transports may be persisted; the value ends up in a class name.
		if ( ! isset( $this->push_syndicate_transports[ $transport_type ] ) ) {
			return;
		}

		update_post_meta( $post_id, 'syn_transport_type', $transport_type );

		$site_enabled = isset( $_POST['site_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['site_enabled'] ) ) : 'off';

		try {
			$save = Syndication_Client_Factory::save_client_settings( $post_id, $transport_type );
			if ( ! $save ) {
				return;
			}
			$client = Syndication_Client_Factory::get_client( $transport_type, $post_id );

			if ( $client->test_connection() ) {
				add_filter(
					'redirect_post_location',
					function ( $location ) {
						return add_query_arg( 'message', 251, $location );
					}
				);
			} else {
				// Run before the default priority, so a more specific message the client added in test_connection() wins.
				add_filter(
					'redirect_post_location',
					function ( $location ) {
						return add_query_arg( 'message', 252, $location );
					},
					9
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

		update_post_meta( $post_id, 'syn_site_enabled', $site_enabled );
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
			add_meta_box( 'syndicatediv', __( 'Syndicate', 'push-syndication' ), array( $this, 'add_syndicate_metabox' ), $selected_post_type, 'side', 'high' );
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

	/**
	 * Persist the syndicate metabox when a post changes status.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post being saved.
	 * @return void
	 */
	public function save_syndicate_settings( $new_status, $old_status, $post ) {

		// autosave verification.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// if our nonce isn't there, or we can't verify it return.
		if ( ! isset( $_POST['syndicate_noncename'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['syndicate_noncename'] ) ), plugin_basename( __FILE__ ) ) ) {
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

	/**
	 * Schedule a push when a post with selected sitegroups changes.
	 *
	 * Driven by the saved selection rather than the Syndicate box, so posts
	 * published by cron, WP-CLI or other code are pushed too. There is no nonce
	 * or capability check: choosing the sitegroups was the authorized step.
	 * save_syndicate_settings() runs first on the same hook, so a selection
	 * saved in the editor is pushed in the same request, once.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post whose status changed.
	 * @return void
	 */
	public function pre_schedule_push_content( $new_status, $old_status, $post ) {

		// autosave verification.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Trashing and restoring are left to the "Delete pushed posts" setting.
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || 'trash' === $new_status || 'trash' === $old_status ) {
			return;
		}

		// Nothing to push to or remove from. Returning here also keeps meta off unrelated posts.
		if ( empty( get_post_meta( $post->ID, '_syn_selected_sitegroups', true ) ) && empty( get_post_meta( $post->ID, '_syn_old_sitegroups', true ) ) ) {
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

		// If another process holds the lock, try again shortly rather than dropping this push.
		if ( 'locked' === get_transient( 'syn_syndicate_lock' ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'syn_push_content', array( $sites ) );
			return;
		}

		// set value as locked, valid for 5 mins.
		set_transient( 'syn_syndicate_lock', 'locked', 60 * 5 );

		// Release the lock whatever happens, or every push waits for it to expire.
		try {
			$this->push_content_to_sites( $sites );
		} finally {
			delete_transient( 'syn_syndicate_lock' );
		}
	}

	/**
	 * Push a post to its selected sites and remove it from its removed sites.
	 *
	 * Callers must hold the push lock.
	 *
	 * @param array $sites Array of sites data to syndicate content to.
	 * @return void
	 */
	private function push_content_to_sites( $sites ) {

		/** Start of critical section. */

		$post_ID = $sites['post_ID'];

		// an array containing states of sites.
		$slave_post_states = get_post_meta( $post_ID, '_syn_slave_post_states', true );
		$slave_post_states = ! empty( $slave_post_states ) ? $slave_post_states : array();

		$sites = apply_filters( 'syn_pre_push_post_sites', $sites, $post_ID, $slave_post_states );

		if ( ! empty( $sites['selected_sites'] ) ) {
			foreach ( $sites['selected_sites'] as $site ) {
				// get_site_info() drops the site's entry; a site that is skipped gets it back.
				$states_before_site = $slave_post_states;

				$transport_type = get_post_meta( $site->ID, 'syn_transport_type', true );
				$client         = Syndication_Client_Factory::get_client( $transport_type, $site->ID );
				$info           = $this->get_site_info( $site->ID, $slave_post_states, $client );

				// Check if post already exists on target to prevent syndication loops.
				// Only before the first push: after that the target holds our own copy, which carries the same post_uniqueid.
				if ( in_array( $info['state'], array( 'new', 'new-error' ), true ) && in_array( $transport_type, array( 'WP_REST', 'WP_XMLRPC' ), true ) ) {
					$unique_id = get_post_meta( $post_ID, 'post_uniqueid', true );

					if ( ! empty( $unique_id ) && $client->is_source_site_post( 'post_uniqueid', $unique_id, get_post_type( $post_ID ) ) ) {
						$slave_post_states = $states_before_site;
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
						$slave_post_states = $states_before_site;
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
				// get_site_info() drops the site's entry; a skipped removal puts it back.
				$states_before_site = $slave_post_states;

				$transport_type = get_post_meta( $site->ID, 'syn_transport_type', true );
				$client         = Syndication_Client_Factory::get_client( $transport_type, $site->ID );
				$info           = $this->get_site_info( $site->ID, $slave_post_states, $client );

				// if the post is not pushed we do not need to delete them.
				if ( 'success' === $info['state'] || 'edit-error' === $info['state'] || 'remove-error' === $info['state'] ) {
					// The same hooks as deleting the remote copies of a trashed post, so removals are logged.
					$push_delete_shortcircuit = apply_filters( 'syn_pre_push_delete_post_shortcircuit', false, $info['ext_ID'], $post_ID, $site->ID, $transport_type, $client ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook.
					if ( true === $push_delete_shortcircuit ) {
						$slave_post_states[ $info['state'] ][ $site->ID ] = $states_before_site[ $info['state'] ][ $site->ID ];
						continue;
					}

					$result = $client->delete_post( $info['ext_ID'] );

					do_action( 'syn_post_push_delete_post', $result, $info['ext_ID'], $post_ID, $site->ID, $transport_type, $client ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook.

					if ( is_wp_error( $result ) ) {
						$slave_post_states['remove-error'][ $site->ID ] = $result;
						$this->update_slave_post_states( $post_ID, $slave_post_states );
					}
				}
			}
		}


		/** End of critical section. */
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

		// A site still targeted by a ticked sitegroup, directly or through a parent, keeps its copy.
		$listed_site_ids = wp_list_pluck( $data['selected_sites'], 'ID' );

		if ( ! empty( $removed_sitegroups ) ) {
			foreach ( $removed_sitegroups as $removed_sitegroup ) {

				// get all the sites in the sitegroup.
				$sites = $this->get_sites_by_sitegroup( $removed_sitegroup );
				if ( empty( $sites ) ) {
					continue;
				}

				foreach ( $sites as $site ) {
					$site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
					if ( 'on' === $site_enabled && ! in_array( $site->ID, $listed_site_ids, true ) ) {
						$data['removed_sites'][] = $site;
						$listed_site_ids[]       = $site->ID;
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

		$sites = array();
		$paged = 1;

		// Fetch in batches so a large sitegroup never needs one unbounded query.
		do {
			$results = new WP_Query(
				array(
					'post_type'      => 'syn_site',
					'posts_per_page' => 100,
					'paged'          => $paged,
					'no_found_rows'  => true,
					// The ID tie-break keeps the order stable across pages.
					'orderby'        => array(
						'date' => 'DESC',
						'ID'   => 'DESC',
					),
					'tax_query'      => array(
						array(
							'taxonomy' => 'syn_sitegroup',
							'field'    => 'slug',
							'terms'    => $sitegroup,
						),
					),
				)
			);

			$sites      = array_merge( $sites, $results->posts );
			$batch_size = count( $results->posts );
			++$paged;
		} while ( 100 === $batch_size );

		return $sites;
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
	 * @param int|bool|WP_Error            $result            The remote post ID, or true or false if no remote post was created.
	 * @param array                        $slave_post_states Array of slave post states, passed by reference.
	 * @param int                          $site_ID           The site ID.
	 * @param Syndication_Client_Interface $client            The syndication client.
	 *
	 * @return int|bool|WP_Error The result.
	 */
	public function validate_result_new_post( $result, &$slave_post_states, $site_ID, $client ) {

		if ( is_wp_error( $result ) ) {
			$slave_post_states['new-error'][ $site_ID ] = $result;
		} elseif ( is_numeric( $result ) && (int) $result > 0 ) {
			$slave_post_states['success'][ $site_ID ] = array(
				'ext_ID' => (int) $result,
			);
		}
		// Anything else created no remote post: true when a push filter skipped it, false from a pull-only client.

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

	/**
	 * Schedule deletion of a post's remote copies, if the settings ask for it.
	 *
	 * Runs when a post is trashed, and when it is permanently deleted without
	 * being trashed first. No capability check: whoever may delete the post
	 * may delete its copies, including cron and WP-CLI.
	 *
	 * @param int $post_id ID of the post being trashed or deleted.
	 * @return void
	 */
	public function pre_schedule_delete_content( $post_id ) {

		// if slave post deletion is not enabled return.
		$delete_pushed_posts = ! empty( $this->push_syndicate_settings['delete_pushed_posts'] ) ? $this->push_syndicate_settings['delete_pushed_posts'] : 'off';
		if ( 'on' !== $delete_pushed_posts ) {
			return;
		}

		// A post deleted from the trash had its remote copies handled when it was trashed.
		if ( 'trash' === get_post_status( $post_id ) ) {
			return;
		}

		do_action( 'syn_schedule_delete_content', $post_id );
	}

	/**
	 * Schedule a cron job to delete a post's remote copies.
	 *
	 * The remote IDs are captured now, as the post meta recording them may be
	 * gone by the time the job runs.
	 *
	 * @param int $post_ID ID of the post whose remote copies should be deleted.
	 * @return void
	 */
	public function schedule_delete_content( $post_ID ) {
		$slave_posts = $this->get_slave_posts( $post_ID );

		if ( empty( $slave_posts ) ) {
			return;
		}

		wp_schedule_single_event(
			time() - 1,
			'syn_delete_content',
			array( $post_ID, $slave_posts )
		);
	}

	/**
	 * Delete a post's remote copies.
	 *
	 * @param int        $post_ID     ID of the local post.
	 * @param array|null $slave_posts Remote copies as $site_ID => $ext_ID. Read from the
	 *                                post's meta when null, as for jobs scheduled before 2.2.3.
	 * @return void
	 */
	public function delete_content( $post_ID, $slave_posts = null ) {

		$delete_error_sites = get_option( 'syn_delete_error_sites' );
		$delete_error_sites = ! empty( $delete_error_sites ) ? $delete_error_sites : array();
		$slave_posts        = null === $slave_posts ? $this->get_slave_posts( $post_ID ) : $slave_posts;

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

					if ( ! $result || is_wp_error( $result ) ) {
						$delete_error_sites[ $site_ID ] = array( $ext_ID );
					}
				}
			}
		}

		update_option( 'syn_delete_error_sites', $delete_error_sites );
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
	 * Post types that pulled content may be written as.
	 *
	 * Types with an admin UI, except core's internal ones (templates, global styles,
	 * patterns, navigation menus and attachments) and syn_site, which holds the
	 * plugin's own endpoints.
	 *
	 * @return string[] Post type names.
	 */
	public static function get_pull_post_types() {
		$post_types = array_merge(
			array( 'post', 'page' ),
			get_post_types(
				array(
					'show_ui'  => true,
					'_builtin' => false,
				)
			)
		);

		/**
		 * Filters the post types that pulled content may be written as.
		 *
		 * @param string[] $post_types Post type names.
		 */
		return (array) apply_filters( 'syndication_pull_post_types', array_values( array_diff( $post_types, array( 'syn_site' ) ) ) );
	}

	/**
	 * Whether a pulled post may be written with its post type, logging an error if not.
	 *
	 * @param array $post    Post data for wp_insert_post() or wp_update_post().
	 * @param int   $site_id The site post pulled from.
	 * @return bool
	 */
	private function is_pull_post_type_allowed( $post, $site_id ) {
		if ( ! empty( $post['post_type'] ) ) {
			$post_type = $post['post_type'];
		} else {
			// Mirror the type core will use: the existing post's on update, otherwise 'post'.
			$post_type = empty( $post['ID'] ) ? 'post' : get_post_type( $post['ID'] );
		}

		if ( in_array( $post_type, self::get_pull_post_types(), true ) ) {
			return true;
		}

		Syndication_Logger::log_post_error(
			$site_id,
			'invalid_post_type',
			// translators: %s: Post type name.
			sprintf( __( 'skipping post with disallowed post type %s', 'push-syndication' ), $post_type ),
			null,
			array( 'post' => $post )
		);

		return false;
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
			'interval' => max( self::MIN_PULL_TIME_INTERVAL, absint( $this->push_syndicate_settings['pull_time_interval'] ) ),
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

		// Clear every pull job, whatever its arguments. Jobs scheduled before 2.2.3 carried
		// whole site posts, which stop matching as soon as a site changes.
		wp_unschedule_hook( 'syn_pull_content' );

		// Schedule new jobs: one job for each site, by ID.
		foreach ( $sites as $site ) {
			wp_schedule_event(
				time() - 1,
				'syn_pull_time_interval',
				'syn_pull_content',
				array( array( $site->ID ) )
			);
		}
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

		// Temporarily suspend comment and term counting. Cache invalidation stays
		// on: terms a pull creates must be visible to the rest of the same pull,
		// or a lookup answered from a stale cache creates the term again.
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		// Keep track of posts that are added or changed.
		$updated_post_ids = array();

		foreach ( $sites as $site ) {
			// Pull jobs pass site IDs; jobs scheduled before 2.2.3, and direct callers, pass site posts.
			$site = is_object( $site ) ? $site : get_post( (int) $site );
			if ( ! $site || 'syn_site' !== $site->post_type ) {
				continue;
			}

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
				// translators: 1: Site post ID, 2: Number of posts in the feed.
				Syndication_Logger::log_post_info( $site_id, $status = 'start_import', $message = sprintf( __( 'starting import for site id %1$d with %2$d posts', 'push-syndication' ), $site_id, count( $posts ) ), $log_time = null, $extra = array() );
			} else {
				// translators: %d: Site post ID.
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

						if ( ! $this->is_pull_post_type_allowed( $post, $site_id ) ) {
							continue;
						}

						$result = wp_update_post( $post, true );

						do_action( 'syn_post_pull_edit_post', $result, $post, $site, $transport_type, $client );

						if ( ! is_wp_error( $result ) ) {
							$updated_post_ids[] = (int) $result;
						}
					} else {
						$pull_new_shortcircuit = apply_filters( 'syn_pre_pull_new_post_shortcircuit', false, $post, $site, $transport_type, $client );
						if ( true === $pull_new_shortcircuit ) {
							Syndication_Logger::log_post_info( $site_id, $status = 'syn_pre_pull_new_post_shortcircuit', $message = sprintf( __( 'skipping post per syn_pre_pull_edit_post_shortcircuit', 'push-syndication' ) ), $log_time = null, $extra = array( 'post' => $post ) );
							continue;
						}
						$post = apply_filters( 'syn_pull_new_post', $post, $site, $client );

						// Pulled data must not pick the local post: an ID would overwrite an existing one.
						unset( $post['ID'], $post['import_id'] );

						if ( ! $this->is_pull_post_type_allowed( $post, $site_id ) ) {
							continue;
						}

						$result = wp_insert_post( $post, true );

						do_action( 'syn_post_pull_new_post', $result, $post, $site, $transport_type, $client );

						if ( ! is_wp_error( $result ) ) {
							update_post_meta( $result, 'syn_post_guid', $post['post_guid'] );
							update_post_meta( $result, 'syn_source_site_id', $site_id );
							$updated_post_ids[] = (int) $result;
						}
					}
				}

				foreach ( $post_types_processed as $post_type ) {
					add_post_type_support( $post_type, 'revisions' );
				}
			}

			update_post_meta( $site_id, 'syn_last_pull_time', time() );
		}

		// Resume comment and term counting.
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

	/**
	 * Run the upgrade routine if the stored version is older than the plugin.
	 *
	 * Runs on every request, not just in wp-admin, so cron, WP-CLI and REST
	 * requests never run new code against settings that haven't been migrated.
	 *
	 * @return void
	 * @throws Throwable Anything the upgrade throws, after releasing the lock.
	 */
	public function maybe_upgrade() {
		if ( version_compare( $this->version, SYNDICATION_VERSION, '>=' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Only one request upgrades. After a successful upgrade the lock is left to expire, so a
		// request that read the old version before the upgrade finished can't run it again.
		if ( ! WP_Upgrader::create_lock( 'syn_upgrade', 5 * MINUTE_IN_SECONDS ) ) {
			return;
		}

		try {
			$this->upgrade();
		} catch ( Throwable $e ) {
			// Let the next request try again.
			WP_Upgrader::release_lock( 'syn_upgrade' );
			throw $e;
		}
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

		// Upgrade to 2.2.3: XML pulls now apply the site's status settings instead of
		// publishing every new post with the default comment and ping status. Save
		// those as the settings, so existing XML sites keep pulling the way they did.
		if ( version_compare( $this->version, '2.2.3', '<' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off upgrade, covering sites in any status.
			$xml_site_ids = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'syn_transport_type' AND meta_value = 'WP_XML'" );

			foreach ( $xml_site_ids as $site_id ) {
				$post_type = get_post_meta( $site_id, 'syn_default_post_type', true );
				$post_type = empty( $post_type ) ? 'post' : $post_type;

				update_post_meta( $site_id, 'syn_default_post_status', 'publish' );
				update_post_meta( $site_id, 'syn_default_comment_status', get_default_comment_status( $post_type ) );
				update_post_meta( $site_id, 'syn_default_ping_status', get_default_comment_status( $post_type, 'pingback' ) );
			}
		}

		// Upgrade to 2.2.3. Trashing a pushed post used to delete its remote copies whatever
		// the "Delete pushed posts" setting said, so turn the setting on for existing installs
		// to keep that behavior visible and switchable. New installs keep the default.
		if ( ! empty( $this->version ) && version_compare( $this->version, '2.2.3', '<' ) ) {
			$this->push_syndicate_settings['delete_pushed_posts'] = 'on';

			// Skip the settings validation, which would encrypt the stored client secret again.
			$validating = remove_filter( 'sanitize_option_push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
			update_option( 'push_syndicate_settings', $this->push_syndicate_settings );
			if ( $validating ) {
				add_filter( 'sanitize_option_push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );
			}
		}

		// Upgrade to 2.2.3: pull jobs are keyed by site ID. Replace the old jobs, and drop
		// the site posts that were stored only to unschedule them.
		if ( version_compare( $this->version, '2.2.3', '<' ) ) {
			delete_option( 'syn_old_pull_sites' );
			$this->schedule_deferred_pull_jobs_refresh();
		}

		update_option( 'syn_version', SYNDICATION_VERSION );
	}
}
