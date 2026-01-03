<?php
namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class EncryptorOpenSSLTest
 */
class EncryptorOpenSSLTest extends EncryptorTestCase {

	/**
	 * Runs before the test, set-up.
	 */
	public function set_up() {
		parent::set_up();

		$this->encryptor = new \Syndication_Encryptor_OpenSSL();
	}

	/**
	 * Test the expected cipher for openssl
	 */
	public function test_cipher() {
		// Test the cipher.
		$cipher_data = $this->encryptor->get_cipher();

		// Test if is an array.
		self::assertIsArray( $cipher_data, 'assert if the cipher data is array' );
		self::assertCount( 3, $cipher_data, 'assert if cipher data have three elements' );

		$cipher = $cipher_data['cipher'];
		$iv     = $cipher_data['iv'];
		$key    = $cipher_data['key'];

		// test cipher.
		$expected_cipher = 'aes-256-cbc';
		self::assertEquals( $expected_cipher, $cipher, 'assert if cipher is available' );

		// test key.
		self::assertEquals( $key, md5( PUSH_SYNDICATE_KEY ), 'assert if the key is generated as expected' );

		// test iv.
		self::assertEquals( 16, strlen( $iv ), 'assert iv size (must be 16)' );
		$generated_iv = substr( md5( md5( PUSH_SYNDICATE_KEY ) ), 0, 16 );
		self::assertEquals( $generated_iv, $iv, 'assert if generated iv is as expected' );
	}
}
