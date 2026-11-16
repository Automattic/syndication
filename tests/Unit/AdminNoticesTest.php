<?php
/**
 * Unit tests for Syndication_Logger_Admin_Notice.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Syndication_Logger_Admin_Notice;

/**
 * Test case for Syndication_Logger_Admin_Notice.
 *
 * @group unit
 * @covers Syndication_Logger_Admin_Notice
 */
class AdminNoticesTest extends TestCase {

	/**
	 * Load the class with WordPress's option, escaping and translation functions stubbed.
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-admin-notices.php';

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs( array( 'sanitize_text_field', 'wp_unslash' ) );

		// No option has been saved yet, so get_option() returns whatever default it is given.
		Functions\when( 'get_option' )->alias(
			static function ( $option, $default_value = false ) {
				return $default_value;
			}
		);
	}

	/**
	 * Clean up request superglobals.
	 */
	protected function tearDown(): void {
		unset( $_GET['syn_dismiss'], $_GET['syn_dismiss_nonce'] );
		parent::tearDown();
	}

	/**
	 * The first notice on a site with no saved notices is stored as an array.
	 */
	public function test_add_notice_stores_the_first_notice(): void {
		$message_key = md5( 'Syndication site disabled' . 'Site 1 disabled' );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'syn_notices',
				array(
					'Syndication site disabled' => array(
						$message_key => array(
							'message_text'       => 'Site 1 disabled',
							'summarize_multiple' => false,
							'message_type'       => 'Syndication site disabled',
							'class'              => 'error',
						),
					),
				)
			);

		$this->assertTrue( Syndication_Logger_Admin_Notice::add_notice( 'Site 1 disabled', 'Syndication site disabled', 'error' ) );
	}

	/**
	 * A notice that is already stored isn't saved again.
	 */
	public function test_add_notice_skips_a_duplicate(): void {
		$message_key = md5( 'Syndication' . 'Already there' );

		Functions\when( 'get_option' )->justReturn(
			array( 'Syndication' => array( $message_key => array( 'message_text' => 'Already there' ) ) )
		);
		Functions\expect( 'update_option' )->never();

		$this->assertTrue( Syndication_Logger_Admin_Notice::add_notice( 'Already there' ) );
	}

	/**
	 * Summarising a notice for the first time stores the bundle as an array.
	 */
	public function test_display_valid_notices_stores_the_first_bundle(): void {
		$message_key = md5( 'Syndication' . 'Site 1 failed' );
		$summary_key = md5( 'Syndication' . 'Several sites failed' );

		Functions\when( 'get_option' )->alias(
			static function ( $option, $default_value = false ) use ( $message_key ) {
				if ( 'syn_notices' !== $option ) {
					return $default_value;
				}

				return array(
					'Syndication' => array(
						$message_key => array(
							'message_text'       => 'Site 1 failed',
							'summarize_multiple' => true,
							'message_type'       => 'Syndication',
							'class'              => 'error',
						),
					),
				);
			}
		);
		Filters\expectApplied( 'syn_message_text_multiple' )->andReturn( 'Several sites failed' );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'update_option' )
			->once()
			->with( 'syn_notice_bundles', array( $summary_key => array( $message_key ) ) );

		Syndication_Logger_Admin_Notice::display_valid_notices();
	}

	/**
	 * Dismissing on a site with no saved notices saves empty lists.
	 */
	public function test_dismiss_with_no_saved_notices(): void {
		$_GET['syn_dismiss']       = 'abc';
		$_GET['syn_dismiss_nonce'] = 'nonce';

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\expect( 'update_option' )->once()->with( 'syn_notices', array() );
		Functions\expect( 'update_option' )->once()->with( 'syn_notice_bundles', array() );

		Syndication_Logger_Admin_Notice::handle_dismiss_syndication_notice();
	}
}
