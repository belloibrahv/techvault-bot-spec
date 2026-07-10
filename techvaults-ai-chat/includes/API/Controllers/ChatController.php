<?php
/**
 * Chat controller — handles POST /tva/v1/message.
 *
 * Orchestrates: nonce check → rate limit → KB retrieval → LLM call → analytics log.
 * Never writes a lead. Never touches the DB directly — delegates to domain classes.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API\Controllers;

use TechVaults\Chat\Analytics\EventRepository;
use TechVaults\Chat\API\RequestGuard;
use TechVaults\Chat\KnowledgeBase\Repository as KBRepository;
use TechVaults\Chat\KnowledgeBase\Retriever;
use TechVaults\Chat\LLM\Client;

class ChatController {

	private RequestGuard   $guard;
	private Retriever      $retriever;
	private EventRepository $events;

	public function __construct() {
		$this->guard     = new RequestGuard();
		$this->retriever = new Retriever( new KBRepository() );
		$this->events    = new EventRepository();
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// ── Auth ──────────────────────────────────────────────────────────────
		if ( ! $this->guard->nonceIsValid( $request ) ) {
			return new \WP_REST_Response( [ 'error' => 'invalid_nonce' ], 403 );
		}

		// ── Rate limit ────────────────────────────────────────────────────────
		if ( $this->guard->isRateLimited( $this->guard->clientIp() ) ) {
			return new \WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
		}

		// ── Input ─────────────────────────────────────────────────────────────
		$params    = $request->get_json_params();
		$message   = sanitize_textarea_field( $params['message']    ?? '' );
		$sessionId = sanitize_text_field(     $params['session_id'] ?? '' );
		$pageUrl   = esc_url_raw(             $params['page_url']   ?? '' );
		$history   = is_array( $params['history'] ?? null ) ? $params['history'] : [];

		if ( empty( $message ) || empty( $sessionId ) ) {
			return new \WP_REST_Response( [ 'error' => 'missing_fields' ], 400 );
		}

		// ── Retrieve → Generate ───────────────────────────────────────────────
		$context = $this->retriever->retrieve( $message );
		$reply   = Client::respond( $message, $context, $history );

		// ── Analytics ─────────────────────────────────────────────────────────
		$resolved = ! str_contains( strtolower( $reply ), "i'm not sure" )
		         && ! str_contains( strtolower( $reply ), 'connect you' )
		         && ! str_contains( strtolower( $reply ), "i'm having trouble" );

		$this->events->log( $sessionId, EventRepository::TYPE_MESSAGE, $pageUrl, $message, $resolved );

		return new \WP_REST_Response( [ 'reply' => $reply ], 200 );
	}
}
