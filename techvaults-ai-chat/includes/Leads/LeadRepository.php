<?php
/**
 * Lead repository — persists captured leads and triggers admin notification.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Leads;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\Core\Logger;

class LeadRepository {

	/**
	 * Insert a new lead row.
	 *
	 * @param array{
	 *   session_id:        string,
	 *   name:              string,
	 *   phone:             string,
	 *   email:             string,
	 *   stated_need:       string,
	 *   qualifying_answer: string,
	 *   preferred_time:    string,
	 *   source_url:        string,
	 *   transcript:        string,
	 * } $data Sanitised lead fields from the REST controller.
	 *
	 * @return int|false New row ID on success, false on failure.
	 */
	public function save( array $data ): int|false {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'tva_leads',
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
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			Logger::error( 'Failed to insert lead.', [ 'session_id' => $data['session_id'] ?? '' ] );
			return false;
		}

		$id = $wpdb->insert_id;
		$this->notify( $data );

		return $id;
	}

	// ── Private ───────────────────────────────────────────────────────────────

	private function notify( array $data ): void {
		$to = Config::notifyEmail();

		// Strip CRLF from any field that appears in email headers — prevents
		// header injection (RFC 5321 §4.1.1.1).
		$name = str_replace( [ "\r", "\n", "\0" ], '', $data['name'] ?? '' );
		$name = $name ?: 'Unnamed visitor';

		// Subject: sanitize then cap length — no user HTML, no header injection.
		$subject = sanitize_text_field( "[TechVaults] New chatbot lead: {$name}" );
		$subject = mb_substr( $subject, 0, 200 );

		// Body is plain-text — escape special chars for email safety but do NOT
		// use HTML because wp_mail defaults to text/plain.
		$body = "A new lead was captured via the TechVaults website chatbot.\r\n\r\n"
			. "Name:           " . sanitize_text_field( $data['name']          ?? '' ) . "\r\n"
			. "Phone:          " . sanitize_text_field( $data['phone']         ?? '' ) . "\r\n"
			. "Email:          " . sanitize_email(      $data['email']         ?? '' ) . "\r\n"
			. "Need:           " . sanitize_text_field( $data['stated_need']   ?? '' ) . "\r\n"
			. "Preferred time: " . sanitize_text_field( $data['preferred_time'] ?? '' ) . "\r\n"
			. "Page:           " . esc_url_raw(         $data['source_url']    ?? '' ) . "\r\n\r\n"
			. "Log in to wp-admin → TechVaults Chat → Leads to view and follow up.\r\n";

		$sent = wp_mail( $to, $subject, $body );

		if ( ! $sent ) {
			Logger::warning( 'Lead notification email failed to send.', [ 'to' => $to ] );
		}
	}
}
