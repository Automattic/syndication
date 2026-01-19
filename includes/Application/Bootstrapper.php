<?php
/**
 * Plugin bootstrapper.
 *
 * @package Automattic\Syndication\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application;

use Automattic\Syndication\Application\Services\PullService;
use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;

/**
 * Main plugin bootstrapper.
 *
 * Wires up the DI container and registers WordPress hooks for the
 * new DDD-based architecture. This class serves as the entry point
 * for the refactored syndication functionality.
 */
final class Bootstrapper {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * DI container.
	 *
	 * @var Container
	 */
	private readonly Container $container;

	/**
	 * Hook manager.
	 *
	 * @var HookManager
	 */
	private readonly HookManager $hooks;

	/**
	 * Hook registrar.
	 *
	 * @var HookRegistrar
	 */
	private readonly HookRegistrar $hook_registrar;

	/**
	 * Whether the bootstrapper has been initialised.
	 *
	 * @var bool
	 */
	private bool $initialised = false;

	/**
	 * Private constructor - use init() to get instance.
	 *
	 * @param Container $container DI container.
	 */
	private function __construct( Container $container ) {
		$this->container      = $container;
		$this->hooks          = $container->get( HookManager::class );
		$this->hook_registrar = new HookRegistrar( $container, $this->hooks );
	}

	/**
	 * Initialise the bootstrapper.
	 *
	 * @param Container|null $container Optional container for testing.
	 * @return self The singleton instance.
	 */
	public static function init( ?Container $container = null ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $container ?? new Container() );
		}

		if ( ! self::$instance->initialised ) {
			self::$instance->register_services();
			self::$instance->register_hooks();
			self::$instance->initialised = true;
		}

		return self::$instance;
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return self|null The instance or null if not initialised.
	 */
	public static function get_instance(): ?self {
		return self::$instance;
	}

	/**
	 * Reset the singleton instance (for testing).
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Get the DI container.
	 *
	 * @return Container The container.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Get the hook manager.
	 *
	 * @return HookManager The hook manager.
	 */
	public function hooks(): HookManager {
		return $this->hooks;
	}

	/**
	 * Register additional services in the container.
	 */
	private function register_services(): void {
		// Register PushService.
		$this->container->register(
			PushService::class,
			function ( Container $container ): PushService {
				$factory = $container->get( TransportFactoryInterface::class );
				\assert( $factory instanceof TransportFactoryInterface );
				return new PushService( $factory );
			}
		);

		// Register PullService.
		$this->container->register(
			PullService::class,
			function ( Container $container ): PullService {
				$factory = $container->get( TransportFactoryInterface::class );
				\assert( $factory instanceof TransportFactoryInterface );
				return new PullService( $factory );
			}
		);
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Note: These hooks run in parallel with the legacy hooks during
	 * migration. Once verified, the legacy code can be removed.
	 */
	private function register_hooks(): void {
		// Register hook to provide DI container to legacy code.
		$this->hooks->add_action(
			'syn_get_container',
			function (): Container {
				return $this->container;
			}
		);

		// Register all hooks via the HookRegistrar.
		$this->hook_registrar->register();
	}

	/**
	 * Get the hook registrar.
	 *
	 * @return HookRegistrar The hook registrar.
	 */
	public function hook_registrar(): HookRegistrar {
		return $this->hook_registrar;
	}

	/**
	 * Get the push service.
	 *
	 * @return PushService The push service.
	 */
	public function push_service(): PushService {
		$service = $this->container->get( PushService::class );
		\assert( $service instanceof PushService );
		return $service;
	}

	/**
	 * Get the pull service.
	 *
	 * @return PullService The pull service.
	 */
	public function pull_service(): PullService {
		$service = $this->container->get( PullService::class );
		\assert( $service instanceof PullService );
		return $service;
	}
}
