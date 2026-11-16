<?php
/**
 * WP-CLI commands for content syndication.
 *
 * @package Syndication
 */

WP_CLI::add_command( 'syndication', 'Syndication_CLI_Command' );

/**
 * Pushes and pulls syndicated content.
 *
 * Pushes posts to the sites in their selected site groups, one at a time or
 * in bulk, and pulls content from a site or a whole site group.
 */
class Syndication_CLI_Command extends WP_CLI_Command {
	var $enabled_verbosity = false;

	/**
	 * Pushes all posts of a given type to the sites in their selected site groups.
	 *
	 * Works through the posts 150 at a time. Posts with no selected site groups
	 * are skipped with a warning, and the run carries on.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<post-type>]
	 * : Post type to push. Default: post.
	 *
	 * [--paged=<page>]
	 * : Page of 150 posts to start from, to resume an interrupted run. Default: 1.
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication push-all-posts --post_type=page
	 *
	 * @subcommand push-all-posts
	 * @synopsis [--post_type=<post-type>] [--paged=<page>]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments including post_type and paged.
	 */
	public function push_all_posts( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'post_type' => 'post',
				'paged'     => 1,
			) 
		);

		$query_args = array(
			'post_type'      => $assoc_args['post_type'],
			'posts_per_page' => 150,
			'paged'          => $assoc_args['paged'],
		);

		$query = new WP_Query( $query_args );

		while ( $query->post_count ) {
			WP_CLI::log( sprintf( 'Processing page %d', $query_args['paged'] ) );

			foreach ( $query->posts as $post ) {
				WP_CLI::log( sprintf( 'Processing post %d (%s)', $post->ID, $post->post_title ) );

				$this->push_post( array(), array( 'post_id' => $post->ID ) );
			}

			$this->stop_the_insanity();

			sleep( 2 );

			++$query_args['paged'];

			$query = new WP_Query( $query_args );
		}
	}

	/**
	 * Pushes a post to the sites in its selected site groups.
	 *
	 * A post with no selected site groups, or none with enabled sites, is skipped
	 * with a warning. A site that rejects the push is reported with a warning.
	 *
	 * ## OPTIONS
	 *
	 * --post_id=<post-id>
	 * : ID of the post to push.
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication push-post --post_id=123
	 *
	 * @alias push-post
	 * @synopsis --post_id=<post-id>
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments including post_id.
	 */
	public function push_post( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'post_id' => 0,
			) 
		);

		$post_id = intval( $assoc_args['post_id'] );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( __( 'Invalid post_id', 'push-syndication' ) );
		}

		$this->_make_em_talk_push();

		$server = $this->_get_syndication_server();
		$sites  = $server->get_sites_by_post_ID( $post_id );

		// The server always returns an array, so check for sites rather than emptiness.
		if ( empty( $sites['selected_sites'] ) && empty( $sites['removed_sites'] ) ) {
			/* translators: %d: post ID. */
			WP_CLI::warning( sprintf( __( 'Post %d has no selected site groups with enabled sites; skipping.', 'push-syndication' ), $post_id ) );
			return;
		}

		$server->push_content( $sites );
	}

	/**
	 * Pulls content from a site.
	 *
	 * ## OPTIONS
	 *
	 * --site_id=<site-id>
	 * : ID of the site (a syn_site post) to pull from.
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication pull-site --site_id=45
	 *
	 * @alias pull-site
	 * @synopsis --site_id=<site-id>
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments including site_id.
	 */
	public function pull_site( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'site_id' => 0,
			) 
		);

		$site_id = intval( $assoc_args['site_id'] );
		$site    = get_post( $site_id );

		if ( ! $site || 'syn_site' !== $site->post_type ) {
			WP_CLI::error( 'Please select a valid site.' );
		}

		if ( 'on' !== get_post_meta( $site_id, 'syn_site_enabled', true ) ) {
			/* translators: %d: site ID. */
			WP_CLI::warning( sprintf( __( 'Site %d is disabled; nothing to pull.', 'push-syndication' ), $site_id ) );
			return;
		}

		// Enable verbosity.
		$this->_make_em_talk_pull();

		$this->_get_syndication_server()->pull_content( array( $site ) );
	}

	/**
	 * Pulls content from every site in a site group.
	 *
	 * ## OPTIONS
	 *
	 * --sitegroup=<sitegroup>
	 * : Slug of the site group to pull from.
	 *
	 * ## EXAMPLES
	 *
	 *     wp syndication pull-sitegroup --sitegroup=news-partners
	 *
	 * @alias pull-sitegroup
	 * @synopsis --sitegroup=<sitegroup>
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments including sitegroup.
	 */
	public function pull_sitegroup( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'sitegroup' => '',
			)
		);

		$sitegroup = sanitize_key( $assoc_args['sitegroup'] );

		if ( empty( $sitegroup ) ) {
			WP_CLI::error( 'Please specify a valid site group' );
		}

		if ( ! get_term_by( 'slug', $sitegroup, 'syn_sitegroup' ) ) {
			/* translators: %s: sitegroup slug. */
			WP_CLI::error( sprintf( __( 'Site group "%s" does not exist.', 'push-syndication' ), $sitegroup ) );
		}

		$server = $this->_get_syndication_server();
		$sites  = $server->get_sites_by_sitegroup( $sitegroup );

		// pull_content() treats an empty list as "every scheduled site", so stop here.
		if ( empty( $sites ) ) {
			/* translators: %s: sitegroup slug. */
			WP_CLI::warning( sprintf( __( 'Site group "%s" has no sites; nothing to pull.', 'push-syndication' ), $sitegroup ) );
			return;
		}

		// Enable verbosity.
		$this->_make_em_talk_pull();

		// Do it, to it.
		$server->pull_content( $sites );
	}

	/**
	 * Enable verbose output for pull operations.
	 *
	 * Hooks into syndication filters and actions to output progress information to the CLI.
	 */
	private function _make_em_talk_pull() {
		if ( $this->enabled_verbosity ) {
			return;
		}

		$this->enabled_verbosity = true;

		// Output when a post is new or updated.
		add_filter(
			'syn_pre_pull_posts',
			function ( $posts, $site, $client ) {
				WP_CLI::log( sprintf( 'Processing feed %s (%d)', $site->post_title, $site->ID ) );
				WP_CLI::log( sprintf( '-- found %s posts', count( $posts ) ) );

				return $posts;
			},
			10,
			3 
		);

		add_action(
			'syn_post_pull_new_post',
			function ( $result, $post, $site, $transport_type, $client ) {
				if ( is_wp_error( $result ) ) {
					/* translators: 1: post GUID, 2: error message. */
					WP_CLI::warning( sprintf( __( '-- Failed to add post %1$s: %2$s', 'push-syndication' ), $post['post_guid'], $result->get_error_message() ) );
					return;
				}

				WP_CLI::log( sprintf( '-- New post #%d (%s)', $result, $post['post_guid'] ) );
			},
			10,
			5 
		);

		add_action(
			'syn_post_pull_edit_post',
			function ( $result, $post, $site, $transport_type, $client ) {
				if ( is_wp_error( $result ) ) {
					/* translators: 1: post GUID, 2: error message. */
					WP_CLI::warning( sprintf( __( '-- Failed to update post %1$s: %2$s', 'push-syndication' ), $post['post_guid'], $result->get_error_message() ) );
					return;
				}

				WP_CLI::log( sprintf( '-- Updated post #%d (%s)', $result, $post['post_guid'] ) );
			},
			10,
			5 
		);
	}

	/**
	 * Enable verbose output for push operations.
	 *
	 * Hooks into syndication filters and actions to output progress information to the CLI.
	 */
	private function _make_em_talk_push() {
		if ( $this->enabled_verbosity ) {
			return;
		}

		$this->enabled_verbosity = true;

		add_filter(
			'syn_pre_push_post_sites',
			function ( $sites, $post_id, $slave_states ) {
				WP_CLI::log( sprintf( 'Processing post_id #%d (%s)', $post_id, get_the_title( $post_id ) ) );
				WP_CLI::log( sprintf( '-- pushing to %s sites and deleting from %s sites', number_format( count( $sites['selected_sites'] ) ), number_format( count( $sites['removed_sites'] ) ) ) );

				return $sites;
			},
			10,
			3 
		);

		add_action(
			'syn_post_push_new_post',
			function ( $result, $post_ID, $site, $transport_type, $client, $info ) {
				if ( is_wp_error( $result ) ) {
					/* translators: 1: site title, 2: error message. */
					WP_CLI::warning( sprintf( __( '-- Failed to add remote post on %1$s: %2$s', 'push-syndication' ), $site->post_title, $result->get_error_message() ) );
					return;
				}

				WP_CLI::log( sprintf( '-- Added remote post #%d for post #%d (%s)', $result, $post_ID, $site->post_title ) );
			},
			10,
			6 
		);

		add_action(
			'syn_post_push_edit_post',
			function ( $result, $post_ID, $site, $transport_type, $client, $info ) {
				if ( is_wp_error( $result ) ) {
					/* translators: 1: site title, 2: error message. */
					WP_CLI::warning( sprintf( __( '-- Failed to update remote post on %1$s: %2$s', 'push-syndication' ), $site->post_title, $result->get_error_message() ) );
					return;
				}

				WP_CLI::log( sprintf( '-- Updated remote post #%d for post #%d (%s)', $info['ext_ID'], $post_ID, $site->post_title ) );
			},
			10,
			6 
		);
	}

	/**
	 * Get the global syndication server instance.
	 *
	 * @return WP_Push_Syndication_Server The syndication server instance.
	 */
	private function _get_syndication_server() {
		return $GLOBALS['push_syndication_server'];
	}

	/**
	 * Clear object caches to reduce memory usage during long-running operations.
	 *
	 * Resets WP query cache and object cache to prevent memory exhaustion
	 * when processing large numbers of posts.
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // Or define( 'WP_IMPORTING', true ).

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		$wp_object_cache->group_ops      = array();
		$wp_object_cache->stats          = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache          = array();

		if ( is_callable( array( $wp_object_cache, '__remoteset' ) ) ) {
			$wp_object_cache->__remoteset(); // Important.
		}
	}
}
