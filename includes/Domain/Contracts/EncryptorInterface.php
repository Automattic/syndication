<?php
/**
 * Encryptor interface for data encryption.
 *
 * @package Automattic\Syndication\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\Contracts;

/**
 * Interface for encryption implementations.
 *
 * Defines the contract for encrypting and decrypting sensitive
 * syndication data such as credentials and tokens.
 */
interface EncryptorInterface {

	/**
	 * Encrypt data.
	 *
	 * @param string|array<string, mixed> $data Data to encrypt.
	 * @return string Encrypted data.
	 */
	public function encrypt( string|array $data ): string;

	/**
	 * Decrypt data.
	 *
	 * @param string $data Encrypted data to decrypt.
	 * @return array<string, mixed>|string|null Decrypted data, or null on failure.
	 */
	public function decrypt( string $data ): array|string|null;
}
