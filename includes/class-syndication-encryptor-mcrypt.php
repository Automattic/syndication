<?php

/**
 * Class Syndication_Encryptor_OpenSSL
 */
class Syndication_Encryptor_MCrypt extends Syndication_Encryptor {

	/**
	 * @inheritDoc
	 */
	public function encrypt( $data ) {
		$data = serialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.mcrypt_encryptDeprecatedRemoved,PHPCompatibility.Extensions.RemovedExtensions.mcryptDeprecatedRemoved,PHPCompatibility.Constants.RemovedConstants.mcrypt_rijndael_256DeprecatedRemoved,PHPCompatibility.Constants.RemovedConstants.mcrypt_mode_cbcDeprecatedRemoved
		return base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, md5( PUSH_SYNDICATE_KEY ), $data, MCRYPT_MODE_CBC, md5( md5( PUSH_SYNDICATE_KEY ) ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function decrypt( $data, $associative = true ) {
		// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.mcrypt_decryptDeprecatedRemoved,PHPCompatibility.Extensions.RemovedExtensions.mcryptDeprecatedRemoved,PHPCompatibility.Constants.RemovedConstants.mcrypt_rijndael_256DeprecatedRemoved,PHPCompatibility.Constants.RemovedConstants.mcrypt_mode_cbcDeprecatedRemoved
		$data = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5( PUSH_SYNDICATE_KEY ), base64_decode( $data ), MCRYPT_MODE_CBC, md5( md5( PUSH_SYNDICATE_KEY ) ) ), "\0" );
		if ( ! $data ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged
		return @unserialize( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getCipher() {
		return MCRYPT_RIJNDAEL_256; // phpcs:ignore PHPCompatibility.Constants.RemovedConstants.mcrypt_rijndael_256DeprecatedRemoved
	}
}
