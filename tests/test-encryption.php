<?php
namespace Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class EncryptionTest
 */
class EncryptionTest extends WPIntegrationTestCase {
	private $simple_string;
	private $complex_array;


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
	}

	/**
	 * Test if the encryptor being used with PHP 7.1 or older is the mcrypt encryptor.
	 *
	 * @requires PHP < 7.1
	 */
	public function test_get_encryptor_before_php_71() {
		$encryptor = \Syndication_Encryption::get_encryptor();

		// If PHP < 7.1, it should be using the mcrypt encryptor.
		self::assertInstanceOf( \Syndication_Encryptor_MCrypt::class, $encryptor );
	}

	/**
	 * Test if the encryptor being used with newer PHP is OpenSSL encryptor.
	 *
	 * @requires PHP >= 7.1
	 */
	public function test_get_encryptor_after_php_71() {
		$encryptor = \Syndication_Encryption::get_encryptor();

		// Test if the Encryptor being used is the OpenSSL.
		self::assertInstanceOf( \Syndication_Encryptor_OpenSSL::class, $encryptor );
	}

	/**
	 * Tests the encryption and decryption functions
	 */
	public function test_encryption() {
		$encrypted_simple           = push_syndicate_encrypt( $this->simple_string );
		$encrypted_simple_different = push_syndicate_encrypt( $this->simple_string . '1' );
		$encrypted_complex          = push_syndicate_encrypt( $this->complex_array );

		self::assertIsString( $encrypted_simple, 'assert if the string is encrypted' );
		self::assertIsString( $encrypted_complex, 'assert if the array is encrypted' );

		self::assertNotEquals( $encrypted_simple, $encrypted_complex, 'assert that the two different objects have different results' );
		self::assertNotEquals( $encrypted_simple, $encrypted_simple_different, 'assert that the two different strings have different results' );

		$decrypted_simple        = push_syndicate_decrypt( $encrypted_simple );
		$decrypted_complex_array = push_syndicate_decrypt( $encrypted_complex );

		self::assertEquals( $this->simple_string, $decrypted_simple, 'asserts if the decrypted string is the same as the original' );

		self::assertIsArray( $decrypted_complex_array, 'asserts if the decrypted complex data was decrypted as an array' );
		self::assertEquals( $this->complex_array, $decrypted_complex_array, 'check if the decrypted array is the same as the original' );
	}

	/**
	 * Tests the Syndication_Encryptor_OpenSSL encryptor.
	 */
	public function test_encryptor_openssl() {
		$encryptor = new \Syndication_Encryptor_OpenSSL();

		// Test the cipher.
		$cipher_data = $encryptor->getCipher();

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

		// Test simple encryption and decryption.
		$encrypted = $encryptor->encrypt( $this->simple_string );
		self::assertIsString( $encrypted );

		$decrypted = $encryptor->decrypt( $encrypted );
		self::assertEquals( $this->simple_string, $decrypted );
	}

	/**
	 * Tests the Syndication_Encryptor_MCrypt encryptor, only if the module is present (usually PHP < 7.1)
	 *
	 * @requires extension mcrypt
	 */
	public function test_encryptor_mcrypt() {
		$encryptor = new \Syndication_Encryptor_MCrypt();



		// Test the cipher.
		$expected_cipher = MCRYPT_RIJNDAEL_256; // phpcs:ignore PHPCompatibility.Constants.RemovedConstants.mcrypt_rijndael_256DeprecatedRemoved
		$cipher          = $encryptor->getCipher();
		self::assertSame( $expected_cipher, $cipher );

		// Test simple encryption and decryption.
		$encrypted = $encryptor->encrypt( $this->simple_string );
		self::assertIsString( $encrypted );

		$decrypted = $encryptor->decrypt( $encrypted );
		self::assertEquals( $this->simple_string, $decrypted );
	}


}
