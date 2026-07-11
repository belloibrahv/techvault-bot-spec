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
		// Capability is enforced in permission_callback on the route — no
		// redundant check needed here, but we leave this as defence-in-depth.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response( [ 'error' => 'Unauthorized' ], 403 );
		}

		global $wpdb;

		// ── 1. DB tables — use prepare() even though table names are trusted ──
		$leadsTable  = $wpdb->prefix . 'tva_leads';
		$eventsTable = $wpdb->prefix . 'tva_chat_events';

		$leadsExists  = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $leadsTable )
		) === $leadsTable;

		$eventsExists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $eventsTable )
		) === $eventsTable;

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

	private static function pingGemini( string $apiKey, string $model ): array {
		$url = sprintf(
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

		// ── WP HTTP layer error (DNS, timeout, SSL, etc.) ─────────────────────
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			Logger::error( 'Health: Gemini ping WP_Error.', [ 'error' => $msg ] );
			return [
				'result'     => 'ERROR',
				'stage'      => 'wp_http',
				'detail'     => $msg,
				'suggestion' => 'Your server cannot reach generativelanguage.googleapis.com. Check outbound HTTPS firewall rules or contact your host.',
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		// ── Non-200 from Gemini API ────────────────────────────────────────────
		if ( $code !== 200 ) {
			$geminiMsg = $decoded['error']['message'] ?? $raw;
			$geminiStatus = $decoded['error']['status'] ?? '';
			Logger::error( 'Health: Gemini ping non-200.', [ 'status' => $code, 'gemini_status' => $geminiStatus, 'detail' => $geminiMsg ] );

			$suggestion = match ( $code ) {
				400 => 'Bad request — model name may be wrong. Try "gemini-1.5-flash".',
				401, 403 => 'API key is invalid, expired, or the Generative Language API is not enabled in your Google Cloud project.',
				404 => 'Model not found. Confirm the model name at https://ai.google.dev/gemini-api/docs/models',
				429 => 'Quota exceeded. Wait 60 seconds and try again, or upgrade your Google AI Studio plan.',
				500, 503 => 'Gemini service error — not your fault. Retry in a few minutes.',
				default => 'Unexpected error. See detail above.',
			};

			return [
				'result'        => 'ERROR',
				'stage'         => 'gemini_api',
				'http_status'   => $code,
				'gemini_status' => $geminiStatus,
				'detail'        => $geminiMsg,
				'suggestion'    => $suggestion,
			];
		}

		// ── 200 but unexpected body structure ─────────────────────────────────
		$reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
		if ( empty( $reply ) ) {
			return [
				'result'     => 'WARN',
				'stage'      => 'parse',
				'detail'     => 'Gemini returned 200 but response body had unexpected structure.',
				'raw_sample' => substr( $raw, 0, 300 ),
			];
		}

		return [
			'result' => 'OK',
			'reply'  => trim( $reply ),
		];
	}
}
