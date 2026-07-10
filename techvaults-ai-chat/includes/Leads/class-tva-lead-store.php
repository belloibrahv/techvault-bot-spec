<?php
/**
 * Lead Store — persists captured leads and sends admin notification.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Lead_Store {

	/**
	 * Insert a new lead row and send a notification email.
	 *
	 * @param array $data Sanitised lead fields from the REST handler.
	 * @return int|false The new row ID on success, false on failure.
	 */
	public static function save( array $data ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'tva_leads';

		$inserted = $wpdb->insert(
			$table,
			[
				'session_id'        => $data['session_id']        ?? '',
				'name'              => $data['name']               ?? '',
				'phone'             => $data['phone']              ?? '',
				'email'             => $data['email']              ?? '',
				'stated_need'       => $data['stated_need']        ?? '',
				'qualifying_answer' => $data['qualifying_answer']  ?? '',
				'preferred_time'    => $data['preferred_time']     ?? '',
				'source_url'        => $data['source_url']         ?? '',
				'transcript'        => $data['transcript']         ?? '',
				'status'            => 'new',
			],
			[
				'%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s',
			]
		);

		if ( $inserted ) {
			self::notify( $data );
			return $wpdb->insert_id;
		}

		error_log( 'TVA Chat: Failed to insert lead. DB error: ' . $wpdb->last_error );
		return false;
	}

	/**
	 * Send an email notification to the configured address when a lead is captured.
	 * WhatsApp API notification is deferred to Phase 2 (see spec §13).
	 */
	private static function notify( array $data ): void {
		$to      = get_option( 'tva_chat_notify_email', get_option( 'admin_email' ) );
		$subject = sprintf(
			'[TechVaults] New chatbot lead: %s',
			! empty( $data['name'] ) ? $data['name'] : 'Unnamed visitor'
		);

		$body = "A new lead was captured via the TechVaults website chatbot.\n\n"
			. "Name:           {$data['name']}\n"
			. "Phone:          {$data['phone']}\n"
			. "Email:          {$data['email']}\n"
			. "Need:           {$data['stated_need']}\n"
			. "Preferred time: {$data['preferred_time']}\n"
			. "Page:           {$data['source_url']}\n\n"
			. "Log in to wp-admin → TechVaults Chat → Leads to view and follow up.\n";

		wp_mail( $to, $subject, $body );
	}
}
