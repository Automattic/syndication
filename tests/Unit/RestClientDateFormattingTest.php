<?php
/**
 * Unit tests for the Syndication_WP_REST_Client formatting helpers.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

/**
 * Exercises the real private formatting helpers through Reflection.
 *
 * @covers \Syndication_WP_REST_Client
 * @group unit
 */
class RestClientDateFormattingTest extends TestCase {

	/**
	 * PHP default timezone before each test, restored afterwards.
	 *
	 * @var string
	 */
	private $original_timezone;

	/**
	 * Load the class under test.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-wp-rest-client.php';
	}

	/**
	 * Run each test in a non-UTC PHP timezone, so nothing can pass by assuming UTC.
	 */
	protected function set_up() {
		parent::set_up();

		$this->original_timezone = date_default_timezone_get();
		date_default_timezone_set( 'America/New_York' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
	}

	/**
	 * Restore the PHP timezone.
	 */
	protected function tear_down() {
		date_default_timezone_set( $this->original_timezone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set

		parent::tear_down();
	}

	/**
	 * Call a private method on a client built without its constructor.
	 *
	 * @param string $method Method name.
	 * @param mixed  $arg    Single argument.
	 * @return mixed
	 */
	private function call( string $method, $arg ) {
		$class  = new \ReflectionClass( \Syndication_WP_REST_Client::class );
		$client = $class->newInstanceWithoutConstructor();
		$method = $class->getMethod( $method );
		$method->setAccessible( true );

		return $method->invoke( $client, $arg );
	}

	/**
	 * Unset and zero dates have nothing to send.
	 *
	 * @dataProvider data_empty_dates
	 *
	 * @param mixed $date The stored date.
	 */
	public function test_empty_date_returns_empty_string( $date ): void {
		$this->assertSame( '', $this->call( 'format_date_for_api', $date ) );
	}

	/**
	 * Dates that mean "no date".
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function data_empty_dates(): array {
		return array(
			'empty string' => array( '' ),
			'null'         => array( null ),
			'zero date'    => array( '0000-00-00 00:00:00' ),
		);
	}

	/**
	 * The GMT date is sent as UTC, whatever timezone the server or site is in.
	 *
	 * @dataProvider data_gmt_dates
	 *
	 * @param string $gmt      Stored post_date_gmt.
	 * @param string $expected ISO 8601 date the API should receive.
	 */
	public function test_gmt_date_is_sent_as_utc( string $gmt, string $expected ): void {
		$this->assertSame( $expected, $this->call( 'format_date_for_api', $gmt ) );
	}

	/**
	 * GMT dates and their ISO 8601 form.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_gmt_dates(): array {
		return array(
			'winter'         => array( '2024-01-15 10:00:00', '2024-01-15T10:00:00+00:00' ),
			'summer'         => array( '2024-06-15 14:30:00', '2024-06-15T14:30:00+00:00' ),
			'scheduled post' => array( '2030-12-25 23:59:59', '2030-12-25T23:59:59+00:00' ),
		);
	}
}
