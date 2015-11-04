<?php
/**
 * Syndication_Logger implements a unified logging mechanism for the syndication plugin.
 */

namespace Automattic\Syndication;

class Syndication_Logger {

	/**
	 * singleton handle
	 * @var object
	 */
	private static $__instance = null;

	/**
	 * maximum amount of rows to keep for single object log entries
	 * @var integer
	 */
	private $log_entry_limit = 150;

	/**
	 * level of information to log. Can be info for all and default for errors/success
	 * @var string
	 */
	private $debug_level = null;

	/**
	 * if true log messages will also be sent to php error log
	 * @var boolean
	 */
	private $use_php_error_logging = null;

	/**
	 * filter variables for retrieving log messages
	 * @var array
	 */
	private $log_filter = array();

	/**
	 * a unique identifier for each page load / logging session
	 * @var string
	 */
	private $log_id = null;

	public function __construct() {
		add_action( 'syn_post_pull_new_post', array( __CLASS__, 'log_new' ), 10, 5 );
		add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'log_update' ), 10, 5 );
		add_action( 'syn_post_push_delete_post', array( __CLASS__, 'log_delete' ), 10, 6 );
		add_action( 'syn_post_push_new_post', array( __CLASS__, 'log_new' ), 10, 5 );
		add_action( 'syn_post_push_edit_post', array( __CLASS__, 'log_update' ), 10, 5 );

		// Allow
		/**
		 * Filter the debug level.
		 *
		 * Enables setting of a debug_level. Return true for info and false for default.
		 *
		 * @param bool $enable_debug Whether to enable debug logging. Default is false.
		 *                           Note that WP_DEBUG must also be true.
		 */
		$this->debug_level = apply_filters( 'syn_pre_debug_level', false );

		if ( false === $this->debug_level ) {
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				$this->debug_level = 'info';	// log everything
			} else {
				$this->debug_level = 'default';	// log only errors + success
			}
		}

		/**
		 * Filter the `use_php_error_logging` setting.
		 *
		 * Returning true causes the plugin to add `error_log` calls when logging activity.
		 *
		 * @param bool $use_php_error_logging Whether to use PHP error logging. Default is false.
		 */
		$this->use_php_error_logging = apply_filters( 'syn_use_php_error_logging', false );

		if ( false === $this->use_php_error_logging ) {
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				$this->use_php_error_logging = true;	// log also with error_log()
			} else {
				$this->use_php_error_logging = false;	// log only in db
			}
		}
	}

	/**
	 * Initialization function that is called only once to set the unique handle for the logging session
	 */
	public static function init() {
		self::instance()->log_id = md5( uniqid() . microtime() );
		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/class-syndication-logger-viewer.php' );
			$viewer = new Syndication_Logger_Viewer;
		}
	}

	/*
	 * Use this singleton to address non-static methods
	 */
	public static function instance() {
		if ( self::$__instance == null ) {
			self::$__instance = new self();
		}
		return self::$__instance;
	}


	/**
	 * Log a new post creation event
	 * usually implemented via action hook
	 * do_action( 'syn_post_pull_new_post', $result, $post, $site, $transport_type, $client );
	 * do_action( 'syn_post_push_new_post', $result, $post_ID, $site, $transport_type, $client, $info );
	 *
	 * @param  mixed  $result         Result object of previous wp_insert_post action
	 * @param  mixed  $post           Post object or post_id
	 * @param  object $site           Post object for the site doing the syndication
	 * @param  string $transport_type Post meta syn_transport_type for site
	 * @param  object $client         Syndication_Client class
	 */
	public static function log_new( $result, $post, $site, $transport_type, $client ) {
		self::instance()->log_post_event( 'new', $result, $post, $site, $transport_type, $client );
	}

	/**
	 * Log a post update event
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
	public static function log_update( $result, $post, $site, $transport_type, $client ) {
		self::instance()->log_post_event( 'update', $result, $post, $site, $transport_type, $client );
	}

	/**
	 * Log a post delete event
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
	public static function log_delete( $result, $external_id, $post, $site, $transport_type, $client ) {
		self::instance()->log_post_event( 'delete', $result, $post, $site, $transport_type, $client );
	}

	/**
	 * Prepares data for the post level log events
	 * @param  string $event          Type of event new/update/delete
	 * @param  mixed  $result         Result object of previous wp_insert_post action
 	 * @param  mixed  $post           Post object or post_id
 	 * @param  object $site           Post object for the site doing the syndication
 	 * @param  string $transport_type Post meta syn_transport_type for site
 	 * @param  object $client         Syndication_Client class
	 */
	private function log_post_event( $event, $result, $post, $site, $transport_type, $client ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post, ARRAY_A );
		}

		if ( isset( $post->post_data['postmeta'] ) && isset( $post->post_data['postmeta']['is_update'] ) ) {
			$log_time = $post->post_data['postmeta']['is_update'];
		} else {
			$log_time = null;
		}

		$extra = array(
			'post' 			 => $post,
			'result' 		 => $result,
			'transpost_type' => $transport_type,
			'client' 		 => $client,
		);

		if ( false == $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
			} else {
				$message = 'fail';
			}
			Syndication_Logger::log_post_error( $site->ID, $status = __( esc_attr( $event ), 'push-syndication' ), $message, $log_time, $extra );
		} else {
			$guid    = isset( $post->post_data['post_guid'] ) ? sanitize_text_field( $post->post_data['post_guid'] ) : sanitize_text_field( $post->post_data['guid'] );
			$message = sprintf( '%s,%d', $guid, intval( $result ) );
			Syndication_Logger::log_post_success( $site->ID, $status = __( esc_attr( $event ), 'push-syndication' ), $message, $log_time, $extra );
		}
	}

	/**
	 * Log a faulty post level event
	 * @param  int 	  $post_id  post_id to attach the log entry to
	 * @param  string $status   status entry
	 * @param  string $message  log message
	 * @param  string $log_time time of event
	 * @param  array  $extra    additional data
	 */
	public static function log_post_error( $post_id, $status = 'error', $message = '', $log_time = '', $extra = array() ) {
		self::instance()->log_post( 'error', $post_id, $status, $message, $log_time, $extra );
	}

	/**
	 * Log a successful post level event
	 * @param  int 	  $post_id  post_id to attach the log entry to
	 * @param  string $status   status entry
	 * @param  string $message  log message
	 * @param  string $log_time time of event
	 * @param  array  $extra    additional data
	 */
	public function log_post_success( $post_id, $status = 'ok', $message = '', $log_time = '', $extra = array() ) {
		self::instance()->log_post( 'success', $post_id, $status, $message, $log_time, $extra );
	}

	/**
	 * Log a post level informal event
	 * @param  int 	  $post_id  post_id to attach the log entry to
	 * @param  string $status   status entry
	 * @param  string $message  log message
	 * @param  string $log_time time of event
	 * @param  array  $extra    additional data
	 */
	public static function log_post_info( $post_id, $status = '', $message = '', $log_time = '', $extra = array() ) {
		self::instance()->log_post( 'info', $post_id, $status, $message, $log_time, $extra );
	}

	/**
	 * Pass post level events to logger function
	 * @param  string $msg_type event type success/error/info
	 * @param  int 	  $post_id  post_id to attach the log entry to
 	 * @param  string $status   status entry
 	 * @param  string $message  log message
 	 * @param  string $log_time time of event
 	 * @param  array  $extra    additional data
	 */
	private function log_post( $msg_type, $post_id, $status, $message, $log_time, $extra ) {
		$this->log( $storage_type = 'object', $msg_type, $object_type = 'post', $object_id = $post_id, $status, $message, $log_time, $extra );
	}

	/**
	 * Log an entry to the database
	 * @param  string $storage_type Where the log entry will be stored. object / option
 	 * @param  string $msg_type 	event type success/error/info
	 * @param  string $object_type  Type of object to attach log entry to. Currently only "post" is supported
 	 * @param  int 	  $object_id  	object_id (post_id) to attach the log entry to
  	 * @param  string $status   	status entry
  	 * @param  string $message  	log message
  	 * @param  string $log_time 	time of event
  	 * @param  array  $extra    	additional data
	 * @return mixed                true or WP_Error
	 */
	private function log( $storage_type, $msg_type, $object_type = 'post', $object_id = '', $status, $message, $log_time, $extra ) {
		// Don't log infos depending on debug level
		if ( 'info' == $msg_type && 'info' != $this->debug_level ) {
			return;
		}

		$log_entry = array(
			'log_id' 	=> $this->log_id,
			'msg_type'  => $msg_type,
		);

		if ( ! empty( $object_type ) ) {
			$log_entry['object_type'] = sanitize_text_field( $object_type );
		}

		if ( ! empty( $object_id ) ) {
			$log_entry['object_id'] = (int) $object_id;
		}

		if ( ! empty( $status ) ) {
			$log_entry['status'] = sanitize_text_field( $status );
		}

		if ( ! empty( $message ) ) {
			$log_entry['message'] = sanitize_text_field( $message );
		}

		if ( ! empty( $log_time ) ) {
			$log_entry['time'] = date('Y-m-d H:i:s', strtotime( $log_time ) );
		} else {
			$log_entry['time'] = current_time('mysql');
		}

		if ( ! empty( $extra ) && is_array( $extra ) ) {
			// @TODO sanitize extra data
			// $log_entry['extra'] = array_map( 'sanitize_text_field', $extra );
		}


		if ( true === $this->use_php_error_logging ) {
			error_log( $this->format_log_message( $msg_type, $log_entry ) );
		}

		if ( 'object' == $storage_type ) {
			// Storing the log alongside the object

			if ( 'post' == $object_type ) {

				if ( ! is_integer( $object_id ) ) {
					return new \WP_Error( 'logger_no_post_id', __( 'You need to provide a valid post_id or use log_option instead', 'push-syndication' ) );
				}

				$post = get_post( $object_id );
				if ( ! $post ) {
					return new \WP_Error( 'logger_no_post', __( 'The post_id provided does not exist.', 'push-syndication' ) );
				}

				$log = get_post_meta( $post->ID, 'syn_log', true);

				if ( empty( $log ) ) {
					$log[0] = $log_entry;
				} else {
					if ( count( $log ) >= $this->log_entry_limit ) {
						// Slice the array to keep the log size in the limits
						$offset = count ( $log ) - $this->log_entry_limit;
						$log = array_slice( $log, $offset + 1 );
					}

					$log[] = $log_entry;
				}
				update_post_meta( $post->ID, 'syn_log', $log );

				if ( 'success' == $msg_type ) {
					update_post_meta( $post->ID, 'syn_log_errors', 0 );
				} else if ( 'error' == $msg_type ) {
					// track failures since last success
					$errors = get_post_meta( $post->ID, 'syn_log_errors', true );
					$errors = (int) $errors + 1;
					update_post_meta( $post->ID, 'syn_log_errors', $errors );
					// track overall failures
					$errors = get_post_meta( $post->ID, 'syn_log_errors_overall', true );
					$errors = (int) $errors + 1;
					update_post_meta( $post->ID, 'syn_log_errors_overall', $errors );
				}

				// @TODO log error counter
			} else if ( 'term' == $object_type ) {
				// @TODO implement if needed
			}

		} else if ( 'option' == $storage_type ) {
			// Storing the log in an option value

			$log = get_option( 'syn_log', true );
			if ( empty( $log ) ) {
				$log[0] = $log_entry;
			} else {
				if ( count( $log ) >= $this->log_entry_limit ) {
					// Slice the array to keep the log size in the limits
					$offset = count ( $log ) - $this->log_entry_limit;
					$log = array_slice( $log, $offset + 1 );
				}

				$log[] = $log_entry;
			}
			update_option( 'syn_log', $log );

			if ( 'success' == $msg_type ) {
				update_option( 'syn_log_errors', 0 );
			} else if ( 'error' == $msg_type ) {
				// track failures since last success
				$errors = get_option( 'syn_log_errors' );
				$errors = (int) $errors + 1;
				update_option( 'syn_log_errors', $errors );
				// track overall failures
				$errors = get_option( 'syn_log_errors_overall' );
				$errors = (int) $errors + 1;
				update_option( 'syn_log_errors_overall', $errors );
			}
		}
		return true;
	}

	/**
	 * Format log message for error_log()
	 * @param  string $msg_type  Type of message
	 * @param  array  $log_entry Prepared log_entry array
	 * @return string            Formatted log message
	 */
	private function format_log_message( $msg_type, $log_entry ) {
		/**
		 * Filter the format used for log entries written via `error_log`.
		 *
		 * @param string $msg       The formatted message.
		 * @param string $msg_type  Type of message.
	 	 * @param array  $log_entry Prepared log_entry array.
		 */
		$msg = apply_filters( 'syn_error_log_message_format', sprintf( 'SYN_%s:%s,%d,%s%s', strtoupper( $msg_type ), $log_entry['object_type'], $log_entry['object_id'], $log_entry['status'], $log_entry['message'] ? ',' . $log_entry['message'] : '' ), $msg_type, $log_entry );
		return $msg;
	}

	/**
	 * Retrieve and filter log messages from database
 	 * @param  string $log_id		unique log session id
 	 * @param  string $msg_type 	event type success/error/info
 	 * @param  int 	  $object_id  	object_id (post_id) to attach the log entry to
	 * @param  string $object_type  Type of object to attach log entry to. Currently only "post" is supported
  	 * @param  string $status   	status entry
	 * @param  string $date_start   Date string for starting date filter
	 * @param  string $date_end     Date string for starting date filter
	 * @param  string $message      regular expression for message text matching
	 * @param  string $storage_type Where the log entry will be stored. object / option
  	 * @return array                Array of matching log entries
	 */
	public static function get_messages( $log_id = null, $msg_type = null, $object_id = null, $object_type = 'post', $status = null, $date_start = null, $date_end = null, $message = null, $storage_type = 'object' ) {

		$log_entries = array();

		if ( 'object' == $storage_type ) {
			if ( 'post' == $object_type ) {
				if ( ! empty( $object_id ) ) {
					$log_entries[$object_id] = get_post_meta( $object_id, 'syn_log' );
				} else {
					global $wpdb;
					/**
					 * Direct database call without caching:
					 * This call may return objects larger than 1 MB and is usually only called infrequently
					 * from the admin dashboard. Implementing a segmented object caching for this result seems
					 * unnecessary.
					 * @TODO implement walker
					 */
					$all_log_entries = $wpdb->get_results( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = 'syn_log' GROUP BY post_id ORDER BY meta_id DESC LIMIT 0, 100" ); // cache pass (see note above)
					foreach( $all_log_entries as $log_entry ) {
						$log_entries[$log_entry->post_id] = unserialize( $log_entry->meta_value );
					}
				}
			}
		}

		$filter = array();
		foreach( array( 'log_id', 'msg_type', 'object_type', 'status', 'date_start', 'date_end', 'message' ) as $filter_key ) {
			if ( ! empty( ${$filter_key} ) ) {
				$filter[$filter_key] = ${$filter_key};
			}
		}
		self::instance()->log_filter = $filter;

		foreach( $log_entries as $object_id => $entries ) {
			$entries = array_filter( $entries, array( self::instance(), 'filter_log_entries' ) );
			$log_entries[$object_id] = $entries;
		}
		return $log_entries;
	}

	/**
	 * Retrieve log_filter variable
	 * @return array Log filter conditions
	 */
	public function get_log_filter() {
		return $this->log_filter;
	}

	/**
	 * Filter retrieved log entries by log_filter conditions
	 * @uses array_filter()
	 * @param  array $data Array Element
	 * @return boolean     True to keep the entry, false to skip it
	 */
	public function filter_log_entries( $data ) {
		$filter = $this->get_log_filter();

		foreach( $filter as $key => $value ) {
			switch( $key ) {
				case 'log_id':
				case 'msg_type':
				case 'object_type':
				case 'status':
					if ( ! isset( $data[$key] ) || $data[$key] <> $value ) {
						return false;
					}
					break;
				case 'date_start':
					if ( ! isset( $data['time'] ) || strtotime( $data['time'] ) <= strtotime( $value ) ) {
						return false;
					}
					break;
				case 'date_end':
					if ( ! isset( $data['time'] ) || strtotime( $data['time'] ) >= strtotime( $value ) ) {
						return false;
					}
					break;
				case 'message':
					if ( ! isset( $data['message'] ) || ! preg_match( '#' . preg_quote( $value, '#' ) . '#', $data['message'] ) ) {
						return false;
					}
					break;
			}
		}

		return true;
	}
}
