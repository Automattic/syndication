<?php
/**
 * Plugin Bootstrap
 */

namespace Automattic\Syndication;

/**
 * Include all the files required for the plugin.
 */

// Functions
require __DIR__ . '/functions-helpers.php';
require __DIR__ . '/functions-template-tags.php';

// Types
require __DIR__ . '/types/class-post.php';
require __DIR__ . '/types/class-term.php';

// Custom Taxonomies
require __DIR__ . '/custom-taxonomies/class-sitegroup-taxonomy.php';

// Custom post types
require __DIR__ . '/custom-post-types/class-site-post-type.php';

// Classes
require __DIR__ . '/class-client-manager.php';
require __DIR__ . '/class-cron.php';
require __DIR__ . '/class-legacy-hooks.php';
require __DIR__ . '/class-puller.php';
require __DIR__ . '/class-pusher.php';
require __DIR__ . '/class-site-manager.php';
require __DIR__ . '/class-syndication-logger.php';
require __DIR__ . '/class-syndication-logger-viewer.php';
require __DIR__ . '/class-syndication-runner.php';
require __DIR__ . '/class-syndication-settings.php';
require __DIR__ . '/class-syndication-site-auto-retry.php';
require __DIR__ . '/class-syndication-site-failure-monitor.php';
require __DIR__ . '/class-upgrade-tasks.php';
require __DIR__ . '/class-syndication-event-counter.php';

// Clients
// @todo combine client files into single file

// XML Pull
require __DIR__ . '/clients/xml-pull/class-bootstrap.php';
require __DIR__ . '/clients/xml-pull/class-client-options.php';
require __DIR__ . '/clients/xml-pull/class-pull-client.php';
require __DIR__ . '/clients/xml-pull/class-site-options.php';
require __DIR__ . '/clients/xml-pull/class-walker-category-dropdown-multiple.php';

// XML Push
require __DIR__ . '/clients/xml-push/class-bootstrap.php';
require __DIR__ . '/clients/xml-push/class-client-options.php';
require __DIR__ . '/clients/xml-push/class-push-client.php';
require __DIR__ . '/clients/xml-push/class-site-options.php';
require __DIR__ . '/clients/xml-push/class-walker-category-dropdown-multiple.php';

// RSS Pull
require __DIR__ . '/clients/rss-pull/class-bootstrap.php';
require __DIR__ . '/clients/rss-pull/class-client-options.php';
require __DIR__ . '/clients/rss-pull/class-pull-client.php';
require __DIR__ . '/clients/rss-pull/class-site-options.php';
require __DIR__ . '/clients/rss-pull/class-walker-category-dropdown-multiple.php';

// Admin
require __DIR__ . '/admin/class-post-edit-screen.php';
require __DIR__ . '/admin/class-settings-screen.php';
require __DIR__ . '/admin/class-site-edit-screen.php';
require __DIR__ . '/admin/class-site-list-screen.php';



class Bootstrap {

	/**
	 * Fire up the Syndication plugin
	 *
	 * Note: Class Autoloading is in use
	 */
	public function __construct() {

		// Load our helper functions which autoload can't..load
		require_once( SYNDICATION_PATH . 'includes-new/functions-helpers.php');

		// Always load.
		new Custom_Post_Types\Site_Post_Type();
		new Custom_Taxonomies\Sitegroup_Taxonomy();
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
		new Clients\XML_Pull\Bootstrap();
		new Clients\RSS_Pull\Bootstrap();
		new Clients\XML_Push\Bootstrap();

		// Command line stuff.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once( SYNDICATION_PATH . 'includes-new/class-syndication-cli-command.php' );
			\WP_CLI::add_command( 'syndication', 'Syndication_CLI_Command' );
		}

		// Hooks.
		add_action( 'init', [ $this, 'init' ] );
	}


	public function init() {
		do_action( 'syndication/init' );
	}
}
