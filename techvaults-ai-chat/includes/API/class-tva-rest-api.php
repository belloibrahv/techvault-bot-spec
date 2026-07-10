<?php
/**
 * REST API — registers the three endpoints the front-end widget talks to.
 *
 * Endpoints:
 *   POST /wp-json/tva/v1/message   — chat turn (LLM call)
 *   POST /wp-json/tva/v1/lead      — lead capture
 *   POST /wp-json/tva/v1/event     — fire-and-forget analytics
 *
 * Each endpoint is intentionally single-responsibility:
 *   /message never writes a lead.
 *   /lead    never calls the LLM.
 *   /event   is fire-and-forget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_REST_API {

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$namespace = 'tva/v1';

		register_rest_route( $namespace, '/message', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle_message' ],
			'permission_callback' => '__return_true', // Public; protected by nonce + rate limit.
		] );

		register_rest_route( $namespace, '/lead', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle_lead' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/event', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle_event' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Verify the WordPress REST nonce sent by the widget.
	 * This ensures the request originated from our own site.
	 */
	private static function check_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return ! empty( $nonce ) && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Fixed-window rate limiter using WordPress transients.
	 * Keyed by hashed IP. Limit: 30 requests per rolling hour (SRS §8.4).
	 */
	private static function rate_limited( string $ip ): bool {
		$key   = 'tva_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 30 ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/** Get the visitor's IP, falling back to a safe default. */
	private static function client_ip(): string {
		return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
	}

	// ─── Handlers ─────────────────────────────────────────────────────────────

	public static function handle_message( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::check_nonce( $request ) ) {
			return new WP_REST_Response( [ 'error' => 'invalid_nonce' ], 403 );
		}

		if ( self::rate_limited( self::client_ip() ) ) {
			return new WP_REST_Response( [ 'error' => 'rate_limited' ], 429 );
		}

		$params     = $request->get_json_params();
		$message    = sanitize_textarea_field( $params['message']    ?? '' );
		$session_id = sanitize_text_field(     $params['session_id'] ?? '' );
		$page_url   = esc_url_raw(             $params['page_url']   ?? '' );
		$history    = is_array( $params['history'] ?? null ) ? $params['history'] : [];

		if ( empty( $message ) || empty( $session_id ) ) {
			return new WP_REST_Response( [ 'error' => 'missing_fields' ], 400 );
		}

		$context = TVA_Knowledge_Base::retrieve( $message );
		$reply   = TVA_LLM_Client::get_response( $message, $context, $history );

		$resolved = ! str_contains( strtolower( $reply ), "i'm not sure" )
		         && ! str_contains( strtolower( $reply ), 'connect you to someone' )
		         && ! str_contains( strtolower( $reply ), 'connect you to a human' );

		TVA_Analytics::log_event( $session_id, 'message', $page_url, $message, $resolved );

		return new WP_REST_Response( [ 'reply' => $reply ], 200 );
	}

	public static function handle_lead( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::check_nonce( $request ) ) {
			return new WP_REST_Response( [ 'error' => 'invalid_nonce' ], 403 );
		}

		$params  = $request->get_json_params();
		$lead_id = TVA_Lead_Store::save( [
			'session_id'       => sanitize_text_field(     $params['session_id']       ?? '' ),
			'name'             => sanitize_text_field(     $params['name']             ?? '' ),
			'phone'            => sanitize_text_field(     $params['phone']            ?? '' ),
			'email'            => sanitize_email(          $params['email']            ?? '' ),
			'stated_need'      => sanitize_textarea_field( $params['stated_need']      ?? '' ),
			'qualifying_answer'=> sanitize_textarea_field( $params['qualifying_answer'] ?? '' ),
			'preferred_time'   => sanitize_text_field(     $params['preferred_time']   ?? '' ),
			'source_url'       => esc_url_raw(             $params['source_url']       ?? '' ),
			'transcript'       => wp_kses_post(            $params['transcript']       ?? '' ),
		] );

		TVA_Analytics::log_event(
			sanitize_text_field( $params['session_id'] ?? '' ),
			'lead_captured',
			esc_url_raw( $params['source_url'] ?? '' ),
			'',
			true
		);

		return new WP_REST_Response( [ 'success' => (bool) $lead_id ], 200 );
	}

	public static function handle_event( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		TVA_Analytics::log_event(
			sanitize_text_field(     $params['session_id'] ?? '' ),
			sanitize_text_field(     $params['event_type'] ?? 'unknown' ),
			esc_url_raw(             $params['page_url']   ?? '' ),
			sanitize_textarea_field( $params['message']    ?? '' ),
			true
		);

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}
