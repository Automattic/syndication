<?php
/**
 * Unit tests for OpenSSLEncryptor.
 *
 * @package Automattic\Syndication\Tests\Unit\Infrastructure\Encryption
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Infrastructure\Encryption;

use Automattic\Syndication\Infrastructure\Encryption\OpenSSLEncryptor;
use Automattic\Syndication\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Test case for OpenSSLEncryptor.
 *
 * @group unit
 * @covers \Automattic\Syndication\Infrastructure\Encryption\OpenSSLEncryptor
 */
class OpenSSLEncryptorTest extends TestCase {

	/**
	 * Encryptor instance.
	 *
	 * @var OpenSSLEncryptor
	 */
	private OpenSSLEncryptor $encryptor;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$this->encryptor = new OpenSSLEncryptor( 'test-key' );
	}

	/**
	 * Test encrypting a string returns a non-empty string.
	 */
	public function test_encrypt_string_returns_non_empty_string(): void {
		$result = $this->encryptor->encrypt( 'secret data' );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertNotEquals( 'secret data', $result );
	}

	/**
	 * Test encrypting an array returns a non-empty string.
	 */
	public function test_encrypt_array_returns_non_empty_string(): void {
		$data   = array(
			'username' => 'admin',
			'password' => 'secret123',
		);
		$result = $this->encryptor->encrypt( $data );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test decrypting returns original string.
	 */
	public function test_decrypt_returns_original_string(): void {
		$original  = 'secret data';
		$encrypted = $this->encryptor->encrypt( $original );
		$decrypted = $this->encryptor->decrypt( $encrypted );

		$this->assertEquals( $original, $decrypted );
	}

	/**
	 * Test decrypting returns original array.
	 */
	public function test_decrypt_returns_original_array(): void {
		$original = array(
			'username' => 'admin',
			'password' => 'secret123',
		);

		$encrypted = $this->encryptor->encrypt( $original );
		$decrypted = $this->encryptor->decrypt( $encrypted );

		$this->assertIsArray( $decrypted );
		$this->assertEquals( $original, $decrypted );
	}

	/**
	 * Test decrypting empty string returns null.
	 */
	public function test_decrypt_empty_string_returns_null(): void {
		$result = $this->encryptor->decrypt( '' );

		$this->assertNull( $result );
	}

	/**
	 * Test decrypting invalid data returns null.
	 */
	public function test_decrypt_invalid_data_returns_null(): void {
		$result = $this->encryptor->decrypt( 'not-valid-encrypted-data!' );

		$this->assertNull( $result );
	}

	/**
	 * Test different keys produce different encrypted output.
	 */
	public function test_different_keys_produce_different_output(): void {
		$encryptor1 = new OpenSSLEncryptor( 'key-one' );
		$encryptor2 = new OpenSSLEncryptor( 'key-two' );

		$data = 'sensitive information';

		$encrypted1 = $encryptor1->encrypt( $data );
		$encrypted2 = $encryptor2->encrypt( $data );

		$this->assertNotEquals( $encrypted1, $encrypted2 );
	}

	/**
	 * Test data encrypted with one key cannot be decrypted with another.
	 */
	public function test_wrong_key_cannot_decrypt(): void {
		$encryptor1 = new OpenSSLEncryptor( 'key-one' );
		$encryptor2 = new OpenSSLEncryptor( 'key-two' );

		$data      = 'sensitive information';
		$encrypted = $encryptor1->encrypt( $data );

		$decrypted = $encryptor2->decrypt( $encrypted );

		$this->assertNotEquals( $data, $decrypted );
	}

	/**
	 * Test encrypting nested array structure.
	 */
	public function test_encrypt_nested_array(): void {
		$data = array(
			'credentials' => array(
				'username' => 'admin',
				'password' => 'secret',
			),
			'settings'    => array(
				'enabled' => true,
				'timeout' => 30,
			),
		);

		$encrypted = $this->encryptor->encrypt( $data );
		$decrypted = $this->encryptor->decrypt( $encrypted );

		$this->assertEquals( $data, $decrypted );
	}
}
