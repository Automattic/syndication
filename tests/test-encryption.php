<?php

/**
 * Class EncryptionTest
 */
class EncryptionTest extends WP_UnitTestCase {
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
	 * Runs after the test, clean-up
	 */
	public function tearDown() {
		// Nothing yet.
	}

	/**
	 * Tests if the cipher is available on PHP < 7.1 and if the function is returning the correct cipher.
	 *
	 * If using a PHP version older than 7.1, it will expect a mcrypt cipher.
	 *
	 * @requires PHP < 7.1
	 */
	public function test_cypher_pre_72() {
		// phpcs:ignore PHPCompatibility.Constants.RemovedConstants.mcrypt_rijndael_256DeprecatedRemoved
		$expected_cipher = MCRYPT_RIJNDAEL_256;

		// Test the cipher.
		$cipher = push_syndicate_get_cipher();
		$this->assertSame( $expected_cipher, $cipher );
	}

	/**
	 * Tests if the cipher is available on PHP >= 7.1 and if the function is returning the correct cipher.
	 *
	 * If using a PHP 7.1 or later, it should use openssl instead of mcrypt.
	 *
	 * @requires PHP >= 7.1
	 */
	public function test_cypher() {
		// Test the cipher.
		$cipher_data = push_syndicate_get_cipher();

		// Test if is an array.
		$this->assertIsArray( $cipher_data, 'assert if the cipher data is array' );
		$this->assertCount( 3, $cipher_data, 'assert if cipher data have three elements' );

		$cipher = $cipher_data['cipher'];
		$iv     = $cipher_data['iv'];
		$key    = $cipher_data['key'];

		// test cipher.
		$expected_cipher = 'aes-256-cbc';
		$this->assertEquals( $expected_cipher, $cipher, 'assert if cipher is available' );

		// test key.
		$this->assertEquals( $key, md5( PUSH_SYNDICATE_KEY ), 'assert if the key is generated as expected' );

		// test iv.
		$this->assertEquals( 16, strlen( $iv ), 'assert iv size (must be 16)' );
		$generated_iv = substr( md5( md5( PUSH_SYNDICATE_KEY ) ), 0, 16 );
		$this->assertEquals( $generated_iv, $iv, 'assert if generated iv is as expected' );
	}

	/**
	 * Tests the encryption and decryption methods.
	 */
	public function test_encryption() {
		$encrypted_simple           = push_syndicate_encrypt( $this->simple_string );
		$encrypted_simple_different = push_syndicate_encrypt( $this->simple_string . '1' );
		$encrypted_complex          = push_syndicate_encrypt( $this->complex_array );

		// Because older WP versions might use older phpunit versions, assertIsString() might not be available.
		if ( method_exists( $this, 'assertIsString' ) ) {
			$this->assertIsString( $encrypted_simple, 'assert if the string is encrypted' );
			$this->assertIsString( $encrypted_complex, 'assert if the array is encrypted' );
		} else {
			$this->assertTrue( is_string( $encrypted_simple ), 'assert if the string is encrypted (is_string)' );
			$this->assertTrue( is_string( $encrypted_complex ), 'assert if the array is encrypted (is_string)' );
		}

		$this->assertNotEquals( $encrypted_simple, $encrypted_complex, 'assert that the two different objects have different results' );
		$this->assertNotEquals( $encrypted_simple, $encrypted_simple_different, 'assert that the two different strings have different results' );

		$decrypted_simple        = push_syndicate_decrypt( $encrypted_simple );
		$decrypted_complex_array = push_syndicate_decrypt( $encrypted_complex );

		$this->assertEquals( $this->simple_string, $decrypted_simple, 'asserts if the decrypted string is the same as the original' );

		// Because older WP versions might use older phpunit versions, assertIsArray() might not be available.
		if ( method_exists( $this, 'assertIsArray' ) ) {
			$this->assertIsArray( $decrypted_complex_array, 'asserts if the decrypted complex data was decrypted as an array' );
		} else {
			$this->assertTrue( is_array( $decrypted_complex_array ), 'asserts if the decrypted complex data was decrypted as an array (is_array)' );
		}

		$this->assertEquals( $this->complex_array, $decrypted_complex_array, 'check if the decrypted array is the same as the original' );
	}


}
