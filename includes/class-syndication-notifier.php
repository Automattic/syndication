<?php
/**
 * Syndication_Notifier implements a unified notification mechanism for the syndication plugin.
 *
 * @since 2.1
 * @package Automattic\Syndication;
 */

namespace Automattic\Syndication;

/**
 * Class Syndication_Notifier
 *
 * @since 2.1
 * @package Automattic\Syndication;
 */
class Syndication_Notifier {
	/**
	 * Construct
	 *
	 * Syndication_Notifier constructor.
	 *
	 * @since 2.1
	 */
	public function __construct() {
		add_action( 'syn_post_pull_new_post', array( $this, 'notify_new' ), 10, 3 );
		add_action( 'syn_post_pull_edit_post', array( $this, 'notify_update' ), 10, 3 );
		add_action( 'syn_post_push_delete_post', array( $this, 'notify_delete' ), 10, 4 );
		add_action( 'syn_post_push_new_post', array( $this, 'notify_new' ), 10, 3 );
		add_action( 'syn_post_push_edit_post', array( $this, 'notify_update' ), 10, 3 );
		add_action( 'syn_post_pull_endpoint_processed', array( $this, 'notify_processed' ), 10, 2 );
	}

	/**
	 * Notify New
	 *
	 * Notify about a new post creation event usually implemented via action hook.
	 *
	 * `do_action( 'syn_post_pull_new_post', $result, $post, $site, $transport_type, $client );`
	 * `do_action( 'syn_post_push_new_post', $result, $post_ID, $site, $transport_type, $client, $info );`
	 *
	 * @since 2.1
	 * @param mixed  $result         Result object of previous wp_insert_post action.
	 * @param mixed  $post           Post object or post_id.
	 * @param object $site_id        ID of the site doing the syndication.
	 */
	public function notify_new( $result, $post, $site_id ) {
		$this->notify_post_event( 'create', $result, $post, $site_id );
	}

	/**
	 * Notify about a post update event usually implemented via action hook.
	 * do_action( 'syn_post_pull_edit_post', $result, $post, $site, $transport_type, $client );
	 * do_action( 'syn_post_push_edit_post', $result, $post_ID, $site, $transport_type, $client, $info );
	 *
	 * @since 2.1
	 * @param  mixed  $result         Result object of previous wp_insert_post action.
	 * @param  mixed  $post           Post object or post_id.
	 * @param  object $site_id        ID of the site doing the syndication.
	 */
	public function notify_update( $result, $post, $site_id ) {
		$this->notify_post_event( 'update', $result, $post, $site_id );
	}

	/**
	 * Notify about a post delete event usually implemented via action hook.
	 * do_action( 'syn_post_push_delete_post', $result, $ext_ID, $post_ID, $site_ID, $transport_type, $client );
	 *
	 * @since 2.1
	 * @param  mixed  $result         Result object of previous wp_insert_post action.
	 * @param  mixed  $external_id    External post post_id.
	 * @param  mixed  $post           Post object or post_id.
	 * @param  object $site_id        ID of the site doing the syndication.
	 */
	public function notify_delete( $result, $external_id, $post, $site_id ) {
		$this->notify_post_event( 'delete', $result, $post, $site_id );
	}

	/**
	 * Notification for when an endpoint is finished being processed.
	 *
	 * @since 2.1
	 * @param integer $site_id          The site ID that was processed.
	 * @param array   $processed_posts  An array of post ID's that were processed.
	 * @return bool
	 */
	public function notify_processed( $site_id, $processed_posts ) {
		if ( ! $this->should_notify( 'processed' ) ) {
			return false;
		}

		if ( count( $processed_posts ) > 0 ) {
			$message = sprintf(
				_n( '%1$d post was', '%1$d posts were', count( $processed_posts ), 'push-syndication' ) . ' ' . __( 'successfully processed on %2$s.', 'push-syndication' ),
				count( $processed_posts ),
				'<a href="' . admin_url( 'post.php?post=' . intval( $site_id ) . '&action=edit' ) . '">' . __( 'Endpoint ID: ', 'push-syndication' ) . intval( $site_id ) . '</a>'
			);

			$this->send_notification( __( 'Syndication Endpoint Processed', 'push-syndication' ), $message );
		}
	}

