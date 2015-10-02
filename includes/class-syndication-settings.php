<?php

namespace Automattic\Syndication;

/**
 * Syndication Settings
 *
 * The role of the syndication settings class is to initialize and
 * retrieve all syndication settings.
 *
 * @package Automattic\Syndication
 */
class Syndication_Settings {

	protected $push_syndicate_default_settings;
	protected $push_syndicate_settings;

	/**
	 * Construct the Syndication Settings class.
	 */
	function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Set up the syndication settings, combining defaults with stored options.
	 */
	public function init() {

		$this->push_syndicate_default_settings = array(
			'selected_pull_sitegroups'  => array(),
			'selected_post_types'       => array( 'post' ),
			'delete_pushed_posts'       => 'off',
			'pull_time_interval'        => '3600',
			'update_pulled_posts'       => 'off',
			'client_id'                 => '',
			'client_secret'             => '',
		);

		/**
		 * Merge the values stored in options with the default values.
		 */
		$this->push_syndicate_settings = wp_parse_args(
			/* This filter is documented in includes/admin/class-settings-screen.php */
			(array) get_option( 'push_syndicate_settings' ),
			$this->push_syndicate_default_settings
		);
	}

	/**
	 * Get a setting value.
	 *
	 * @param $setting_id string The id of the setting to retrieve.
	 *
	 * @return mixed             The setting value, or false if unset.
	 */
	public function get_setting( $setting_id, $default =false ) {
		return isset( $this->push_syndicate_settings[ $setting_id ] ) ? $this->push_syndicate_settings[ $setting_id ] : $default;
	}

	static function syndicate_encrypt( $data ) {

		$data = serialize( $data );
		return base64_encode(
			mcrypt_encrypt(
				MCRYPT_RIJNDAEL_256,
				md5( PUSH_SYNDICATE_KEY ),
				$data,
				MCRYPT_MODE_CBC,
				md5( md5( PUSH_SYNDICATE_KEY ) )
			)
		);

	}

	static function syndicate_decrypt( $data ) {

		$data = rtrim(
			mcrypt_decrypt(
				MCRYPT_RIJNDAEL_256 ,
				md5( PUSH_SYNDICATE_KEY ) ,
				base64_decode( $data ),
				MCRYPT_MODE_CBC,
				md5(
					md5( PUSH_SYNDICATE_KEY )
				)
			), '\0'
		);
		if ( ! $data ) {
			return false;
		}

		return @unserialize( $data );

	}

	// checking user capability
	public function current_user_can_syndicate() {
		/**
		 * Filter the capability required for a user to syndication.
		 *
		 * @param string $capability The capability required to syndicate content. Default is 'manage_options'.
		 */
		$syndicate_cap = apply_filters( 'syn_syndicate_cap', 'manage_options' );
		return current_user_can( $syndicate_cap );
	}
}
