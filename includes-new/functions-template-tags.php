<?php

/**
 * Fetch a pull client by it's slug
 *
 * Example:
 * $client = syn_get_pull_client( 'my-client-slug' );
 * echo $client['label'];
 *
 * @param  string $client_slug The slug of the client you wish to fetch
 * @return array               The client if it's found
 */
function syn_get_pull_client( $client_slug = '' ) {
	global $client_manager;

	$pull_client = $client_manager->get_pull_client( $client_slug );

	if ( false !== $pull_client ) {
		return $pull_client;
	}
}