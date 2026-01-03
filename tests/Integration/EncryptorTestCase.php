<?php
namespace Automattic\Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class EncryptorMCryptTest
 *
 * @requires extension mcrypt
 */
abstract class EncryptorTestCase extends WPIntegrationTestCase {
	protected $simple_string;
	protected $complex_array;
	protected $encryptor;

	/**
	 * Runs before the test, set-up.
	 */
	public function set_up() {
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

		// Test without associative set to true.
		$decrypted = $this->encryptor->decrypt( $encrypted, false );
		self::assertIsObject( $decrypted, 'assert if the decrypted data is an object' );
		self::assertEquals( $decrypted->element, $this->complex_array['element'], 'assert if the first element is the same' );

	}

	/**
	 * Test the expected cipher
	 */
	abstract public function test_cipher();

}
