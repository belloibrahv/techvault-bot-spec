<?php
/**
 * Runs on plugin deactivation (not deletion).
 *
 * We deliberately do NOT drop tables or delete options here.
 * Deactivation is reversible — the admin may reactivate the plugin.
 * Destructive cleanup belongs in uninstall.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Deactivator {

	public static function deactivate(): void {
		// Clear any scheduled cron jobs added by this plugin in the future.
		// wp_clear_scheduled_hook( 'tva_chat_weekly_report' );

		// Flush rewrite rules so the CPT slugs are removed cleanly.
		flush_rewrite_rules();
	}
}
