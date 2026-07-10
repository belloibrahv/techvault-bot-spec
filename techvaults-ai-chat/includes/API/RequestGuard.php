<?php
/**
 * Request Guard — nonce verification and rate limiting.
 *
 * Extracted from controllers so the security logic is tested in one place
 * and shared by every endpoint that needs it.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API;

use TechVaults\Chat\Core\Config;

class RequestGuard {

	/**
	 * Verify the WP REST nonce sent by the widget.
	 * Ensures the request originated from our own site's browser session.
	 */
	public function nonceIsValid( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return ! empty( $nonce ) && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Fixed-window rate limiter using WordPress transients, keyed by hashed IP.
	 * Returns true if the IP has exceeded the limit and should be blocked.
	 */
	public function isRateLimited( string $ip ): bool {
		$key   = 'tva_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= Config::rateLimitPerHour() ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Get the visitor IP from the server globals.
	 */
	public function clientIp(): string {
		return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
	}
}
