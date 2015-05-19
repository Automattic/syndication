<?php

namespace Automattic\Syndication;

class Cron {

	public function __construct() {

		add_filter( 'cron_schedules', array( $this, 'add_pull_time_interval' ) );
	}

	public function add_pull_time_interval( $schedules ) {

		// Adds the custom time interval to the existing schedules.
		$schedules['syn_pull_time_interval'] = array(
			'interval' => (int) get_option( 'syndication/pull_time_interval' ),
			'display' => __( 'Pull Time Interval', 'push-syndication' )
		);

		return $schedules;
	}
}
