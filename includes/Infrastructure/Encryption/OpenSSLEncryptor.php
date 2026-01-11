<?php
/**
 * OpenSSL encryption implementation.
 *
 * @package Automattic\Syndication\Infrastructure\Encryption
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\Encryption;

use Automattic\Syndication\Domain\Contracts\EncryptorInterface;

/**
 * OpenSSL-based encryptor for sensitive data.
 *
 * Uses AES-256-CBC encryption with a configurable key.
 */
final class OpenSSLEncryptor implements EncryptorInterface {

	/**
	 * The cipher algorithm.
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * The encryption key.
	 *
	 * @var string
	 */
	private readonly string $key;

	/**
	 * Constructor.
	 *
	 * @param string $key Encryption key.
	 */
	public function __construct( string $key ) {
		$this->key = $key;
	}

	/**
	 * Encrypt data using OpenSSL.
	 *
	 * @param string|array<string, mixed> $data Data to encrypt.
	 * @return string Base64-encoded encrypted data.
	 */
	public function encrypt( string|array $data ): string {
		$json   = wp_json_encode( $data );
		$cipher = $this->get_cipher_config();

		if ( false === $cipher ) {
			// Cipher not available, return JSON-encoded data unencrypted.
			return (string) $json;
		}

		$encrypted = openssl_encrypt(
			(string) $json,
			$cipher['cipher'],
			$cipher['key'],
			0,
			$cipher['iv']
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( (string) $encrypted );
	}

	/**
	 * Decrypt data using OpenSSL.
	 *
	 * @param string $data Base64-encoded encrypted data.
	 * @return array<string, mixed>|string|null Decrypted data, or null on failure.
	 */
	public function decrypt( string $data ): array|string|null {
		if ( '' === $data ) {
			return null;
		}

		$cipher = $this->get_cipher_config();

		if ( false === $cipher ) {
			// Cipher not available, try to decode as JSON.
			$decoded = json_decode( $data, true );
			return is_array( $decoded ) ? $decoded : $data;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded_data = base64_decode( $data, true );

		if ( false === $decoded_data ) {
			return null;
		}

		$decrypted = openssl_decrypt(
			$decoded_data,
			$cipher['cipher'],
			$cipher['key'],
			0,
			$cipher['iv']
		);

		if ( false === $decrypted ) {
			return null;
		}

		$result = json_decode( $decrypted, true );

		// json_decode returns null on failure, otherwise the decoded value.
		return $result;
	}

	/**
	 * Get cipher configuration.
	 *
	 * @return array{cipher: string, key: string, iv: string}|false Configuration or false if unavailable.
	 */
	private function get_cipher_config(): array|false {
		if ( ! in_array( self::CIPHER, openssl_get_cipher_methods(), true ) ) {
			return false;
		}

		return array(
			'cipher' => self::CIPHER,
			'key'    => md5( $this->key ),
			'iv'     => substr( md5( md5( $this->key ) ), 0, 16 ),
		);
	}
}
