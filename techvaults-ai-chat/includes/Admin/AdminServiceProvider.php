<?php
/**
 * Admin service provider — registers the menu and settings.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Admin;

use TechVaults\Chat\Core\ServiceProviderInterface;

class AdminServiceProvider implements ServiceProviderInterface {

	public function register(): void {
		( new Menu() )->register();
		add_action( 'admin_init', [ new Settings(), 'registerSettings' ] );
	}

	public function boot(): void {}
}
