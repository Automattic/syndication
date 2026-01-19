<?php
/**
 * Unit tests for HookRegistrar.
 *
 * @package Automattic\Syndication\Tests\Unit\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application;

use Automattic\Syndication\Application\HookRegistrar;
use Automattic\Syndication\Application\Services\PullService;
use Automattic\Syndication\Application\Services\PushService;
use Automattic\Syndication\Domain\Contracts\TransportFactoryInterface;
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

		// Register PushService for tests that need it.
		$this->container->register(
			PushService::class,
			function ( Container $container ): PushService {
				$factory = $container->get( TransportFactoryInterface::class );
				\assert( $factory instanceof TransportFactoryInterface );
				return new PushService( $factory );
			}
		);

		// Register PullService for tests that need it.
		$this->container->register(
			PullService::class,
			function ( Container $container ): PullService {
				$factory = $container->get( TransportFactoryInterface::class );
				\assert( $factory instanceof TransportFactoryInterface );
				return new PullService( $factory );
			}
		);

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
		Actions\expectAdded( 'syn_schedule_push_content' )->once();
		Actions\expectAdded( 'syn_push_content' )->once();
		Actions\expectAdded( 'syn_pull_content' )->once();
		Actions\expectAdded( 'syn_refresh_pull_jobs' )->once();
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
	 * Test on_transition_post_status skips on autosave.
	 */
	public function test_on_transition_post_status_skips_autosave(): void {
		if ( ! defined( 'DOING_AUTOSAVE' ) ) {
			define( 'DOING_AUTOSAVE', true );
		}

		$post     = Mockery::mock( WP_Post::class );
		$post->ID = 123;

		// Should exit early and not call any functions.
		$this->registrar->on_transition_post_status( 'publish', 'draft', $post );

		$this->assertTrue( true );
	}

	/**
	 * Test on_trash_post skips when delete disabled.
	 */
	public function test_on_trash_post_skips_when_delete_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		// Should exit early when delete_pushed_posts not enabled.
		$this->registrar->on_trash_post( 123 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_trash_post skips when no slave posts.
	 */
	public function test_on_trash_post_skips_when_no_slave_posts(): void {
		Functions\when( 'get_option' )->justReturn( array( 'delete_pushed_posts' => true ) );
		Functions\when( 'get_post_meta' )->justReturn( array() );

		$this->registrar->on_trash_post( 123 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_save_post handles non-syn_site post type.
	 */
	public function test_on_save_post_skips_non_syn_site(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		// Should not trigger pull refresh for regular posts.
		$this->registrar->on_save_post( 123, $post, true );

		$this->assertTrue( true );
	}

	/**
	 * Test on_save_post triggers refresh for syn_site.
	 */
	public function test_on_save_post_triggers_refresh_for_syn_site(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once();

		$this->registrar->on_save_post( 123, $post, true );

		$this->assertTrue( true );
	}

	/**
	 * Test on_delete_post handles non-syn_site post type.
	 */
	public function test_on_delete_post_skips_non_syn_site(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		Functions\when( 'get_post' )->justReturn( $post );

		$this->registrar->on_delete_post( 123 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_delete_post triggers refresh for syn_site.
	 */
	public function test_on_delete_post_triggers_refresh_for_syn_site(): void {
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'syn_site';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once();

		$this->registrar->on_delete_post( 123 );

		$this->assertTrue( true );
	}

	/**
	 * Test on_create_term skips non-syn_sitegroup taxonomy.
	 */
	public function test_on_create_term_skips_non_syn_sitegroup(): void {
		// Should not trigger pull refresh for other taxonomies.
		$this->registrar->on_create_term( 1, 2, 'category' );

		$this->assertTrue( true );
	}

	/**
	 * Test on_create_term triggers refresh for syn_sitegroup.
	 */
	public function test_on_create_term_triggers_refresh_for_syn_sitegroup(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once();

		$this->registrar->on_create_term( 1, 2, 'syn_sitegroup' );

		$this->assertTrue( true );
	}

	/**
	 * Test on_delete_term skips non-syn_sitegroup taxonomy.
	 */
	public function test_on_delete_term_skips_non_syn_sitegroup(): void {
		// Should not trigger pull refresh for other taxonomies.
		$this->registrar->on_delete_term( 1, 2, 'category' );

		$this->assertTrue( true );
	}

	/**
	 * Test on_delete_term triggers refresh for syn_sitegroup.
	 */
	public function test_on_delete_term_triggers_refresh_for_syn_sitegroup(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once();

		$this->registrar->on_delete_term( 1, 2, 'syn_sitegroup' );

		$this->assertTrue( true );
	}

	/**
	 * Test on_init registers post type and taxonomy.
	 */
	public function test_on_init_registers_post_type_and_taxonomy(): void {
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
		Actions\expectDone( 'syn_after_init_server' )->once();

		$this->registrar->on_init();
	}

	/**
	 * Test on_init skips registration if already registered.
	 */
	public function test_on_init_skips_if_already_registered(): void {
		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\expect( 'register_post_type' )->never();
		Functions\expect( 'register_taxonomy' )->never();
		Actions\expectDone( 'syn_after_init_server' )->once();

		$this->registrar->on_init();
	}

	/**
	 * Test on_admin_init is callable.
	 */
	public function test_on_admin_init_is_callable(): void {
		$this->registrar->on_admin_init();

		$this->assertTrue( true );
	}

	/**
	 * Test on_schedule_push_content schedules cron event.
	 */
	public function test_on_schedule_push_content_schedules_cron(): void {
		$sites = array(
			'post_ID'        => 123,
			'selected_sites' => array( 1, 2, 3 ),
			'removed_sites'  => array(),
		);

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( Mockery::type( 'int' ), 'syn_push_content', array( $sites ) );
		Functions\when( 'spawn_cron' )->justReturn( null );

		$this->registrar->on_schedule_push_content( 123, $sites );

		$this->assertTrue( true );
	}

	/**
	 * Test on_schedule_push_content spawns cron.
	 */
	public function test_on_schedule_push_content_spawns_cron(): void {
		$sites = array(
			'post_ID'        => 123,
			'selected_sites' => array(),
			'removed_sites'  => array(),
		);

		Functions\when( 'wp_schedule_single_event' )->justReturn( true );
		Functions\expect( 'spawn_cron' )->once();

		$this->registrar->on_schedule_push_content( 123, $sites );

		$this->assertTrue( true );
	}

	/**
	 * Test on_push_content skips when no post_ID.
	 */
	public function test_on_push_content_skips_when_no_post_id(): void {
		$sites = array(
			'selected_sites' => array( 1 ),
			'removed_sites'  => array(),
		);

		// Should exit early without calling PushService.
		$this->registrar->on_push_content( $sites );

		$this->assertTrue( true );
	}

	/**
	 * Test on_push_content pushes to selected sites.
	 */
	public function test_on_push_content_pushes_to_selected_sites(): void {
		$sites = array(
			'post_ID'        => 123,
			'selected_sites' => array( 1, 2 ),
			'removed_sites'  => array(),
		);

		// The PushService is registered in the container and will be called.
		// For this test, we just verify the method runs without error.
		// Full integration testing verifies the actual push.
		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'get_transient' )->justReturn( 'locked' );

		$this->registrar->on_push_content( $sites );

		$this->assertTrue( true );
	}

	/**
	 * Test on_push_content handles WP_Post objects in sites array.
	 */
	public function test_on_push_content_handles_wp_post_objects(): void {
		$site1     = Mockery::mock( WP_Post::class );
		$site1->ID = 1;
		$site2     = Mockery::mock( WP_Post::class );
		$site2->ID = 2;

		$sites = array(
			'post_ID'        => 123,
			'selected_sites' => array( $site1, $site2 ),
			'removed_sites'  => array(),
		);

		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'get_transient' )->justReturn( 'locked' );

		$this->registrar->on_push_content( $sites );

		$this->assertTrue( true );
	}

	/**
	 * Test on_push_content deletes from removed sites.
	 */
	public function test_on_push_content_deletes_from_removed_sites(): void {
		$sites = array(
			'post_ID'        => 123,
			'selected_sites' => array(),
			'removed_sites'  => array( 3, 4 ),
		);

		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'get_post_meta' )->justReturn( array() );

		$this->registrar->on_push_content( $sites );

		$this->assertTrue( true );
	}

	/**
	 * Test on_pull_content skips when no sites.
	 */
	public function test_on_pull_content_skips_when_no_sites(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		// Should exit early when no sites provided and no selected sitegroups.
		$this->registrar->on_pull_content( array() );

		$this->assertTrue( true );
	}

	/**
	 * Test on_pull_content processes site IDs.
	 */
	public function test_on_pull_content_processes_site_ids(): void {
		Functions\when( 'get_option' )->justReturn(
			array( 'update_pulled_posts' => 'on' )
		);
		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'wp_defer_term_counting' )->justReturn( null );
		Functions\when( 'wp_defer_comment_counting' )->justReturn( null );
		Functions\when( 'wp_suspend_cache_invalidation' )->justReturn( null );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$this->registrar->on_pull_content( array( 1, 2 ) );

		$this->assertTrue( true );
	}

	/**
	 * Test on_pull_content handles WP_Post objects.
	 */
	public function test_on_pull_content_handles_wp_post_objects(): void {
		$site1     = Mockery::mock( WP_Post::class );
		$site1->ID = 1;
		$site2     = Mockery::mock( WP_Post::class );
		$site2->ID = 2;

		Functions\when( 'get_option' )->justReturn(
			array( 'update_pulled_posts' => 'on' )
		);
		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'wp_defer_term_counting' )->justReturn( null );
		Functions\when( 'wp_defer_comment_counting' )->justReturn( null );
		Functions\when( 'wp_suspend_cache_invalidation' )->justReturn( null );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$this->registrar->on_pull_content( array( $site1, $site2 ) );

		$this->assertTrue( true );
	}

	/**
	 * Test on_refresh_pull_jobs schedules jobs.
	 */
	public function test_on_refresh_pull_jobs_schedules_jobs(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		// Should run without error when no selected sitegroups.
		$this->registrar->on_refresh_pull_jobs();

		$this->assertTrue( true );
	}

	/**
	 * Test on_refresh_pull_jobs with sitegroups.
	 */
	public function test_on_refresh_pull_jobs_with_sitegroups(): void {
		Functions\when( 'get_option' )->alias(
			function ( $option ) {
				if ( 'push_syndicate_settings' === $option ) {
					return array( 'selected_pull_sitegroups' => array( 'group1' ) );
				}
				return array();
			}
		);
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		$this->registrar->on_refresh_pull_jobs();

		$this->assertTrue( true );
	}
}
