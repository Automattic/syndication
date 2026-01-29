<?php
/**
 * WP-CLI command for listing sitegroups.
 *
 * @package Automattic\Syndication\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\CLI;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Lists all syndication sitegroups.
 *
 * ## EXAMPLES
 *
 *     # List all sitegroups
 *     $ wp syndication sitegroups-list
 *
 * @subcommand sitegroups-list
 */
final class ListSitegroupsCommand {

	/**
	 * Sitegroup taxonomy name.
	 */
	private const TAXONOMY = 'syn_sitegroup';

	/**
	 * List all syndication sitegroups.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, csv, json, yaml, ids. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all sitegroups as a table
	 *     $ wp syndication sitegroups-list
	 *
	 *     # List all sitegroups as JSON
	 *     $ wp syndication sitegroups-list --format=json
	 *
	 *     # List only sitegroup slugs (one per line)
	 *     $ wp syndication sitegroups-list --format=ids
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

		// Get all sitegroup terms.
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			WP_CLI::warning( __( 'No sitegroups found.', 'push-syndication' ) );
			return;
		}

		// Handle 'ids' format specially (output slugs one per line, like 2.1 branch).
		if ( 'ids' === $format ) {
			$slugs = array_map(
				fn( $term ) => $term->slug,
				$terms
			);
			WP_CLI::line( implode( PHP_EOL, $slugs ) );
			return;
		}

		// Format the data for output.
		$items = array();
		foreach ( $terms as $term ) {
			$items[] = array(
				'term_id' => $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'count'   => $term->count,
			);
		}

		Utils\format_items(
			$format,
			$items,
			array( 'term_id', 'name', 'slug', 'count' )
		);
	}
}