	/**
	 * Prepares data for the post level notify events.
	 *
	 * @param  string $event          Type of event new/update/delete.
	 * @param  mixed  $result         Result object of previous wp_insert_post action.
	 * @param  mixed  $post           Post object or post_id.
	 * @param  object $site_id        ID of the site doing the syndication.
	 * @return mixed
	 */
	private function notify_post_event( $event, $result, $post, $site_id ) {
		if ( ! $this->should_notify( $event ) ) {
			return false;
		}

		if ( false === $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$message = __( 'Syndication on %s failed. The following error occurred:', 'push-syndication' );
				$message .= "\n" . $result->get_error_message();
			} else {
				$message = __( 'Syndication on %s failed.', 'push-syndication' );
			}

			$message = sprintf(
				$message,
				'<a href="' . admin_url( 'post.php?post=' . intval( $site_id ) . '&action=edit' ) . '">' . __( 'Endpoint ID: ', 'push-syndication' ) . intval( $site_id ) . '</a>'
			);

			$this->send_notification( __( 'Syndication Failure Notification', 'push-syndication' ), $message );
		} else {
			$message = sprintf(
				__( 'Syndication on %s succeeded.', 'push-syndication' ),
				'<a href="' . admin_url( 'post.php?post=' . intval( $site_id ) . '&action=edit' ) . '">' . __( 'Endpoint ID: ', 'push-syndication' ) . intval( $site_id ) . '</a>'
			);

			$message .= sprintf(
				' %s %s.',
				ucwords( $this->action_verb( $event ) ),
				'<a href="' . admin_url( 'post.php?post=' . intval( $post->post_data['ID'] ) . '&action=edit' ) . '">' . esc_html( $post->post_data['post_title'] ) . '</a>'
			);

			$this->send_notification( __( 'Syndication Success Notification', 'push-syndication' ), $message );
		}
	}

	/**
	 * Converts and event action in to it's verb counterpart.
	 *
	 * @since 2.1
	 * @param string $event The event name.
	 * @return mixed
	 */
	public function action_verb( $event ) {
		switch ( $event ) {
			case 'create':
				return __( 'created', 'push-syndication' );
			case 'update':
				return __( 'updated', 'push-syndication' );
			case 'delete':
				return __( 'deleted', 'push-syndication' );
		}

		return '';
	}

	/**
	 * Should Notify
	 *
	 * Check to see if we should send a notification of the syndication based on
	 * how the settings are configured.
	 *
	 * @since 2.1
	 * @param string $event The event that is currently being run.
	 * @return bool
	 */
	public function should_notify( $event ) {
		global $settings_manager;

		$notification_methods = $settings_manager->get_setting( 'notification_methods' );

		if ( ! empty( $notification_methods ) ) {
			foreach ( $notification_methods as $method ) {
				switch ( $method ) {
					case 'email':
						$notification_types = $settings_manager->get_setting( 'notification_email_types' );
						break;
					case 'slack':
						$notification_types = $settings_manager->get_setting( 'notification_slack_types' );
						break;
				}

				if ( ! empty( $notification_types ) && is_array( $notification_types ) && in_array( $event, $notification_types, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Send notification
	 *
	 * @since 2.1
	 * @param string $subject The message subject.
	 * @param string $message The actual message.
	 */
	private function send_notification( $subject, $message ) {
		global $settings_manager;

		$notification_methods = $settings_manager->get_setting( 'notification_methods' );

		if ( ! empty( $notification_methods ) ) {
			foreach ( $notification_methods as $method ) {
				switch ( $method ) {
					case 'email':
						$this->email_notification( $subject, $message );
						break;
					case 'slack':
						$this->slack_notification( $subject, $message );
						break;
				}
			}
		}
	}

	/**
	 * Sends an email notification if an email is set in the settings.
	 *
	 * @since 2.1
	 * @param string $subject The subject of the email.
	 * @param string $message The message to send.
	 */
	private function email_notification( $subject, $message ) {
		global $settings_manager;

		$email = $settings_manager->get_setting( 'notification_email_address' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$sent = wp_mail(
			$email,
			$subject,
			$message,
			$headers
		);

		if ( ! $sent ) {
			Syndication_Logger::log_post_info( false, 'posts_processed', __( 'Failed to send email notification. Please ensure your server is setup to send email using wp_mail()', 'push-syndication' ), null, array() );
		}
	}

	/**
	 * Sends a Slack notification if a webhook address is set.
	 *
	 * @since 2.1
	 * @param string $subject The subject of the message.
	 * @param string $message The message to send.
	 */
	private function slack_notification( $subject, $message ) {
		global $settings_manager;

		$slack_webhook = $settings_manager->get_setting( 'notification_slack_webhook' );

		if ( empty( $slack_webhook ) || ! filter_var( $slack_webhook, FILTER_VALIDATE_URL ) ) {
			return;
		}

		$payload = wp_json_encode(
			array(
				'username' => 'Syndication',
				'text'     => $subject . "\n" . $this->format_slack_message( $message ),
			)
		);

		$response = wp_remote_post( $slack_webhook, array( 'body' => $payload ) );

		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			Syndication_Logger::log_post_info( false, 'posts_processed', __( 'Failed to send Slack notification. Please ensure that the webhook URL entered in the settings is correct', 'push-syndication' ), null, array() );
		}
	}

	/**
	 * Parses a message and converts HTML links to correct format for Slack.
	 *
	 * @see https://api.slack.com/docs/message-formatting#linking_to_urls
	 *
	 * @since 2.1
	 * @param string $message The message to parse.
	 * @return mixed
	 */
	public function format_slack_message( $message ) {
		$message = str_replace( 'a href="', '', $message );
		$message = str_replace( '">', '|', $message );
		$message = str_replace( '</a>', '>', $message );
		return $message;
	}
}