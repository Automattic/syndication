<?php
/**
 * Restores the syndication server's in-memory state after each test.
 *
 * @package Automattic\Syndication\Tests
 */

namespace Automattic\Syndication\Tests;

use ReflectionProperty;

/**
 * The database rolls back between tests, but the global server object keeps
 * whatever a test or upgrade() left in its properties. Tests that change the
 * stored version, settings or transports use this so later tests start clean.
 */
trait RestoresServerState {

	/**
	 * Server state saved before the test.
	 *
	 * @var array
	 */
	private $saved_server_state = array();

	/**
	 * Save the server's version, settings and transports.
	 *
	 * @before
	 */
	public function save_server_state(): void {
		$server = $GLOBALS['push_syndication_server'];

		$this->saved_server_state = array(
			'version'    => $this->server_version_property()->getValue( $server ),
			'settings'   => $server->push_syndicate_settings,
			'transports' => $server->push_syndicate_transports,
		);
	}

	/**
	 * Put the saved version, settings and transports back.
	 *
	 * @after
	 */
	public function restore_server_state(): void {
		$server = $GLOBALS['push_syndication_server'];

		$this->server_version_property()->setValue( $server, $this->saved_server_state['version'] );
		$server->push_syndicate_settings   = $this->saved_server_state['settings'];
		$server->push_syndicate_transports = $this->saved_server_state['transports'];
	}

	/**
	 * The server's private version property.
	 *
	 * @return ReflectionProperty
	 */
	private function server_version_property(): ReflectionProperty {
		$property = new ReflectionProperty( $GLOBALS['push_syndication_server'], 'version' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true ); // A no-op from PHP 8.1, and deprecated in 8.5.
		}

		return $property;
	}
}
