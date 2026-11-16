<?php
/**
 * Unit tests for Syndication_Encryption.
 *
 * @package Syndication
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit;

/**
 * Test case for Syndication_Encryption.
 *
 * @group unit
 * @covers Syndication_Encryption
 */
class EncryptionTest extends TestCase {

	/**
	 * Load the facade and its interface.
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/includes/interface-syndication-encryptor.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-syndication-encryption.php';
	}

	/**
	 * Encrypting hands the data to the encryptor and returns its result.
	 */
	public function test_encrypt_forwards_to_the_encryptor(): void {
		$encryptor = $this->createMock( \Syndication_Encryptor::class );
		$encryptor->expects( $this->once() )
			->method( 'encrypt' )
			->with( array( 'plain' ) )
			->willReturn( 'cipher text' );

		$this->assertSame( 'cipher text', ( new \Syndication_Encryption( $encryptor ) )->encrypt( array( 'plain' ) ) );
	}

	/**
	 * Decrypting hands the data and the associative flag to the encryptor.
	 *
	 * @dataProvider data_associative
	 *
	 * @param array $args     Arguments passed to decrypt().
	 * @param bool  $expected Associative flag the encryptor should receive.
	 */
	public function test_decrypt_forwards_to_the_encryptor( array $args, bool $expected ): void {
		$encryptor = $this->createMock( \Syndication_Encryptor::class );
		$encryptor->expects( $this->once() )
			->method( 'decrypt' )
			->with( 'cipher text', $expected )
			->willReturn( array( 'plain' ) );

		$this->assertSame( array( 'plain' ), ( new \Syndication_Encryption( $encryptor ) )->decrypt( ...$args ) );
	}

	/**
	 * Associative flags passed to decrypt(), and the default.
	 *
	 * @return array<string, array{array, bool}>
	 */
	public function data_associative(): array {
		return array(
			'default'         => array( array( 'cipher text' ), true ),
			'as an object'    => array( array( 'cipher text', false ), false ),
			'as an array'     => array( array( 'cipher text', true ), true ),
		);
	}
}
