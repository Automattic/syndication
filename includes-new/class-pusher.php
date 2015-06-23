<?php

namespace Automattic\Syndication;

use Automattic\Syndication\Types\Post;

class Pusher {

	public function __construct( Client_Manager $client_manager ) {

		$this->_client_manager = $client_manager;
	}

	public function process_site( $site_id ) {

		// @todo check site status

		// Load the required client.
		$client_slug = get_post_meta( $site_id, 'syn_transport_type', true );
		if ( !$client_slug ) {
			// @todo log that this site was skipped because no client set.
			throw new \Exception( 'No client selected.' );
		}

		$client = $this->_client_manager->get_pull_client( $client_slug );
		if ( !$client ) {
			// @todo log that selected client does not exist.
		}

		// @todo mark site as in progress

		try {
			$syn_posts = $client->get_posts();
		} catch ( \Exception $e ) {
			// @todo log and bail.
		}

		// @todo update site status
	}

	public function get_posts() {


	}
}