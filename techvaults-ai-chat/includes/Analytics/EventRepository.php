<?php
/**
 * Analytics event repository.
 *
 * Single responsibility: read/write the wp_tva_chat_events table.
 * No business logic lives here — just data access.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Analytics;

use TechVaults\Chat\Core\Logger;

class EventRepository {

	// ── Event type constants — used by callers, never magic strings ───────────

	public const TYPE_MESSAGE         = 'message';
	public const TYPE_WIDGET_OPENED   = 'widget_opened';
	public const TYPE_WHATSAPP_CLICK  = 'whatsapp_handoff';
	public const TYPE_LEAD_CAPTURED   = 'lead_captured';

	// ── Writes ────────────────────────────────────────────────────────────────

	public function log(
		string $sessionId,
		string $eventType,
		string $pageUrl,
		string $message,
		bool   $resolved
	): void {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'tva_chat_events',
			[
				'session_id' => $sessionId,
				'event_type' => $eventType,
				'page_url'   => $pageUrl,
				'message'    => $message,
				'resolved'   => $resolved ? 1 : 0,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( $inserted === false ) {
			Logger::error( 'Failed to insert chat event.', [
				'event_type' => $eventType,
				'session_id' => $sessionId,
			] );
		}
	}

	// ── Reads ─────────────────────────────────────────────────────────────────

	/**
	 * Summary of the last 7 days — used on the admin settings page (SRS FR-7.2).
	 *
	 * @return array{total_conversations: int, unresolved_messages: string[]}
	 */
	public function weeklySummary(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tva_chat_events';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE created_at >= %s",
				$since
			)
		);

		$unresolved = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT message FROM {$table}
				 WHERE event_type = %s
				   AND resolved   = 0
				   AND created_at >= %s
				 ORDER BY created_at DESC
				 LIMIT 10",
				self::TYPE_MESSAGE,
				$since
			)
		);

		return [
			'total_conversations' => $total,
			'unresolved_messages' => $unresolved ?: [],
		];
	}
}
