<?php
/**
 * Unit tests for HookManager.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\WordPress;

use Automattic\Syndication\Infrastructure\WordPress\HookManager;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

/**
 * Test case for HookManager.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\WordPress\HookManager
 */
class HookManagerTest extends TestCase {

	/**
	 * HookManager instance.
	 *
	 * @var HookManager
	 */
	private HookManager $hook_manager;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->hook_manager = new HookManager();
	}

	/**
	 * Test add_action calls WordPress add_action.
	 */
	public function test_add_action_calls_wordpress_function(): void {
		$callback = static function (): void {};

		Actions\expectAdded( 'test_action' )
			->once()
			->with( $callback, 10, 1 );

		$result = $this->hook_manager->add_action( 'test_action', $callback );

		$this->assertSame( $this->hook_manager, $result );
	}

	/**
	 * Test add_action with custom priority and args.
	 */
	public function test_add_action_with_custom_priority(): void {
		$callback = static function (): void {};

		Actions\expectAdded( 'test_action' )
			->once()
			->with( $callback, 20, 3 );

		$this->hook_manager->add_action( 'test_action', $callback, 20, 3 );
	}

	/**
	 * Test add_filter calls WordPress add_filter.
	 */
	public function test_add_filter_calls_wordpress_function(): void {
		$callback = static function ( $value ) {
			return $value;
		};

		Filters\expectAdded( 'test_filter' )
			->once()
			->with( $callback, 10, 1 );

		$result = $this->hook_manager->add_filter( 'test_filter', $callback );

		$this->assertSame( $this->hook_manager, $result );
	}

	/**
	 * Test add_filter with custom priority and args.
	 */
	public function test_add_filter_with_custom_priority(): void {
		$callback = static function ( $value ) {
			return $value;
		};

		Filters\expectAdded( 'test_filter' )
			->once()
			->with( $callback, 15, 2 );

		$this->hook_manager->add_filter( 'test_filter', $callback, 15, 2 );
	}

	/**
	 * Test remove_action calls WordPress remove_action.
	 */
	public function test_remove_action_calls_wordpress_function(): void {
		$callback = static function (): void {};

		Functions\when( 'remove_action' )->justReturn( true );

		$result = $this->hook_manager->remove_action( 'test_action', $callback, 10 );

		$this->assertTrue( $result );
	}

	/**
	 * Test remove_filter calls WordPress remove_filter.
	 */
	public function test_remove_filter_calls_wordpress_function(): void {
		$callback = static function ( $value ) {
			return $value;
		};

		Functions\when( 'remove_filter' )->justReturn( true );

		$result = $this->hook_manager->remove_filter( 'test_filter', $callback, 10 );

		$this->assertTrue( $result );
	}

	/**
	 * Test do_action calls WordPress do_action.
	 */
	public function test_do_action_calls_wordpress_function(): void {
		Actions\expectDone( 'test_action' )
			->once()
			->with( 'arg1', 'arg2' );

		$this->hook_manager->do_action( 'test_action', 'arg1', 'arg2' );
	}

	/**
	 * Test apply_filters calls WordPress apply_filters.
	 */
	public function test_apply_filters_calls_wordpress_function(): void {
		Filters\expectApplied( 'test_filter' )
			->once()
			->with( 'original', 'extra_arg' )
			->andReturn( 'filtered' );

		$result = $this->hook_manager->apply_filters( 'test_filter', 'original', 'extra_arg' );

		$this->assertEquals( 'filtered', $result );
	}

	/**
	 * Test has_action checks for registered actions.
	 */
	public function test_has_action_returns_true_when_registered(): void {
		Functions\when( 'has_action' )->justReturn( 10 );

		$result = $this->hook_manager->has_action( 'test_action' );

		$this->assertTrue( $result );
	}

	/**
	 * Test has_action returns false when not registered.
	 */
	public function test_has_action_returns_false_when_not_registered(): void {
		Functions\when( 'has_action' )->justReturn( false );

		$result = $this->hook_manager->has_action( 'test_action' );

		$this->assertFalse( $result );
	}

	/**
	 * Test has_filter checks for registered filters.
	 */
	public function test_has_filter_returns_true_when_registered(): void {
		Functions\when( 'has_filter' )->justReturn( 10 );

		$result = $this->hook_manager->has_filter( 'test_filter' );

		$this->assertTrue( $result );
	}

	/**
	 * Test has_filter returns false when not registered.
	 */
	public function test_has_filter_returns_false_when_not_registered(): void {
		Functions\when( 'has_filter' )->justReturn( false );

		$result = $this->hook_manager->has_filter( 'test_filter' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_registered_actions returns tracked actions.
	 */
	public function test_get_registered_actions_returns_tracked_actions(): void {
		$callback = static function (): void {};

		Actions\expectAdded( 'tracked_action' )->once();

		$this->hook_manager->add_action( 'tracked_action', $callback, 15, 2 );

		$actions = $this->hook_manager->get_registered_actions();

		$this->assertArrayHasKey( 'tracked_action', $actions );
		$this->assertCount( 1, $actions['tracked_action'] );
		$this->assertEquals( 15, $actions['tracked_action'][0]['priority'] );
		$this->assertEquals( 2, $actions['tracked_action'][0]['args'] );
	}

	/**
	 * Test get_registered_filters returns tracked filters.
	 */
	public function test_get_registered_filters_returns_tracked_filters(): void {
		$callback = static function ( $value ) {
			return $value;
		};

		Filters\expectAdded( 'tracked_filter' )->once();

		$this->hook_manager->add_filter( 'tracked_filter', $callback, 20, 3 );

		$filters = $this->hook_manager->get_registered_filters();

		$this->assertArrayHasKey( 'tracked_filter', $filters );
		$this->assertCount( 1, $filters['tracked_filter'] );
		$this->assertEquals( 20, $filters['tracked_filter'][0]['priority'] );
		$this->assertEquals( 3, $filters['tracked_filter'][0]['args'] );
	}

	/**
	 * Test method chaining works.
	 */
	public function test_method_chaining(): void {
		$callback1 = static function (): void {};
		$callback2 = static function ( $value ) {
			return $value;
		};

		Actions\expectAdded( 'action_one' )->once();
		Actions\expectAdded( 'action_two' )->once();
		Filters\expectAdded( 'filter_one' )->once();

		$this->hook_manager
			->add_action( 'action_one', $callback1 )
			->add_action( 'action_two', $callback1 )
			->add_filter( 'filter_one', $callback2 );

		$actions = $this->hook_manager->get_registered_actions();
		$filters = $this->hook_manager->get_registered_filters();

		$this->assertCount( 2, $actions );
		$this->assertCount( 1, $filters );
	}
}
