<?php
/**
 * WP-CLI command for listing syndication sites.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Lists all syndication sites.
 *
 * ## EXAMPLES
 *
 *     # List all sites
 *     $ wp syndication sites-list
 *
 * @subcommand sites-list
 */
final class ListSitesCommand {

	/**
	 * Site repository.
	 *
	 * @var SiteRepositoryInterface
	 */
	private readonly SiteRepositoryInterface $site_repository;

	/**
	 * Constructor.
	 *
	 * @param SiteRepositoryInterface $site_repository Site repository.
	 */
	public function __construct( SiteRepositoryInterface $site_repository ) {
		$this->site_repository = $site_repository;
	}

	/**
	 * List all syndication sites.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all sites as a table
	 *     $ wp syndication sites-list
	 *
	 *     # List all sites as JSON
	 *     $ wp syndication sites-list --format=json
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'format' => 'table',
			)
		);

		$format = sanitize_key( $assoc_args['format'] );

		// Query all syn_site posts.
		$sites = get_posts(
			array(
				'post_type'      => 'syn_site',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $sites ) ) {
			WP_CLI::warning( __( 'No syndication sites found.', 'push-syndication' ) );
			return;
		}

		// Format the data for output.
		$items = array();
		foreach ( $sites as $site ) {
			$enabled        = get_post_meta( $site->ID, 'syn_site_enabled', true );
			$transport_type = get_post_meta( $site->ID, 'syn_transport_type', true );
			$url            = get_post_meta( $site->ID, 'syn_site_url', true );

			$items[] = array(
				'ID'             => $site->ID,
				'post_title'     => $site->post_title,
				'post_name'      => $site->post_name,
				'enabled'        => 'on' === $enabled ? 'yes' : 'no',
				'transport_type' => ! empty( $transport_type ) ? $transport_type : 'N/A',
				'url'            => ! empty( $url ) ? $url : 'N/A',
			);
		}

		Utils\format_items(
			$format,
			$items,
			array( 'ID', 'post_title', 'post_name', 'enabled', 'transport_type', 'url' )
		);
	}
}
