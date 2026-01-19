<?php
/**
 * Integration tests for Bootstrapper.
 *
 * @package Automattic\Syndication\Tests\Integration\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\Application;

use Automattic\Syndication\Application\Bootstrapper;
use Automattic\Syndication\Application\HookRegistrar;
use Automattic\Syndication\Application\Services\PullService;
use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;
use Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Integration tests for Bootstrapper.
 *
 * Verifies that the new architecture bootstraps correctly within WordPress.
 *
 * @group integration
 * @covers \Automattic\Syndication\Application\Bootstrapper
 */
class BootstrapperIntegrationTest extends WPIntegrationTestCase {

	/**
	 * Test bootstrapper is initialised by plugin.
	 */
	public function test_bootstrapper_is_initialised(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertInstanceOf( Bootstrapper::class, $bootstrapper );
	}

	/**
	 * Test container is available.
	 */
	public function test_container_is_available(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertInstanceOf( Container::class, $bootstrapper->container() );
	}

	/**
	 * Test hook manager is available.
	 */
	public function test_hook_manager_is_available(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertInstanceOf( HookManager::class, $bootstrapper->hooks() );
	}

	/**
	 * Test hook registrar is available.
	 */
	public function test_hook_registrar_is_available(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertInstanceOf( HookRegistrar::class, $bootstrapper->hook_registrar() );
	}

	/**
	 * Test push service is available.
	 */
	public function test_push_service_is_available(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertInstanceOf( PushService::class, $bootstrapper->push_service() );
	}

	/**
	 * Test pull service is available.
	 */
	public function test_pull_service_is_available(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertInstanceOf( PullService::class, $bootstrapper->pull_service() );
	}

	/**
	 * Test syndication_container helper function exists.
	 */
	public function test_syndication_container_helper_exists(): void {
		$this->assertTrue( function_exists( 'syndication_container' ) );
	}

	/**
	 * Test syndication_container returns container.
	 */
	public function test_syndication_container_returns_container(): void {
		$container = syndication_container();

		$this->assertInstanceOf( Container::class, $container );
	}

	/**
	 * Test syndication_container returns same container as bootstrapper.
	 */
	public function test_syndication_container_returns_bootstrapper_container(): void {
		$bootstrapper = Bootstrapper::get_instance();

		$this->assertSame(
			$bootstrapper->container(),
			syndication_container()
		);
	}

	/**
	 * Test global bootstrapper variable is set.
	 */
	public function test_global_bootstrapper_variable_is_set(): void {
		$this->assertArrayHasKey( 'syndication_bootstrapper', $GLOBALS );
		$this->assertInstanceOf( Bootstrapper::class, $GLOBALS['syndication_bootstrapper'] );
	}

	/**
	 * Test container has required services registered.
	 */
	public function test_container_has_required_services(): void {
		$container = syndication_container();

		$this->assertTrue( $container->has( PushService::class ) );
		$this->assertTrue( $container->has( PullService::class ) );
		$this->assertTrue( $container->has( HookManager::class ) );
		$this->assertTrue( $container->has( PostTypeRegistrar::class ) );
	}
}
