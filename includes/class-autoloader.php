<?php
/**
 * Autoloader
 *
 * Very simple autoloader that uses WordPress-style paths and file names. If
 * given a class like \My_Namespace\My_Subnamespace\My_Class it will try to
 * load the file at my-namespace/my-subnamespace/class-my-class.php.
 *
 * Will also load all functions-*.php files in the root path.
 */

namespace Automattic\Syndication;

class Autoloader
{
	/**
	 * Namespaces mapped to their root paths.
	 *
	 * @var array
	 */
	private static $_ns_to_path = array();
	
	/**
	 * Whether or not the autoload function has been registered.
	 *
	 * @var bool
	 */
	private static $_registered = false;

	/**
	 * Register the autoloader.
	 *
	 * @param string $namespace The namespace this auto loader will handle.
	 * @param string $path The path where class files for classes in this
	 * namespace can be found.
	 */
	public static function register_namespace( $namespace, $path )
	{
		// Since namespaces and classes are case insensitive, convert everything
		// to lowercase for comparison purposes. 
		$namespace = untrailingslashit( strtolower( $namespace ) );
		$path = untrailingslashit( $path );
		
		if ( ! array_key_exists( $namespace, self::$_ns_to_path ) ) {
			self::$_ns_to_path[ $namespace ] = $path;
		}
		
		if ( ! self::$_registered ) {
			spl_autoload_register( array( __CLASS__, 'autoload' ) );
			self::$_registered = true;
		}

		// Load all functions files in the namespace root. These can't be
		// autoloaded because they don't contain classes.
		foreach ( glob( $path . '/functions-*.php' ) as $file ) {
			include $file;
		}
	}
	
	/**
	 * Attempt to autoload the given class.
	 *
	 * @param string $class
	 */
	public static function autoload( $class )
	{
		$parsed_class = self::_parse_class( $class );
		
		if ( ! $parsed_class ) {
			return;
		}
		
		// Build the path.
		$parsed_class['remaining_parts'] = str_replace( '_', '-', $parsed_class['remaining_parts'] );

		$path = $parsed_class['path'] . '/';
		
		if ( 1 < count( $parsed_class['remaining_parts'] ) ) {
			$path .= implode( '/', array_slice( $parsed_class['remaining_parts'], 0, -1 ) ) . '/';
		}

		foreach ( array( 'class', 'interface', 'abstract', 'trait' ) as $prefix ) {
			$test_path = $path . $prefix . '-' . end( $parsed_class['remaining_parts'] ) . '.php';

			if ( is_readable( $test_path ) ) {
				include $test_path;
				break;
			}
		}
	}
	
	/**
	 * Break the class into pieces and use those to find the most appropriate
	 * namespace.
	 *
	 * @param string $class
	 * @return array
	 */
	private static function _parse_class( $class )
	{
		$class = strtolower( $class );
		$parts = explode( '\\', $class );
		
		for ( $i = count( $parts ) - 1; $i; $i-- ) {
			$needle = implode( '\\', array_slice( $parts, 0, $i ) );
			
			if ( array_key_exists( $needle, self::$_ns_to_path ) ) {
				return array(
					'namespace' => $needle,
					'path' => self::$_ns_to_path[ $needle ],
					'remaining_parts' => array_slice( $parts, $i ),
				);
			}
		}
	}
}