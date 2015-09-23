<?php
/**
 * Plugin Bootstrap
 */

namespace Automattic\Syndication;

class Bootstrap {

	/**
	 * Fire up the Syndication plugin
	 *
	 * Note: Class Autoloading is in use
	 */
	public function __construct() {

		// Load our helper functions which autoload can't..load
		require_once( SYNDICATION_PATH . 'includes/functions-helpers.php');

		// Always load.
		new Cron();

		// Settings helper.
		global $settings_manager;
		$settings_manager = new Syndication_Settings();

		global $client_manager;
		$client_manager = new Client_Manager();

		global $site_manager;
		$site_manager = new Site_Manager();

		Syndication_Logger::init();
		new Syndication_Event_Counter();
		new Syndication_Site_Failure_Monitor();
		new Syndication_Fail_Auto_Retry();

		new Upgrade_Tasks();
		new Legacy_Hooks();

		// Bootstrap admin.
		new Admin\Settings_Screen();
		new Admin\Site_List_Screen();
		new Admin\Site_Edit_Screen( $client_manager );
		new Admin\Post_Edit_Screen();

		// Load the runner.
		new Syndication_Runner();

		// Bootstrap individual built-in clients.
		new Clients\Test\Bootstrap();
		new Clients\XML_Pull\Bootstrap();
		new Clients\RSS_Pull\Bootstrap();
		new Clients\XML_Push\Bootstrap();
		new Clients\REST_Push\Bootstrap();

		// Command line stuff.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once( SYNDICATION_PATH . 'includes/class-syndication-cli-command.php' );
			\WP_CLI::add_command( 'syndication', 'Syndication_CLI_Command' );
		}

		// Hooks.
		add_action( 'init', [ $this, 'init' ] );
	}


	/**
	 * Initialize the plugin!
	 */
	public function init() {
		$this->register_taxonomy();
		$this->register_post_type();

		/**
		 * Fires after the syndication server is initialized.
		 *
		 * Watched by syndication clients trigger initialization.
		 */
		do_action( 'syndication/init' );

		/**
		 * Fires after the syndication server is initialized.
		 *
		 * Legacy hook.
		 */
		do_action( 'syn_after_init_server' );

	}

	public function register_taxonomy() {
		$taxonomy_capabilities = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		register_taxonomy(
			'syn_sitegroup',
			'syn_site', array(
				'labels' => array(
					'name'              => __( 'Site Groups' ),
					'singular_name'     => __( 'Site Group' ),
					'search_items'      => __( 'Search Site Groups' ),
					'popular_items'     => __( 'Popular Site Groups' ),
					'all_items'         => __( 'All Site Groups' ),
					'parent_item'       => __( 'Parent Site Group' ),
					'parent_item_colon' => __( 'Parent Site Group' ),
					'edit_item'         => __( 'Edit Site Group' ),
					'update_item'       => __( 'Update Site Group' ),
					'add_new_item'      => __( 'Add New Site Group' ),
					'new_item_name'     => __( 'New Site Group Name' ),
				),
				'public'                => false,
				'show_ui'               => true,
				'show_tagcloud'         => false,
				'show_in_nav_menus'     => false,
				'hierarchical'          => true,
				'rewrite'               => false,
				'capabilities'          => $taxonomy_capabilities,
			)
		);
	}

	/**
	 * Set up the `syn_site` custom post type.
	 */
	public function register_post_type() {
		/* This filter is documented in includes/admin/class-settings-screen.php */
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		$post_type_capabilities = array(
			'edit_post'          => $capability,
			'read_post'          => $capability,
			'delete_posts'       => $capability,
			'delete_post'        => $capability,
			'edit_posts'         => $capability,
			'edit_others_posts'  => $capability,
			'publish_posts'      => $capability,
			'read_private_posts' => $capability,
		);

		register_post_type(
			'syn_site', array(
				'labels' => array(
					'name'              => __( 'Sites' ),
					'singular_name'     => __( 'Site' ),
					'add_new'           => __( 'Add Site' ),
					'add_new_item'      => __( 'Add New Site' ),
					'edit_item'         => __( 'Edit Site' ),
					'new_item'          => __( 'New Site' ),
					'view_item'         => __( 'View Site' ),
					'search_items'      => __( 'Search Sites' ),
				),
				'description'           => __( 'Sites in the network' ),
				'public'                => false,
				'show_ui'               => true,
				'publicly_queryable'    => false,
				'exclude_from_search'   => true,
				'menu_position'         => 100,
				// @TODO we need a menu icon here
				'hierarchical'          => false, // @TODO check this
				'query_var'             => false,
				'rewrite'               => false,
				'supports'              => array( 'title' ),
				'can_export'            => true,
				// 'register_meta_box_cb'  => array( $this, 'site_metaboxes' ),
				'capabilities'          => $post_type_capabilities,
			)
		);
	}
}
