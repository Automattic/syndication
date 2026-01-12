<?php
/**
 * XML-RPC transport for WordPress syndication.
 *
 * @package Automattic\Syndication\Infrastructure\Transport\XMLRPC
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Transport\XMLRPC;

use Automattic\Syndication\Domain\Contracts\PullTransportInterface;
use Automattic\Syndication\Domain\Contracts\SettingsRendererInterface;
use Automattic\Syndication\Infrastructure\Transport\AbstractPushTransport;
use IXR_Date;
use WP_Error;
use WP_HTTP_IXR_Client;
use WP_Post;

/**
 * XML-RPC transport for pushing and pulling content between WordPress sites.
 *
 * This transport uses the WordPress XML-RPC API for communication.
 */
final class XMLRPCTransport extends AbstractPushTransport implements PullTransportInterface, SettingsRendererInterface {

	/**
	 * The XML-RPC client.
	 *
	 * @var WP_HTTP_IXR_Client
	 */
	private WP_HTTP_IXR_Client $client;

	/**
	 * XML-RPC username.
	 *
	 * @var string
	 */
	private readonly string $username;

	/**
	 * XML-RPC password.
	 *
	 * @var string
	 */
	private readonly string $password;

	/**
	 * Remote server URL.
	 *
	 * @var string
	 */
	private readonly string $server_url;

	/**
	 * Constructor.
	 *
	 * @param int    $site_id    The site post ID.
	 * @param string $server_url The XML-RPC server URL.
	 * @param string $username   XML-RPC username.
	 * @param string $password   XML-RPC password.
	 * @param int    $timeout    Request timeout in seconds.
	 */
	public function __construct(
		int $site_id,
		string $server_url,
		string $username,
		string $password,
		int $timeout = 45
	) {
		parent::__construct( $site_id, $timeout );

		$this->server_url = $this->normalise_server_url( $server_url );
		$this->username   = $username;
		$this->password   = $password;
		$this->client     = new WP_HTTP_IXR_Client( $this->server_url );
	}

	/**
	 * Get client metadata.
	 *
	 * @return array{id: string, modes: array<string>, name: string} Client metadata.
	 */
	public static function get_client_data(): array {
		return array(
			'id'    => 'WP_XMLRPC',
			'modes' => array( 'push', 'pull' ),
			'name'  => 'WordPress XML-RPC',
		);
	}

	/**
	 * Normalise the server URL to ensure it points to xmlrpc.php.
	 *
	 * @param string $url The server URL.
	 * @return string Normalised URL.
	 */
	private function normalise_server_url( string $url ): string {
		$url = untrailingslashit( $url );
		if ( false === strpos( $url, 'xmlrpc.php' ) ) {
			$url = trailingslashit( $url ) . 'xmlrpc.php';
		}
		return esc_url_raw( $url );
	}

	/**
	 * Test the connection to the remote site.
	 *
	 * @return bool True if connection is successful.
	 */
	public function test_connection(): bool {
		$result = $this->client->query(
			'wp.getPostTypes',
			'1',
			$this->username,
			$this->password
		);

		return (bool) $result;
	}

	/**
	 * Check if a post exists on the remote site.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool True if the post exists.
	 */
	public function is_post_exists( int $remote_id ): bool {
		$post = $this->get_remote_post( $remote_id );
		return null !== $post && isset( $post['post_id'] ) && (int) $post['post_id'] === $remote_id;
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
		$filtered = apply_filters( 'syn_xmlrpc_push_filter_new_post', $post_data, $post_id );
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
		$filtered = apply_filters( 'syn_xmlrpc_push_filter_edit_post', $post_data, $post_id );
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
		$args = $this->prepare_xmlrpc_args( $post_data, $post_id );

		$filter = 'push' === $action
			? 'syn_xmlrpc_push_new_post_args'
			: 'syn_xmlrpc_push_edit_post_args';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Legacy hooks for backward compatibility.
		return (array) apply_filters( $filter, $args, $post_data );
	}

