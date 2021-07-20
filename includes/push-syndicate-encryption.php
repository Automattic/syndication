<?php

/**
 * Encrypts data.
 *
 * @param string $data The data to encrypt.
 *
 * @return false|string
 */
function push_syndicate_encrypt( $data ) {
	return Syndication_Encryption::encrypt( $data );
}

/**
 * Decrypts data.
 *
 * @param string $data        The encrypted data to decrypt.
 * @param bool   $associative If true, returns as an associative array. Otherwise returns as a class.
 *
 * @return array|false|object
 */
function push_syndicate_decrypt( $data, $associative = true ) {
	return Syndication_Encryption::decrypt( $data, $associative );
}
