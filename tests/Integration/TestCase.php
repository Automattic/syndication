<?php
/**
 * Base test case for integration tests.
 *
 * @package Automattic\Syndication\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Integration;

use Automattic\Syndication\Infrastructure\DI\Container;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Base test case for integration tests.
 *
 * Provides common helper methods for accessing plugin services.
 */
abstract class TestCase extends WPIntegrationTestCase {

	/**
	 * Get the plugin container.
	 *
	 * @return Container The container instance.
	 */
	protected function container(): Container {
		return Container::instance();
	}
}