	/**
	 * Prepare XML-RPC arguments from post data.
	 *
	 * @param array<string, mixed> $post_data The post data.
	 * @param int                  $post_id   The post ID.
	 * @return array<string, mixed> XML-RPC arguments.
	 */
	private function prepare_xmlrpc_args( array $post_data, int $post_id ): array {
		$args = array(
			'post_title'    => $post_data['post_title'] ?? '',
			'post_content'  => $post_data['post_content'] ?? '',
			'post_excerpt'  => $post_data['post_excerpt'] ?? '',
			'post_status'   => $post_data['post_status'] ?? 'draft',
			'post_type'     => $post_data['post_type'] ?? 'post',
			'wp_password'   => $post_data['post_password'] ?? '',
			'post_date_gmt' => $this->convert_date_gmt(
				$post_data['post_date_gmt'] ?? '',
				$post_data['post_date'] ?? ''
			),
		);

		$args['terms_names']   = $this->get_post_terms( $post_id );
		$args['custom_fields'] = $this->get_custom_fields( $post_id );

		return $args;
	}

	/**
	 * Perform the push operation.
	 *
	 * @param array<string, mixed> $post_data Prepared post data (XML-RPC args).
	 * @param int                  $post_id   The local post ID.
	 * @return int|WP_Error Remote post ID on success, WP_Error on failure.
	 */
	protected function do_push( array $post_data, int $post_id ): int|WP_Error {
		$result = $this->client->query(
			'wp.newPost',
			'1',
			$this->username,
			$this->password,
			$post_data
		);

		if ( ! $result ) {
			return new WP_Error(
				'xmlrpc-push-fail',
				$this->client->getErrorMessage()
			);
		}

		$remote_post_id = (int) $this->client->getResponse();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'syn_xmlrpc_push_new_post_success', $remote_post_id, $post_id );

