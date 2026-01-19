<?php
/**
 * Unit tests for Container.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\DI;

use Automattic\Syndication\Domain\Contracts\EncryptorInterface;
use Automattic\Syndication\Domain\Contracts\SiteRepositoryInterface;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\Encryption\OpenSSLEncryptor;
use Automattic\Syndication\Infrastructure\Repositories\SiteRepository;
use Automattic\Syndication\Infrastructure\Transport\TransportFactory;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;
use Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar;
use Automattic\Syndication\Tests\Unit\TestCase;

/**
 * Test case for Container.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\DI\Container
 */
class ContainerTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->container = new Container();
	}

	/**
	 * Test register adds service.
	 */
	public function test_register_adds_service(): void {
		$this->container->register(
			'test_service',
			static function (): \stdClass {
				return new \stdClass();
			}
		);

		$this->assertTrue( $this->container->has( 'test_service' ) );
	}

	/**
	 * Test get returns service instance.
	 */
	public function test_get_returns_service(): void {
		$this->container->register(
			'test_service',
			static function (): \stdClass {
				$obj       = new \stdClass();
				$obj->name = 'test';
				return $obj;
			}
		);

		$service = $this->container->get( 'test_service' );

		$this->assertInstanceOf( \stdClass::class, $service );
		$this->assertSame( 'test', $service->name );
	}

	/**
	 * Test get returns same instance on subsequent calls.
	 */
	public function test_get_returns_same_instance(): void {
		$this->container->register(
			'test_service',
			static function (): \stdClass {
				return new \stdClass();
			}
		);

		$first  = $this->container->get( 'test_service' );
		$second = $this->container->get( 'test_service' );

		$this->assertSame( $first, $second );
	}

	/**
	 * Test get throws for unregistered service.
	 */
	public function test_get_throws_for_unregistered(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "Service 'unknown_service' is not registered." );

		$this->container->get( 'unknown_service' );
	}

	/**
	 * Test has returns false for unregistered service.
	 */
	public function test_has_returns_false_for_unregistered(): void {
		$this->assertFalse( $this->container->has( 'unknown_service' ) );
	}

	/**
	 * Test factory receives container.
	 */
	public function test_factory_receives_container(): void {
		$received_container = null;

		$this->container->register(
			'test_service',
			static function ( Container $container ) use ( &$received_container ): \stdClass {
				$received_container = $container;
				return new \stdClass();
			}
		);

		$this->container->get( 'test_service' );

		$this->assertSame( $this->container, $received_container );
	}

	/**
	 * Test get_services_by_interface returns matching services.
	 */
	public function test_get_services_by_interface_returns_matching(): void {
		$this->container->register(
			'matching1',
			static function (): \ArrayObject {
				return new \ArrayObject();
			}
		);
		$this->container->register(
			'matching2',
			static function (): \ArrayIterator {
				return new \ArrayIterator();
			}
		);
		$this->container->register(
			'not_matching',
			static function (): \stdClass {
				return new \stdClass();
			}
		);

		$services = $this->container->get_services_by_interface( \Traversable::class );

		$this->assertCount( 2, $services );
		$this->assertArrayHasKey( 'matching1', $services );
		$this->assertArrayHasKey( 'matching2', $services );
	}

	/**
	 * Test get_services_by_interface returns empty array when none match.
	 */
	public function test_get_services_by_interface_returns_empty_when_none_match(): void {
		$this->container->register(
			'service',
			static function (): \stdClass {
				return new \stdClass();
			}
		);

		$services = $this->container->get_services_by_interface( \Traversable::class );

		$this->assertEmpty( $services );
	}

	/**
	 * Test default services are registered.
	 */
	public function test_default_services_are_registered(): void {
		$this->assertTrue( $this->container->has( EncryptorInterface::class ) );
		$this->assertTrue( $this->container->has( SiteRepositoryInterface::class ) );
		$this->assertTrue( $this->container->has( HookManager::class ) );
		$this->assertTrue( $this->container->has( TransportFactory::class ) );
		$this->assertTrue( $this->container->has( TransportFactoryInterface::class ) );
		$this->assertTrue( $this->container->has( PostTypeRegistrar::class ) );
	}

	/**
	 * Test EncryptorInterface resolves to OpenSSLEncryptor.
	 */
	public function test_encryptor_resolves_correctly(): void {
		$encryptor = $this->container->get( EncryptorInterface::class );

		$this->assertInstanceOf( OpenSSLEncryptor::class, $encryptor );
	}

	/**
	 * Test SiteRepositoryInterface resolves to SiteRepository.
	 */
	public function test_site_repository_resolves_correctly(): void {
		$repository = $this->container->get( SiteRepositoryInterface::class );

		$this->assertInstanceOf( SiteRepository::class, $repository );
	}

	/**
	 * Test HookManager resolves correctly.
	 */
	public function test_hook_manager_resolves_correctly(): void {
		$hooks = $this->container->get( HookManager::class );

		$this->assertInstanceOf( HookManager::class, $hooks );
	}

	/**
	 * Test TransportFactory resolves correctly.
	 */
	public function test_transport_factory_resolves_correctly(): void {
		$factory = $this->container->get( TransportFactory::class );

		$this->assertInstanceOf( TransportFactory::class, $factory );
	}

	/**
	 * Test TransportFactoryInterface resolves to TransportFactory.
	 */
	public function test_transport_factory_interface_resolves_to_transport_factory(): void {
		$factory = $this->container->get( TransportFactoryInterface::class );

		$this->assertInstanceOf( TransportFactory::class, $factory );
	}

	/**
	 * Test PostTypeRegistrar resolves correctly.
	 */
	public function test_post_type_registrar_resolves_correctly(): void {
		$registrar = $this->container->get( PostTypeRegistrar::class );

		$this->assertInstanceOf( PostTypeRegistrar::class, $registrar );
	}

	/**
	 * Test register overwrites existing service.
	 */
	public function test_register_overwrites_existing(): void {
		$this->container->register(
			'test_service',
			static function (): \stdClass {
				$obj       = new \stdClass();
				$obj->name = 'original';
				return $obj;
			}
		);

		// Get the original to cache it.
		$original = $this->container->get( 'test_service' );
		$this->assertSame( 'original', $original->name );

		// Register new factory - this won't affect cached instance.
		$this->container->register(
			'test_service',
			static function (): \stdClass {
				$obj       = new \stdClass();
				$obj->name = 'overwritten';
				return $obj;
			}
		);

		// Still returns cached instance.
		$this->assertSame( 'original', $this->container->get( 'test_service' )->name );
	}
}
