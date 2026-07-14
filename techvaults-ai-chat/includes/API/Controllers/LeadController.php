<?php
/**
 * Lead controller — handles POST /tva/v1/lead.
 *
 * Never calls the LLM. Never touches the chat events table directly.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API\Controllers;

use TechVaults\Chat\Analytics\EventRepository;
use TechVaults\Chat\API\RequestGuard;
use TechVaults\Chat\Leads\LeadRepository;

class LeadController {

	private RequestGuard    $guard;
	private LeadRepository  $leads;
	private EventRepository $events;

	public function __construct() {
		$this->guard  = new RequestGuard();
		$this->leads  = new LeadRepository();
		$this->events = new EventRepository();
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// Nonce validation is bypassed for public-facing endpoints to prevent caching plugins
		// from breaking the widget for guest users (due to expired nonces in cached HTML).
		// Spam is prevented via the strict IP-based rate limiter below.

		// ── Rate limit (stricter for leads — prevents notification spam) ────────
		if ( $this->guard->isLeadRateLimited( $this->guard->clientIp() ) ) {
			return new \WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
		}

		$params = $request->get_json_params();

		$leadId = $this->leads->save( [
			'session_id'        => sanitize_text_field(     $params['session_id']        ?? '' ),
			'name'              => sanitize_text_field(     $params['name']               ?? '' ),
			'phone'             => sanitize_text_field(     $params['phone']              ?? '' ),
			'email'             => sanitize_email(          $params['email']              ?? '' ),
			'stated_need'       => sanitize_textarea_field( $params['stated_need']        ?? '' ),
			'qualifying_answer' => sanitize_textarea_field( $params['qualifying_answer']  ?? '' ),
			'preferred_time'    => sanitize_text_field(     $params['preferred_time']     ?? '' ),
			'source_url'        => esc_url_raw(             $params['source_url']         ?? '' ),
			'transcript'        => wp_kses_post(            $params['transcript']         ?? '' ),
		] );

		$this->events->log(
			sanitize_text_field( $params['session_id'] ?? '' ),
			EventRepository::TYPE_LEAD_CAPTURED,
			esc_url_raw( $params['source_url'] ?? '' ),
			'',
			true
		);

		return new \WP_REST_Response( [ 'success' => (bool) $leadId ], 200 );
	}
}
