<?php
/**
 * CLI output handler trait.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use Automattic\Syndication\Application\DTO\PullResult;
use Automattic\Syndication\Application\DTO\PushResult;
use WP_CLI;

/**
 * Trait for shared CLI output handling functionality.
 *
 * Provides verbose output hooks and memory cleanup utilities
 * for WP-CLI commands.
 */
trait CLIOutputHandler {

	/**
	 * Whether verbosity has been enabled.
	 *
	 * @var bool
	 */
	private bool $verbosity_enabled = false;

	/**
	 * Enable verbose output for push operations.
	 *
	 * Hooks into syndication filters and actions to output progress information to the CLI.
	 */
	protected function enable_push_verbosity(): void {
		if ( $this->verbosity_enabled ) {
			return;
		}

		$this->verbosity_enabled = true;

		add_filter(
			'syn_pre_push_post_sites',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature requires all parameters.
			static function ( $sites, $post_id, $slave_states ) {
				WP_CLI::log( sprintf( 'Processing post_id #%d (%s)', $post_id, get_the_title( $post_id ) ) );
				WP_CLI::log(
					sprintf(
						'-- pushing to %s sites and deleting from %s sites',
						number_format( count( $sites['selected_sites'] ?? array() ) ),
						number_format( count( $sites['removed_sites'] ?? array() ) )
					)
				);

				return $sites;
			},
			10,
			3
		);

		add_action(
			'syn_post_push_new_post',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature requires all parameters.
			static function ( $result, $post_id, $site, $transport_type, $client, $info ): void {
				WP_CLI::log( sprintf( '-- Added remote post #%d (%s)', $post_id, $site->post_title ?? 'Unknown' ) );
			},
			10,
			6
		);

		add_action(
			'syn_post_push_edit_post',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature requires all parameters.
			static function ( $result, $post_id, $site, $transport_type, $client, $info ): void {
				WP_CLI::log( sprintf( '-- Updated remote post #%d (%s)', $post_id, $site->post_title ?? 'Unknown' ) );
			},
			10,
			6
		);
	}

	/**
	 * Enable verbose output for pull operations.
	 *
	 * Hooks into syndication filters and actions to output progress information to the CLI.
	 */
	protected function enable_pull_verbosity(): void {
		if ( $this->verbosity_enabled ) {
			return;
		}

		$this->verbosity_enabled = true;

		add_filter(
			'syn_pre_pull_posts',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature requires all parameters.
			static function ( $posts, $site, $client ) {
				WP_CLI::log( sprintf( 'Processing feed %s (%d)', $site->post_title ?? 'Unknown', $site->ID ?? 0 ) );
				WP_CLI::log( sprintf( '-- found %s posts', count( $posts ) ) );

				return $posts;
			},
			10,
			3
		);

		add_action(
			'syn_post_pull_new_post',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature requires all parameters.
			static function ( $result, $post, $site, $transport_type, $client ): void {
				WP_CLI::log( sprintf( '-- New post #%d (%s)', $result, $post['post_guid'] ?? 'Unknown' ) );
			},
			10,
			5
		);

		add_action(
			'syn_post_pull_edit_post',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature requires all parameters.
			static function ( $result, $post, $site, $transport_type, $client ): void {
				WP_CLI::log( sprintf( '-- Updated post #%d (%s)', $result, $post['post_guid'] ?? 'Unknown' ) );
			},
			10,
			5
		);
	}

	/**
	 * Clear object caches to reduce memory usage during long-running operations.
	 *
	 * Resets WP query cache and object cache to prevent memory exhaustion
	 * when processing large numbers of posts.
	 */
	protected function stop_the_insanity(): void {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array();

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress core object property.
		if ( property_exists( $wp_object_cache, 'group_ops' ) ) {
			$wp_object_cache->group_ops = array();
		}

		if ( property_exists( $wp_object_cache, 'stats' ) ) {
			$wp_object_cache->stats = array();
		}

		if ( property_exists( $wp_object_cache, 'memcache_debug' ) ) {
			$wp_object_cache->memcache_debug = array();
		}

		if ( property_exists( $wp_object_cache, 'cache' ) ) {
			$wp_object_cache->cache = array();
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( is_callable( array( $wp_object_cache, '__remoteset' ) ) ) {
			$wp_object_cache->__remoteset();
		}
	}

	/**
	 * Output a PushResult to the CLI.
	 *
	 * @param PushResult $result The push result.
	 */
	protected function output_push_result( PushResult $result ): void {
		$site = get_post( $result->site_id );
		$name = $site->post_title ?? "Site #{$result->site_id}";

		if ( $result->is_success() ) {
			WP_CLI::success( sprintf( '%s: %s (remote ID: %d)', $name, $result->action, $result->remote_id ) );
		} elseif ( $result->is_skipped() ) {
			WP_CLI::warning( sprintf( '%s: Skipped - %s', $name, $result->message ) );
		} else {
			WP_CLI::warning( sprintf( '%s: Failed - %s (%s)', $name, $result->message, $result->error_code ) );
		}
	}

	/**
	 * Output a PullResult to the CLI.
	 *
	 * @param PullResult $result The pull result.
	 */
	protected function output_pull_result( PullResult $result ): void {
		$site = get_post( $result->site_id );
		$name = $site->post_title ?? "Site #{$result->site_id}";

		if ( $result->is_success() ) {
			WP_CLI::success(
				sprintf(
					'%s: Created %d, updated %d posts',
					$name,
					$result->created,
					$result->updated
				)
			);
		} elseif ( $result->is_skipped() ) {
			WP_CLI::warning( sprintf( '%s: Skipped - %s', $name, $result->message ) );
		} elseif ( $result->is_partial() ) {
			WP_CLI::warning(
				sprintf(
					'%s: Partial - Created %d, updated %d, skipped %d, errors: %d',
					$name,
					$result->created,
					$result->updated,
					$result->skipped,
					count( $result->errors )
				)
			);
			foreach ( $result->errors as $error ) {
				WP_CLI::warning( "  - {$error}" );
			}
		} else {
			WP_CLI::warning( sprintf( '%s: Failed - %s (%s)', $name, $result->message, $result->error_code ) );
		}
	}
}
