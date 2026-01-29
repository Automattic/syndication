<?php
/**
 * WP_CLI output capture helper for integration tests.
 *
 * @package Automattic\Syndication\Tests\Integration\CLI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\CLI;

/**
 * Helper class to capture WP_CLI output during command execution.
 *
 * Wraps the WP_CLI stub's call tracking to provide a cleaner API for tests.
 *
 * @see \WP_CLI (stub in tests/Stubs/WpCliStub.php)
 */
class WpCliOutputCapture {

	/**
	 * Reset WP_CLI call tracking and start fresh capture.
	 */
	public function reset(): void {
		if ( class_exists( '\WP_CLI', false ) && method_exists( '\WP_CLI', 'reset' ) ) {
			\WP_CLI::reset();
		}
	}

	/**
	 * Start capturing (alias for reset for API consistency).
	 */
	public function start_capture(): void {
		$this->reset();
	}

	/**
	 * Stop capturing (no-op, but here for API consistency).
	 */
	public function stop_capture(): void {
		// Nothing to do - the stub stores calls statically.
	}

	/**
	 * Record an error manually (for exceptions).
	 *
	 * @param string $message The error message.
	 */
	public function record_error( string $message ): void {
		if ( class_exists( '\WP_CLI', false ) ) {
			\WP_CLI::error( $message, false );
		}
	}

	/**
	 * Get stdout as a single string.
	 *
	 * Includes: line, log, success, warning messages.
	 *
	 * @return string
	 */
	public function get_stdout_string(): string {
		$lines = array();

		if ( ! class_exists( '\WP_CLI', false ) || ! property_exists( '\WP_CLI', 'calls' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Static property in stub.
		foreach ( \WP_CLI::$calls as $call ) {
			$method = $call[0];
			if ( in_array( $method, array( 'line', 'log' ), true ) ) {
				$lines[] = $call[1] ?? '';
			} elseif ( 'success' === $method ) {
				$lines[] = 'Success: ' . ( $call[1] ?? '' );
			} elseif ( 'warning' === $method ) {
				$lines[] = 'Warning: ' . ( $call[1] ?? '' );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get stderr as a single string.
	 *
	 * Includes: error messages.
	 *
	 * @return string
	 */
	public function get_stderr_string(): string {
		$lines = array();

		if ( ! class_exists( '\WP_CLI', false ) || ! property_exists( '\WP_CLI', 'calls' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Static property in stub.
		foreach ( \WP_CLI::$calls as $call ) {
			if ( 'error' === $call[0] ) {
				$lines[] = 'Error: ' . ( $call[1] ?? '' );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get combined output as a single string.
	 *
	 * @return string
	 */
	public function get_combined_string(): string {
		$stdout = $this->get_stdout_string();
		$stderr = $this->get_stderr_string();

		if ( '' === $stdout && '' === $stderr ) {
			return '';
		}

		return trim( $stdout . "\n" . $stderr );
	}

	/**
	 * Check if an error occurred.
	 *
	 * @return bool
	 */
	public function had_error(): bool {
		return class_exists( '\WP_CLI', false )
			&& method_exists( '\WP_CLI', 'was_called' )
			&& \WP_CLI::was_called( 'error' );
	}

	/**
	 * Check if success occurred.
	 *
	 * @return bool
	 */
	public function had_success(): bool {
		return class_exists( '\WP_CLI', false )
			&& method_exists( '\WP_CLI', 'was_called' )
			&& \WP_CLI::was_called( 'success' );
	}

	/**
	 * Check if a warning occurred.
	 *
	 * @return bool
	 */
	public function had_warning(): bool {
		return class_exists( '\WP_CLI', false )
			&& method_exists( '\WP_CLI', 'was_called' )
			&& \WP_CLI::was_called( 'warning' );
	}
}
