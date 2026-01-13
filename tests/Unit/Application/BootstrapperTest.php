<?php
/**
 * Unit tests for Bootstrapper.
 *
 * @package Automattic\Syndication\Tests\Unit\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application;

use Automattic\Syndication\Application\Bootstrapper;
use Automattic\Syndication\Application\Services\PullService;
use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

/**
 * Test case for Bootstrapper.
 *
 * @group unit
 * @covers \Automattic\Syndication\Application\Bootstrapper
 */
class BootstrapperTest extends TestCase {

	/**
	 * Reset the singleton before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Bootstrapper::reset();
	}

	/**
	 * Reset after each test.
	 */
	protected function tearDown(): void {
		Bootstrapper::reset();
		parent::tearDown();
	}

	/**
	 * Test init creates singleton instance.
	 */
	public function test_init_creates_singleton(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init();

		$this->assertInstanceOf( Bootstrapper::class, $bootstrapper );
		$this->assertSame( $bootstrapper, Bootstrapper::get_instance() );
	}

	/**
	 * Test init returns same instance on subsequent calls.
	 */
	public function test_init_returns_same_instance(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$first  = Bootstrapper::init();
		$second = Bootstrapper::init();

		$this->assertSame( $first, $second );
	}

	/**
	 * Test get_instance returns null before init.
	 */
	public function test_get_instance_returns_null_before_init(): void {
		$this->assertNull( Bootstrapper::get_instance() );
	}

	/**
	 * Test reset clears the singleton.
	 */
	public function test_reset_clears_singleton(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		Bootstrapper::init();
		Bootstrapper::reset();

		$this->assertNull( Bootstrapper::get_instance() );
	}

	/**
	 * Test container returns DI container.
	 */
	public function test_container_returns_di_container(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init();
		$container    = $bootstrapper->container();

		$this->assertInstanceOf( Container::class, $container );
	}

	/**
	 * Test hooks returns hook manager.
	 */
	public function test_hooks_returns_hook_manager(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init();
		$hooks        = $bootstrapper->hooks();

		$this->assertInstanceOf( HookManager::class, $hooks );
	}

	/**
	 * Test push_service returns PushService.
	 */
	public function test_push_service_returns_push_service(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init();
		$service      = $bootstrapper->push_service();

		$this->assertInstanceOf( PushService::class, $service );
	}

	/**
	 * Test pull_service returns PullService.
	 */
	public function test_pull_service_returns_pull_service(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init();
		$service      = $bootstrapper->pull_service();

		$this->assertInstanceOf( PullService::class, $service );
	}

	/**
	 * Test init accepts custom container.
	 */
	public function test_init_accepts_custom_container(): void {
		$container = new Container();
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init( $container );

		$this->assertSame( $container, $bootstrapper->container() );
	}

	/**
	 * Test services are registered in container.
	 */
	public function test_services_are_registered(): void {
		Actions\expectAdded( 'syn_get_container' )->once();

		$bootstrapper = Bootstrapper::init();
		$container    = $bootstrapper->container();

		$this->assertTrue( $container->has( PushService::class ) );
		$this->assertTrue( $container->has( PullService::class ) );
	}

	/**
	 * Test syn_get_container hook is registered.
	 */
	public function test_syn_get_container_hook_registered(): void {
		Actions\expectAdded( 'syn_get_container' )
			->once()
			->with( \Mockery::type( 'callable' ), 10, 1 );

		Bootstrapper::init();
	}
}
