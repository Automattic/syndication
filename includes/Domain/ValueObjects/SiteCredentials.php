<?php
/**
 * Site credentials value object.
 *
 * @package Automattic\Syndication\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Domain\ValueObjects;

/**
 * Immutable value object representing site authentication credentials.
 *
 * Supports different authentication schemes:
 * - OAuth tokens (WordPress.com REST API)
 * - Username/password (XML-RPC)
 *
 * Credentials are stored encrypted and decrypted on access.
 */
final class SiteCredentials {

	/**
	 * OAuth access token.
	 *
	 * @var string|null
	 */
	private ?string $token;

	/**
	 * Username for basic authentication.
	 *
	 * @var string|null
	 */
	private ?string $username;

	/**
	 * Password for basic authentication.
	 *
	 * @var string|null
	 */
	private ?string $password;

	/**
	 * Constructor.
	 *
	 * @param string|null $token    OAuth access token.
	 * @param string|null $username Username for basic auth.
	 * @param string|null $password Password for basic auth.
	 */
	private function __construct( ?string $token, ?string $username, ?string $password ) {
		$this->token    = $token;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Create credentials for OAuth token authentication.
	 *
	 * @param string $token The OAuth access token.
	 * @return self
	 */
	public static function from_token( string $token ): self {
		return new self( $token, null, null );
	}

	/**
	 * Create credentials for username/password authentication.
	 *
	 * @param string $username The username.
	 * @param string $password The password.
	 * @return self
	 */
	public static function from_username_password( string $username, string $password ): self {
		return new self( null, $username, $password );
	}

	/**
	 * Create empty credentials.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self( null, null, null );
	}

	/**
	 * Check if token authentication is available.
	 *
	 * @return bool
	 */
	public function has_token(): bool {
		return null !== $this->token && '' !== $this->token;
	}

	/**
	 * Check if username/password authentication is available.
	 *
	 * @return bool
	 */
	public function has_username_password(): bool {
		return null !== $this->username && '' !== $this->username;
	}

	/**
	 * Get the OAuth token.
	 *
	 * @return string|null
	 */
	public function get_token(): ?string {
		return $this->token;
	}

	/**
	 * Get the username.
	 *
	 * @return string|null
	 */
	public function get_username(): ?string {
		return $this->username;
	}

	/**
	 * Get the password.
	 *
	 * @return string|null
	 */
	public function get_password(): ?string {
		return $this->password;
	}

	/**
	 * Check if any credentials are set.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return ! $this->has_token() && ! $this->has_username_password();
	}
}
