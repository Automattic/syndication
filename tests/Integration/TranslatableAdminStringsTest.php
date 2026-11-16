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
	 * Mark every translation in the plugin's text domain.
	 */
	public function set_up(): void {
		parent::set_up();

		add_filter(
			'gettext_push-syndication',
			static function ( $translation ) {
				return '[' . $translation . ']';
			}
		);
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

		$this->assertMatchesRegularExpression( '#\[To generate the following information automatically, visit the <a href="[^"]+" target="_blank">settings page</a>\.\]#', $html );
	}
}
