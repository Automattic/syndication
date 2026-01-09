<?php

/**
 * Class Syndication_Encryptor_OpenSSL
 */
class Syndication_Encryptor_OpenSSL implements Syndication_Encryptor {

	/**
	 * The cipher to be used for encryption.
	 *
	 * @var string
	 */
	private $cipher = 'aes-256-cbc';

	/**
	 * Encrypts data using OpenSSL.
	 *
	 * @param mixed $data Data to encrypt.
	 *
	 * @return string Encrypted data.
	 */
	public function encrypt( $data ) {
		$data   = wp_json_encode( $data );
		$cipher = $this->get_cipher();

		if ( ! $cipher ) {
			return $data;
		}

		$encrypted_data = openssl_encrypt( $data, $cipher['cipher'], $cipher['key'], 0, $cipher['iv'] );
		return base64_encode( $encrypted_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypts data using OpenSSL.
	 *
	 * @param string $data        Encrypted data to decrypt.
	 * @param bool   $associative Whether to return associative array. Default true.
	 *
	 * @return mixed|false Decrypted data or false on failure.
	 */
	public function decrypt( $data, $associative = true ) {
		$cipher = $this->get_cipher();

		if ( ! $cipher ) {
			return $data;
		}

		$data = openssl_decrypt( base64_decode( $data ), $cipher['cipher'], $cipher['key'], 0, $cipher['iv'] ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! $data ) {
			return false;
		}

		return json_decode( $data, $associative );
	}

	/**
	 * Gets the cipher configuration.
	 *
	 * @return array|false Cipher configuration array or false if cipher unavailable.
	 */
	public function get_cipher() {
		if ( in_array( $this->cipher, openssl_get_cipher_methods(), true ) ) {
			return array(
				'cipher' => $this->cipher,
				'iv'     => substr( md5( md5( PUSH_SYNDICATE_KEY ) ), 0, 16 ),
				'key'    => md5( PUSH_SYNDICATE_KEY ),
			);
		}

		return false; // @TODO: return another default cipher? return exception?
	}
}
