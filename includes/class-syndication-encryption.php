<?php

/**
 * Class Syndication_Encryption
 */
class Syndication_Encryption {

	/**
	 * Stores the current Syndication_Encryptor, used for the encryption/decryption operations.
	 *
	 * @var Syndication_Encryptor
	 */
	private $encryptor;

	/**
	 * Syndication_Encryption constructor.
	 *
	 * @param Syndication_Encryptor $encryptor Encryptor to be used.
	 */
	public function __construct( Syndication_Encryptor $encryptor ) {
		$this->encryptor = $encryptor;
	}

	/**
	 * Returns the best possible Encryptor, given the current environment.
	 *
	 * @return Syndication_Encryptor
	 */
	public function get_encryptor() {
		return $this->encryptor;
	}

	/**
	 * Set the Encryptor that will be used for the encryption and decryption operations.
	 *
	 * @param Syndication_Encryptor $encryptor Encryptor to be used in the encryption.
	 *
	 * @return Syndication_Encryptor Returns the encryptor
	 */
	public function set_encryptor( Syndication_Encryptor $encryptor ) {
		$this->encryptor = $encryptor;
		return $encryptor;
	}

	/**
	 * Given $data, encrypt it using a Syndication_Encryptor and return the encrypted string.
	 *
	 * @param string|array|object $data the data to be encrypted.
	 *
	 * @return false|string
	 */
	public function encrypt( $data ) {
		$encryptor = $this->get_encryptor();
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
	public function decrypt( $data, $associative = true ) {
		$encryptor = $this->get_encryptor();
		return $encryptor->decrypt( $data, $associative );
	}

}
