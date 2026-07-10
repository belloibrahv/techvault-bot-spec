<?php
/**
 * Health controller — GET /tva/v1/health
 *
 * Admin-only endpoint. Returns:
 *   - plugin version
 *   - DB tables exist check
 *   - KB entry count
 *   - Gemini API key configured (yes/no, never the key itself)
 *   - Live Gemini ping result (success / error message)
 *
 * Use this endpoint to verify the plugin is wired up correctly before
 * testing the widget. Open in a browser while logged in as admin:
 *   https://your-site.com/wp-json/tva/v1/health
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API\Controllers;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\Core\Logger;
use TechVaults\Chat\KnowledgeBase\Repository as KBRepository;

class HealthController {

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// Admin only — this endpoint reveals system state.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response( [ 'error' => 'Unauthorized' ], 403 );
		}

		global $wpdb;

		// ── 1. DB tables ──────────────────────────────────────────────────────
		$leadsTable  = $wpdb->prefix . 'tva_leads';
		$eventsTable = $wpdb->prefix . 'tva_chat_events';

		$leadsExists  = $wpdb->get_var( "SHOW TABLES LIKE '{$leadsTable}'" )  === $leadsTable;
		$eventsExists = $wpdb->get_var( "SHOW TABLES LIKE '{$eventsTable}'" ) === $eventsTable;

		// ── 2. KB entry count ─────────────────────────────────────────────────
		$kbCount = count( ( new KBRepository() )->findAll() );

		// ── 3. API key ────────────────────────────────────────────────────────
		$apiKey        = Config::llmApiKey();
		$keyConfigured = ! empty( $apiKey );

		// ── 4. Live Gemini ping ───────────────────────────────────────────────
		$geminiStatus = 'skipped — no API key configured';

		if ( $keyConfigured ) {
			$geminiStatus = self::pingGemini( $apiKey, Config::llmModel() );
		}

		// ── Response ──────────────────────────────────────────────────────────
		return new \WP_REST_Response( [
			'status'  => 'ok',
			'plugin'  => [
				'version'      => TVC_VERSION,
				'db_version'   => Config::dbVersion(),
			],
			'database' => [
				'tva_leads_table_exists'       => $leadsExists,
				'tva_chat_events_table_exists' => $eventsExists,
			],
			'knowledge_base' => [
				'entry_count'  => $kbCount,
				'ready'        => $kbCount >= 5,
				'note'         => $kbCount < 5
					? 'Add at least 5 KB entries before testing the widget.'
					: 'KB looks good.',
			],
			'llm' => [
				'provider'        => Config::llmProvider(),
				'model'           => Config::llmModel(),
				'api_key_set'     => $keyConfigured,
				'gemini_ping'     => $geminiStatus,
			],
		], 200 );
	}

	// ── Gemini connectivity test ───────────────────────────────────────────────

	private static function pingGemini( string $apiKey, string $model ): string {
		$url  = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $apiKey )
		);

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'contents' => [ [
					'role'  => 'user',
					'parts' => [ [ 'text' => 'Reply with exactly the word: PONG' ] ],
				] ],
				'generationConfig' => [ 'maxOutputTokens' => 10 ],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			Logger::error( 'Health check Gemini ping failed.', [ 'error' => $response->get_error_message() ] );
			return 'ERROR — ' . $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			Logger::error( 'Health check Gemini ping non-200.', [ 'status' => $code, 'body' => $body ] );
			return "ERROR — HTTP {$code}: " . substr( $body, 0, 120 );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$reply   = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ( $reply ) {
			return 'OK — Gemini responded: ' . trim( $reply );
		}

		return 'WARN — Gemini returned 200 but response structure was unexpected.';
	}
}
