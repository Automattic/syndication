<?php
/**
 * Unit tests for REST client date formatting.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Tests for the format_date_for_api method.
 *
 * @group unit
 */
class RestClientDateFormattingTest extends TestCase {

	/**
	 * Test double instance that replicates the date formatting logic.
	 *
	 * @var object
	 */
	private $client;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a test double that replicates the exact method.
		$this->client = new class() {
			/**
			 * Format a MySQL date string for the WordPress.com REST API.
			 *
			 * @param string $mysql_date Date in MySQL format (Y-m-d H:i:s).
			 * @return string Date in ISO 8601 format, or empty string if invalid.
			 */
			public function format_date_for_api( $mysql_date ) {
				if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
					return '';
				}

				return mysql2date( 'c', $mysql_date, false );
			}
		};
	}

	/**
	 * Test that empty date returns empty string.
	 */
	public function test_empty_date_returns_empty_string() {
		Functions\expect( 'mysql2date' )->never();

		$result = $this->client->format_date_for_api( '' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that null date returns empty string.
	 */
	public function test_null_date_returns_empty_string() {
		Functions\expect( 'mysql2date' )->never();

		$result = $this->client->format_date_for_api( null );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that zero date returns empty string.
	 */
	public function test_zero_date_returns_empty_string() {
		Functions\expect( 'mysql2date' )->never();

		$result = $this->client->format_date_for_api( '0000-00-00 00:00:00' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test that valid date is converted to ISO 8601 format.
	 */
	public function test_valid_date_returns_iso_8601_format() {
		$mysql_date    = '2024-06-15 14:30:00';
		$expected_date = '2024-06-15T14:30:00+00:00';

		Functions\expect( 'mysql2date' )
			->once()
			->with( 'c', $mysql_date, false )
			->andReturn( $expected_date );

		$result = $this->client->format_date_for_api( $mysql_date );

		$this->assertSame( $expected_date, $result );
	}

	/**
	 * Test that future date (scheduled post) is formatted correctly.
	 */
	public function test_future_date_is_formatted_correctly() {
		$mysql_date    = '2025-12-25 10:00:00';
		$expected_date = '2025-12-25T10:00:00+00:00';

		Functions\expect( 'mysql2date' )
			->once()
			->with( 'c', $mysql_date, false )
			->andReturn( $expected_date );

		$result = $this->client->format_date_for_api( $mysql_date );

		$this->assertSame( $expected_date, $result );
	}

	/**
	 * Test that mysql2date is called with correct parameters.
	 */
	public function test_mysql2date_called_with_iso_format_code() {
		$mysql_date = '2024-01-01 00:00:00';

		Functions\expect( 'mysql2date' )
			->once()
			->with(
				'c',      // ISO 8601 format code
				$mysql_date,
				false     // Don't translate
			)
			->andReturn( '2024-01-01T00:00:00+00:00' );

		$this->client->format_date_for_api( $mysql_date );
	}

	/**
	 * Test that date with timezone offset is handled.
	 */
	public function test_date_with_timezone_offset() {
		$mysql_date    = '2024-03-15 08:45:30';
		$expected_date = '2024-03-15T08:45:30-05:00';

		Functions\expect( 'mysql2date' )
			->once()
			->with( 'c', $mysql_date, false )
			->andReturn( $expected_date );

		$result = $this->client->format_date_for_api( $mysql_date );

		$this->assertSame( $expected_date, $result );
	}
}
