<?php
/**
 * Knowledge Base service provider.
 * Registers the CPT — that's the only WordPress integration in this domain.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\KnowledgeBase;

use TechVaults\Chat\Core\ServiceProviderInterface;

class KnowledgeBaseServiceProvider implements ServiceProviderInterface {

	public function register(): void {
		( new CPT() )->register();
	}

	public function boot(): void {}
}
