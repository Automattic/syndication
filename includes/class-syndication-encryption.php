<?php

require_once dirname( __FILE__ ) . '/class-syndication-encryptor.php';
require_once dirname( __FILE__ ) . '/class-syndication-encryptor-openssl.php';
require_once dirname( __FILE__ ) . '/class-syndication-encryptor-mcrypt.php';

/**
 * Class Syndication_Encryption
 */
class Syndication_Encryption {

	/**
	 * Stores the current Syndication_Encryptor, used for the encryption/decryption operations.
	 *
	 * @var Syndication_Encryptor
	 */
	private static $encryptor;

	/**
	 * Returns the best possible Encryptor, given the current environment.
	 *
	 * @return Syndication_Encryptor
	 */
	public static function get_encryptor() {
		if ( isset( self::$encryptor ) && self::$encryptor instanceof Syndication_Encryptor ) {
			return self::$encryptor;
		}

		// If mcrypt is available ( PHP < 7.1 ), use it instead.
		if ( defined( 'MCRYPT_RIJNDAEL_256' ) ) {
			self::$encryptor = new Syndication_Encryptor_MCrypt();
			return self::$encryptor;
		}

		self::$encryptor = new Syndication_Encryptor_OpenSSL();
		return self::$encryptor;
	}

	/**
	 * Given $data, encrypt it using a Syndication_Encryptor and return the encrypted string.
	 *
	 * @param string|array|object $data the data to be encrypted.
	 *
	 * @return false|string
	 */
	public static function encrypt( $data ) {
		$encryptor = self::get_encryptor();
		return $encryptor->encrypt( $data );
	}

	/**
	 * Decrypts an encrypted $data using a Syndication_Encryptor, and returns the decrypted object.
	 *
	 * @param string $data        The encrypted data.
	 * @param bool   $associative If true, returns as an associative array. Otherwise returns as a class.
	 *
	 * @return false|array|object
	 */
	public static function decrypt( $data, $associative = true ) {
		$encryptor = self::get_encryptor();
		return $encryptor->decrypt( $data, $associative );
	}

}
