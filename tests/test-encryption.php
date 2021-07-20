<?php
namespace Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class EncryptionTest
 *
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
	 * Test if setting the encryptor works as expected
	 */
	public function test_set_encryptor() {
		$encryptor = \Syndication_Encryption::set_encryptor( new \Syndication_Encryptor_OpenSSL() );
		self::assertInstanceOf( \Syndication_Encryptor_OpenSSL::class, $encryptor, 'assert if the encryptor is set' );

		$encryptor = \Syndication_Encryption::set_encryptor( new \stdClass() );
		self::assertFalse( $encryptor, 'assert if invalid encryptor returns false' );
	}

	/**
	 * Tests the encryption functions
	 */
	public function test_encryption() {
		$encrypted_simple           = push_syndicate_encrypt( $this->simple_string );
		$encrypted_simple_different = push_syndicate_encrypt( $this->simple_string . '1' );
		$encrypted_complex          = push_syndicate_encrypt( $this->complex_array );

		self::assertIsString( $encrypted_simple, 'assert if the string is encrypted' );
		self::assertIsString( $encrypted_complex, 'assert if the array is encrypted' );

		self::assertNotEquals( $encrypted_simple, $encrypted_complex, 'assert that the two different objects have different results' );
		self::assertNotEquals( $encrypted_simple, $encrypted_simple_different, 'assert that the two different strings have different results' );

		return array( $encrypted_simple, $encrypted_complex );
	}

	/**
	 * Tests the decryption functions
	 *
	 * @param array[2] $array_encrypted Array with the encrypted data. First element is a string, second element is array.
	 *
	 * @depends test_encryption
	 */
	public function test_decryption( $array_encrypted ) {
		$decrypted_simple        = push_syndicate_decrypt( $array_encrypted[0] );
		$decrypted_complex_array = push_syndicate_decrypt( $array_encrypted[1] );

		self::assertEquals( $this->simple_string, $decrypted_simple, 'asserts if the decrypted string is the same as the original' );

		self::assertIsArray( $decrypted_complex_array, 'asserts if the decrypted complex data was decrypted as an array' );
		self::assertEquals( $this->complex_array, $decrypted_complex_array, 'check if the decrypted array is the same as the original' );
	}

}
