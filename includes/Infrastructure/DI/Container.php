<?php
/**
 * Syndication Dependency Injection Container.
 *
 * @package Automattic\Syndication\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\DI;

/**
 * Simple dependency injection container for Syndication services.
 *
 * This container handles service registration and resolution without
 * falling into the service locator anti-pattern. Services are only
 * resolved in factory methods, not injected into business logic classes.
 */
final class Container {

	/**
	 * Registered services and their factories.
	 *
	 * @var array<string, callable>
	 */
	private array $services = array();

	/**
	 * Resolved service instances (singletons).
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_default_services();
	}

	/**
	 * Register a service with a factory function.
	 *
	 * @param string   $id      Service identifier (usually class name).
	 * @param callable $factory Factory function that creates the service.
	 */
	public function register( string $id, callable $factory ): void {
		$this->services[ $id ] = $factory;
	}

	/**
	 * Get a service by its identifier.
	 *
	 * @param string $id Service identifier.
	 * @return object The service instance.
	 * @throws \InvalidArgumentException If service is not registered.
	 */
	public function get( string $id ): object {
		// Return cached instance if available.
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check if service is registered.
		if ( ! isset( $this->services[ $id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message not output to browser.
			throw new \InvalidArgumentException( "Service '{$id}' is not registered." );
		}

		// Create and cache the instance.
		$instance               = $this->services[ $id ]( $this );
		$this->instances[ $id ] = $instance;

		return $instance;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool True if registered, false otherwise.
	 */
	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}

	/**
	 * Get all services that implement a specific interface.
	 *
	 * @param string $interface_name The interface class name to filter by.
	 * @return array<string, object> Array of service ID => instance pairs.
	 */
	public function get_services_by_interface( string $interface_name ): array {
		$matching_services = array();

		foreach ( array_keys( $this->services ) as $service_id ) {
			try {
				$instance = $this->get( $service_id );
				if ( $instance instanceof $interface_name ) {
					$matching_services[ $service_id ] = $instance;
				}
			} catch ( \Exception $e ) {
				// Skip services that can't be instantiated in the current context.
				continue;
			} catch ( \Error $e ) {
				// Skip services that can't be instantiated due to missing dependencies.
				continue;
			}
		}

		return $matching_services;
	}

	/**
	 * Register all default services.
	 *
	 * Services are added here as we implement them. The container starts
	 * minimal and grows with the refactoring progress.
	 */
	private function register_default_services(): void {
		// Services will be registered here as they are implemented during the refactor.
	}
}
