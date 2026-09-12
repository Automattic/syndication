<?php
/**
 * Tests for the Syndication_Logger_List_Table output escaping.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;
use Syndication_Logger_List_Table;

require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-logger-viewer.php';

/**
 * Class LoggerViewerTest
 *
 * Log entries are read from the unprotected `syn_log` post meta, so their
 * values must be treated as untrusted when rendered by WP_List_Table.
 *
 * @covers Syndication_Logger_List_Table
 */
class LoggerViewerTest extends WPIntegrationTestCase {

	/**
	 * The list table under test.
	 *
	 * @var Syndication_Logger_List_Table
	 */
	private $table;

	/**
	 * Set up the list table.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->table = new Syndication_Logger_List_Table();
	}

	/**
	 * Clean up request superglobals.
	 */
	public function tear_down(): void {
		unset( $_GET['orderby'], $_GET['order'] );
		parent::tear_down();
	}

	/**
	 * Every rendered column value must be escaped.
	 *
	 * @covers Syndication_Logger_List_Table::column_default
	 */
	public function test_column_default_escapes_values(): void {
		$item = array( 'message' => '<script>alert(1)</script>' );

		$this->assertSame(
			'&lt;script&gt;alert(1)&lt;/script&gt;',
			$this->table->column_default( $item, 'message' )
		);
	}

	/**
	 * Non-scalar or missing values must not leak a var dump of the whole item.
	 *
	 * @covers Syndication_Logger_List_Table::column_default
	 */
	public function test_column_default_returns_empty_string_for_unusable_values(): void {
		$item = array( 'status' => array( '<script>alert(1)</script>' ) );

		$this->assertSame( '', $this->table->column_default( $item, 'status' ) );
		$this->assertSame( '', $this->table->column_default( $item, 'message' ) );
	}

	/**
	 * The truncated log ID must be escaped without mangling the ellipsis.
	 *
	 * @covers Syndication_Logger_List_Table::column_log_id
	 */
	public function test_column_log_id_escapes_and_truncates(): void {
		$item = array( 'log_id' => '<script>alert(1)</script>' );

		$this->assertSame( '&lt;sc&hellip;pt&gt;', $this->table->column_log_id( $item ) );
		$this->assertSame( '', $this->table->column_log_id( array( 'log_id' => array() ) ) );
	}

	/**
	 * An arbitrary `orderby` must fall back to the default sort column.
	 *
	 * @covers Syndication_Logger_List_Table::usort_reorder
	 */
	public function test_usort_reorder_ignores_unknown_orderby(): void {
		$_GET['orderby'] = 'bogus_column';
		$_GET['order']   = 'asc';

		$a = array( 'time' => '2026-01-01 00:00:00' );
		$b = array( 'time' => '2026-02-01 00:00:00' );

		// Falls back to 'time', so $a sorts before $b.
		$this->assertLessThan( 0, $this->table->usort_reorder( $a, $b ) );
	}

	/**
	 * A known `orderby` is honoured, and `order` is whitelisted.
	 *
	 * @covers Syndication_Logger_List_Table::usort_reorder
	 */
	public function test_usort_reorder_honours_known_orderby_and_order(): void {
		$a = array( 'status' => 'aaa' );
		$b = array( 'status' => 'bbb' );

		$_GET['orderby'] = 'status';
		$_GET['order']   = 'asc';
		$this->assertLessThan( 0, $this->table->usort_reorder( $a, $b ) );

		// Anything that is not 'asc' means descending.
		$_GET['order'] = 'nonsense';
		$this->assertGreaterThan( 0, $this->table->usort_reorder( $a, $b ) );
	}
}
