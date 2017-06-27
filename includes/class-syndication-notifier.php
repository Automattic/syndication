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
		add_action( 'syn_post_pull_new_post', array( $this, 'notify_new' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( $this, 'notify_update' ), 10, 5 );
		add_action( 'syn_post_push_delete_post', array( $this, 'notify_delete' ), 10, 6 );
		add_action( 'syn_post_push_new_post', array( $this, 'notify_new' ), 10, 5 );
		add_action( 'syn_post_push_edit_post', array( $this, 'notify_update' ), 10, 5 );
	}

	/**
	 * Notify New
	 *
	 * Notify about a new post creation event usually implemented via action hook
	 *
	 * `do_action( 'syn_post_pull_new_post', $result, $post, $site, $transport_type, $client );`
	 * `do_action( 'syn_post_push_new_post', $result, $post_ID, $site, $transport_type, $client, $info );`
	 *
	 * @since 2.1
	 * @param mixed  $result         Result object of previous wp_insert_post action
	 * @param mixed  $post           Post object or post_id
	 * @param object $site           Post object for the site doing the syndication
	 * @param string $transport_type Post meta syn_transport_type for site
	 * @param object $client         Syndication_Client class
	 */
	public function notify_new( $result, $post, $site, $transport_type, $client ) {
		$this->notify_post_event( 'new', $result, $post, $site, $transport_type, $client );
	}

	/**
	 * Notify about a post update event
	 * usually implemented via action hook
	 * do_action( 'syn_post_pull_edit_post', $result, $post, $site, $transport_type, $client );
	 * do_action( 'syn_post_push_edit_post', $result, $post_ID, $site, $transport_type, $client, $info );
	 *
	 * @param  mixed  $result         Result object of previous wp_insert_post action
	 * @param  mixed  $post           Post object or post_id
	 * @param  object $site           Post object for the site doing the syndication
	 * @param  string $transport_type Post meta syn_transport_type for site
	 * @param  object $client         Syndication_Client class
	 */
	public function notify_update( $result, $post, $site, $transport_type, $client ) {
		$this->notify_post_event( 'update', $result, $post, $site, $transport_type, $client );
	}

	/**
	 * Notify about a post delete event
	 * usually implemented via action hook
	 * do_action( 'syn_post_push_delete_post', $result, $ext_ID, $post_ID, $site_ID, $transport_type, $client );
	 *
	 * @param  mixed  $result         Result object of previous wp_insert_post action
	 * @param  mixed  $external_id    External post post_id
	 * @param  mixed  $post           Post object or post_id
	 * @param  object $site           Post object for the site doing the syndication
	 * @param  string $transport_type Post meta syn_transport_type for site
	 * @param  object $client         Syndication_Client class
	 */
	public function notify_delete( $result, $external_id, $post, $site, $transport_type, $client ) {
		$this->notify_post_event( 'delete', $result, $post, $site, $transport_type, $client );
	}

	/**
	 * Prepares data for the post level notify events
	 *
	 * @param  string $event          Type of event new/update/delete
	 * @param  mixed  $result         Result object of previous wp_insert_post action
	 * @param  mixed  $post           Post object or post_id
	 * @param  object $site           Post object for the site doing the syndication
	 * @param  string $transport_type Post meta syn_transport_type for site
	 * @param  object $client         Syndication_Client class
	 * @return mixed
	 */
	private function notify_post_event( $event, $result, $post, $site, $transport_type, $client ) {
		if ( ! $this->should_notify( $event ) ) {
			return false;
		}

		if ( is_int( $post ) ) {
			$post = get_post( $post, ARRAY_A );
		}

		if ( isset( $post['postmeta'] ) && isset( $post['postmeta']['is_update'] ) ) {
			$log_time = $post['postmeta']['is_update'];
		} else {
			$log_time = null;
		}

		if ( false === $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$message = __( 'Syndication on %s failed. The following error occured:', 'push-syndication' );
				$message .= "\n" . $result->get_error_message();
			} else {
				$message = __( 'Syndication on %s failed.', 'push-syndication' );
			}

			$message = sprintf(
				$message,
				'<a href="' . admin_url( 'post.php?post=' . $site->ID . '&action=edit' ) . '">' . __( 'Endpoint ID:', 'push-syndication' ) . $site->ID . '</a>'
			);

			$this->send_notification( false, $message );
		} else {
			$message = sprintf(
				__( 'Syndication on %s succeeded.', 'push-syndication' ),
				'<a href="' . admin_url( 'post.php?post=' . $site->ID . '&action=edit' ) . '">' . __( 'Endpoint ID:', 'push-syndication' ) . $site->ID . '</a>'
			);

			$message .= ucwords( $this->action_verb( $event ) );

			$message .= sprintf(
				' %s at %s',
				'<a href="' . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . '">' . $post->post_title . '</a>',
				$log_time
			);

			$this->send_notification( true, $message );
		}
	}

	protected function action_verb( $event ) {
		switch ( $event ) {
			case 'new':
				return __( 'created', 'push-syndication' );
			case 'delete':
				return __( 'deleted', 'push-syndication' );
			case 'update':
				return __( 'updated', 'push-syndication' );
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
	protected function should_notify( $event ) {
		global $settings_manager;

		$notification_methods = $settings_manager->get_setting( 'notification_methods' );

		if ( ! empty( $notification_methods ) ) {
			$notification_types = $settings_manager->get_setting( 'notification_types' );

			if ( is_array( $notification_types ) && in_array( $event, $notification_types, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Send notification
	 *
	 * @since 2.1
	 * @param string $result   status entry.
	 * @param string $message  log message.
	 */
	protected function send_notification( $result, $message ) {
		global $settings_manager;

		$notification_methods = $settings_manager->get_setting( 'notification_methods' );

		if ( ! empty( $notification_methods ) ) {
			foreach ( $notification_methods as $method ) {
				switch ( $method ) {
					case 'email':
						$this->email_notification( $result, $message );
						break;
					case 'slack':
						$this->slack_notification( $result, $message );
						break;
				}
			}
		}
	}

	protected function email_notification( $result, $message ) {
		global $settings_manager;

		$email = $settings_manager->get_setting( 'notification_email' );

		if ( empty( $email ) ) {
			return;
		}

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		wp_mail(
			$email,
			$this->get_notification_subject( $result ),
			$message,
			$headers
		);
	}

	protected function slack_notificaiton( $result, $message ) {
		global $settings_manager;

		$slack_webhook = $settings_manager->get_setting( 'notification_slack_webhook' );

		if ( empty( $slack_webhook ) ) {
			return;
		}

		$payload = wp_json_encode(
			array(
				'username' => 'Syndication',
				'text'     => $this->get_notification_subject( $result ) . "\n" . $message,
			)
		);

		wp_remote_post( $slack_webhook, array( 'body' => $payload ) );
	}

	protected function get_notification_subject( $result ) {
		if ( $result ) {
			return __( 'Syndication Success Notification', 'push-syndication' );
		}

		return __( 'Syndication Failure Notification', 'push-syndication' );
	}
}