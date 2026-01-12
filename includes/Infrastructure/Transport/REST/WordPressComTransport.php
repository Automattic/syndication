<?php
/**
 * WordPress.com REST API transport.
 *
 * @package Automattic\Syndication\Infrastructure\Transport\REST
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Transport\REST;

use Automattic\Syndication\Domain\Contracts\SettingsRendererInterface;
use Automattic\Syndication\Infrastructure\Transport\AbstractPushTransport;
use WP_Error;
use WP_Post;

/**
 * WordPress.com REST API transport for pushing content.
 *
 * Uses the WordPress.com public REST API to push content to WordPress.com
 * or Jetpack-connected sites.
 */
final class WordPressComTransport extends AbstractPushTransport implements SettingsRendererInterface {

	/**
	 * API base URL.
	 */
	private const API_BASE = 'https://public-api.wordpress.com/rest/v1/';

	/**
	 * OAuth access token.
	 *
	 * @var string
	 */
	private readonly string $access_token;

	/**
	 * Remote blog ID.
	 *
	 * @var string
	 */
	private readonly string $blog_id;

	/**
	 * Constructor.
	 *
	 * @param int    $site_id      The site post ID.
	 * @param string $access_token OAuth access token.
	 * @param string $blog_id      Remote blog ID.
	 * @param int    $timeout      Request timeout in seconds.
	 */
	public function __construct(
		int $site_id,
		string $access_token,
		string $blog_id,
		int $timeout = 45
	) {
		parent::__construct( $site_id, $timeout );
		$this->access_token = $access_token;
		$this->blog_id      = $blog_id;
	}

	/**
	 * Get client metadata.
	 *
	 * @return array{id: string, modes: array<string>, name: string} Client metadata.
	 */
	public static function get_client_data(): array {
		return array(
			'id'    => 'WP_REST',
			'modes' => array( 'push' ),
			'name'  => 'WordPress.com REST',
		);
	}

