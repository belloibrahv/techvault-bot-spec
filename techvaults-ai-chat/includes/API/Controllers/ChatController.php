<?php
/**
 * Chat controller — handles POST /tva/v1/message.
 *
 * Security flow:
 *   nonce check → rate limit → input validation/length caps →
 *   history sanitization → KB retrieval → LLM call → analytics log
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API\Controllers;

use TechVaults\Chat\Analytics\EventRepository;
use TechVaults\Chat\API\RequestGuard;
use TechVaults\Chat\KnowledgeBase\Repository as KBRepository;
use TechVaults\Chat\KnowledgeBase\Retriever;
use TechVaults\Chat\LLM\Client;

class ChatController {

	// Hard limits — prevents prompt-injection payloads and oversized LLM context.
	private const MAX_MESSAGE_LEN  = 500;   // characters
	private const MAX_HISTORY_TURNS = 8;    // conversation turns sent to LLM
	private const MAX_TURN_LEN     = 800;   // characters per history turn
	private const MAX_SESSION_LEN  = 64;    // session_id max length

	// Allowed history roles — reject anything else.
	private const ALLOWED_ROLES = [ 'user', 'assistant', 'model' ];

	private RequestGuard    $guard;
	private Retriever       $retriever;
	private EventRepository $events;

	public function __construct() {
		$this->guard     = new RequestGuard();
		$this->retriever = new Retriever( new KBRepository() );
		$this->events    = new EventRepository();
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// Nonce validation is bypassed for public-facing endpoints to prevent caching plugins
		// from breaking the widget for guest users (due to expired nonces in cached HTML).
		// Abuse is prevented via the IP-based rate limiter below.

		// ── 2. Rate limit ─────────────────────────────────────────────────────
		if ( $this->guard->isRateLimited( $this->guard->clientIp() ) ) {
			return new \WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
		}

		// ── 3. Parse and validate input ───────────────────────────────────────
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return new \WP_REST_Response( [ 'error' => 'invalid_json' ], 400 );
		}

		// Message: sanitize and cap length.
		$message = sanitize_textarea_field( $params['message'] ?? '' );
		$message = mb_substr( $message, 0, self::MAX_MESSAGE_LEN );

		// Session ID: alphanumeric + hyphens only (UUID format), capped length.
		$sessionId = sanitize_text_field( $params['session_id'] ?? '' );
		$sessionId = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $sessionId );
		$sessionId = mb_substr( $sessionId, 0, self::MAX_SESSION_LEN );

		// Page URL: sanitize as URL.
		$pageUrl = esc_url_raw( $params['page_url'] ?? '' );

		if ( empty( $message ) || empty( $sessionId ) ) {
			return new \WP_REST_Response( [ 'error' => 'missing_fields' ], 400 );
		}

		// ── 4. Sanitize conversation history ──────────────────────────────────
		// Reject non-array, cap turns, validate each turn's shape and content.
		$rawHistory = is_array( $params['history'] ?? null ) ? $params['history'] : [];
		$history    = $this->sanitizeHistory( $rawHistory );

		// ── 5. KB retrieval + LLM ─────────────────────────────────────────────
		$context = $this->retriever->retrieve( $message );
		$reply   = Client::respond( $message, $context, $history );

		// ── 6. Analytics ──────────────────────────────────────────────────────
		$resolved = ! str_contains( strtolower( $reply ), "i'm not sure" )
		         && ! str_contains( strtolower( $reply ), 'connect you' )
		         && ! str_contains( strtolower( $reply ), "i'm having trouble" );

		$this->events->log( $sessionId, EventRepository::TYPE_MESSAGE, $pageUrl, $message, $resolved );

		return new \WP_REST_Response( [ 'reply' => $reply ], 200 );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Sanitize the history array sent by the client.
	 *
	 * Enforces:
	 *  - Maximum turn count (prevents oversized LLM context / cost attacks)
	 *  - Valid role allowlist (prevents role injection into the LLM)
	 *  - Content is sanitized text, capped per turn (prevents prompt injection)
	 *  - Non-conforming turns are dropped silently (fail safe)
	 *
	 * @param  array<mixed> $raw
	 * @return array<int, array{role: string, content: string}>
	 */
	private function sanitizeHistory( array $raw ): array {
		$clean = [];

		// Take only the most recent N turns before processing.
		$raw = array_slice( $raw, -self::MAX_HISTORY_TURNS );

		foreach ( $raw as $turn ) {
			// Each turn must be an associative array with role + content.
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$role    = sanitize_text_field( $turn['role']    ?? '' );
			$content = sanitize_textarea_field( $turn['content'] ?? '' );

			// Drop turns with invalid roles or empty content.
			if ( ! in_array( $role, self::ALLOWED_ROLES, true ) || empty( $content ) ) {
				continue;
			}

			// Cap each turn's content length.
			$content = mb_substr( $content, 0, self::MAX_TURN_LEN );

			$clean[] = [ 'role' => $role, 'content' => $content ];
		}

		return $clean;
	}
}
