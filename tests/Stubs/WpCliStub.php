<?php
/**
 * WP_CLI stub for integration tests.
 *
 * Provides a minimal implementation that tracks method calls for assertion
 * in tests, without exiting on errors like the real WP_CLI does.
 *
 * @package Automattic\Syndication\Tests\Stubs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stub must match real WP_CLI class name.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Stubs file with WP_CLI classes.

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Minimal WP_CLI stub for testing.
	 *
	 * Collects method calls for assertion in tests without executing
	 * real WP_CLI behaviour (which would exit on errors).
	 */
	class WP_CLI {

		/**
		 * Tracks all method calls.
		 *
		 * @var array<int, array{0: string, 1?: string, 2?: string}>
		 */
		public static array $calls = array();

		/**
		 * Reset the call tracker.
		 */
		public static function reset(): void {
			self::$calls = array();
		}

		/**
		 * Record a success message.
		 *
		 * @param string $message The message.
		 */
		public static function success( string $message ): void {
			self::$calls[] = array( 'success', $message );
		}

		/**
		 * Record an error message.
		 *
		 * Unlike real WP_CLI, this does NOT exit. Tests should check
		 * was_called('error') to verify error state.
		 *
		 * @param string $message The message.
		 * @param bool   $exit    Whether to exit (ignored in stub).
		 */
		public static function error( string $message, bool $exit = true ): void {
			self::$calls[] = array( 'error', $message );
		}

		/**
		 * Record a warning message.
		 *
		 * @param string $message The message.
		 */
		public static function warning( string $message ): void {
			self::$calls[] = array( 'warning', $message );
		}

		/**
		 * Record a line of output.
		 *
		 * @param string $message The message.
		 */
		public static function line( string $message = '' ): void {
			self::$calls[] = array( 'line', $message );
		}

		/**
		 * Record a log message.
		 *
		 * @param string $message The message.
		 */
		public static function log( string $message ): void {
			self::$calls[] = array( 'log', $message );
		}

		/**
		 * Record a debug message.
		 *
		 * @param string $message The message.
		 * @param string $group   Debug group.
		 */
		public static function debug( string $message, string $group = '' ): void {
			self::$calls[] = array( 'debug', $message, $group );
		}

		/**
		 * Confirm prompt (always proceeds in stub).
		 *
		 * @param string               $question   The question.
		 * @param array<string, mixed> $assoc_args Associative arguments.
		 */
		public static function confirm( string $question, array $assoc_args = array() ): void {
			self::$calls[] = array( 'confirm', $question );
			// In tests, this is effectively a no-op - always proceeds.
		}

		/**
		 * Get a call by method name.
		 *
		 * @param string $method The method name.
		 * @return array<int, string>|null The call data, or null if not found.
		 */
		public static function get_call( string $method ): ?array {
			foreach ( self::$calls as $call ) {
				if ( $call[0] === $method ) {
					return $call;
				}
			}
			return null;
		}

		/**
		 * Get all calls of a specific method.
		 *
		 * @param string $method The method name.
		 * @return array<int, array<int, string>> Array of calls.
		 */
		public static function get_calls( string $method ): array {
			$result = array();
			foreach ( self::$calls as $call ) {
				if ( $call[0] === $method ) {
					$result[] = $call;
				}
			}
			return $result;
		}

		/**
		 * Check if a method was called.
		 *
		 * @param string $method The method name.
		 * @return bool True if called.
		 */
		public static function was_called( string $method ): bool {
			return null !== self::get_call( $method );
		}

		/**
		 * Add a command (no-op for testing).
		 *
		 * @param string $name    Command name.
		 * @param mixed  $command Command class or callable.
		 */
		public static function add_command( string $name, $command ): void {
			// No-op - we don't need to actually register commands in tests.
		}
	}
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Minimal WP_CLI_Command stub.
	 *
	 * WP-CLI commands extend this base class.
	 */
	class WP_CLI_Command {
		// Empty base class - WP_CLI commands extend this.
	}
}
