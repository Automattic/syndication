<?php
/**
 * Unit tests for PostTypeRegistrar.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\WordPress;

use Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test case for PostTypeRegistrar.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\WordPress\PostTypeRegistrar
 */
class PostTypeRegistrarTest extends TestCase {

	/**
	 * PostTypeRegistrar instance.
	 *
	 * @var PostTypeRegistrar
	 */
	private PostTypeRegistrar $registrar;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->registrar = new PostTypeRegistrar();
	}

	/**
	 * Test register calls both post type and taxonomy registration.
	 */
	public function test_register_registers_post_type_and_taxonomy(): void {
		Functions\when( 'post_type_exists' )->justReturn( false );
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\expect( 'register_post_type' )
			->once()
			->with( 'syn_site', Mockery::type( 'array' ) );
		Functions\expect( 'register_taxonomy' )
			->once()
			->with( 'syn_sitegroup', 'syn_site', Mockery::type( 'array' ) );

		$this->registrar->register();
	}

	/**
	 * Test register skips post type if already exists.
	 */
	public function test_register_skips_post_type_if_exists(): void {
		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\expect( 'register_post_type' )->never();
		Functions\expect( 'register_taxonomy' )
			->once()
			->with( 'syn_sitegroup', 'syn_site', Mockery::type( 'array' ) );

		$this->registrar->register();
	}

	/**
	 * Test register skips taxonomy if already exists.
	 */
	public function test_register_skips_taxonomy_if_exists(): void {
		Functions\when( 'post_type_exists' )->justReturn( false );
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\expect( 'register_post_type' )
			->once()
			->with( 'syn_site', Mockery::type( 'array' ) );
		Functions\expect( 'register_taxonomy' )->never();

		$this->registrar->register();
	}

	/**
	 * Test register skips both if already registered.
	 */
	public function test_register_skips_both_if_exist(): void {
		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\expect( 'register_post_type' )->never();
		Functions\expect( 'register_taxonomy' )->never();

		$this->registrar->register();
	}

	/**
	 * Test post type uses filtered capability.
	 */
	public function test_register_uses_filtered_capability(): void {
		Functions\when( 'post_type_exists' )->justReturn( false );
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( '__' )->returnArg( 1 );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'syn_syndicate_cap', 'manage_options' )
			->andReturn( 'custom_cap' );

		Functions\expect( 'register_post_type' )
			->once()
			->with(
				'syn_site',
				Mockery::on(
					function ( $args ) {
						return $args['capabilities']['edit_post'] === 'custom_cap';
					}
				)
			);

		$this->registrar->register();
	}

	/**
	 * Test constants are defined.
	 */
	public function test_constants_are_defined(): void {
		$this->assertSame( 'syn_site', PostTypeRegistrar::POST_TYPE );
		$this->assertSame( 'syn_sitegroup', PostTypeRegistrar::TAXONOMY );
		$this->assertSame( 'manage_options', PostTypeRegistrar::DEFAULT_CAPABILITY );
	}
}
