<?php
namespace Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class EncryptorOpenSSLTest
 */
class EncryptorOpenSSLTest extends WPIntegrationTestCase {
	private $simple_string;
	private $complex_array;
	private $encryptor;

	/**
	 * Runs before the test, set-up.
	 */
	public function setUp() {
		$this->simple_string = 'this is a simple string!';
		$this->complex_array = array(
			'element' => 'this is a element',
			'group'   => array(
				'another',
				'sub',
				'array',
				'info' => 'test',
			),
			'',
			145,
			1         => 20.04,
			3         => true,
		);

		$this->encryptor = new \Syndication_Encryptor_OpenSSL();
	}

	/**
	 * Test a simple string encryption
	 */
	public function test_simple_encryption() {
		$encrypted = $this->encryptor->encrypt( $this->simple_string );

		self::assertIsString( $encrypted, 'assert if the string encryption returns string' );
		self::assertEquals( base64_encode( base64_decode( $encrypted ) ), $encrypted, 'assert if the encrypted data is encoded in base64' );

		return $encrypted;
	}

	/**
	 * Test a simple string decryption.
	 *
	 * @param string $encrypted The encrypted string from the previous test.
	 *
	 * @depends test_simple_encryption
	 */
	public function test_simple_decryption( $encrypted ) {
		$decrypted = $this->encryptor->decrypt( $encrypted );
		self::assertEquals( $this->simple_string, $decrypted );
	}

	/**
	 * Test a complex (array) encryption.
	 */
	public function test_complex_encryption() {
		$encrypted = $this->encryptor->encrypt( $this->complex_array );

		self::assertIsString( $encrypted, 'assert if the array encryption returns string' );
		self::assertEquals( base64_encode( base64_decode( $encrypted ) ), $encrypted, 'assert if the encrypted data is encoded in base64' );

		return $encrypted;
	}

	/**
	 * Test an array decryption.
	 *
	 * @param string $encrypted The encrypted string from the previous test.
	 *
	 * @depends test_complex_encryption
	 */
	public function test_complex_decryption( $encrypted ) {
		$decrypted = $this->encryptor->decrypt( $encrypted );

		self::assertIsArray( $decrypted, 'assert if the decrypted data is an array' );
		self::assertEquals( $this->complex_array, $decrypted, 'assert if the decrypted array is equal to the original array' );
	}

	/**
	 * Test the expected cipher for openssl
	 */
	public function test_cipher() {
		// Test the cipher.
		$cipher_data = $this->encryptor->getCipher();

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
