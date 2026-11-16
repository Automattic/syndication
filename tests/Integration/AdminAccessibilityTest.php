<?php
/**
 * Tests for the accessibility of the plugin's admin markup.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class AdminAccessibilityTest
 *
 * @covers WP_Push_Syndication_Server::add_site_status_metabox
 * @covers WP_Push_Syndication_Server::display_transports
 * @covers Syndication_WP_XML_Client::display_settings
 * @covers WP_Push_Syndication_Server::load_scripts_and_styles
 */
class AdminAccessibilityTest extends WPIntegrationTestCase {

	/**
	 * A site post for the site edit screen meta boxes.
	 *
	 * @var \WP_Post
	 */
	private $site;

	/**
	 * Create the site, and load the admin template functions the meta boxes use.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->site      = self::factory()->post->create_and_get( array( 'post_type' => 'syn_site' ) );
		$GLOBALS['post'] = $this->site;
	}

	/**
	 * Undo the screen and scripts a test set up.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['typenow'] );
		wp_dequeue_script( 'wp-a11y' );
		wp_dequeue_style( 'syn-edit-site' );

		parent::tear_down();
	}

	/**
	 * Render some markup and return an XPath over it.
	 *
	 * @param callable $render Callback that echoes the markup.
	 * @return \DOMXPath
	 */
	private function render( callable $render ): \DOMXPath {
		ob_start();
		$render();
		$html = ob_get_clean();

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		return new \DOMXPath( $dom );
	}

	/**
	 * The server instance that renders the admin screens.
	 *
	 * @return \WP_Push_Syndication_Server
	 */
	private function server(): \WP_Push_Syndication_Server {
		return $GLOBALS['push_syndication_server'];
	}

	/**
	 * The site status box leaves the page's tab order and keyboard shortcuts alone.
	 */
	public function test_site_status_box_keeps_the_natural_tab_order(): void {
		$xpath = $this->render(
			function () {
				$this->server()->add_site_status_metabox( $this->site );
			}
		);

		$this->assertSame( 0, $xpath->query( '//*[@tabindex > 0]' )->length, 'Positive tabindex found.' );
		$this->assertSame( 0, $xpath->query( '//*[@accesskey]' )->length, 'accesskey found.' );
	}

	/**
	 * The site status is a labeled select, shown without having to reveal it first.
	 */
	public function test_site_status_select_is_labeled_and_shown(): void {
		update_post_meta( $this->site->ID, 'syn_site_enabled', 'off' );

		$xpath = $this->render(
			function () {
				$this->server()->add_site_status_metabox( $this->site );
			}
		);

		$select = $xpath->query( '//select[@name="site_enabled"]' )->item( 0 );
		$this->assertNotNull( $select );
		$this->assertSame( 1, $xpath->query( '//label[@for="' . $select->getAttribute( 'id' ) . '"]' )->length, 'Status select has no label.' );
		$this->assertSame( 0, $xpath->query( 'ancestor::*[contains(concat(" ", @class, " "), " hide-if-js ")]', $select )->length, 'Status select is hidden until revealed.' );
		$this->assertSame( 'off', $xpath->query( './/option[@selected]', $select )->item( 0 )->getAttribute( 'value' ) );
	}

	/**
	 * Choosing a transport doesn't submit the form until the user asks it to.
	 */
	public function test_choosing_a_transport_does_not_submit_the_form(): void {
		$this->server()->admin_init();

		$xpath = $this->render(
			function () {
				$this->server()->display_transports( 'WP_XMLRPC', 'pull' );
			}
		);

		$select = $xpath->query( '//select[@name="transport_type"]' )->item( 0 );
		$this->assertNotNull( $select );
		$this->assertFalse( $select->hasAttribute( 'onchange' ), 'Changing the transport submits the form.' );
		$this->assertSame( 1, $xpath->query( '//button[@type="submit"]' )->length, 'No button to change the transport.' );
	}

	/**
	 * The XPath mappings are a table, with real buttons that say which mapping they delete.
	 */
	public function test_xml_mappings_are_a_table_with_buttons(): void {
		update_post_meta(
			$this->site->ID,
			'syn_node_config',
			array(
				'nodes' => array(
					'//title' => array(
						array(
							'is_item'  => 1,
							'is_photo' => 0,
							'is_meta'  => 0,
							'is_tax'   => 0,
							'field'    => 'post_title',
						),
					),
				),
			)
		);

		$xpath = $this->render(
			function () {
				\Syndication_WP_XML_Client::display_settings( $this->site );
			}
		);

		$this->assertSame( 0, $xpath->query( '//a[@href="#"]' )->length, 'A link is acting as a button.' );
		$this->assertSame( 7, $xpath->query( '//table/thead/tr/th[@scope="col"]' )->length, 'Mapping columns have no headers.' );

		$rows = $xpath->query( '//table/tbody/tr' );
		$this->assertSame( 2, $rows->length, 'Expected the saved mapping and an empty row.' );

		foreach ( $rows as $row ) {
			$this->assertSame( 2, $xpath->query( './/input[@type="text"][@aria-label]', $row )->length );
			$this->assertSame( 4, $xpath->query( './/input[@type="checkbox"][@aria-label]', $row )->length );

			$delete = $xpath->query( './/button[@type="button"]', $row )->item( 0 );
			$this->assertNotNull( $delete, 'Delete is not a button.' );

			// Every id the button is labeled by exists, so its name includes the row's XPath.
			foreach ( explode( ' ', $delete->getAttribute( 'aria-labelledby' ) ) as $id ) {
				$this->assertSame( 1, $xpath->query( '//*[@id="' . $id . '"]' )->length, "Missing label element {$id}." );
			}
		}

		$this->assertSame( 1, $xpath->query( '//button[@type="button"][contains(@class, "syn-pull-xpath-add-new")]' )->length, 'Add new is not a button.' );
	}

	/**
	 * The site edit screen loads wp.a11y.speak(), which the mapping rows announce changes with.
	 */
	public function test_site_edit_screen_loads_the_speak_script(): void {
		$GLOBALS['typenow'] = 'syn_site';

		$this->server()->load_scripts_and_styles( 'post.php' );

		$this->assertTrue( wp_script_is( 'wp-a11y', 'enqueued' ) );
	}
}
