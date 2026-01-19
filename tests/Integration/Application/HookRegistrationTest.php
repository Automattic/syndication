<?php
/**
 * Integration tests for hook registration.
 *
 * @package Automattic\Syndication\Tests\Integration\Application
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration\Application;

use Automattic\Syndication\Application\Bootstrapper;
use Automattic\Syndication\Application\HookRegistrar;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Integration tests for hook registration.
 *
 * Verifies that all required WordPress hooks are registered by the new architecture.
 *
 * @group integration
 * @covers \Automattic\Syndication\Application\HookRegistrar
 */
class HookRegistrationTest extends WPIntegrationTestCase {

	/**
	 * Hook registrar instance.
	 *
	 * @var HookRegistrar
	 */
	private HookRegistrar $registrar;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->registrar = Bootstrapper::get_instance()->hook_registrar();
	}

	/**
	 * Test init hook is registered.
	 */
	public function test_init_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'init', array( $this->registrar, 'on_init' ) )
		);
	}

	/**
	 * Test admin_init hook is registered.
	 */
	public function test_admin_init_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'admin_init', array( $this->registrar, 'on_admin_init' ) )
		);
	}

	/**
	 * Test transition_post_status hook is registered.
	 */
	public function test_transition_post_status_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'transition_post_status', array( $this->registrar, 'on_transition_post_status' ) )
		);
	}

	/**
	 * Test wp_trash_post hook is registered.
	 */
	public function test_wp_trash_post_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'wp_trash_post', array( $this->registrar, 'on_trash_post' ) )
		);
	}

	/**
	 * Test cron_schedules filter is registered.
	 */
	public function test_cron_schedules_filter_is_registered(): void {
		$this->assertIsInt(
			has_filter( 'cron_schedules', array( $this->registrar, 'on_cron_schedules' ) )
		);
	}

	/**
	 * Test save_post hook is registered.
	 */
	public function test_save_post_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'save_post', array( $this->registrar, 'on_save_post' ) )
		);
	}

	/**
	 * Test delete_post hook is registered.
	 */
	public function test_delete_post_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'delete_post', array( $this->registrar, 'on_delete_post' ) )
		);
	}

	/**
	 * Test create_term hook is registered.
	 */
	public function test_create_term_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'create_term', array( $this->registrar, 'on_create_term' ) )
		);
	}

	/**
	 * Test delete_term hook is registered.
	 */
	public function test_delete_term_hook_is_registered(): void {
		$this->assertIsInt(
			has_action( 'delete_term', array( $this->registrar, 'on_delete_term' ) )
		);
	}

	/**
	 * Test syn_get_container hook is registered.
	 */
	public function test_syn_get_container_hook_is_registered(): void {
		$this->assertNotFalse( has_action( 'syn_get_container' ) );
	}

	/**
	 * Test cron_schedules filter adds syn_pull_time_interval.
	 */
	public function test_cron_schedules_adds_pull_interval(): void {
		$schedules = wp_get_schedules();

		$this->assertArrayHasKey( 'syn_pull_time_interval', $schedules );
		$this->assertArrayHasKey( 'interval', $schedules['syn_pull_time_interval'] );
		$this->assertArrayHasKey( 'display', $schedules['syn_pull_time_interval'] );
	}

	/**
	 * Test syn_after_init_server action fires.
	 */
	public function test_syn_after_init_server_fires(): void {
		// This action should have already fired during plugin load.
		// We can verify by checking it was fired at least once.
		$this->assertGreaterThan( 0, did_action( 'syn_after_init_server' ) );
	}
}
