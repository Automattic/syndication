<?php
/**
 * Unit tests for HookRegistrar.
 *
 * @package Automattic\Syndication\Tests\Unit\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application;

use Automattic\Syndication\Application\HookRegistrar;
use Automattic\Syndication\Infrastructure\DI\Container;
use Automattic\Syndication\Infrastructure\WordPress\HookManager;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use WP_Post;

/**
 * Test case for HookRegistrar.
 *
 * @group unit
 * @covers \Automattic\Syndication\Application\HookRegistrar
 */
class HookRegistrarTest extends TestCase {

	/**
	 * HookRegistrar instance.
	 *
	 * @var HookRegistrar
	 */
	private HookRegistrar $registrar;

	/**
	 * Mock container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Hook manager.
	 *
	 * @var HookManager
	 */
	private HookManager $hooks;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->container = new Container();
		$this->hooks     = new HookManager();
		$this->registrar = new HookRegistrar( $this->container, $this->hooks );
	}

	/**
	 * Test register adds all required hooks.
	 */
	public function test_register_adds_all_hooks(): void {
		Actions\expectAdded( 'init' )->once();
		Actions\expectAdded( 'admin_init' )->once();
		Actions\expectAdded( 'transition_post_status' )->once();
		Actions\expectAdded( 'wp_trash_post' )->once();
		Filters\expectAdded( 'cron_schedules' )->once();
		Actions\expectAdded( 'save_post' )->once();
		Actions\expectAdded( 'delete_post' )->once();
		Actions\expectAdded( 'create_term' )->once();
		Actions\expectAdded( 'delete_term' )->once();

		$this->registrar->register();
	}

	/**
	 * Test on_cron_schedules adds custom interval.
	 */
	public function test_on_cron_schedules_adds_interval(): void {
		Functions\when( 'get_option' )->justReturn(
			array( 'pull_time_interval' => 7200 )
		);
		Functions\when( '__' )->returnArg( 1 );

		$schedules = $this->registrar->on_cron_schedules( array() );

		$this->assertArrayHasKey( 'syn_pull_time_interval', $schedules );
		$this->assertEquals( 7200, $schedules['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Test on_cron_schedules uses default interval.
	 */
	public function test_on_cron_schedules_uses_default_interval(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );

		$schedules = $this->registrar->on_cron_schedules( array() );

		$this->assertEquals( 3600, $schedules['syn_pull_time_interval']['interval'] );
	}

	/**
	 * Test on_cron_schedules preserves existing schedules.
	 */
	public function test_on_cron_schedules_preserves_existing(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );

		$existing  = array(
			'hourly' => array(
				'interval' => 3600,
				'display'  => 'Hourly',
			),
		);
		$schedules = $this->registrar->on_cron_schedules( $existing );

		$this->assertArrayHasKey( 'hourly', $schedules );
		$this->assertArrayHasKey( 'syn_pull_time_interval', $schedules );
	}

	/**
	 * Test on_transition_post_status is callable.
	 */
	public function test_on_transition_post_status_is_callable(): void {
		$post     = Mockery::mock( WP_Post::class );
		$post->ID = 123;

		// Should not throw.
		$this->registrar->on_transition_post_status( 'publish', 'draft', $post );

		$this->assertTrue( true );
	}

	/**
	 * Test on_trash_post is callable.
	 */
	public function test_on_trash_post_is_callable(): void {
		$this->registrar->on_trash_post( 123 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_save_post is callable.
	 */
	public function test_on_save_post_is_callable(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		$this->registrar->on_save_post( 123, $post, true );

		$this->assertTrue( true );
	}

	/**
	 * Test on_delete_post is callable.
	 */
	public function test_on_delete_post_is_callable(): void {
		$this->registrar->on_delete_post( 123 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_create_term is callable.
	 */
	public function test_on_create_term_is_callable(): void {
		$this->registrar->on_create_term( 1, 2, 'syn_sitegroup' );

		$this->assertTrue( true );
	}

	/**
	 * Test on_delete_term is callable.
	 */
	public function test_on_delete_term_is_callable(): void {
		$this->registrar->on_delete_term( 1, 2, 'syn_sitegroup' );

		$this->assertTrue( true );
	}

	/**
	 * Test on_init is callable.
	 */
	public function test_on_init_is_callable(): void {
		$this->registrar->on_init();

		$this->assertTrue( true );
	}

	/**
	 * Test on_admin_init is callable.
	 */
	public function test_on_admin_init_is_callable(): void {
		$this->registrar->on_admin_init();

		$this->assertTrue( true );
	}
}
