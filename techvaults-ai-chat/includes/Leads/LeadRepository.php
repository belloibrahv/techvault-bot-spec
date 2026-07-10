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
		$to      = Config::notifyEmail();
		$name    = ! empty( $data['name'] ) ? $data['name'] : 'Unnamed visitor';
		$subject = "[TechVaults] New chatbot lead: {$name}";

		$body = "A new lead was captured via the TechVaults website chatbot.\n\n"
			. "Name:           {$data['name']}\n"
			. "Phone:          {$data['phone']}\n"
			. "Email:          {$data['email']}\n"
			. "Need:           {$data['stated_need']}\n"
			. "Preferred time: {$data['preferred_time']}\n"
			. "Page:           {$data['source_url']}\n\n"
			. "Log in to wp-admin → TechVaults Chat → Leads to view and follow up.\n";

		$sent = wp_mail( $to, $subject, $body );

		if ( ! $sent ) {
			Logger::warning( 'Lead notification email failed to send.', [ 'to' => $to ] );
		}
	}
}
