<?php
/**
 * Runs on plugin activation.
 *
 * Creates the two custom database tables using dbDelta, which is
 * idempotent — safe to re-run on plugin updates that alter the schema.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Activator {

	/** DB schema version — bump this when you add or change columns. */
	const DB_VERSION = '1.0.0';

	public static function activate(): void {
		self::create_tables();
		update_option( 'tva_chat_db_version', self::DB_VERSION );
		flush_rewrite_rules();
	}

	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// ── Leads table ───────────────────────────────────────────────────────
		$leads_table = $wpdb->prefix . 'tva_leads';
		$sql_leads   = "CREATE TABLE {$leads_table} (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id       VARCHAR(64)     NOT NULL,
			name             VARCHAR(120)    DEFAULT '',
			phone            VARCHAR(30)     DEFAULT '',
			email            VARCHAR(150)    DEFAULT '',
			stated_need      TEXT,
			qualifying_answer TEXT,
			preferred_time   VARCHAR(120)    DEFAULT '',
			source_url       VARCHAR(255)    DEFAULT '',
			transcript       LONGTEXT,
			status           VARCHAR(20)     DEFAULT 'new',
			created_at       DATETIME        DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY status     (status)
		) {$charset_collate};";

		// ── Chat events / analytics table ─────────────────────────────────────
		$events_table = $wpdb->prefix . 'tva_chat_events';
		$sql_events   = "CREATE TABLE {$events_table} (
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
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_leads );
		dbDelta( $sql_events );
	}
}
