<?php
/**
 * Plugin deactivator.
 *
 * Deactivation is reversible — we do NOT drop tables or options here.
 * That belongs in uninstall.php.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Database;

class Deactivator {

	public static function deactivate(): void {
		// Clear any scheduled cron events added by this plugin.
		// wp_clear_scheduled_hook( 'tva_chat_weekly_report' );

		// Flush so CPT rewrite rules are removed cleanly.
		flush_rewrite_rules();
	}
}
