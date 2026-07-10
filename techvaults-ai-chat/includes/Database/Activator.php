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
		$leads = $wpdb->prefix . 'tva_leads';
		$sqlLeads = "CREATE TABLE {$leads} (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id        VARCHAR(64)     NOT NULL,
			name              VARCHAR(120)    DEFAULT '',
			phone             VARCHAR(30)     DEFAULT '',
			email             VARCHAR(150)    DEFAULT '',
			stated_need       TEXT,
			qualifying_answer TEXT,
			preferred_time    VARCHAR(120)    DEFAULT '',
			source_url        VARCHAR(255)    DEFAULT '',
			transcript        LONGTEXT,
			status            VARCHAR(20)     DEFAULT 'new',
			created_at        DATETIME        DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY status     (status)
		) {$collate};";

		// ── wp_tva_chat_events ────────────────────────────────────────────────
		$events = $wpdb->prefix . 'tva_chat_events';
		$sqlEvents = "CREATE TABLE {$events} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64)     NOT NULL,
			event_type VARCHAR(40)     NOT NULL,
			page_url   VARCHAR(255)    DEFAULT '',
			message    TEXT,
			resolved   TINYINT(1)      DEFAULT 1,
			created_at DATETIME        DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY event_type (event_type)
		) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sqlLeads );
		dbDelta( $sqlEvents );
	}
}
