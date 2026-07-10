<?php
/**
 * PSR-4-style autoloader for the TechVaults AI Chat plugin.
 *
 * Maps class names to file paths using the convention:
 *   TVA_Some_Class  →  includes/<Domain>/class-tva-some-class.php
 *
 * All plugin classes must be prefixed with TVA_.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Autoloader {

	/**
	 * Domain → subdirectory map.
	 * Add new domains here as the plugin grows.
	 */
	private static array $domains = [
		'Core'          => 'Core',
		'Admin'         => 'Admin',
		'API'           => 'API',
		'Analytics'     => 'Analytics',
		'Database'      => 'Database',
		'KnowledgeBase' => 'KnowledgeBase',
		'Leads'         => 'Leads',
		'LLM'           => 'LLM',
		'Widget'        => 'Widget',
	];

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class_name ): void {
		// Only handle our own classes.
		if ( strpos( $class_name, 'TVA_' ) !== 0 ) {
			return;
		}

		// Convert TVA_Some_Class to class-tva-some-class.php
		$filename  = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		$base_path = TVA_CHAT_PATH . 'includes/';

		// Search each domain folder.
		foreach ( self::$domains as $domain_dir ) {
			$file = $base_path . $domain_dir . '/' . $filename;
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Fallback: root includes/ folder (for legacy or flat files).
		$fallback = $base_path . $filename;
		if ( file_exists( $fallback ) ) {
			require_once $fallback;
		}
	}
}