	/**
	 * Test the connection to the remote site.
	 *
	 * @return bool True if connection is successful, false otherwise.
	 */
	public function test_connection(): bool {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- External API call.
		$response = wp_remote_get(
			self::API_BASE . 'me/?pretty=1',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->user_agent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		return empty( $body->error );
	}

	/**
	 * Check if a post exists on the remote site.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool True if the post exists, false otherwise.
	 */
	public function is_post_exists( int $remote_id ): bool {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- External API call.
		$response = wp_remote_get(
			self::API_BASE . 'sites/' . $this->blog_id . '/posts/' . $remote_id . '/?pretty=1',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->user_agent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		return empty( $body->error );
	}

	/**
	 * Apply pre-push filter for backward compatibility.
	 *
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed>|false Filtered data or false to skip.
	 */
	protected function apply_pre_push_filter( array $post_data, int $post_id ): array|false {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$filtered = apply_filters( 'syn_rest_push_filter_new_post', $post_data, $post_id );
		return false === $filtered ? false : (array) $filtered;
	}

	/**
	 * Apply pre-update filter for backward compatibility.
	 *
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed>|false Filtered data or false to skip.
	 */
	protected function apply_pre_update_filter( array $post_data, int $post_id ): array|false {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		$filtered = apply_filters( 'syn_rest_push_filter_edit_post', $post_data, $post_id );
		return false === $filtered ? false : (array) $filtered;
	}

	/**
	 * Apply body filter for backward compatibility.
	 *
	 * @param string               $action    The action (push or update).
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed> Filtered data.
	 */
	protected function apply_body_filter( string $action, array $post_data, int $post_id ): array {
		$body = $this->prepare_api_body( $post_data, $post_id );

		$filter = 'push' === $action
			? 'syn_rest_push_filter_new_post_body'
			: 'syn_rest_push_filter_edit_post_body';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Legacy hooks for backward compatibility.
		return (array) apply_filters( $filter, $body, $post_id );
	}

	/**
	 * Perform the push operation.
	 *
	 * @param array<string, mixed> $post_data Prepared post data (API body).
	 * @param int                  $post_id   The local post ID.
	 * @return int|WP_Error Remote post ID on success, WP_Error on failure.
	 */
	protected function do_push( array $post_data, int $post_id ): int|WP_Error {
		$response = wp_remote_post(
			self::API_BASE . 'sites/' . $this->blog_id . '/posts/new/',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->user_agent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'       => $post_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $body->error ) && isset( $body->ID ) ) {
			return (int) $body->ID;
		}

		$message = $body->message ?? 'Unknown error';
		return new WP_Error( 'rest-push-new-fail', $message );
	}

	/**
	 * Perform the update operation.
	 *
	 * @param array<string, mixed> $post_data Prepared post data (API body).
	 * @param int                  $post_id   The local post ID.
	 * @param int                  $remote_id The remote post ID.
	 * @return int|WP_Error Local post ID on success, WP_Error on failure.
	 */
	protected function do_update( array $post_data, int $post_id, int $remote_id ): int|WP_Error {
		$response = wp_remote_post(
			self::API_BASE . 'sites/' . $this->blog_id . '/posts/' . $remote_id . '/',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->user_agent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'       => $post_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $body->error ) ) {
			return $post_id;
		}

		$message = $body->message ?? 'Unknown error';
		return new WP_Error( 'rest-push-edit-fail', $message );
	}

	/**
	 * Perform the delete operation.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	protected function do_delete( int $remote_id ): bool|WP_Error {
		$response = wp_remote_post(
			self::API_BASE . 'sites/' . $this->blog_id . '/posts/' . $remote_id . '/delete',
			array(
				'timeout'    => $this->timeout,
				'user-agent' => $this->user_agent,
				'sslverify'  => false,
				'headers'    => array(
					'authorization' => 'Bearer ' . $this->access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $body->error ) ) {
			return true;
		}

		$message = $body->message ?? 'Unknown error';
		return new WP_Error( 'rest-push-delete-fail', $message );
	}

	/**
	 * Prepare the API request body from post data.
	 *
	 * @param array<string, mixed> $post_data Raw post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed> API body data.
	 */
	private function prepare_api_body( array $post_data, int $post_id ): array {
		$categories = wp_get_object_terms( $post_id, 'category', array( 'fields' => 'names' ) );
		$tags       = wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );

		return array(
			'title'      => $post_data['post_title'] ?? '',
			'content'    => $post_data['post_content'] ?? '',
			'excerpt'    => $post_data['post_excerpt'] ?? '',
			'status'     => $post_data['post_status'] ?? 'draft',
			'password'   => $post_data['post_password'] ?? '',
			'date'       => $this->format_date_for_api( $post_data['post_date_gmt'] ?? '' ),
			'categories' => $this->prepare_terms( is_array( $categories ) ? $categories : array() ),
			'tags'       => $this->prepare_terms( is_array( $tags ) ? $tags : array() ),
		);
	}

	/**
	 * Format a MySQL date for the API.
	 *
	 * @param string $mysql_date Date in MySQL format.
	 * @return string Date in ISO 8601 format.
	 */
	private function format_date_for_api( string $mysql_date ): string {
		if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		$formatted = mysql2date( 'c', $mysql_date, false );
		return is_string( $formatted ) ? $formatted : '';
	}

	/**
	 * Prepare terms as CSV string.
	 *
	 * @param array<string> $terms Array of term names.
	 * @return string CSV string.
	 */
	private function prepare_terms( array $terms ): string {
		return implode( ',', $terms );
	}

	/**
	 * Display settings form fields.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( WP_Post $site ): void {
		$site_token = get_post_meta( $site->ID, 'syn_site_token', true );
		$site_id    = get_post_meta( $site->ID, 'syn_site_id', true );
		$site_url   = get_post_meta( $site->ID, 'syn_site_url', true );

		// Decrypt token for display check (don't show actual value).
		$has_token = ! empty( $site_token );
		?>
		<p>
			<label for="site_token"><?php esc_html_e( 'Access Token', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="password" class="widefat" name="site_token" id="site_token" autocomplete="off" value="" />
			<?php if ( $has_token ) : ?>
				<span class="description"><?php esc_html_e( 'Token is saved. Leave blank to keep current token.', 'push-syndication' ); ?></span>
			<?php endif; ?>
		</p>
		<p>
			<label for="site_id"><?php esc_html_e( 'Blog ID', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_id" id="site_id" value="<?php echo esc_attr( (string) $site_id ); ?>" />
		</p>
		<p>
			<label for="site_url"><?php esc_html_e( 'Site URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" value="<?php echo esc_attr( (string) $site_url ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save settings from POST data.
	 *
	 * @param int $site_id The site post ID.
	 * @return bool True on success.
	 */
	public static function save_settings( int $site_id ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
		$token = isset( $_POST['site_token'] ) ? sanitize_text_field( wp_unslash( $_POST['site_token'] ) ) : '';

		if ( ! empty( $token ) ) {
			$encrypted = push_syndicate_encrypt( $token );
			update_post_meta( $site_id, 'syn_site_token', $encrypted );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
		$blog_id = isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : '';
		update_post_meta( $site_id, 'syn_site_id', $blog_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
		$url = isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : '';
		update_post_meta( $site_id, 'syn_site_url', $url );

		return true;
	}
}
