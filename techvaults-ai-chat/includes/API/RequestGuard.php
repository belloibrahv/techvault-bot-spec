<?php
/**
 * Request Guard — nonce verification, rate limiting, IP resolution.
 *
 * Security design decisions:
 *
 * IP resolution:
 *   We read REMOTE_ADDR by default — it is the only value we can trust
 *   unconditionally, because it is set by the server, not the client.
 *   X-Forwarded-For / CF-Connecting-IP are only used when the site owner
 *   has opted in via the TVC_TRUST_PROXY constant (set in wp-config.php).
 *   When trusted, we take only the FIRST IP in X-Forwarded-For (the
 *   leftmost entry added by the outermost proxy) to prevent client spoofing
 *   of inner entries.
 *
 * Rate limiting:
 *   Fixed-window transient keys. 30 req/hr for chat/events, 5 req/hr for
 *   lead submissions (to prevent notification spam).
 */

declare( strict_types=1 );

namespace TechVaults\Chat\API;

use TechVaults\Chat\Core\Config;

class RequestGuard {

	// Separate limits per action type.
	private const LIMIT_CHAT = 30;  // messages + events per hour
	private const LIMIT_LEAD =  5;  // lead form submissions per hour

	/**
	 * Verify the WP REST nonce sent by the widget.
	 * Ensures the request originated from our own site's browser session.
	 */
	public function nonceIsValid( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			return false;
		}
		// wp_verify_nonce returns 1 (recent) or 2 (older but valid), not just bool.
		return (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' );
	}

	/**
	 * Rate-limit check for chat messages and analytics events.
	 * Returns true if the IP is over limit and should be blocked.
	 */
	public function isRateLimited( string $ip ): bool {
		return $this->checkLimit( $ip, 'msg', self::LIMIT_CHAT );
	}

	/**
	 * Stricter rate-limit for lead form submissions.
	 * Prevents notification email flooding.
	 */
	public function isLeadRateLimited( string $ip ): bool {
		return $this->checkLimit( $ip, 'lead', self::LIMIT_LEAD );
	}

	/**
	 * Resolve the real visitor IP address.
	 *
	 * REMOTE_ADDR is always used as the baseline. X-Forwarded-For is only
	 * trusted when TVC_TRUST_PROXY is defined and truthy in wp-config.php,
	 * which the site owner sets after confirming their host strips or sets
	 * that header reliably.
	 */
	public function clientIp(): string {
		$raw = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

		// Only use forwarded header if the operator has explicitly opted in.
		if ( defined( 'TVC_TRUST_PROXY' ) && TVC_TRUST_PROXY ) {
			// CF-Connecting-IP (Cloudflare) takes precedence if set.
			$cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
			if ( $this->isValidIp( $cf ) ) {
				return $cf;
			}

			// X-Forwarded-For: take the leftmost (client) entry only.
			$xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
			if ( ! empty( $xff ) ) {
				$first = trim( explode( ',', $xff )[0] );
				if ( $this->isValidIp( $first ) ) {
					return $first;
				}
			}
		}

		return filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '0.0.0.0';
	}

	// ── Private ───────────────────────────────────────────────────────────────

	/**
	 * Shared fixed-window rate-limit implementation.
	 *
	 * Transient key = tva_rl_{action}_{md5(ip)} so chat and lead limits
	 * are tracked separately per IP.
	 */
	private function checkLimit( string $ip, string $action, int $max ): bool {
		$key   = 'tva_rl_' . $action . '_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return true; // blocked
		}

		// Increment. First hit starts the 1-hour window.
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	private function isValidIp( string $ip ): bool {
		return ! empty( $ip ) &&
			(bool) filter_var( trim( $ip ), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}
}
