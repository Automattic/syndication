<?php
/**
 * Transport factory for creating transport instances.
 *
 * @package Automattic\Syndication\Infrastructure\Transport
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Transport;

use Automattic\Syndication\Domain\Contracts\EncryptorInterface;
use Automattic\Syndication\Domain\Contracts\PullTransportInterface;
use Automattic\Syndication\Domain\Contracts\PushTransportInterface;
use Automattic\Syndication\Domain\Contracts\TransportInterface;
use Automattic\Syndication\Infrastructure\Transport\Feed\RSSFeedTransport;
use Automattic\Syndication\Infrastructure\Transport\REST\WordPressComTransport;
use Automattic\Syndication\Infrastructure\Transport\XMLRPC\XMLRPCTransport;
use WP_Post;

/**
 * Factory for creating transport instances from site configuration.
 *
 * Creates the appropriate transport implementation based on site metadata,
 * handling credential decryption and configuration.
 */
final class TransportFactory {

	/**
	 * Transport type to class mapping.
	 */
	private const TRANSPORT_MAP = array(
		'WP_XMLRPC' => XMLRPCTransport::class,
		'WP_REST'   => WordPressComTransport::class,
		'WP_RSS'    => RSSFeedTransport::class,
	);

	/**
	 * Encryptor for decrypting credentials.
	 *
	 * @var EncryptorInterface
	 */
	private readonly EncryptorInterface $encryptor;

	/**
	 * Constructor.
	 *
	 * @param EncryptorInterface $encryptor Encryptor for credential decryption.
	 */
	public function __construct( EncryptorInterface $encryptor ) {
		$this->encryptor = $encryptor;
	}

	/**
	 * Create a transport instance for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return TransportInterface|null The transport instance or null if invalid.
	 */
	public function create( int $site_id ): ?TransportInterface {
		$site = get_post( $site_id );

		if ( ! $site instanceof WP_Post || 'syn_site' !== $site->post_type ) {
			return null;
		}

		$transport_type = (string) get_post_meta( $site_id, 'syn_transport_type', true );

		if ( empty( $transport_type ) || ! isset( self::TRANSPORT_MAP[ $transport_type ] ) ) {
			return null;
		}

		return match ( $transport_type ) {
			'WP_XMLRPC' => $this->create_xmlrpc_transport( $site_id ),
			'WP_REST'   => $this->create_rest_transport( $site_id ),
			'WP_RSS'    => $this->create_rss_transport( $site_id ),
			default     => null,
		};
	}

	/**
	 * Create a push transport for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return PushTransportInterface|null The transport or null if not a push transport.
	 */
	public function create_push_transport( int $site_id ): ?PushTransportInterface {
		$transport = $this->create( $site_id );

		if ( ! $transport instanceof PushTransportInterface ) {
			return null;
		}

		return $transport;
	}

	/**
	 * Create a pull transport for a site.
	 *
	 * @param int $site_id The site post ID.
	 * @return PullTransportInterface|null The transport or null if not a pull transport.
	 */
	public function create_pull_transport( int $site_id ): ?PullTransportInterface {
		$transport = $this->create( $site_id );

		if ( ! $transport instanceof PullTransportInterface ) {
			return null;
		}

		return $transport;
	}

	/**
	 * Get available transport types.
	 *
	 * @return array<string, array{id: string, modes: array<string>, name: string}>
	 */
	public function get_available_transports(): array {
		$transports = array();

		foreach ( self::TRANSPORT_MAP as $id => $class ) {
			if ( method_exists( $class, 'get_client_data' ) ) {
				$transports[ $id ] = $class::get_client_data();
			}
		}

		return $transports;
	}

	/**
	 * Check if a transport type supports push.
	 *
	 * @param string $transport_type The transport type ID.
	 * @return bool True if push is supported.
	 */
	public function supports_push( string $transport_type ): bool {
		if ( ! isset( self::TRANSPORT_MAP[ $transport_type ] ) ) {
			return false;
		}

		$class = self::TRANSPORT_MAP[ $transport_type ];

		if ( ! method_exists( $class, 'get_client_data' ) ) {
			return false;
		}

		$data = $class::get_client_data();

		return in_array( 'push', $data['modes'] ?? array(), true );
	}

