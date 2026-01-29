<?php
/**
 * Credential testing service.
 *
 * Handles AJAX requests for testing site credentials without saving.
 *
 * @package Automattic\Syndication\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\Services;

use Automattic\Syndication\Domain\Contracts\EncryptorInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use Automattic\Syndication\Domain\Contracts\TransportInterface;
use Automattic\Syndication\Infrastructure\Transport\Feed\RSSFeedTransport;
use Automattic\Syndication\Infrastructure\Transport\REST\WordPressComTransport;
use Automattic\Syndication\Infrastructure\Transport\REST\WordPressRestTransport;
use Automattic\Syndication\Infrastructure\Transport\XMLRPC\XMLRPCTransport;

/**
 * Service for testing site credentials via AJAX.
 */
final class CredentialTestingService {

	/**
	 * Transport factory.
	 *
	 * @var TransportFactoryInterface
	 */
	private readonly TransportFactoryInterface $transport_factory;

	/**
	 * Encryptor for decrypting stored credentials.
	 *
	 * @var EncryptorInterface
	 */
	private readonly EncryptorInterface $encryptor;

	/**
	 * Available transports.
	 *
	 * @var array<string, array{id: string, modes: array<string>, name: string}>
	 */
	private array $transports = array();

	/**
	 * Constructor.
	 *
	 * @param TransportFactoryInterface $transport_factory Transport factory.
	 * @param EncryptorInterface        $encryptor         Encryptor.
	 */
	public function __construct( TransportFactoryInterface $transport_factory, EncryptorInterface $encryptor ) {
		$this->transport_factory = $transport_factory;
		$this->encryptor         = $encryptor;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_syn_test_credentials', array( $this, 'handle_ajax' ) );
		add_action( 'admin_init', array( $this, 'load_transports' ) );
	}

	/**
	 * Load available transports.
	 */
	public function load_transports(): void {
		$this->transports = $this->transport_factory->get_available_transports();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$this->transports = apply_filters( 'syn_transports', $this->transports );
	}

	/**
	 * Handle the AJAX credential test request.
	 */
	public function handle_ajax(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value used only for verification.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'syn_test_credentials' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'push-syndication' ) ) );
		}

		if ( ! $this->current_user_can_syndicate() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'push-syndication' ) ) );
		}

		$transport_type_mode = isset( $_POST['transport_type_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['transport_type_mode'] ) ) : '';
		$parts               = explode( '|', $transport_type_mode );
		$transport_type      = $parts[0] ?? '';

		if ( empty( $transport_type ) || ! isset( $this->transports[ $transport_type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid transport type.', 'push-syndication' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values sanitized in get_test_credentials().
		$credentials = $this->get_test_credentials( $transport_type, $_POST, $site_id );
		$transport   = $this->create_transport_for_testing( $transport_type, $credentials );

		if ( null === $transport ) {
			wp_send_json_error( array( 'message' => __( 'Could not create transport. Please check all fields are filled.', 'push-syndication' ) ) );
		}

		try {
			$result = $transport->test_connection();

			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'Connection successful! Credentials are valid.', 'push-syndication' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Connection failed. Please check your credentials.', 'push-syndication' ) ) );
			}
		} catch ( \Exception $e ) {
			/* translators: %s: error message */
			wp_send_json_error( array( 'message' => sprintf( __( 'Connection error: %s', 'push-syndication' ), $e->getMessage() ) ) );
		}
	}

	/**
	 * Get credentials for testing, merging form data with stored values.
	 *
	 * @param string              $transport_type The transport type.
	 * @param array<string,mixed> $post_data      The POST data from the form.
	 * @param int                 $site_id        The site post ID (0 for new sites).
	 * @return array<string,mixed> Merged credentials.
	 */
	private function get_test_credentials( string $transport_type, array $post_data, int $site_id ): array {
		$credentials = $post_data;

		if ( $site_id <= 0 ) {
			return $credentials;
		}

		// Site URL.
		if ( empty( $credentials['site_url'] ) ) {
			$credentials['site_url'] = get_post_meta( $site_id, 'syn_site_url', true );
		}

		// Username.
		if ( empty( $credentials['site_username'] ) ) {
			$credentials['site_username'] = get_post_meta( $site_id, 'syn_site_username', true );
		}

		// Password.
		if ( empty( $credentials['site_password'] ) ) {
			$encrypted = get_post_meta( $site_id, 'syn_site_password', true );
			if ( ! empty( $encrypted ) ) {
				$decrypted = $this->encryptor->decrypt( $encrypted );
				$credentials['site_password'] = is_string( $decrypted ) ? $decrypted : '';
			}
		}

		// Token (for WordPress.com).
		if ( empty( $credentials['site_token'] ) ) {
			$encrypted = get_post_meta( $site_id, 'syn_site_token', true );
			if ( ! empty( $encrypted ) ) {
				$decrypted = $this->encryptor->decrypt( $encrypted );
				$credentials['site_token'] = is_string( $decrypted ) ? $decrypted : '';
			}
		}

		// Blog ID (for WordPress.com).
		if ( empty( $credentials['blog_id'] ) ) {
			$credentials['blog_id'] = get_post_meta( $site_id, 'syn_site_id', true );
		}

		// Feed URL (for RSS).
		if ( empty( $credentials['feed_url'] ) ) {
			$credentials['feed_url'] = get_post_meta( $site_id, 'syn_feed_url', true );
		}

		return $credentials;
	}

	/**
	 * Create a transport instance for credential testing.
	 *
	 * @param string              $transport_type The transport type ID.
	 * @param array<string,mixed> $post_data      The POST data with credentials.
	 * @return TransportInterface|null The transport or null.
	 */
	private function create_transport_for_testing( string $transport_type, array $post_data ): ?TransportInterface {
		$site_url = isset( $post_data['site_url'] ) ? esc_url_raw( (string) $post_data['site_url'] ) : '';
		$username = isset( $post_data['site_username'] ) ? sanitize_text_field( (string) $post_data['site_username'] ) : '';
		$password = isset( $post_data['site_password'] ) ? (string) $post_data['site_password'] : '';

		switch ( $transport_type ) {
			case 'WP_REST_API':
				if ( empty( $site_url ) || empty( $username ) || empty( $password ) ) {
					return null;
				}
				return new WordPressRestTransport( 0, $site_url, $username, $password );

			case 'WP_XMLRPC':
				if ( empty( $site_url ) || empty( $username ) || empty( $password ) ) {
					return null;
				}
				return new XMLRPCTransport( 0, $site_url, $username, $password );

			case 'WP_REST':
				$token   = isset( $post_data['site_token'] ) ? (string) $post_data['site_token'] : $password;
				$blog_id = isset( $post_data['site_id'] ) ? sanitize_text_field( (string) $post_data['site_id'] ) : '';
				if ( empty( $token ) || empty( $blog_id ) ) {
					return null;
				}
				return new WordPressComTransport( 0, $token, $blog_id );

			case 'WP_RSS':
				$feed_url = isset( $post_data['feed_url'] ) ? esc_url_raw( (string) $post_data['feed_url'] ) : $site_url;
				if ( empty( $feed_url ) ) {
					return null;
				}
				return new RSSFeedTransport( 0, $feed_url, 'post', 'draft', 'closed', 'closed', false );

			default:
				return null;
		}
	}

	/**
	 * Check if the current user can syndicate.
	 *
	 * @return bool True if user can syndicate.
	 */
	private function current_user_can_syndicate(): bool {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		return current_user_can( $capability );
	}
}
