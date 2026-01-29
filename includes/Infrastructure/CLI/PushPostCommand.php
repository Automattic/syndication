<?php
/**
 * WP-CLI command for pushing a single post.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use Automattic\Syndication\Application\Contracts\PushServiceInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use WP_CLI;

/**
 * Pushes a single post to syndicated sites.
 *
 * ## EXAMPLES
 *
 *     # Push a post to all its configured sites
 *     $ wp syndication push-post --post_id=123
 *
 * @subcommand push-post
 */
final class PushPostCommand {

	use CLIOutputHandler;

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
	 * Push a single post to syndicated sites.
	 *
	 * ## OPTIONS
	 *
	 * --post_id=<id>
	 * : The ID of the post to push.
	 *
	 * [--verbose]
	 * : Enable verbose output showing push progress.
	 *
	 * ## EXAMPLES
	 *
	 *     # Push a post
	 *     $ wp syndication push-post --post_id=123
	 *
	 *     # Push with verbose output
	 *     $ wp syndication push-post --post_id=123 --verbose
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'post_id' => 0,
				'verbose' => false,
			)
		);

		$post_id = (int) $assoc_args['post_id'];
		$verbose = (bool) $assoc_args['verbose'];

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( __( 'Invalid post_id', 'push-syndication' ) );
		}

		// Enable verbose output if requested.
		if ( $verbose ) {
			$this->enable_push_verbosity();
		}

		// Get sites for this post.
		$site_ids = $this->get_sites_for_post( $post_id );

		if ( empty( $site_ids ) ) {
			WP_CLI::error( __( 'Post has no selected sitegroups / sites', 'push-syndication' ) );
		}

		WP_CLI::log( sprintf( 'Pushing post #%d to %d site(s)...', $post_id, count( $site_ids ) ) );

		// Push to all sites.
		$results = $this->push_service->push_to_sites( $post_id, $site_ids );

		// Output results.
		$success_count = 0;
		$failure_count = 0;

		foreach ( $results as $result ) {
			if ( $verbose ) {
				$this->output_push_result( $result );
			}

			if ( $result->is_success() ) {
				++$success_count;
			} else {
				++$failure_count;
			}
		}

		// Summary.
		if ( $failure_count > 0 ) {
			WP_CLI::warning(
				sprintf(
					'Push completed with %d success, %d failures.',
					$success_count,
					$failure_count
				)
			);
		} else {
			WP_CLI::success(
				sprintf( 'Successfully pushed to %d site(s).', $success_count )
			);
		}
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
