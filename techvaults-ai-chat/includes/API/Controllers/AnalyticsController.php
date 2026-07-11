<?php
/**
 * Analytics controller — handles POST /tva/v1/event.
 *
 * Requires a valid nonce so cross-origin CSRF is blocked.
 * Rate-limited per IP to prevent DB flooding.
 * event_type is validated against a strict allowlist.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API\Controllers;

use TechVaults\Chat\Analytics\EventRepository;
use TechVaults\Chat\API\RequestGuard;

class AnalyticsController {

	private EventRepository $events;
	private RequestGuard    $guard;

	public function __construct() {
		$this->events = new EventRepository();
		$this->guard  = new RequestGuard();
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// ── Nonce (CSRF protection) ───────────────────────────────────────────
		if ( ! $this->guard->nonceIsValid( $request ) ) {
			return new \WP_REST_Response( [ 'error' => 'invalid_nonce' ], 403 );
		}

		// ── Rate limit ────────────────────────────────────────────────────────
		if ( $this->guard->isRateLimited( $this->guard->clientIp() ) ) {
			return new \WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
		}

		$params = $request->get_json_params();

		// ── event_type allowlist — never store arbitrary strings ──────────────
		$allowed = [
			EventRepository::TYPE_MESSAGE,
			EventRepository::TYPE_WIDGET_OPENED,
			EventRepository::TYPE_WHATSAPP_CLICK,
			EventRepository::TYPE_LEAD_CAPTURED,
		];
		$rawType   = sanitize_text_field( $params['event_type'] ?? '' );
		$eventType = in_array( $rawType, $allowed, true ) ? $rawType : 'unknown';

		$this->events->log(
			sanitize_text_field(     $params['session_id'] ?? '' ),
			$eventType,
			esc_url_raw(             $params['page_url']   ?? '' ),
			sanitize_textarea_field( $params['message']    ?? '' ),
			true
		);

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}
}
