<?php
/**
 * REST API route registration.
 *
 * Single responsibility: map URL patterns to controller methods.
 * No request handling, no business logic — that lives in the controllers.
 *
 * Adding a new endpoint: one register_rest_route() call here, one method
 * on an existing or new controller. Nothing else changes.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API;

use TechVaults\Chat\API\Controllers\ChatController;
use TechVaults\Chat\API\Controllers\LeadController;
use TechVaults\Chat\API\Controllers\AnalyticsController;

class Routes {

	public const NAMESPACE = 'tva/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
	}

	public function registerRoutes(): void {
		$chat      = new ChatController();
		$lead      = new LeadController();
		$analytics = new AnalyticsController();

		// ── Chat ──────────────────────────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/message', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $chat, 'handle' ],
			'permission_callback' => '__return_true', // Protected by nonce + rate limit inside handler.
		] );

		// ── Lead capture ──────────────────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/lead', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $lead, 'handle' ],
			'permission_callback' => '__return_true',
		] );

		// ── Analytics (fire-and-forget) ───────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/event', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $analytics, 'handle' ],
			'permission_callback' => '__return_true',
		] );
	}
}
