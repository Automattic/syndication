<?php
/**
 * Push log viewer for syndication events.
 *
 * @package Automattic\Syndication\Infrastructure\Logging
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Logging;

/**
 * Displays push syndication logs in the WordPress admin.
 */
final class PushLogViewer {

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
	 * Add the Push Logs submenu page.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'edit.php?post_type=syn_site',
			__( 'Push Logs', 'push-syndication' ),
			__( 'Push Logs', 'push-syndication' ),
			'manage_options',
			'syndication-push-logs',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Push Logs page.
	 */
	public function render_page(): void {
		// Get filter parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$timestamp = isset( $_GET['time'] ) ? absint( $_GET['time'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$status    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter params.
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : null;

		// Get logs.
		$logs = $this->log->get_push_logs(
			$post_id ?: null,
			$timestamp ?: null,
			$status ?: null,
			$search ?: null
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Push Logs', 'push-syndication' ); ?></h1>

			<form method="get" action="">
				<input type="hidden" name="post_type" value="syn_site">
				<input type="hidden" name="page" value="syndication-push-logs">

				<div class="tablenav top">
					<div class="alignleft actions">
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
						</select>

						<?php submit_button( __( 'Filter', 'push-syndication' ), 'secondary', 'filter_action', false ); ?>

						<?php if ( $post_id || $status || $timestamp || $search ) : ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=syn_site&page=syndication-push-logs' ) ); ?>" class="button">
								<?php esc_html_e( 'Clear filters', 'push-syndication' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<p class="search-box">
						<label class="screen-reader-text" for="log-search-input">
							<?php esc_html_e( 'Search logs', 'push-syndication' ); ?>
						</label>
						<input type="search" id="log-search-input" name="s" value="<?php echo esc_attr( (string) $search ); ?>" placeholder="<?php esc_attr_e( 'Search posts or sites...', 'push-syndication' ); ?>">
						<?php submit_button( __( 'Search', 'push-syndication' ), 'secondary', '', false ); ?>
					</p>
				</div>
			</form>

			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No push logs found.', 'push-syndication' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-post" style="width: 25%;">
								<?php esc_html_e( 'Post', 'push-syndication' ); ?>
							</th>
							<th scope="col" class="manage-column column-time" style="width: 15%;">
								<?php esc_html_e( 'Time', 'push-syndication' ); ?>
							</th>
							<th scope="col" class="manage-column column-status" style="width: 10%;">
								<?php esc_html_e( 'Status', 'push-syndication' ); ?>
							</th>
							<th scope="col" class="manage-column column-result">
								<?php esc_html_e( 'Sites', 'push-syndication' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $entry ) : ?>
							<tr>
								<td class="column-post">
									<?php
									$post_edit_url = admin_url( 'post.php?post=' . $entry['post_id'] . '&action=edit' );
									?>
									<a href="<?php echo esc_url( $post_edit_url ); ?>">
										<?php echo esc_html( $entry['post_title'] ); ?>
									</a>
									<br>
									<span class="description">#<?php echo esc_html( (string) $entry['post_id'] ); ?></span>
								</td>
								<td class="column-time">
									<?php
									$time_filter_url = add_query_arg(
										array(
											'post_type' => 'syn_site',
											'page'      => 'syndication-push-logs',
											'time'      => $entry['timestamp'],
											'post'      => $entry['post_id'],
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
									<?php echo wp_kses_post( $this->render_push_result( $entry ) ); ?>
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
		);

		return $badges[ $status ] ?? esc_html( $status );
	}

	/**
	 * Render the result column for a push log entry.
	 *
	 * @param array<string, mixed> $entry The log entry.
	 * @return string HTML for the result.
	 */
	private function render_push_result( array $entry ): string {
		$sites = $entry['sites'] ?? array();

		if ( empty( $sites ) ) {
			return esc_html__( 'No sites.', 'push-syndication' );
		}

		// Group sites by action.
		$grouped = array(
			'created' => array(),
			'updated' => array(),
			'deleted' => array(),
			'failed'  => array(),
		);

		foreach ( $sites as $site ) {
			$action = $site['action'] ?? 'unknown';
			if ( isset( $grouped[ $action ] ) ) {
				$grouped[ $action ][] = $site;
			}
		}

		$parts = array();

		// Created on sites.
		if ( ! empty( $grouped['created'] ) ) {
			$links = array();
			foreach ( $grouped['created'] as $site ) {
				$links[] = $this->render_site_link( $site );
			}
			$parts[] = '<strong>' . esc_html__( 'Created on:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		// Updated on sites.
		if ( ! empty( $grouped['updated'] ) ) {
			$links = array();
			foreach ( $grouped['updated'] as $site ) {
				$links[] = $this->render_site_link( $site );
			}
			$parts[] = '<strong>' . esc_html__( 'Updated on:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		// Deleted from sites.
		if ( ! empty( $grouped['deleted'] ) ) {
			$links = array();
			foreach ( $grouped['deleted'] as $site ) {
				$links[] = $this->render_site_link( $site, false ); // No remote link for deleted.
			}
			$parts[] = '<strong>' . esc_html__( 'Deleted from:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		// Failed sites.
		if ( ! empty( $grouped['failed'] ) ) {
			$links = array();
			foreach ( $grouped['failed'] as $site ) {
				$error   = $site['error'] ?? __( 'Unknown error', 'push-syndication' );
				$links[] = '<span style="color: #d63638;">' .
					esc_html( $site['site_name'] ) .
					' (' . esc_html( $error ) . ')</span>';
			}
			$parts[] = '<strong>' . esc_html__( 'Failed:', 'push-syndication' ) . '</strong> ' . implode( ', ', $links );
		}

		return implode( '<br>', $parts );
	}

	/**
	 * Render a site link with remote post ID.
	 *
	 * @param array<string, mixed> $site               The site data.
	 * @param bool                 $include_remote_link Whether to include a link to the remote post.
	 * @return string HTML for the link.
	 */
	private function render_site_link( array $site, bool $include_remote_link = true ): string {
		$site_id   = $site['site_id'] ?? 0;
		$site_name = $site['site_name'] ?? __( 'Unknown site', 'push-syndication' );
		$remote_id = $site['remote_id'] ?? 0;

		// Site edit link.
		$site_edit_url = admin_url( 'post.php?post=' . $site_id . '&action=edit' );
		$output        = '<a href="' . esc_url( $site_edit_url ) . '">' . esc_html( $site_name ) . '</a>';

		// Remote post link (to front-end, not admin - user may not have access).
		if ( $include_remote_link && $remote_id > 0 ) {
			$site_url = get_post_meta( $site_id, 'syn_site_url', true );
			if ( $site_url ) {
				$remote_url = trailingslashit( $site_url ) . '?p=' . $remote_id;
				$output    .= ' <span style="color: #787c82;">&#8594;</span> ';
				$output    .= '<a href="' . esc_url( $remote_url ) . '" target="_blank" rel="noopener" title="' . esc_attr__( 'View on remote site', 'push-syndication' ) . '">';
				$output    .= '#' . $remote_id . ' <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span></a>';
			} else {
				$output .= ' <span style="color: #787c82;">&#8594; #' . $remote_id . '</span>';
			}
		}

		return $output;
	}
}
