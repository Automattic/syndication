<?php

class Syndication_CLI_Command extends WP_CLI_Command {
	var $enabled_verbosity = false;
	
	/**
	 * List sites
	 *
	 * @subcommand sites-list
	 */
	function list_sites() {
		$site_manager = new \Automattic\Syndication\Site_Manager;
		$sites = $site_manager->get_site_index();
		$sites = $sites['all'];

		WP_CLI\Utils\format_items( 'table', $sites, array( 'ID', 'post_title', 'post_name' ) );
	}

	/**
	 * List sitegroups
	 *
	 * @subcommand sitegroups-list
	 */
	function list_sitegroups() {
		$site_manager = new \Automattic\Syndication\Site_Manager;
		$sites = $site_manager->get_site_index();
		$sitegroups = array_keys( $sites['by_site_group'] );
		$out = implode( PHP_EOL, $sitegroups );
		WP_CLI::line( $out );
	}

	/**
	 * Pushes all posts of a given type
	 *
	 * @subcommand push-all-posts
	 * @synopsis [--post_type=<post-type>] [--paged=<page>]
	 */
	function push_all_posts( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'post_type' 	=> 'post',
			'paged' 		=> 1
		) );

		$query_args = array(
			'post_type' 		=> $assoc_args[ 'post_type' ],
			'posts_per_page' 	=> 150,
			'paged'				=> $assoc_args[ 'paged' ]
		);

		$query = new WP_Query( $query_args );

		while( $query->post_count ) {
			WP_CLI::line( sprintf( 'Processing page %d', $query_args[ 'paged' ] ) );

			foreach( $query->posts as $post ) {
				WP_CLI::line( sprintf( 'Processing post %d (%s)', $post->ID, $post->post_title ) );

				$this->push_post( array(), array( 'post_id' => $post->ID ) );
			}

			$this->stop_the_insanity();

			sleep( 2 );

			$query_args[ 'paged' ]++;

			$query = new WP_Query( $query_args );
		}
	}

	/**
	 * Push a given post
	 *
	 * @subcommand push-post
	 * @synopsis --post_id=<post_id>
	 */
	function push_post( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'post_id' => 0,
		) );

		$post_id = intval( $assoc_args[ 'post_id' ] );
		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( __( 'Invalid post_id', 'push-syndication' ) );
		}

		$this->_make_em_talk_push();

		$server = $this->_get_syndication_server();
		$sites = $server->get_sites_by_post_ID( $post_id );

		if ( empty( $sites ) ) {
			WP_CLI::error( __( 'Post has no selected sitegroups / sites', 'push-syndication' ) );
		}

		$server->push_content( $sites );
	}

	/**
	 * Pull a site
	 *
	 * @subcommand pull-site
	 * @synopsis --blog_id=<blog_id>
	 */
	function pull_site( $args, $assoc_args ) {
		global $client_manager;

		$assoc_args = wp_parse_args( $assoc_args, array(
			'site_id' => 0,
		) );

		$site_id = intval( $assoc_args['site_id'] );
		$site = get_post( $site_id );

		if ( ! $site || 'syn_site' !== $site->post_type )
			WP_CLI::error( "Please select a valid site." );

		// enable verbosity
		$this->_make_em_talk_pull();

		// Fetch the site's client/transport type name
		$client_transport_type = get_post_meta( $site_id, 'syn_transport_type', true );

		// Fetch the site's client by name
		$client_details = $client_manager->get_pull_client( $client_transport_type );

		// Run the client's process_site method
		$client = new $client_details['class'];
		$client->init( $site_id );
		$client->process_site( $site_id, $client );
	}

	/**
	 * Pull a sitegroup
	 *
	 * @subcommand pull-sitegroup
	 * @synopsis --sitegroup=<sitegroup>
	 */
	function pull_sitegroup( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'sitegroup' => '',
		) );

		$sitegroup = sanitize_key( $assoc_args['sitegroup'] );

		if ( empty( $sitegroup ) )
			WP_CLI::error( "Please specify a valid sitegroup" );

		/*
		 * @todo Update for new restructured architecture
		$server = $this->_get_syndication_server();
		$sites = $server->get_sites_by_sitegroup( $sitegroup );
		*/

		// enable verbosity
		$this->_make_em_talk_pull();

		// do it, to it
		//$server->pull_content( $sites );
	}

	private function _make_em_talk_pull() {
		if ( $this->enabled_verbosity )
			return;

		$this->enabled_verbosity = true;

		// @todo make these filters/actions work again
		// output when a post is new or updated
		add_filter( 'syn_pre_pull_posts', function( $posts, $site, $client ) {
			WP_CLI::line( sprintf( 'Processing feed %s (%d)', $site->post_title, $site->ID ) );
			WP_CLI::line( sprintf( '-- found %s posts', count( $posts ) ) );

			return $posts;
		}, 10, 3 );

		add_action( 'syn_post_pull_new_post', function( $result, $post, $site, $transport_type, $client ) {
			WP_CLI::line( sprintf( '-- New post #%d (%s)', $result, $post['post_guid'] ) );
		}, 10, 5 );

		add_action( 'syn_post_pull_edit_post', function( $result, $post, $site, $transport_type, $client ) {
			WP_CLI::line( sprintf( '-- Updated post #%d (%s)', $result, $post['post_guid'] ) );
		}, 10, 5 );
	}

	private function _make_em_talk_push() {
		if ( $this->enabled_verbosity )
			return;

		$this->enabled_verbosity = true;
		
		add_filter( 'syn_pre_push_post_sites', function( $sites, $post_id, $slave_states ) {
			WP_CLI::line( sprintf( "Processing post_id #%d (%s)", $post_id, get_the_title( $post_id ) ) );
			WP_CLI::line( sprintf( "-- pushing to %s sites and deleting from %s sites", number_format( count( $sites['selected_sites'] ) ), number_format( count( $sites['removed_sites'] ) ) ) );

			return $sites;
		}, 10, 3 );

		add_action( 'syn_post_push_new_post', function( $result, $post_ID, $site, $transport_type, $client, $info ) {
			WP_CLI::line( sprintf( '-- Added remote post #%d (%s)', $post_ID, $site->post_title ) );
		}, 10, 6 );

		add_action( 'syn_post_push_edit_post', function( $result, $post_ID, $site, $transport_type, $client, $info ) {
			WP_CLI::line( sprintf( '-- Updated remote post #%d (%s)', $post_ID, $site->post_title ) );
		}, 10, 6 );

	}
	/*
	 * @todo remove, unneeded with new architecture
	private function _get_syndication_server() {
		global $push_syndication_server;
		return $push_syndication_server;
	}
	*/

	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

		if ( !is_object( $wp_object_cache ) )
			return;

		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();

		if ( is_callable( $wp_object_cache, '__remoteset' ) )
			$wp_object_cache->__remoteset(); // important
	}
}