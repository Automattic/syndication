<?php

/**
 * Class Syndication_Encryptor_OpenSSL
 */
class Syndication_Encryptor_OpenSSL extends Syndication_Encryptor {

	/**
	 * The cipher to be used for encryption.
	 *
	 * @var string
	 */
	private $cipher = 'aes-256-cbc';

	/**
	 * @inheritDoc
	 */
	public function encrypt( $data ) {
		$data   = wp_json_encode( $data );
		$cipher = $this->getCipher();

		if ( ! $cipher ) {
			return $data;
		}

		$encrypted_data = openssl_encrypt( $data, $cipher['cipher'], $cipher['key'], 0, $cipher['iv'] );
		return base64_encode( $encrypted_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @inheritDoc
	 */
	public function decrypt( $data, $associative = true ) {
		$cipher = $this->getCipher();

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
	 * @inheritDoc
	 */
	public function getCipher() {
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
