<?php
/**
 * Tests that admin screen text reaches translators as whole strings.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class TranslatableAdminStringsTest
 *
 * Each translation in the plugin's domain is wrapped in brackets, so a test
 * can see where one translatable string starts and ends in the output.
 */
class TranslatableAdminStringsTest extends WPIntegrationTestCase {

	/**
	 * The server's transports as they were before a test changed them.
	 *
	 * @var array|null
	 */
	private $original_transports;

	/**
	 * Mark every translation in the plugin's text domain.
	 */
	public function set_up(): void {
		parent::set_up();

		$mark = static function ( $translation ) {
			return '[' . $translation . ']';
		};
		add_filter( 'gettext_push-syndication', $mark );
		add_filter( 'gettext_with_context_push-syndication', $mark );

		$this->original_transports = $GLOBALS['push_syndication_server']->push_syndicate_transports;
	}

	/**
	 * Restore the server's transports.
	 */
	public function tear_down(): void {
		$GLOBALS['push_syndication_server']->push_syndicate_transports = $this->original_transports;

		parent::tear_down();
	}

	/**
	 * Capture what a callback prints.
	 *
	 * @param callable $callback The callback to run.
	 * @return string
	 */
	private function render( callable $callback ): string {
		ob_start();
		$callback();

		return ob_get_clean();
	}

	/**
	 * The WordPress.com application instructions are one sentence, link included.
	 *
	 * @covers WP_Push_Syndication_Server::display_apitoken_description
	 */
	public function test_application_instructions_are_one_string(): void {
		$html = $this->render( array( $GLOBALS['push_syndication_server'], 'display_apitoken_description' ) );

		$this->assertStringContainsString(
			'[To syndicate content to WordPress.com you must <a href="https://developer.wordpress.com/apps/new/">create a new application</a>.]',
			$html
		);
	}

	/**
	 * The pointer to the settings page on a WordPress.com REST site is one sentence, link included.
	 *
	 * @covers Syndication_WP_REST_Client::display_settings
	 */
	public function test_rest_site_settings_pointer_is_one_string(): void {
		$site = self::factory()->post->create_and_get( array( 'post_type' => 'syn_site' ) );

		$html = $this->render(
			static function () use ( $site ) {
				\Syndication_WP_REST_Client::display_settings( $site );
			}
		);

		$this->assertMatchesRegularExpression( '#\[To generate the following information automatically, visit the <a href="[^"]+" target="_blank">settings page <span class="screen-reader-text">\[\(opens in a new tab\)\]</span></a>\.\]#', $html );
	}

	/**
	 * Create a site using a transport type.
	 *
	 * @param string $transport_type The site's transport type.
	 * @return int The site post ID.
	 */
	private function make_site( string $transport_type ): int {
		$site_id = self::factory()->post->create( array( 'post_type' => 'syn_site' ) );
		update_post_meta( $site_id, 'syn_transport_type', $transport_type );

		return $site_id;
	}

	/**
	 * The transport column on the Sites list translates its format and mode.
	 *
	 * @covers WP_Push_Syndication_Server::manage_columns
	 */
	public function test_sites_list_transport_label_is_translated(): void {
		$site_id = $this->make_site( 'WP_RSS' );

		$html = $this->render(
			static function () use ( $site_id ) {
				$GLOBALS['push_syndication_server']->manage_columns( 'client-type', $site_id );
			}
		);

		$this->assertSame( '[RSS ([pull])]', $html );
	}

	/**
	 * A site whose transport has gone shows a translated message, not the exception text.
	 *
	 * @covers WP_Push_Syndication_Server::add_site_settings_metabox
	 */
	public function test_missing_transport_message_is_translated(): void {
		$GLOBALS['push_syndication_server']->push_syndicate_transports = array();
		$GLOBALS['post'] = get_post( $this->make_site( 'Missing' ) );

		$html = $this->render(
			static function () {
				$GLOBALS['push_syndication_server']->add_site_settings_metabox( $GLOBALS['post'] );
			}
		);

		$this->assertStringContainsString( '[Transport class not found!]', $html );
		$this->assertStringNotContainsString( 'transport class not found', $html );
	}

	/**
	 * The title of the site-disabled admin notice is translated.
	 *
	 * @covers ::syn_add_site_disabled_notice
	 */
	public function test_site_disabled_notice_title_is_translated(): void {
		syn_add_site_disabled_notice( 1, 3 );

		$this->assertArrayHasKey( '[Syndication site disabled]', get_option( 'syn_notices' ) );
	}
}
