<?php
/**
 * Minimal stand-ins for WP-CLI, so the real Syndication_CLI_Command class can
 * be loaded and exercised inside the integration suite.
 *
 * Output is recorded rather than printed. WP_CLI::error() throws, mirroring the
 * way the real method halts the command.
 *
 * @package Automattic\Syndication\Tests
 */

/**
 * Thrown by the WP_CLI::error() stub in place of exiting.
 */
class WP_CLI_Stub_Error extends Exception {}

/**
 * Records WP-CLI output for assertions.
 */
class WP_CLI {

	/**
	 * Output recorded so far, as a list of array( type, message ) pairs.
	 *
	 * @var array
	 */
	public static $output = array();

	/**
	 * Commands are not dispatched in tests.
	 *
	 * @param string $name     Command name.
	 * @param mixed  $callable Command class.
	 */
	public static function add_command( $name, $callable ) {}

	/**
	 * Record an informational line.
	 *
	 * @param string $message Message.
	 */
	public static function log( $message ) {
		self::$output[] = array( 'log', $message );
	}

	/**
	 * Record a warning.
	 *
	 * @param string $message Message.
	 */
	public static function warning( $message ) {
		self::$output[] = array( 'warning', $message );
	}

	/**
	 * Record an error and halt, as the real command would.
	 *
	 * @param string $message Message.
	 * @throws WP_CLI_Stub_Error Always.
	 */
	public static function error( $message ) {
		self::$output[] = array( 'error', $message );
		throw new WP_CLI_Stub_Error( $message );
	}

	/**
	 * Messages of one type.
	 *
	 * @param string $type log, warning or error.
	 * @return string[]
	 */
	public static function messages( $type ) {
		$messages = array();
		foreach ( self::$output as $line ) {
			if ( $type === $line[0] ) {
				$messages[] = $line[1];
			}
		}
		return $messages;
	}
}

/**
 * Base class the command extends.
 */
class WP_CLI_Command {}
