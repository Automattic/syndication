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
		new Clients\Test\Bootstrap();
		new Clients\XML_Pull\Bootstrap();

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
