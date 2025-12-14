<?php
/**
 * Unit tests for Syndication_Event_Counter
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Syndication\Tests\Unit;

use Brain\Monkey\Functions;
use ReflectionMethod;

/**
 * Test case for Syndication_Event_Counter.
 *
 * Tests the option name generation logic.
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
	 * Test safe option name generation uses correct prefix.
	 */
	public function test_get_safe_option_name_has_correct_prefix(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->counter, 'test_event', '123' );

		$this->assertStringStartsWith( 'push_syndication_event_counter_', $result );
	}

	/**
	 * Test safe option name generation produces consistent hashes.
	 */
	public function test_get_safe_option_name_is_consistent(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result1 = $method->invoke( $this->counter, 'test_event', '123' );
		$result2 = $method->invoke( $this->counter, 'test_event', '123' );

		$this->assertSame( $result1, $result2 );
	}

	/**
	 * Test safe option name generation produces different hashes for different slugs.
	 */
	public function test_get_safe_option_name_different_slugs(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result1 = $method->invoke( $this->counter, 'event_a', '123' );
		$result2 = $method->invoke( $this->counter, 'event_b', '123' );

		$this->assertNotSame( $result1, $result2 );
	}

	/**
	 * Test safe option name generation produces different hashes for different object IDs.
	 */
	public function test_get_safe_option_name_different_object_ids(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result1 = $method->invoke( $this->counter, 'test_event', '123' );
		$result2 = $method->invoke( $this->counter, 'test_event', '456' );

		$this->assertNotSame( $result1, $result2 );
	}

	/**
	 * Test safe option name length does not exceed 64 characters.
	 */
	public function test_get_safe_option_name_max_length(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		// Test with very long slug and ID.
		$long_slug = str_repeat( 'very_long_event_slug_', 10 );
		$long_id   = str_repeat( '123456789', 10 );

		$result = $method->invoke( $this->counter, $long_slug, $long_id );

		$this->assertLessThanOrEqual( 64, strlen( $result ) );
	}

	/**
	 * Test safe option name uses MD5 hash.
	 */
	public function test_get_safe_option_name_uses_md5(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result   = $method->invoke( $this->counter, 'test', '123' );
		$expected = 'push_syndication_event_counter_' . md5( 'test123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test safe option name handles empty slug.
	 */
	public function test_get_safe_option_name_empty_slug(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result   = $method->invoke( $this->counter, '', '123' );
		$expected = 'push_syndication_event_counter_' . md5( '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test safe option name handles empty object ID.
	 */
	public function test_get_safe_option_name_empty_object_id(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		$result   = $method->invoke( $this->counter, 'test_event', '' );
		$expected = 'push_syndication_event_counter_' . md5( 'test_event' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test safe option name handles numeric object ID.
	 */
	public function test_get_safe_option_name_numeric_id(): void {
		$method = new ReflectionMethod( \Syndication_Event_Counter::class, '_get_safe_option_name' );
		$method->setAccessible( true );

		// The method converts to string internally.
		$result   = $method->invoke( $this->counter, 'pull_failure', 42 );
		$expected = 'push_syndication_event_counter_' . md5( 'pull_failure42' );

		$this->assertSame( $expected, $result );
	}
}
