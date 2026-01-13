<?php
/**
 * WordPress hook manager for centralised hook registration.
 *
 * @package Automattic\Syndication\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Infrastructure\WordPress;

/**
 * Manages WordPress action and filter hook registration.
 *
 * Provides a centralised, testable interface for hook management
 * that can be mocked in unit tests.
 */
final class HookManager {

	/**
	 * Registered actions for tracking.
	 *
	 * @var array<string, array<array{callback: callable, priority: int, args: int}>>
	 */
	private array $actions = array();

	/**
	 * Registered filters for tracking.
	 *
	 * @var array<string, array<array{callback: callable, priority: int, args: int}>>
	 */
	private array $filters = array();

	/**
	 * Add an action hook.
	 *
	 * @param string   $hook     The action hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Priority for the hook.
	 * @param int      $args     Number of arguments.
	 * @return self
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): self {
		add_action( $hook, $callback, $priority, $args );

		$this->actions[ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);

		return $this;
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string   $hook     The filter hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Priority for the hook.
	 * @param int      $args     Number of arguments.
	 * @return self
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): self {
		add_filter( $hook, $callback, $priority, $args );

		$this->filters[ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);

		return $this;
	}

	/**
	 * Remove an action hook.
	 *
	 * @param string   $hook     The action hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Priority for the hook.
	 * @return bool True if removed, false otherwise.
	 */
	public function remove_action( string $hook, callable $callback, int $priority = 10 ): bool {
		return remove_action( $hook, $callback, $priority );
	}

	/**
	 * Remove a filter hook.
	 *
	 * @param string   $hook     The filter hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Priority for the hook.
	 * @return bool True if removed, false otherwise.
	 */
	public function remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {
		return remove_filter( $hook, $callback, $priority );
	}

	/**
	 * Execute an action hook.
	 *
	 * @param string $hook The action hook name.
	 * @param mixed  ...$args Arguments to pass to the callbacks.
	 */
	public function do_action( string $hook, mixed ...$args ): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Wrapper method accepts any hook.
		do_action( $hook, ...$args );
	}

	/**
	 * Apply filters to a value.
	 *
	 * @param string $hook  The filter hook name.
	 * @param mixed  $value The value to filter.
	 * @param mixed  ...$args Additional arguments to pass.
	 * @return mixed The filtered value.
	 */
	public function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Wrapper method accepts any hook.
		return apply_filters( $hook, $value, ...$args );
	}

	/**
	 * Check if an action has been registered.
	 *
	 * @param string $hook The action hook name.
	 * @return bool True if action is registered.
	 */
	public function has_action( string $hook ): bool {
		return has_action( $hook ) !== false;
	}

	/**
	 * Check if a filter has been registered.
	 *
	 * @param string $hook The filter hook name.
	 * @return bool True if filter is registered.
	 */
	public function has_filter( string $hook ): bool {
		return has_filter( $hook ) !== false;
	}

	/**
	 * Get all registered actions (for testing/debugging).
	 *
	 * @return array<string, array<array{callback: callable, priority: int, args: int}>>
	 */
	public function get_registered_actions(): array {
		return $this->actions;
	}

	/**
	 * Get all registered filters (for testing/debugging).
	 *
	 * @return array<string, array<array{callback: callable, priority: int, args: int}>>
	 */
	public function get_registered_filters(): array {
		return $this->filters;
	}
}
