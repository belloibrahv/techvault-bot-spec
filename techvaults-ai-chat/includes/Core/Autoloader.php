<?php
/**
 * PSR-4 Autoloader for the TechVaults\Chat namespace.
 *
 * Maps:  TechVaults\Chat\<Subdomain>\<ClassName>
 *     →  includes/<Subdomain>/<ClassName>.php
 *
 * The single call to Autoloader::register() in the main plugin file
 * is the only place we need to touch this — all new classes are picked
 * up automatically as long as the file lives in the correct directory.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Core;

class Autoloader {

	private const NAMESPACE_PREFIX = 'TechVaults\\Chat\\';
	private const BASE_DIR         = __DIR__ . '/../../includes/';

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class ): void {
		// Only handle our own namespace.
		$len = strlen( self::NAMESPACE_PREFIX );
		if ( strncmp( $class, self::NAMESPACE_PREFIX, $len ) !== 0 ) {
			return;
		}

		// Strip the namespace prefix, convert namespace separators to directory
		// separators, and append .php.
		$relative = str_replace( '\\', '/', substr( $class, $len ) );
		$file     = self::BASE_DIR . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
