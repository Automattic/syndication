<?php

/**
 * Class to handle Admin notices for dismissable user notifications
 */
class Syndication_Logger_Admin_Notice {

	private static $notice_option = 'syn_notices';
	private static $notice_bundles_option = 'syn_notice_bundles';

	private static $dismiss_parameter = 'syn_dismiss';

	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'handle_dismiss_syndication_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_valid_notices' ) );
	}

	/**
	 * Create a admin notice
	 * @param text    $message_text       The message you would like to show
	 * @param string  $message_type       A message type, shown alongside with your message and used to categorize and bundle messages of the same type
	 * @param string  $class              css class applied to the notice eg. 'updated', 'error', 'update-nag'
	 * @param boolean $summarize_multiple setting this to true allows summarizing messages of the same message_type into one message. The message text is then passed through the syn_message_text_multiple filter and all messages of this type can be dismissed at once
	 */
	public static function add_notice( $message_text, $message_type = 'Syndication', $class = 'updated', $summarize_multiple = false ) {
		$notices = get_option( self::$notice_option );

		$changed = false;
		$message_key = md5( $message_type . $message_text );
		if ( ! is_array( $notices ) || ! isset( $notices[$message_type] ) || ! isset( $notices[$message_type][$message_key] ) ) {
			$notices[$message_type][$message_key] = array(
				'message_text' => sanitize_text_field( $message_text ),
				'summarize_multiple' => (boolean) $summarize_multiple,
				'message_type' => sanitize_text_field( $message_type ),
				'class' => sanitize_text_field( $class ),
			);
			$changed = true;
		}

		if ( true === $changed ) {
			update_option( self::$notice_option, $notices );
		}

		return true;
	}

	/**
	 * Evaluate and display valid notices
	 */
	public static function display_valid_notices() {
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		$messages = get_option( self::$notice_option );
		$notice_bundles = get_option( self::$notice_bundles_option );
		$messages_to_display = array();
		$notice_bundles_changed = false;

		if ( !is_array( $messages ) || empty( $messages ) ) {
			return;
		}

		foreach( $messages as $message_type => $message_values ) {
			foreach( $message_values as $message_key => $message_data ) {
				if ( isset( $message_data['summarize_multiple'] ) && true === $message_data['summarize_multiple'] ) {
					$message_text = apply_filters( 'syn_message_text_multiple', $message_data['message_text'], $message_data );
				} else {
					$message_text = apply_filters( 'syn_message_text', $message_data['message_text'], $message_data );
				}

				$new_message_key = md5( $message_type . $message_text );
				$new_message_data = array(
					'message_text' => sanitize_text_field( $message_text ),
					'summarize_multiple' => (boolean) $message_data['summarize_multiple'],
					'message_type' => sanitize_text_field( $message_data['message_type'] ),
					'class' => sanitize_text_field( $message_data['class'] )
				);

				if ( $new_message_key != $message_key ) {
					if ( ! isset( $notice_bundles[$new_message_key] ) || ! in_array( $message_key, $notice_bundles[$new_message_key] ) ) {
						$notice_bundles[$new_message_key][] = $message_key;
						$notice_bundles_changed = true;
					}
				}

				if ( current_user_can( $capability ) ) {
					$messages_to_display[$message_type][$new_message_key] = $new_message_data;
				}
			}
		}

		if ( true === $notice_bundles_changed ) {
			update_option( self::$notice_bundles_option, $notice_bundles );
		}

		foreach( $messages_to_display as $message_type => $message_values ) {
			foreach( $message_values as $message_key => $message_data ) {
				$dismiss_nonce = wp_create_nonce( esc_attr( $message_key ) );
				printf( '<div class="%s"><p>', esc_attr( $message_data['class'] ) );
				printf( __('%1$s : %2$s <a href="%3$s">Hide Notice</a>'), esc_html( $message_type ), wp_kses_post( $message_data['message_text'] ), add_query_arg( array( self::$dismiss_parameter => esc_attr( $message_key ), 'syn_dismiss_nonce' => esc_attr( $dismiss_nonce ) ) ) );
				printf( '</p></div>' );
			}
		}
	}

	/**
	 * Handle dismissing of notices
	 */
	public static function handle_dismiss_syndication_notice() {
		$capability = apply_filters( 'syn_syndicate_cap', 'manage_options' );

		// add nonce
		if ( isset( $_GET[self::$dismiss_parameter] ) && current_user_can( $capability ) ) {

			$dismiss_key = esc_attr( $_GET[self::$dismiss_parameter] );
			$dismiss_nonce = esc_attr( $_GET['syn_dismiss_nonce'] );
			if ( ! wp_verify_nonce( $dismiss_nonce, $dismiss_key ) ) {
				wp_die( __( "Invalid security check" ) );
			}
			$messages = get_option( self::$notice_option );
			$notice_bundles = get_option( self::$notice_bundles_option );

			$dismiss_items = array();
			if ( isset( $notice_bundles[$dismiss_key] ) ) {
				$dismiss_items = $notice_bundles[$dismiss_key];
			} else {
				$dismiss_items = array( $dismiss_key );
			}

			foreach( $messages as $message_type => $message_values ) {
				$message_keys = array_keys( $message_values );
				$dismiss_it = array_intersect( $message_keys, $dismiss_items );
				foreach( $dismiss_it as $dismiss_it_key ) {
					unset( $messages[$message_type][$dismiss_it_key] );
				}
			}

			if ( isset( $notice_bundles[$dismiss_key] ) ) {
				unset( $notice_bundles[$dismiss_key] );
			}

			update_option( self::$notice_option, $messages );
			update_option( self::$notice_bundles_option, $notice_bundles );

		}
	}
}

add_filter( 'syn_message_text_multiple', 'syn_handle_multiple_error_notices', 10, 2 );
function syn_handle_multiple_error_notices( $message, $message_data ) {
	return __( 'There have been multiple errors. Please validate your syndication logs' );
}

add_action( 'push_syndication_site_disabled', 'syn_add_site_disabled_notice', 10, 2 );
function syn_add_site_disabled_notice( $site_id, $count ) {
	Syndication_Logger_Admin_Notice::add_notice( $message_text = sprintf( __( 'Site %d disabled after %d pull failure(s).', 'push-syndication' ), (int) $site_id, (int) $count ), $message_type = 'Syndication site disabled', $class = 'error', $summarize_multiple = false );
}
