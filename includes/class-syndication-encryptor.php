<?php

/**
 * Class Syndication_Encryptor
 */
abstract class Syndication_Encryptor {

	/**
	 * @param $data
	 *
	 * @return mixed
	 */
	abstract public function encrypt( $data );

	/**
	 * @param $data
	 * @param bool   $associative If true, returns as an associative array. Otherwise returns as a class.
	 *
	 * @return mixed
	 */
	abstract public function decrypt( $data, $associative = true );

	/**
	 * @return mixed
	 */
	abstract public function getCipher();
}
