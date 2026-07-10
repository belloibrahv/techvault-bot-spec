<?php
/**
 * Analytics — logs all chat events and provides summary data for wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Analytics {

	/**
	 * Write a single event row.
	 *
	 * @param string $session_id  Browser session UUID.
	 * @param string $event_type  e.g. 'message', 'lead_captured', 'widget_opened', 'whatsapp_handoff'.
	 * @param string $page_url    Page the event originated from.
	 * @param string $message     The visitor message (blank for non-message events).
	 * @param bool   $resolved    Whether the bot handled the turn without escalation.
	 */
	public static function log_event(
		string $session_id,
		string $event_type,
		string $page_url,
		string $message,
		bool   $resolved
	): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'tva_chat_events',
			[
				'session_id' => $session_id,
				'event_type' => $event_type,
				'page_url'   => $page_url,
				'message'    => $message,
				'resolved'   => $resolved ? 1 : 0,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);
	}

	/**
	 * Used by the wp-admin dashboard widget (SRS FR-7.2).
	 * Returns a summary of the last 7 days of activity.
	 *
	 * @return array {
	 *   int   $total_conversations  Unique session count.
	 *   array $unresolved_messages  Up to 10 messages the bot could not answer.
	 * }
	 */
	public static function weekly_summary(): array {
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
				 WHERE event_type = 'message'
				   AND resolved = 0
				   AND created_at >= %s
				 ORDER BY created_at DESC
				 LIMIT 10",
				$since
			)
		);

		return [
			'total_conversations' => $total,
			'unresolved_messages' => $unresolved ?: [],
		];
	}
}
