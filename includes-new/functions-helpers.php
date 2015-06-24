<?php

namespace Automattic\Syndication;

/**
 * Checks if variable is a WP_Error object, and if so, rethrows the error as an
 * exception.
 *
 * @param $res
 * @return bool
 * @throws \Exception
 *
 * @todo Handle WP_Error objects with multiple errors and codes.
 * @todo Create an exception object that is just for WP_Errors so we can catch
 * those specifically.
 */
function is_wp_error_do_throw( $res ) {

	if ( is_wp_error( $res ) ) {
		throw new \Exception( $res->get_message(), $res->get_error_code() );

		return true;
	} else {
		return false;
	}
}
