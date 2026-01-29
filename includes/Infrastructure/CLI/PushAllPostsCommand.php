<?php
/**
 * WP-CLI command for pushing all posts of a given type.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use Automattic\Syndication\Application\Contracts\PushServiceInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use WP_CLI;
use WP_Query;

/**
 * Pushes all posts of a given type to syndicated sites.
 *
 * ## EXAMPLES
 *
 *     # Push all posts
 *     $ wp syndication push-all-posts
 *
 *     # Push all pages starting from page 3
 *     $ wp syndication push-all-posts --post_type=page --paged=3
 *
 * @subcommand push-all-posts
 */
final class PushAllPostsCommand {

	use CLIOutputHandler;

	/**
	 * Posts per page for batch processing.
	 */
	private const POSTS_PER_PAGE = 150;

	/**
	 * Sleep duration between batches in seconds.
	 */
	private const BATCH_SLEEP = 2;

	/**
	 * Push service.
	 *
	 * @var PushServiceInterface
	 */
	private readonly PushServiceInterface $push_service;

	/**
	 * Site repository.
	 *
	 * @var SiteRepositoryInterface
	 */
	private readonly SiteRepositoryInterface $site_repository;

	/**
	 * Constructor.
	 *
	 * @param PushServiceInterface    $push_service    Push service.
	 * @param SiteRepositoryInterface $site_repository Site repository.
	 */
	public function __construct(
		PushServiceInterface $push_service,
		SiteRepositoryInterface $site_repository
	) {
		$this->push_service    = $push_service;
		$this->site_repository = $site_repository;
	}

	/**
	 * Push all posts of a given type to syndicated sites.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<type>]
	 * : The post type to push. Default: post.
	 *
	 * [--paged=<page>]
	 * : Starting page number. Default: 1.
	 *
	 * [--verbose]
	 * : Enable verbose output showing push progress.
	 *
	 * ## EXAMPLES
	 *
	 *     # Push all posts
	 *     $ wp syndication push-all-posts
	 *
	 *     # Push all pages starting from page 3
	 *     $ wp syndication push-all-posts --post_type=page --paged=3
	 *
	 *     # Push with verbose output
	 *     $ wp syndication push-all-posts --verbose
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'post_type' => 'post',
				'paged'     => 1,
				'verbose'   => false,
			)
		);

		$post_type = sanitize_key( $assoc_args['post_type'] );
		$paged     = max( 1, (int) $assoc_args['paged'] );
		$verbose   = (bool) $assoc_args['verbose'];

		// Enable verbose output if requested.
		if ( $verbose ) {
			$this->enable_push_verbosity();
		}

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => self::POSTS_PER_PAGE,
			'paged'          => $paged,
			'post_status'    => 'publish',
		);

		$query          = new WP_Query( $query_args );
		$total_success  = 0;
		$total_failures = 0;
		$total_posts    = 0;

		while ( $query->post_count > 0 ) {
			WP_CLI::log( sprintf( 'Processing page %d (%d posts)...', $paged, $query->post_count ) );

			foreach ( $query->posts as $post ) {
				++$total_posts;

				if ( $verbose ) {
					WP_CLI::log( sprintf( 'Processing post %d (%s)', $post->ID, $post->post_title ) );
				}

				$result = $this->push_single_post( $post->ID, $verbose );

				$total_success  += $result['success'];
				$total_failures += $result['failures'];
			}

			// Clear memory between batches.
			$this->stop_the_insanity();

			// Sleep between batches to avoid overwhelming remote servers.
			sleep( self::BATCH_SLEEP );

			// Next page.
			++$paged;
			$query_args['paged'] = $paged;
			$query               = new WP_Query( $query_args );
		}

		// Final summary.
		WP_CLI::log( '' );
		WP_CLI::log( '=== Summary ===' );
		WP_CLI::log( sprintf( 'Total posts processed: %d', $total_posts ) );
		WP_CLI::log( sprintf( 'Successful pushes: %d', $total_success ) );
		WP_CLI::log( sprintf( 'Failed pushes: %d', $total_failures ) );

		if ( $total_failures > 0 ) {
			WP_CLI::warning( 'Completed with some failures.' );
		} else {
			WP_CLI::success( 'All posts pushed successfully.' );
		}
	}

	/**
	 * Push a single post and return counts.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $verbose Whether to output verbose information.
	 * @return array{success: int, failures: int}
	 */
	private function push_single_post( int $post_id, bool $verbose ): array {
		$site_ids = $this->get_sites_for_post( $post_id );

		if ( empty( $site_ids ) ) {
			if ( $verbose ) {
				WP_CLI::log( '  -- No sites configured for this post' );
			}
			return array(
				'success'  => 0,
				'failures' => 0,
			);
		}

		$results  = $this->push_service->push_to_sites( $post_id, $site_ids );
		$success  = 0;
		$failures = 0;

		foreach ( $results as $result ) {
			if ( $verbose ) {
				$this->output_push_result( $result );
			}

			if ( $result->is_success() ) {
				++$success;
			} else {
				++$failures;
			}
		}

		return array(
			'success'  => $success,
			'failures' => $failures,
		);
	}

	/**
	 * Get site IDs for a post based on its selected sitegroups.
	 *
	 * @param int $post_id The post ID.
	 * @return array<int> Array of site IDs.
	 */
	private function get_sites_for_post( int $post_id ): array {
		$selected_sitegroups = get_post_meta( $post_id, '_syn_selected_sitegroups', true );
		$selected_sitegroups = ! empty( $selected_sitegroups ) && is_array( $selected_sitegroups )
			? $selected_sitegroups
			: array();

		if ( empty( $selected_sitegroups ) ) {
			return array();
		}

		$site_ids = array();

		foreach ( $selected_sitegroups as $sitegroup_id ) {
			$sites = $this->site_repository->get_by_group( (int) $sitegroup_id );

			foreach ( $sites as $site ) {
				if ( $site->is_enabled() ) {
					$site_ids[] = $site->get_site_id();
				}
			}
		}

		return array_unique( $site_ids );
	}
}
