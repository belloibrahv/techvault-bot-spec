<?php
/**
 * Plugin bootstrap — singleton that owns the provider lifecycle.
 *
 * Usage in main plugin file:
 *
 *   Plugin::getInstance()->run();
 *
 * To add a new subsystem, register its provider here. That is the only
 * file that ever needs to change when a new domain is introduced.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Core;

use TechVaults\Chat\Admin\AdminServiceProvider;
use TechVaults\Chat\API\ApiServiceProvider;
use TechVaults\Chat\KnowledgeBase\KnowledgeBaseServiceProvider;
use TechVaults\Chat\Widget\WidgetServiceProvider;

final class Plugin {

	private static ?Plugin $instance = null;

	/** @var ServiceProviderInterface[] */
	private array $providers = [];

	private function __construct() {}

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin. Called once on `plugins_loaded`.
	 */
	public function run(): void {
		$this->loadTextDomain();
		$this->registerProviders();
		$this->bootProviders();
	}

	// ── Text domain ───────────────────────────────────────────────────────────

	private function loadTextDomain(): void {
		load_plugin_textdomain(
			'tva-chat',
			false,
			dirname( TVC_BASENAME ) . '/languages'
		);
	}

	// ── Provider lifecycle ────────────────────────────────────────────────────

	private function registerProviders(): void {
		// Add new domains here — one line per provider, nothing else changes.
		$this->addProvider( new KnowledgeBaseServiceProvider() );
		$this->addProvider( new ApiServiceProvider() );
		$this->addProvider( new AdminServiceProvider() );
		$this->addProvider( new WidgetServiceProvider() );
	}

	private function addProvider( ServiceProviderInterface $provider ): void {
		$provider->register();
		$this->providers[] = $provider;
	}

	private function bootProviders(): void {
		foreach ( $this->providers as $provider ) {
			$provider->boot();
		}
	}
}
