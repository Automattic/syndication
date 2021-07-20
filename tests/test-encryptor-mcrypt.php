<?php
namespace Syndication\Tests;

use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Class EncryptorMCryptTest
 *
 * @requires extension mcrypt
 */
class EncryptorMCryptTest extends WPIntegrationTestCase {
	private $simple_string;
	private $complex_array;
	private $encryptor;
	private $error_reporting;


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

		$this->encryptor = new \Syndication_Encryptor_MCrypt();

		// Disable deprecation warning for this test, as it will run on PHP 7.1. This test will only ensure functionality of the
		// Syndication_Encryptor_MCrypt class.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
		$this->error_reporting = error_reporting( error_reporting() & ~E_DEPRECATED );
	}

	/**
	 * Runs after the test.
	 */
	public function tearDown() {
		// Restore original error reporting.
		error_reporting( $this->error_reporting ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
		parent::tearDown();
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
	 * Test the expected cipher for mcrypt
	 */
	public function test_cipher() {
		$expected_cipher = MCRYPT_RIJNDAEL_256; // phpcs:ignore PHPCompatibility.Constants.RemovedConstants.mcrypt_rijndael_256DeprecatedRemoved
		$cipher          = $this->encryptor->getCipher();

		self::assertSame( $expected_cipher, $cipher );
	}

}
