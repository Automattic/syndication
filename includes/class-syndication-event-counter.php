<?php
/**
 * Event Counter
 *
 * This allows for generic events to be captured and counted. Use the push_syndication_event and push_syndication_reset_event actions to capture and reset counters. Use push_syndication_after_event to handle events once they've occurred, and to see the number of times the event has occurred.
 */

class Syndication_Event_Counter {

	/**
	 * Setup.
	 */
	public function __construct() {
		add_action( 'push_syndication_event', array( $this, 'count_event' ), 10, 2 );
		add_action( 'push_syndication_trigger_event', array( $this, 'count_event' ), 10, 2 );
		add_action( 'push_syndication_reset_event', array( $this, 'reset_event' ), 10, 2 );
	}

	/**
	 * Increments an event counter.
	 *
	 * @param string $event_slug An identifier for the event.
	 * @param string|int $event_object_id An identifier for the object the event is associated with. Should be unique across all objects associated with the given $event_slug.
	 */
	public function count_event( $event_slug, $event_object_id = null ) {
		// Coerce the slug and ID to strings. PHP will fire appropriate warnings if the given slug and ID are not coercible.
		$event_slug = (string) $event_slug;
		$event_object_id = (string) $event_object_id;

		// Increment the event counter.
		$option_name = $this->_get_safe_option_name( $event_slug, $event_object_id );
		$count = get_option( $option_name, 0 );
		$count = $count + 1;
		update_option( $option_name, $count );

		/**
		 * Fires when a syndication event has occurred. Includes the number of times the event has occurred so far.
		 *
		 * @param string $event_slug Event type identifier.
		 * @param string $event_object_id Event object identifier.
		 * @param int $count Number of times the event has been fired.
		 */
		do_action( 'push_syndication_after_event', $event_slug, $event_object_id, $count );

		/**
		 * Fires when a syndication event has occurred. Includes the number of times the event has occurred so far.
		 *
		 * The dynamic portion of the hook name, `$event_slug`, refers to the event slug that triggered the event.
		 *
		 * @param string $event_object_id Event object identifier.
		 * @param int $count Number of times the event has been fired.
		 */
		do_action( "push_syndication_after_event_{$event_slug}", $event_object_id, $count );
	}

	/**
	 * Resets an event counter.
	 *
	 * @param $event_slug
	 * @param $event_object_id
	 */
	public function reset_event( $event_slug, $event_object_id ) {
		// Coerce the slug and ID to strings. PHP will fire appropriate warnings if the given slug and ID are not coercible.
		$event_slug = (string) $event_slug;
		$event_object_id = (string) $event_object_id;

		delete_option( $this->_get_safe_option_name( $event_slug, $event_object_id ) );
	}

	/**
	 * Creates a safe option name for the event counter options.
	 *
	 * The main thing this does is make sure that the option name does not exceed the limit of 64 characters, regardless of the length of $event_slug and $event_object_id. The downside here is that we cannot easily determine which options belong to which slugs when examine the option names directly.
	 *
	 * @param $event_slug
	 * @param $event_object_id
	 * @return string
	 */
	protected function _get_safe_option_name( $event_slug, $event_object_id ) {
		return 'push_syndication_event_counter_' . md5( $event_slug . $event_object_id );
	}
}