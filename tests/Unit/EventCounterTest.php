<?php
/**
 * Unit tests for Syndication_Event_Counter
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use ReflectionMethod;

/**
 * Test case for Syndication_Event_Counter.
 *
 * Tests counting, resetting and the option name generation logic.
 *
 * @group unit
 * @covers Syndication_Event_Counter
 */
class EventCounterTest extends TestCase {

	/**
	 * Event counter instance.
	 *
	 * @var \Syndication_Event_Counter
	 */
	private $counter;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Stub add_action to prevent WordPress dependency.
		Functions\stubs( [ 'add_action' ] );

		$this->counter = new \Syndication_Event_Counter();
	}

	/**
	 * Test safe option name generation produces different hashes for different slugs.
	 */
	public function test_get_safe_option_name_different_slugs(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result1 = $method->invoke( $this->counter, 'event_a', '123' );
		$result2 = $method->invoke( $this->counter, 'event_b', '123' );

		$this->assertNotSame( $result1, $result2 );
	}

	/**
	 * Test safe option name generation produces different hashes for different object IDs.
	 */
	public function test_get_safe_option_name_different_object_ids(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result1 = $method->invoke( $this->counter, 'test_event', '123' );
		$result2 = $method->invoke( $this->counter, 'test_event', '456' );

		$this->assertNotSame( $result1, $result2 );
	}

	/**
	 * Test safe option name uses MD5 hash.
	 */
	public function test_get_safe_option_name_uses_md5(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result   = $method->invoke( $this->counter, 'test', '123' );
		$expected = 'push_syndication_event_counter_' . md5( 'test123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test safe option name handles empty slug.
	 */
	public function test_get_safe_option_name_empty_slug(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result   = $method->invoke( $this->counter, '', '123' );
		$expected = 'push_syndication_event_counter_' . md5( '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test safe option name handles empty object ID.
	 */
	public function test_get_safe_option_name_empty_object_id(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result   = $method->invoke( $this->counter, 'test_event', '' );
		$expected = 'push_syndication_event_counter_' . md5( 'test_event' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test safe option name handles numeric object ID.
	 */
	public function test_get_safe_option_name_numeric_id(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// The method converts to string internally.
		$result   = $method->invoke( $this->counter, 'pull_failure', 42 );
		$expected = 'push_syndication_event_counter_' . md5( 'pull_failure42' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Counting an event increments its stored count and announces the new total.
	 */
	public function test_count_event_increments_the_count_and_fires_actions(): void {
		$option = 'push_syndication_event_counter_' . md5( 'pull_failure42' );

		Functions\expect( 'get_option' )->once()->with( $option, 0 )->andReturn( 2 );
		Functions\expect( 'update_option' )->once()->with( $option, 3 );
		Actions\expectDone( 'push_syndication_after_event' )->once()->with( 'pull_failure', '42', 3 );
		Actions\expectDone( 'push_syndication_after_event_pull_failure' )->once()->with( '42', 3 );

		$this->counter->count_event( 'pull_failure', 42 );
	}

	/**
	 * The first occurrence of an event is counted as one.
	 */
	public function test_count_event_starts_at_one(): void {
		$option = 'push_syndication_event_counter_' . md5( 'pull_failure42' );

		Functions\when( 'get_option' )->returnArg( 2 );
		Functions\expect( 'update_option' )->once()->with( $option, 1 );

		$this->counter->count_event( 'pull_failure', 42 );
	}

	/**
	 * Resetting an event deletes its stored count.
	 */
	public function test_reset_event_deletes_the_count(): void {
		Functions\expect( 'delete_option' )->once()->with( 'push_syndication_event_counter_' . md5( 'pull_failure42' ) );

		$this->counter->reset_event( 'pull_failure', 42 );
	}
}
