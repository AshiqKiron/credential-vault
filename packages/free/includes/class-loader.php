<?php
/**
 * Collects actions and filters and registers them in bulk.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Hook loader (WordPress Plugin Boilerplate pattern).
 */
final class Loader {

	/**
	 * @var array<int, array{hook:string, component:object|string, callback:string, priority:int, accepted_args:int}>
	 */
	private array $actions = array();

	/**
	 * @var array<int, array{hook:string, component:object|string, callback:string, priority:int, accepted_args:int}>
	 */
	private array $filters = array();

	/**
	 * Add an action hook.
	 *
	 * @param string       $hook          Hook name.
	 * @param object|class-string $component Object instance or class name for static methods.
	 * @param string       $callback      Method name.
	 * @param int          $priority      Priority.
	 * @param int          $accepted_args Number of accepted arguments.
	 */
	public function add_action( string $hook, object|string $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string       $hook          Hook name.
	 * @param object|class-string $component Object instance or class name for static methods.
	 * @param string       $callback      Method name.
	 * @param int          $priority      Priority.
	 * @param int          $accepted_args Number of accepted arguments.
	 */
	public function add_filter( string $hook, object|string $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all collected hooks.
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
