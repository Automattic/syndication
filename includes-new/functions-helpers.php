<?php

namespace Automattic\Syndication;

/**
 * Checks if variable is a WP_Error object, and if so, rethrows the error as an
 * exception.
 *
 * @param $res
 * @return mixed
 * @throws \Exception
 *
 * @todo Handle WP_Error objects with multiple errors and codes.
 * @todo Create an exception object that is just for WP_Errors so we can catch
 * those specifically.
 */
function throw_if_wp_error( $res ) {

	if ( is_wp_error( $res ) ) {
		throw new \Exception( $res->get_message(), $res->get_error_code() );
	}
}