	/**
	 * Check if a transport type supports pull.
	 *
	 * @param string $transport_type The transport type ID.
	 * @return bool True if pull is supported.
	 */
	public function supports_pull( string $transport_type ): bool {
		if ( ! isset( self::TRANSPORT_MAP[ $transport_type ] ) ) {
			return false;
		}

		$class = self::TRANSPORT_MAP[ $transport_type ];

		if ( ! method_exists( $class, 'get_client_data' ) ) {
			return false;
		}

		$data = $class::get_client_data();

		return in_array( 'pull', $data['modes'] ?? array(), true );
	}

	/**
	 * Create an XML-RPC transport.
	 *
	 * @param int $site_id The site post ID.
	 * @return XMLRPCTransport|null The transport or null on failure.
	 */
	private function create_xmlrpc_transport( int $site_id ): ?XMLRPCTransport {
		$server_url = (string) get_post_meta( $site_id, 'syn_site_url', true );
		$username   = (string) get_post_meta( $site_id, 'syn_site_username', true );
		$password   = $this->decrypt_credential( $site_id, 'syn_site_password' );

		if ( empty( $server_url ) || empty( $username ) || empty( $password ) ) {
			return null;
		}

		return new XMLRPCTransport(
			$site_id,
			$server_url,
			$username,
			$password
		);
	}

	/**
	 * Create a WordPress.com REST transport.
	 *
	 * @param int $site_id The site post ID.
	 * @return WordPressComTransport|null The transport or null on failure.
	 */
	private function create_rest_transport( int $site_id ): ?WordPressComTransport {
		$access_token = $this->decrypt_credential( $site_id, 'syn_site_token' );
		$blog_id      = (string) get_post_meta( $site_id, 'syn_site_id', true );

		if ( empty( $access_token ) || empty( $blog_id ) ) {
			return null;
		}

		return new WordPressComTransport(
			$site_id,
			$access_token,
			$blog_id
		);
	}

	/**
	 * Create an RSS feed transport.
	 *
	 * @param int $site_id The site post ID.
	 * @return RSSFeedTransport|null The transport or null on failure.
	 */
	private function create_rss_transport( int $site_id ): ?RSSFeedTransport {
		$feed_url = (string) get_post_meta( $site_id, 'syn_feed_url', true );

		if ( empty( $feed_url ) ) {
			return null;
		}

		$post_type      = (string) get_post_meta( $site_id, 'syn_default_post_type', true );
		$post_status    = (string) get_post_meta( $site_id, 'syn_default_post_status', true );
		$comment_status = (string) get_post_meta( $site_id, 'syn_default_comment_status', true );
		$ping_status    = (string) get_post_meta( $site_id, 'syn_default_ping_status', true );
		$import_cats    = 'yes' === get_post_meta( $site_id, 'syn_default_cat_status', true );

		return new RSSFeedTransport(
			$site_id,
			$feed_url,
			! empty( $post_type ) ? $post_type : 'post',
			! empty( $post_status ) ? $post_status : 'draft',
			! empty( $comment_status ) ? $comment_status : 'closed',
			! empty( $ping_status ) ? $ping_status : 'closed',
			$import_cats
		);
	}

	/**
	 * Decrypt a credential from post meta.
	 *
	 * @param int    $site_id  The site post ID.
	 * @param string $meta_key The meta key for the encrypted credential.
	 * @return string The decrypted credential or empty string.
	 */
	private function decrypt_credential( int $site_id, string $meta_key ): string {
		$encrypted = (string) get_post_meta( $site_id, $meta_key, true );

		if ( empty( $encrypted ) ) {
			return '';
		}

		$decrypted = $this->encryptor->decrypt( $encrypted );

		return is_string( $decrypted ) ? $decrypted : '';
	}
}
