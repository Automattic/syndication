<?php
/**
 * Tests for the Syndication_Logger_List_Table output escaping.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;
use ReflectionMethod;
use Syndication_Logger;
use Syndication_Logger_List_Table;
use Syndication_Logger_Viewer;

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
		unset( $_GET['orderby'], $_GET['order'], $_GET['page'], $_REQUEST['s'] );
		parent::tear_down();
	}

	/**
	 * The column widths are output on the Logs screen, and only there.
	 *
	 * @covers Syndication_Logger_List_Table::admin_header
	 */
	public function test_admin_header_outputs_column_styles_only_on_the_logs_screen(): void {
		$_GET['page'] = 'syndication_dashboard';
		ob_start();
		$this->table->admin_header();
		$this->assertStringContainsString( '.column-message', ob_get_clean() );

		$_GET['page'] = 'push-syndicate-settings';
		ob_start();
		$this->table->admin_header();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Every month between the oldest and newest entry is offered, including short months.
	 *
	 * @covers Syndication_Logger_List_Table::_create_months_dropdown
	 */
	public function test_months_dropdown_lists_every_month_in_range(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		Syndication_Logger::log_post_error( $site_id, 'error', 'Oldest', '2026-01-31 12:00:00' );
		Syndication_Logger::log_post_error( $site_id, 'error', 'Newest', '2026-03-01 08:00:00' );

		$this->table->prepare_items();

		$method = new ReflectionMethod( $this->table, '_create_months_dropdown' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invoke( $this->table );
		$output = ob_get_clean();

		preg_match_all( "/value='(\d{4}-\d{2})'/", $output, $matches );
		$this->assertSame( array( '2026-01', '2026-02', '2026-03' ), $matches[1] );
	}

	/**
	 * Month names in the date filter come from the site's locale.
	 *
	 * @covers Syndication_Logger_List_Table::_create_months_dropdown
	 */
	public function test_months_dropdown_uses_localized_month_names(): void {
		global $wp_locale;

		$original_month       = $wp_locale->month['01'];
		$wp_locale->month['01'] = 'Januar';

		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );
		Syndication_Logger::log_post_error( $site_id, 'error', 'Only', '2026-01-15 12:00:00' );

		$this->table->prepare_items();

		$method = new ReflectionMethod( $this->table, '_create_months_dropdown' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invoke( $this->table );
		$output = ob_get_clean();

		$wp_locale->month['01'] = $original_month;

		$this->assertStringContainsString( '>Januar 2026</option>', $output );
	}

	/**
	 * The search box filters entries by message, as plain text rather than a pattern.
	 *
	 * @covers Syndication_Logger_List_Table::prepare_items
	 */
	public function test_search_filters_entries_by_message_text(): void {
		$site_id = $this->factory()->post->create( array( 'post_type' => 'syn_site' ) );

		Syndication_Logger::log_post_error( $site_id, 'error', 'Failed to parse feed' );
		Syndication_Logger::log_post_error( $site_id, 'error', 'Could not reach feed (timeout)' );

		$_REQUEST['s'] = 'failed';
		$this->table->prepare_items();
		$this->assertSame( array( 'Failed to parse feed' ), wp_list_pluck( $this->table->items, 'message' ) );

		$_REQUEST['s'] = '(timeout)';
		$table         = new Syndication_Logger_List_Table();
		$table->prepare_items();
		$this->assertSame( array( 'Could not reach feed (timeout)' ), wp_list_pluck( $table->items, 'message' ) );

		$_REQUEST['s'] = 'feed.*';
		$table         = new Syndication_Logger_List_Table();
		$table->prepare_items();
		$this->assertSame( array(), $table->items );
	}

	/**
	 * The Logs screen is available to whoever has the plugin's capability.
	 *
	 * @covers Syndication_Logger_Viewer::add_menu_items
	 */
	public function test_logs_menu_uses_the_syndicate_capability(): void {
		global $submenu;

		add_filter(
			'syn_syndicate_cap',
			static function () {
				return 'edit_others_posts';
			}
		);
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		( new Syndication_Logger_Viewer() )->add_menu_items();

		$logs_item = wp_list_filter( $submenu['edit.php?post_type=syn_site'] ?? array(), array( 2 => 'syndication_dashboard' ) );
		$submenu   = array();

		$this->assertSame( array( 'edit_others_posts' ), wp_list_pluck( $logs_item, 1 ) );
	}

	/**
	 * The filter dropdown labels go through the plugin's text domain.
	 *
	 * @covers Syndication_Logger_List_Table::_create_months_dropdown
	 * @covers Syndication_Logger_List_Table::_create_types_dropdown
	 */
	public function test_filter_dropdown_labels_are_translatable(): void {
		add_filter(
			'gettext_push-syndication',
			static function ( $translation ) {
				return strtoupper( $translation );
			}
		);

		ob_start();
		foreach ( array( '_create_months_dropdown', '_create_types_dropdown' ) as $method_name ) {
			$method = new ReflectionMethod( $this->table, $method_name );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}
			$method->invoke( $this->table );
		}
		$output = ob_get_clean();

		foreach ( array( 'ALL DATES', 'ALL TYPES', 'SUCCESS', 'INFORMATION', 'ERROR' ) as $label ) {
			$this->assertStringContainsString( ">{$label}</option>", $output );
		}
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
