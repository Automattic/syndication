<?php

namespace Automattic\Syndication;

/**
 * Checks if variable is a WP_Error object, and if so, rethrows the error as an
 * exception.
 *
 * @param $maybe_error Possibly a WP_Error object
 * @return bool
 * @throws \Exception
 *
 * @todo Handle WP_Error objects with multiple errors and codes.
 * @todo Create an exception object that is just for WP_Errors so we can catch those specifically.
 */
function is_wp_error_do_throw( $maybe_error ) {
	try {
		if ( is_wp_error( $maybe_error ) ) {

			throw new \Exception( $maybe_error->get_error_message() . '-' . $maybe_error->get_error_code() );

		} else {
			return false;
		}
	} catch ( \Exception $e ) {
		error_log( $e );

		return true;
	}
}