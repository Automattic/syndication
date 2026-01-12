<?php
/**
 * WP_Error stub for unit tests.
 *
 * @package Automattic\Syndication\Tests\Unit\Stubs
 */

declare( strict_types=1 );

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( class_exists( 'WP_Error' ) ) {
	return;
}

/**
 * Minimal WP_Error stub for unit testing.
 */
class WP_Error {

	/**
	 * Error messages.
	 *
	 * @var array<string, array<string>>
	 */
	private array $errors = array();

	/**
	 * Error data.
	 *
	 * @var array<string, mixed>
	 */
	private array $error_data = array();

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 */
	public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
		if ( ! empty( $code ) ) {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}
	}

	/**
	 * Get the first error code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		$codes = array_keys( $this->errors );
		return $codes[0] ?? '';
	}

	/**
	 * Get error message.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public function get_error_message( string $code = '' ): string {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}
		return $this->errors[ $code ][0] ?? '';
	}

	/**
	 * Get all error codes.
	 *
	 * @return array<string>
	 */
	public function get_error_codes(): array {
		return array_keys( $this->errors );
	}

	/**
	 * Check if there are errors.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}
}
