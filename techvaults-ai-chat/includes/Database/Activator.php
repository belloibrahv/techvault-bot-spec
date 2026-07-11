<?php
/**
 * Plugin activator — creates DB tables and sets schema version.
 *
 * dbDelta is idempotent: safe to re-run on plugin updates.
 * Bump Config::dbVersion() and add ALTER statements here when the schema changes.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Database;

use TechVaults\Chat\Core\Config;

class Activator {

	public static function activate(): void {
		self::createTables();
		update_option( 'tva_chat_db_version', Config::dbVersion() );
		flush_rewrite_rules();
	}

	private static function createTables(): void {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		// ── wp_tva_leads ──────────────────────────────────────────────────────
		// Column widths are intentionally tight and match the server-side input
		// caps in ChatController / LeadController — defence in depth at the DB layer.
		$leads = $wpdb->prefix . 'tva_leads';
		$sqlLeads = "CREATE TABLE {$leads} (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id        VARCHAR(64)     NOT NULL DEFAULT '',
			name              VARCHAR(120)    NOT NULL DEFAULT '',
			phone             VARCHAR(30)     NOT NULL DEFAULT '',
			email             VARCHAR(150)    NOT NULL DEFAULT '',
			stated_need       TEXT,
			qualifying_answer TEXT,
			preferred_time    VARCHAR(120)    NOT NULL DEFAULT '',
			source_url        VARCHAR(500)    NOT NULL DEFAULT '',
			transcript        LONGTEXT,
			status            VARCHAR(20)     NOT NULL DEFAULT 'new',
			created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_session_id (session_id),
			KEY idx_status     (status),
			KEY idx_created_at (created_at)
		) {$collate};";

		// ── wp_tva_chat_events ────────────────────────────────────────────────
		$events = $wpdb->prefix . 'tva_chat_events';
		$sqlEvents = "CREATE TABLE {$events} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64)     NOT NULL DEFAULT '',
			event_type VARCHAR(40)     NOT NULL DEFAULT '',
			page_url   VARCHAR(500)    NOT NULL DEFAULT '',
			message    TEXT,
			resolved   TINYINT(1)      NOT NULL DEFAULT 1,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_session_id (session_id),
			KEY idx_event_type (event_type),
			KEY idx_resolved   (resolved),
			KEY idx_created_at (created_at)
		) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sqlLeads );
		dbDelta( $sqlEvents );
	}
}
