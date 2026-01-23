<?php
/**
 * Pull log viewer for syndication events.
 *
 * @package Automattic\Syndication\Infrastructure\Logging
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Logging;

/**
 * Displays pull syndication logs in the WordPress admin.
 */
final class PullLogViewer {

	/**
	 * The log instance.
	 *
	 * @var SyndicationLog
	 */
	private SyndicationLog $log;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log = SyndicationLog::instance();
	}

	/**
	 * Register the admin menu.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Add the Pull Logs submenu page.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'edit.php?post_type=syn_site',
			__( 'Pull Logs', 'push-syndication' ),
			__( 'Pull Logs', 'push-syndication' ),
			'manage_options',
			'syndication-pull-logs',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Pull Logs page.
	 */
	public function render_page(): void {
		// Get filter parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$site_id   = isset( $_GET['site'] ) ? absint( $_GET['site'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$timestamp = isset( $_GET['time'] ) ? absint( $_GET['time'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$status    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : null;

		// Get logs.
		$logs = $this->log->get_pull_logs(
			$site_id ?: null,
			$timestamp ?: null,
			$status ?: null,
			$search ?: null
		);

		// Get sites for filter dropdown.
		$sites = $this->log->get_sites_with_logs();

		// Get all sites for the dropdown (including those without logs yet).
		$all_sites = get_posts(
			array(
				'post_type'      => 'syn_site',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pull Logs', 'push-syndication' ); ?></h1>

			<form method="get" action="">
				<input type="hidden" name="post_type" value="syn_site">
				<input type="hidden" name="page" value="syndication-pull-logs">

				<div class="tablenav top">
					<div class="alignleft actions">
						<label for="filter-by-site" class="screen-reader-text">
							<?php esc_html_e( 'Filter by site', 'push-syndication' ); ?>
						</label>
						<select name="site" id="filter-by-site">
							<option value=""><?php esc_html_e( 'All sites', 'push-syndication' ); ?></option>
							<?php foreach ( $all_sites as $site ) : ?>
								<option value="<?php echo esc_attr( (string) $site->ID ); ?>" <?php selected( $site_id, $site->ID ); ?>>
									<?php echo esc_html( $site->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<label for="filter-by-status" class="screen-reader-text">
							<?php esc_html_e( 'Filter by status', 'push-syndication' ); ?>
						</label>
						<select name="status" id="filter-by-status">
							<option value=""><?php esc_html_e( 'All statuses', 'push-syndication' ); ?></option>
							<option value="success" <?php selected( $status, 'success' ); ?>>
								<?php esc_html_e( 'Success', 'push-syndication' ); ?>
							</option>
							<option value="partial" <?php selected( $status, 'partial' ); ?>>
								<?php esc_html_e( 'Partial', 'push-syndication' ); ?>
							</option>
							<option value="error" <?php selected( $status, 'error' ); ?>>
								<?php esc_html_e( 'Error', 'push-syndication' ); ?>
							</option>
							<option value="skipped" <?php selected( $status, 'skipped' ); ?>>
								<?php esc_html_e( 'Skipped', 'push-syndication' ); ?>
							</option>
						</select>

						<?php submit_button( __( 'Filter', 'push-syndication' ), 'secondary', 'filter_action', false ); ?>

						<?php if ( $site_id || $status || $timestamp || $search ) : ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=syn_site&page=syndication-pull-logs' ) ); ?>" class="button">
								<?php esc_html_e( 'Clear filters', 'push-syndication' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<p class="search-box">
						<label class="screen-reader-text" for="log-search-input">
							<?php esc_html_e( 'Search logs', 'push-syndication' ); ?>
						</label>
						<input type="search" id="log-search-input" name="s" value="<?php echo esc_attr( (string) $search ); ?>" placeholder="<?php esc_attr_e( 'Search post titles...', 'push-syndication' ); ?>">
						<?php submit_button( __( 'Search', 'push-syndication' ), 'secondary', '', false ); ?>
					</p>
				</div>
			</form>

			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No pull logs found.', 'push-syndication' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-site" style="width: 20%;">
								<?php esc_html_e( 'Site', 'push-syndication' ); ?>
							</th>
							<th scope="col" class="manage-column column-time" style="width: 15%;">
								<?php esc_html_e( 'Time', 'push-syndication' ); ?>
							</th>
							<th scope="col" class="manage-column column-status" style="width: 10%;">
								<?php esc_html_e( 'Status', 'push-syndication' ); ?>
							</th>
							<th scope="col" class="manage-column column-result">
								<?php esc_html_e( 'Result', 'push-syndication' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $entry ) : ?>
							<tr>
								<td class="column-site">
									<?php
									$site_edit_url = admin_url( 'post.php?post=' . $entry['site_id'] . '&action=edit' );
									?>
									<a href="<?php echo esc_url( $site_edit_url ); ?>">
										<?php echo esc_html( $entry['site_name'] ); ?>
									</a>
								</td>
								<td class="column-time">
									<?php
									$time_filter_url = add_query_arg(
										array(
											'post_type' => 'syn_site',
											'page'      => 'syndication-pull-logs',
											'time'      => $entry['timestamp'],
											'site'      => $entry['site_id'],
										),
										admin_url( 'edit.php' )
									);
									?>
									<a href="<?php echo esc_url( $time_filter_url ); ?>" title="<?php esc_attr_e( 'Filter to this entry', 'push-syndication' ); ?>">
										<?php echo esc_html( $this->format_time( $entry['time'] ) ); ?>
									</a>
								</td>
								<td class="column-status">
									<?php echo wp_kses_post( $this->render_status_badge( $entry['status'] ) ); ?>
								</td>
								<td class="column-result">
									<?php echo wp_kses_post( $this->render_pull_result( $entry ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Format a timestamp for display.
	 *
	 * @param string $time MySQL datetime string.
	 * @return string Formatted time.
	 */
	private function format_time( string $time ): string {
		$timestamp = strtotime( $time );
		if ( false === $timestamp ) {
			return $time;
		}

		return wp_date( 'j M Y, H:i:s', $timestamp );
	}

	/**
	 * Render a status badge.
	 *
	 * @param string $status The status.
	 * @return string HTML for the badge.
	 */
	private function render_status_badge( string $status ): string {
		$badges = array(
			'success' => '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;" title="' . esc_attr__( 'Success', 'push-syndication' ) . '"></span> ' . esc_html__( 'Success', 'push-syndication' ),
			'partial' => '<span class="dashicons dashicons-warning" style="color: #dba617;" title="' . esc_attr__( 'Partial', 'push-syndication' ) . '"></span> ' . esc_html__( 'Partial', 'push-syndication' ),
			'error'   => '<span class="dashicons dashicons-dismiss" style="color: #d63638;" title="' . esc_attr__( 'Error', 'push-syndication' ) . '"></span> ' . esc_html__( 'Error', 'push-syndication' ),
			'skipped' => '<span class="dashicons dashicons-minus" style="color: #787c82;" title="' . esc_attr__( 'Skipped', 'push-syndication' ) . '"></span> ' . esc_html__( 'Skipped', 'push-syndication' ),
		);

		return $badges[ $status ] ?? esc_html( $status );
	}

	/**
	 * Render the result column for a pull log entry.
	 *
	 * @param array<string, mixed> $entry The log entry.
	 * @return string HTML for the result.
	 */
	private function render_pull_result( array $entry ): string {
		// If there's an overall error, show it.
		if ( ! empty( $entry['error'] ) ) {
			return '<span style="color: #d63638;">' . esc_html( $entry['error'] ) . '</span>';
		}

		$posts   = $entry['posts'] ?? array();
		$summary = $entry['summary'] ?? array();

		// If no posts, show summary.
		if ( empty( $posts ) ) {
			if ( 'skipped' === $entry['status'] ) {
				return esc_html__( 'No posts to process.', 'push-syndication' );
			}
			return esc_html__( 'No changes.', 'push-syndication' );
		}

		// Group posts by action.
		$grouped = array(
			'created' => array(),
			'updated' => array(),
			'skipped' => array(),
			'failed'  => array(),
		);

		foreach ( $posts as $post ) {
			$action = $post['action'] ?? 'unknown';
			if ( isset( $grouped[ $action ] ) ) {
				$grouped[ $action ][] = $post;
			}
		}

		$parts = array();

		// Created posts.
		if ( ! empty( $grouped['created'] ) ) {
			$links = array();
			foreach ( $grouped['created'] as $post ) {
				$links[] = $this->render_post_link( $post, $entry );
			}
			$parts[] = '<strong>' . esc_html__( 'Created:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		// Updated posts.
		if ( ! empty( $grouped['updated'] ) ) {
			$links = array();
			foreach ( $grouped['updated'] as $post ) {
				$links[] = $this->render_post_link( $post, $entry );
			}
			$parts[] = '<strong>' . esc_html__( 'Updated:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		// Skipped posts (show count only to reduce noise).
		if ( ! empty( $grouped['skipped'] ) ) {
			$count   = count( $grouped['skipped'] );
			$parts[] = '<strong>' . esc_html__( 'Skipped:', 'push-syndication' ) . '</strong> ' .
				sprintf(
					/* translators: %d: number of posts skipped */
					esc_html( _n( '%d post (no changes)', '%d posts (no changes)', $count, 'push-syndication' ) ),
					$count
				);
		}

		// Failed posts.
		if ( ! empty( $grouped['failed'] ) ) {
			$links = array();
			foreach ( $grouped['failed'] as $post ) {
				$error  = $post['error'] ?? __( 'Unknown error', 'push-syndication' );
				$links[] = '<span style="color: #d63638;">' .
					esc_html( $post['title'] ?? '#' . $post['remote_id'] ) .
					' (' . esc_html( $error ) . ')</span>';
			}
			$parts[] = '<strong>' . esc_html__( 'Failed:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		return implode( '<br>', $parts );
	}

	/**
	 * Render a post link with local and remote IDs.
	 *
	 * @param array<string, mixed> $post  The post data.
	 * @param array<string, mixed> $entry The log entry.
	 * @return string HTML for the link.
	 */
	private function render_post_link( array $post, array $entry ): string {
		$local_id  = $post['local_id'] ?? 0;
		$remote_id = $post['remote_id'] ?? 0;
		$title     = $post['title'] ?? '';

		// Build local link (to edit screen).
		$local_link = '';
		if ( $local_id > 0 ) {
			$local_url  = admin_url( 'post.php?post=' . $local_id . '&action=edit' );
			$local_link = '<a href="' . esc_url( $local_url ) . '" title="' . esc_attr( $title ) . '">#' . $local_id . '</a>';
		}

		// Build remote link (to front-end, not admin - user may not have access).
		$remote_link = '';
		if ( $remote_id > 0 ) {
			$site_url = get_post_meta( $entry['site_id'], 'syn_site_url', true );
			if ( $site_url ) {
				$remote_url  = trailingslashit( $site_url ) . '?p=' . $remote_id;
				$remote_link = '<a href="' . esc_url( $remote_url ) . '" target="_blank" rel="noopener" title="' . esc_attr__( 'View on remote site', 'push-syndication' ) . '">#' . $remote_id . ' <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span></a>';
			} else {
				$remote_link = '#' . $remote_id;
			}
		}

		// Format: Local #12 ← Remote #103
		if ( $local_link && $remote_link ) {
			return $local_link . ' <span style="color: #787c82;">&#8592;</span> ' . $remote_link;
		} elseif ( $local_link ) {
			return $local_link;
		} elseif ( $remote_link ) {
			return $remote_link;
		}

		return esc_html( $title ?: __( 'Unknown post', 'push-syndication' ) );
	}
}
