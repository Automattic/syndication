<?php

/**
 * Interface Syndication_Encryptor
 */
interface Syndication_Encryptor {

	/**
	 * Encrypts data.
	 *
	 * @param string|array $data Data to be encrypted.
	 *
	 * @return string
	 */
	public function encrypt( $data );

	/**
	 * Decrypts data
	 *
	 * @param string $data        Data to be decrypted.
	 * @param bool   $associative If true, returns as an associative array. Otherwise returns as a class.
	 *
	 * @return mixed
	 */
	public function decrypt( $data, $associative = true );

	/**
	 * Returns the cipher being used. It can be a string, or a array with the cipher, key and iv.
	 *
	 * @return string|array
	 */
	public function get_cipher();
}
