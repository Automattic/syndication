<?php
/**
 * Tests the WordPress.com authorization section of the settings page.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use WP_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class ApiTokenAuthorizationTest
 *
 * The token request is intercepted at 'pre_http_request', which records the
 * request and answers with whatever $this->response holds.
 *
 * @covers WP_Push_Syndication_Server::get_api_token
 */
class ApiTokenAuthorizationTest extends WPIntegrationTestCase {

	/**
	 * Requests captured at 'pre_http_request'.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private $requests = array();

	/**
	 * The response, or WP_Error, the token request returns.
	 *
	 * @var array|WP_Error
	 */
	private $response;

	/**
	 * The server settings as they were before a test changed them.
	 *
	 * @var array
	 */
	private $original_settings;

	/**
	 * Intercept outbound HTTP and simulate the OAuth redirect back.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->requests = array();
		$this->response = self::response(
			200,
			wp_json_encode(
				array(
					'access_token' => 'the-token',
					'blog_id'      => 12345,
					'blog_url'     => 'https://example.wordpress.com',
				)
			)
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $parsed_args,
				);

				return $this->response;
			},
			10,
			3
		);

		$this->original_settings = $GLOBALS['push_syndication_server']->push_syndicate_settings;

		$GLOBALS['push_syndication_server']->push_syndicate_settings['client_id'] = '42';

		$_GET['code'] = 'auth-code';
	}

	/**
	 * Restore settings and superglobals.
	 */
	public function tear_down(): void {
		unset( $_GET['code'], $_GET['settings-updated'] );

		$GLOBALS['push_syndication_server']->push_syndicate_settings = $this->original_settings;

		parent::tear_down();
	}

	/**
	 * Build an HTTP response array.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Raw response body.
	 * @return array
	 */
	private static function response( int $code, string $body ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Render the authorization section.
	 *
	 * @return string
	 */
	private function render(): string {
		ob_start();
		$GLOBALS['push_syndication_server']->get_api_token();

		return ob_get_clean();
	}

	/**
	 * Assert the output offers the Authorize button.
	 *
	 * @param string $html Rendered output.
	 */
	private function assert_authorize_button( string $html ): void {
		$this->assertStringContainsString( 'https://public-api.wordpress.com/oauth2/authorize?client_id=42', $html );
		$this->assertStringContainsString( 'Authorize', $html );
	}

	/**
	 * The closing instruction reads as one sentence, with a space after the link.
	 */
	public function test_success_instruction_is_one_sentence(): void {
		$this->assertStringContainsString(
			'when registering a <a href="https://wordpress.com" target="_blank">WordPress.com</a> site.',
			$this->render()
		);
	}

	/**
	 * Without a code, only the Authorize button is shown.
	 */
	public function test_shows_authorize_button_without_code(): void {
		unset( $_GET['code'] );

		$html = $this->render();

		$this->assert_authorize_button( $html );
		$this->assertEmpty( $this->requests );
	}

	/**
	 * A successful exchange shows the token, blog ID and blog URL.
	 */
	public function test_shows_token_details_on_success(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'the-token', $html );
		$this->assertStringContainsString( '12345', $html );
		$this->assertStringContainsString( 'https://example.wordpress.com', $html );
		$this->assertSame( 'auth-code', $this->requests[0]['args']['body']['code'] );
	}

	/**
	 * Failed token requests show a readable message and the Authorize button again.
	 *
	 * @dataProvider data_failed_responses
	 *
	 * @param array|WP_Error $response Intercepted response.
	 * @param string         $reason   Text the message must include.
	 */
	public function test_failed_token_request_offers_authorize_again( $response, string $reason ): void {
		$this->response = $response;

		$html = $this->render();

		$this->assertStringContainsString( 'Error retrieving API token', $html );
		$this->assertStringContainsString( $reason, $html );
		$this->assert_authorize_button( $html );
		$this->assertStringNotContainsString( 'Access token', $html );
	}

	/**
	 * Token responses that do not carry a token.
	 *
	 * @return array<string, array{0: array|WP_Error, 1: string}>
	 */
	public function data_failed_responses(): array {
		return array(
			'transport error'               => array(
				new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' ),
				'cURL error 6: Could not resolve host',
			),
			'JSON error with description'   => array(
				self::response(
					400,
					wp_json_encode(
						array(
							'error'             => 'invalid_grant',
							'error_description' => 'The code has expired.',
						)
					)
				),
				'The code has expired.',
			),
			'JSON error without a description' => array(
				self::response( 400, wp_json_encode( array( 'error' => 'invalid_grant' ) ) ),
				'invalid_grant',
			),
			'HTML error page'               => array(
				self::response( 502, '<html><body>Bad Gateway</body></html>' ),
				'502',
			),
			'JSON without a token'          => array(
				self::response( 200, wp_json_encode( array( 'ok' => true ) ) ),
				'200',
			),
		);
	}

	/**
	 * The code is sanitized before it is sent.
	 */
	public function test_code_is_sanitized(): void {
		$_GET['code'] = " auth-code<script>\n";

		$this->render();

		$this->assertSame( 'auth-code', $this->requests[0]['args']['body']['code'] );
	}

	/**
	 * Values from the response are escaped.
	 */
	public function test_response_values_are_escaped(): void {
		$this->response = self::response(
			200,
			wp_json_encode(
				array(
					'access_token' => '<b>token</b>',
					'blog_id'      => 1,
					'blog_url'     => 'https://example.com/"><script>alert(1)</script>',
				)
			)
		);

		$html = $this->render();

		$this->assertStringNotContainsString( '<b>token</b>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * The client ID cannot break out of the Authorize button.
	 */
	public function test_client_id_cannot_break_out_of_authorize_button(): void {
		unset( $_GET['code'] );
		$GLOBALS['push_syndication_server']->push_syndicate_settings['client_id'] = "42');alert(1);//";

		$html = $this->render();

		$this->assertStringNotContainsString( 'alert(1)', html_entity_decode( $html, ENT_QUOTES ) );
	}
}
