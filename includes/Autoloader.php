<?php
/**
 * PSR-4 Autoloader for Syndication Plugin.
 *
 * @package Automattic\Syndication
 */

declare( strict_types=1 );

/**
 * Simple PSR-4 autoloader for the Syndication plugin.
 *
 * Handles autoloading for classes in the Automattic\Syndication namespace.
 */
final class Syndication_Autoloader {

	/**
	 * PSR-4 namespace to directory mappings.
	 *
	 * @var array<string, string>
	 */
	private static array $namespaces = array();

	/**
	 * Whether the autoloader is registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the autoloader.
	 *
	 * @param string $plugin_dir The plugin directory path.
	 */
	public static function register( string $plugin_dir ): void {
		if ( self::$registered ) {
			return;
		}

		// Register PSR-4 namespaces.
		self::$namespaces = array(
			'Automattic\\Syndication\\' => $plugin_dir . '/includes/',
		);

		// Register the autoloader.
		spl_autoload_register( array( __CLASS__, 'autoload' ), true, true );

		self::$registered = true;
	}

	/**
	 * Unregister the autoloader.
	 */
	public static function unregister(): void {
		if ( ! self::$registered ) {
			return;
		}

		spl_autoload_unregister( array( __CLASS__, 'autoload' ) );
		self::$registered = false;
	}

	/**
	 * Autoload a class.
	 *
	 * @param string $class_name The fully qualified class name.
	 */
	public static function autoload( string $class_name ): void {
		// Handle PSR-4 namespaced classes.
		foreach ( self::$namespaces as $namespace => $base_dir ) {
			if ( 0 === strpos( $class_name, $namespace ) ) {
				$relative_class = substr( $class_name, strlen( $namespace ) );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

				if ( is_readable( $file ) ) {
					require_once $file;
					return;
				}
			}
		}
	}

	/**
	 * Check if a class can be autoloaded.
	 *
	 * @param string $class_name The fully qualified class name.
	 * @return bool True if the class can be autoloaded, false otherwise.
	 */
	public static function can_autoload( string $class_name ): bool {
		foreach ( self::$namespaces as $namespace => $base_dir ) {
			if ( 0 === strpos( $class_name, $namespace ) ) {
				$relative_class = substr( $class_name, strlen( $namespace ) );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
				return is_readable( $file );
			}
		}

		return false;
	}

	/**
	 * Get all registered namespaces.
	 *
	 * @return array<string, string> Namespace to directory mappings.
	 */
	public static function get_namespaces(): array {
		return self::$namespaces;
	}
}
