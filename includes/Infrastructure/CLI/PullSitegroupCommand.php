<?php
/**
 * WP-CLI command for pulling content from all sites in a sitegroup.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use Automattic\Syndication\Application\Contracts\PullServiceInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use WP_CLI;

/**
 * Pulls content from all sites in a sitegroup.
 *
 * ## EXAMPLES
 *
 *     # Pull from all sites in a sitegroup
 *     $ wp syndication pull-sitegroup --sitegroup=news-feeds
 *
 * @subcommand pull-sitegroup
 */
final class PullSitegroupCommand {

	use CLIOutputHandler;

	/**
	 * Sitegroup taxonomy name.
	 */
	private const TAXONOMY = 'syn_sitegroup';

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
	 * Pull content from all sites in a sitegroup.
	 *
	 * ## OPTIONS
	 *
	 * --sitegroup=<slug>
	 * : The sitegroup slug to pull from.
	 *
	 * [--verbose]
	 * : Enable verbose output showing pull progress.
	 *
	 * ## EXAMPLES
	 *
	 *     # Pull from a sitegroup
	 *     $ wp syndication pull-sitegroup --sitegroup=news-feeds
	 *
	 *     # Pull with verbose output
	 *     $ wp syndication pull-sitegroup --sitegroup=news-feeds --verbose
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'sitegroup' => '',
				'verbose'   => false,
			)
		);

		$sitegroup_slug = sanitize_key( $assoc_args['sitegroup'] );
		$verbose        = (bool) $assoc_args['verbose'];

		if ( empty( $sitegroup_slug ) ) {
			WP_CLI::error( __( 'Please specify a valid sitegroup', 'push-syndication' ) );
		}

		// Look up the sitegroup term by slug.
		$term = get_term_by( 'slug', $sitegroup_slug, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			WP_CLI::error(
				sprintf(
					// translators: %s: sitegroup slug.
					__( 'Sitegroup "%s" not found.', 'push-syndication' ),
					$sitegroup_slug
				)
			);
		}

		// Get sites in this sitegroup.
		$sites = $this->site_repository->get_by_group( $term->term_id );

		if ( empty( $sites ) ) {
			WP_CLI::error(
				sprintf(
					// translators: %s: sitegroup slug.
					__( 'No sites found in sitegroup "%s".', 'push-syndication' ),
					$sitegroup_slug
				)
			);
		}

		// Enable verbose output if requested.
		if ( $verbose ) {
			$this->enable_pull_verbosity();
		}

		$site_count = count( $sites );
		WP_CLI::log(
			sprintf(
				'Pulling content from %d site(s) in sitegroup "%s"...',
				$site_count,
				$sitegroup_slug
			)
		);

		// Extract site IDs for enabled sites.
		$site_ids = array();
		foreach ( $sites as $site ) {
			if ( $site->is_enabled() ) {
				$site_ids[] = $site->get_site_id();
			}
		}

		if ( empty( $site_ids ) ) {
			WP_CLI::warning( __( 'No enabled sites found in sitegroup.', 'push-syndication' ) );
			return;
		}

		// Pull from all sites.
		$results = $this->pull_service->pull_from_sites( $site_ids );

		// Output results.
		$success_count = 0;
		$failure_count = 0;
		$total_created = 0;
		$total_updated = 0;

		foreach ( $results as $result ) {
			if ( $verbose ) {
				$this->output_pull_result( $result );
			}

			if ( $result->is_success() || $result->is_partial() ) {
				++$success_count;
				$total_created += $result->created;
				$total_updated += $result->updated;
			} elseif ( $result->is_failure() ) {
				++$failure_count;
			}
		}

		// Clear memory after processing.
		$this->stop_the_insanity();

		// Summary.
		WP_CLI::log( '' );
		WP_CLI::log( '=== Summary ===' );
		WP_CLI::log( sprintf( 'Sites processed: %d', count( $results ) ) );
		WP_CLI::log( sprintf( 'Posts created: %d', $total_created ) );
		WP_CLI::log( sprintf( 'Posts updated: %d', $total_updated ) );

		if ( $failure_count > 0 ) {
			WP_CLI::warning(
				sprintf(
					'Pull completed with %d success, %d failures.',
					$success_count,
					$failure_count
				)
			);
		} else {
			WP_CLI::success(
				sprintf( 'Successfully pulled from %d site(s).', $success_count )
			);
		}
	}
}
