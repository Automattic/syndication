<?php
/**
 * Encryption helper functions for syndication.
 *
 * Provides global wrapper functions for encrypting and decrypting
 * sensitive syndication data using the configured encryptor.
 *
 * @package Syndication
 */

/**
 * Encrypts data.
 *
 * @param string $data The data to encrypt.
 *
 * @return false|string
 */
function push_syndicate_encrypt( $data ) {
	global $push_syndication_encryption; // @todo: move from global to WP_Push_Syndication_Server attribute
	return $push_syndication_encryption->encrypt( $data );
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
	global $push_syndication_encryption; // @todo: move from global to WP_Push_Syndication_Server attribute
	return $push_syndication_encryption->decrypt( $data, $associative );
}
