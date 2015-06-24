<?php
/**
 * Plugin Bootstrap
 */

namespace Automattic\Syndication;

class Bootstrap {

	public function __construct() {

		// Always load.
		new Custom_Post_Types\Site_Post_Type();
		new Custom_Taxonomies\Sitegroup_Taxonomy();
		new Cron();

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

		// Bootstrap individual built-in clients.
		new Clients\Test\Bootstrap();
		new Clients\XML_Pull\Bootstrap();

		// Command line stuff.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'syndication', 'Syndication_CLI_Command' );
		}

		// Hooks.
		add_action( 'init', [ $this, 'init' ] );
	}


	public function init() {
		do_action( 'syndication/init' );
	}
}
