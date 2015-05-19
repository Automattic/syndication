<?php

namespace Automattic\Syndication;

interface Pull_Client {

	/**
	 * @param int $site_id
	 */
	public function __construct( $site_id );

	/**
	 * @return array|Traversable
	 */
	public function get_posts();
}