<?php
/**
 * WP-CLI command for pulling content from a single site.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use Automattic\Syndication\Application\Contracts\PullServiceInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use WP_CLI;

/**
 * Pulls content from a single syndication site.
 *
 * ## EXAMPLES
 *
 *     # Pull from a site
 *     $ wp syndication pull-site --site_id=456
 *
 * @subcommand pull-site
 */
final class PullSiteCommand {

	use CLIOutputHandler;

	/**
	 * Pull service.
	 *
	 * @var PullServiceInterface
	 */
	private readonly PullServiceInterface $pull_service;

	/**
	 * Site repository.
	 *
	 * @var SiteRepositoryInterface
	 */
	private readonly SiteRepositoryInterface $site_repository;

	/**
	 * Constructor.
	 *
	 * @param PullServiceInterface    $pull_service    Pull service.
	 * @param SiteRepositoryInterface $site_repository Site repository.
	 */
	public function __construct(
		PullServiceInterface $pull_service,
		SiteRepositoryInterface $site_repository
	) {
		$this->pull_service    = $pull_service;
		$this->site_repository = $site_repository;
	}

	/**
	 * Pull content from a single syndication site.
	 *
	 * ## OPTIONS
	 *
	 * --site_id=<id>
	 * : The ID of the syndication site to pull from.
	 *
	 * [--verbose]
	 * : Enable verbose output showing pull progress.
	 *
	 * ## EXAMPLES
	 *
	 *     # Pull from a site
	 *     $ wp syndication pull-site --site_id=456
	 *
	 *     # Pull with verbose output
	 *     $ wp syndication pull-site --site_id=456 --verbose
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'site_id' => 0,
				'verbose' => false,
			)
		);

		$site_id = (int) $assoc_args['site_id'];
		$verbose = (bool) $assoc_args['verbose'];

		// Validate site exists.
		$site_config = $this->site_repository->get( $site_id );

		if ( null === $site_config ) {
			WP_CLI::error( __( 'Please select a valid site.', 'push-syndication' ) );
		}

		$site = get_post( $site_id );

		if ( ! $site || 'syn_site' !== $site->post_type ) {
			WP_CLI::error( __( 'Please select a valid site.', 'push-syndication' ) );
		}

		// Enable verbose output if requested.
		if ( $verbose ) {
			$this->enable_pull_verbosity();
		}

		WP_CLI::log( sprintf( 'Pulling content from %s...', $site->post_title ) );

		// Pull from the site.
		$result = $this->pull_service->pull_from_site( $site_id );

		// Output result.
		$this->output_pull_result( $result );

		// Exit with appropriate status.
		if ( $result->is_failure() ) {
			WP_CLI::error( 'Pull operation failed.' );
		} elseif ( $result->is_partial() ) {
			WP_CLI::warning( 'Pull completed with some errors.' );
		}
	}
}
