<?php
/**
 * Analytics controller — handles POST /tva/v1/event.
 *
 * Fire-and-forget. No nonce required — analytics pings are low-risk and
 * we don't want failed nonce checks to silently drop widget_opened events.
 * No LLM calls, no lead writes.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API\Controllers;

use TechVaults\Chat\Analytics\EventRepository;

class AnalyticsController {

	private EventRepository $events;

	public function __construct() {
		$this->events = new EventRepository();
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$this->events->log(
			sanitize_text_field(     $params['session_id'] ?? '' ),
			sanitize_text_field(     $params['event_type'] ?? 'unknown' ),
			esc_url_raw(             $params['page_url']   ?? '' ),
			sanitize_textarea_field( $params['message']    ?? '' ),
			true
		);

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}
}