		return $remote_post_id;
	}

	/**
	 * Perform the update operation.
	 *
	 * @param array<string, mixed> $post_data Prepared post data (XML-RPC args).
	 * @param int                  $post_id   The local post ID.
	 * @param int                  $remote_id The remote post ID.
	 * @return int|WP_Error Local post ID on success, WP_Error on failure.
	 */
	protected function do_update( array $post_data, int $post_id, int $remote_id ): int|WP_Error {
		// Fetch remote post to handle custom field deletion.
		$remote_post = $this->get_remote_post( $remote_id );

		if ( null === $remote_post ) {
			return new WP_Error( 'syn-remote-post-not-found', 'Remote post does not exist.' );
		}

		// Mark existing custom fields for deletion before adding new ones.
		$custom_fields = array();
		if ( ! empty( $remote_post['custom_fields'] ) ) {
			foreach ( $remote_post['custom_fields'] as $field ) {
				$custom_fields[] = array(
					'id'              => $field['id'],
					'meta_key_lookup' => $field['key'],
				);
			}
		}

		$post_data['custom_fields'] = array_merge( $custom_fields, $post_data['custom_fields'] ?? array() );

		$result = $this->client->query(
			'wp.editPost',
			'1',
			$this->username,
			$this->password,
			$remote_id,
			$post_data
		);

		if ( ! $result ) {
			return new WP_Error(
				'xmlrpc-update-fail',
				$this->client->getErrorMessage()
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		do_action( 'syn_xmlrpc_push_edit_post_success', $remote_id, $post_id );

		return $post_id;
	}

	/**
	 * Perform the delete operation.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	protected function do_delete( int $remote_id ): bool|WP_Error {
		$result = $this->client->query(
			'wp.deletePost',
			'1',
			$this->username,
			$this->password,
			$remote_id
		);

		if ( ! $result ) {
			return new WP_Error(
				'xmlrpc-delete-fail',
				$this->client->getErrorMessage()
			);
		}

		return true;
	}

	/**
	 * Pull posts from the remote site.
	 *
	 * @param array<string, mixed> $args Optional arguments for filtering posts.
	 * @return array<int, array<string, mixed>> Array of post data.
	 */
	public function pull( array $args = array() ): array {
		$filter = array(
			'number' => $args['number'] ?? 10,
			'offset' => $args['offset'] ?? 0,
		);

		if ( ! empty( $args['post_type'] ) ) {
			$filter['post_type'] = $args['post_type'];
		}

		if ( ! empty( $args['post_status'] ) ) {
			$filter['post_status'] = $args['post_status'];
		}

		$result = $this->client->query(
			'wp.getPosts',
			'1',
			$this->username,
			$this->password,
			$filter
		);

		if ( ! $result ) {
			return array();
		}

		$posts = $this->client->getResponse();

		if ( ! is_array( $posts ) ) {
			return array();
		}

		return array_map( array( $this, 'normalise_remote_post' ), $posts );
	}

	/**
	 * Retrieve a single post from the remote site.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return array<string, mixed>|null Post data array or null if not found.
	 */
	public function get_post( int $remote_id ): ?array {
		$post = $this->get_remote_post( $remote_id );

		if ( null === $post ) {
			return null;
		}

		return $this->normalise_remote_post( $post );
	}

	/**
	 * Get a remote post via XML-RPC.
	 *
	 * @param int $remote_id The remote post ID.
	 * @return array<string, mixed>|null Raw post data or null.
	 */
	private function get_remote_post( int $remote_id ): ?array {
		$result = $this->client->query(
			'wp.getPost',
			'1',
			$this->username,
			$this->password,
			$remote_id
		);

		if ( ! $result ) {
			return null;
		}

		$response = $this->client->getResponse();

		return is_array( $response ) ? $response : null;
	}

	/**
	 * Normalise remote post data to standard format.
	 *
	 * @param array<string, mixed> $post Raw post data from XML-RPC.
	 * @return array<string, mixed> Normalised post data.
	 */
	private function normalise_remote_post( array $post ): array {
		return array(
			'post_title'    => $post['post_title'] ?? '',
			'post_content'  => $post['post_content'] ?? '',
			'post_excerpt'  => $post['post_excerpt'] ?? '',
			'post_status'   => $post['post_status'] ?? 'draft',
			'post_date'     => $post['post_date'] ?? '',
			'post_date_gmt' => $post['post_date_gmt'] ?? '',
			'post_type'     => $post['post_type'] ?? 'post',
			'remote_id'     => (int) ( $post['post_id'] ?? 0 ),
			'post_guid'     => $post['guid'] ?? '',
			'custom_fields' => $post['custom_fields'] ?? array(),
		);
	}

	/**
	 * Get post terms for syndication.
	 *
	 * @param int $post_id The post ID.
	 * @return array<string, array<string>> Terms by taxonomy.
	 */
	private function get_post_terms( int $post_id ): array {
		$terms = array();

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $terms;
		}

		if ( is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			$categories = wp_get_object_terms( $post_id, 'category', array( 'fields' => 'names' ) );
			if ( is_array( $categories ) ) {
				$terms['category'] = $categories;
			}
		}

		if ( is_object_in_taxonomy( $post->post_type, 'post_tag' ) ) {
			$tags = wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
			if ( is_array( $tags ) ) {
				$terms['post_tag'] = $tags;
			}
		}

		return $terms;
	}

	/**
	 * Get custom fields for syndication.
	 *
	 * @param int $post_id The post ID.
	 * @return array<int, array{key: string, value: mixed}> Custom fields.
	 */
	private function get_custom_fields( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$all_meta  = get_post_custom( $post_id );
		$blacklist = $this->get_meta_blacklist( $post_id );
		$fields    = array();

		foreach ( (array) $all_meta as $key => $values ) {
			// Skip blacklisted and syndication meta.
			if ( in_array( $key, $blacklist, true ) || preg_match( '/^_?syn/i', $key ) ) {
				continue;
			}

			foreach ( $values as $value ) {
				$fields[] = array(
					'key'   => $key,
					'value' => maybe_unserialize( $value ),
				);
			}
		}

		// Add source URL.
		$fields[] = array(
			'key'   => 'syn_source_url',
			'value' => $post->guid,
		);

		return $fields;
	}

	/**
	 * Get list of meta keys to exclude from syndication.
	 *
	 * @param int $post_id The post ID.
	 * @return array<string> Blacklisted meta keys.
	 */
	private function get_meta_blacklist( int $post_id ): array {
		$blacklist = array( '_edit_last', '_edit_lock', '_thumbnail_id' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook for backward compatibility.
		return apply_filters( 'syn_ignored_meta_fields', $blacklist, $post_id );
	}

	/**
	 * Convert a GMT date for XML-RPC.
	 *
	 * @param string $date_gmt GMT date string.
	 * @param string $date     Local date string.
	 * @return IXR_Date The IXR date object.
	 */
	private function convert_date_gmt( string $date_gmt, string $date ): IXR_Date {
		if ( '0000-00-00 00:00:00' !== $date && '0000-00-00 00:00:00' === $date_gmt ) {
			$formatted = get_gmt_from_date(
				mysql2date( 'Y-m-d H:i:s', $date, false ),
				'Ymd\TH:i:s'
			);
			return new IXR_Date( is_string( $formatted ) ? $formatted : '00000000T00:00:00Z' );
		}

		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return new IXR_Date( '00000000T00:00:00Z' );
		}

		$formatted = mysql2date( 'Ymd\TH:i:s', $date_gmt, false );
		return new IXR_Date( is_string( $formatted ) ? $formatted : '00000000T00:00:00Z' );
	}

	/**
	 * Display settings form fields.
	 *
	 * @param WP_Post $site The site post object.
	 */
	public static function display_settings( WP_Post $site ): void {
		$site_url      = get_post_meta( $site->ID, 'syn_site_url', true );
		$site_username = get_post_meta( $site->ID, 'syn_site_username', true );
		$has_password  = ! empty( get_post_meta( $site->ID, 'syn_site_password', true ) );

		?>
		<p>
			<label for="site_url"><?php esc_html_e( 'Site URL', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_url" id="site_url" value="<?php echo esc_attr( (string) $site_url ); ?>" />
		</p>
		<p>
			<label for="site_username"><?php esc_html_e( 'Username', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="text" class="widefat" name="site_username" id="site_username" value="<?php echo esc_attr( (string) $site_username ); ?>" />
		</p>
		<p>
			<label for="site_password"><?php esc_html_e( 'Password', 'push-syndication' ); ?></label>
		</p>
		<p>
			<input type="password" class="widefat" name="site_password" id="site_password" autocomplete="off" value="" />
			<?php if ( $has_password ) : ?>
				<span class="description"><?php esc_html_e( 'Password is saved. Leave blank to keep current password.', 'push-syndication' ); ?></span>
			<?php endif; ?>
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
		$url = isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : '';
		$url = str_replace( '/xmlrpc.php', '', $url );
		update_post_meta( $site_id, 'syn_site_url', $url );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
		$username = isset( $_POST['site_username'] ) ? sanitize_text_field( wp_unslash( $_POST['site_username'] ) ) : '';
		update_post_meta( $site_id, 'syn_site_username', $username );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by caller.
		$password = isset( $_POST['site_password'] ) ? wp_strip_all_tags( wp_unslash( $_POST['site_password'] ) ) : '';
		if ( ! empty( $password ) ) {
			$encrypted = push_syndicate_encrypt( $password );
			update_post_meta( $site_id, 'syn_site_password', $encrypted );
		}

		return true;
	}
}
