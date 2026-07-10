<?php
/**
 * Service Provider contract.
 *
 * Every subsystem (Admin, API, KnowledgeBase, …) ships as a ServiceProvider.
 * The plugin bootstrap simply iterates its registered providers and calls
 * register() then boot() on each — it never needs to know what's inside them.
 *
 *   register() — bind dependencies, add WordPress hooks that don't depend
 *                on other providers being ready yet.
 *
 *   boot()     — called after all providers are registered. Safe to call
 *                methods on other services here.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Core;

interface ServiceProviderInterface {

	/**
	 * Register hooks and services this provider owns.
	 * Called during `plugins_loaded`, before boot().
	 */
	public function register(): void;

	/**
	 * Called after all providers have been registered.
	 * Use for anything that depends on other providers being ready.
	 */
	public function boot(): void;
}
