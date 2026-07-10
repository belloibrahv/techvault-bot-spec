<?php
/**
 * API service provider — registers REST routes.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API;

use TechVaults\Chat\Core\ServiceProviderInterface;

class ApiServiceProvider implements ServiceProviderInterface {

	public function register(): void {
		( new Routes() )->register();
	}

	public function boot(): void {}
}
