<?php

WP_CLI::add_command( 'syndication', 'Syndication_CLI_Command' );

class Syndication_CLI_Command extends WP_CLI_Command {
	var $enabled_verbosity = false;

	function pull_site( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'site_id' => 0,
		) );

		$site_id = intval( $assoc_args['site_id'] );
		$site = get_post( $site_id );

		if ( ! $site || 'syn_site' !== $site->post_type )
			WP_CLI::error( "Please select a valid site." );

		// enable verbosity
		$this->_make_em_talk_pull();

		$this->_get_syndication_server()->pull_content( array( $site ) );
	}

	function pull_sitegroup( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'sitegroup' => '',
		) );

		$sitegroup = sanitize_key( $assoc_args['sitegroup'] );

		if ( empty( $sitegroup ) )
			WP_CLI::error( "Please specify a valid sitegroup" );

		$server = $this->_get_syndication_server();
		$sites = $server->get_sites_by_sitegroup( $sitegroup );

		// enable verbosity
		$this->_make_em_talk_pull();

		// do it, to it
		$server->pull_content( $sites );
	}

	private function _make_em_talk_pull() {
		if ( $this->enabled_verbosity )
			return;

		$this->enabled_verbosity = true;

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

	private function _get_syndication_server() {
		global $push_syndication_server;
		return $push_syndication_server;
	}
}
