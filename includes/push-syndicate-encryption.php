<?php

function push_syndicate_get_cipher() {
	$cipher = 'aes-256-cbc';

	if ( function_exists( 'mcrypt_encrypt' ) ) {
		return MCRYPT_RIJNDAEL_256;
	}

	if ( in_array( $cipher, openssl_get_cipher_methods(), true ) ) {
		return array(
			'cipher' => $cipher,
			'iv'     => substr( md5( md5( PUSH_SYNDICATE_KEY ) ), 0, 16 ),
			'key'    => md5( PUSH_SYNDICATE_KEY ),
		);
	}

	return false; // @TODO: return another default cipher? return exception?
}

function push_syndicate_encrypt( $data ) {
	// @todo: replace mcrypt with openssl. problem: Rijndael AES is not available on openssl;s AES-256.
	// Will most likely break backwards compatibility with older keys
	// https://stackoverflow.com/questions/49997338/mcrypt-rijndael-256-to-openssl-aes-256-ecb-conversion

	// Backwards compatibility with PHP < 7.2.
	if ( function_exists( 'mcrypt_encrypt' ) ) {
		// @codingStandardsIgnoreStart
		$data = serialize( $data );
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5(PUSH_SYNDICATE_KEY), $data, MCRYPT_MODE_CBC, md5(md5(PUSH_SYNDICATE_KEY))));
		// @codingStandardsIgnoreEnd
	}

	$data   = wp_json_encode( $data );
	$cipher = push_syndicate_get_cipher();

	if ( ! $cipher ) {
		return $data;
	}

	$encrypted_data = openssl_encrypt( $data, $cipher['cipher'], $cipher['key'], 0, $cipher['iv'] );
	return base64_encode( $encrypted_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

}

function push_syndicate_decrypt( $data ) {

	// Backwards compatibility with PHP < 7.2.
	if ( function_exists( 'mcrypt_encrypt' ) ) {
		// @codingStandardsIgnoreStart
		$data = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5( PUSH_SYNDICATE_KEY ), base64_decode( $data ), MCRYPT_MODE_CBC, md5( md5( PUSH_SYNDICATE_KEY ) ) ), "\0" );
		if ( ! $data ) {
			return false;
		}
		return @unserialize( $data );
		// @codingStandardsIgnoreEnd
	}

	$cipher = push_syndicate_get_cipher();

	if ( ! $cipher ) {
		return $data;
	}

	$data = openssl_decrypt( base64_decode( $data ), $cipher['cipher'], $cipher['key'], 0, $cipher['iv'] ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

	if ( ! $data ) {
		return false;
	}

	return json_decode( $data );
}
