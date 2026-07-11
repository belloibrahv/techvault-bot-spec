<?php
/**
 * Plugin logger.
 *
 * A thin wrapper around WordPress's error_log / WP_DEBUG_LOG.
 * All plugin log calls go through here — never through bare error_log().
 *
 * Upgrade path: replace the write() implementation to fan out to a
 * database table or external monitoring service without changing any
 * call site.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Core;

final class Logger {

	public const INFO    = 'INFO';
	public const WARNING = 'WARNING';
	public const ERROR   = 'ERROR';
	public const DEBUG   = 'DEBUG';

	private function __construct() {} // Static-only class.

	public static function info( string $message, array $context = [] ): void {
		self::write( self::INFO, $message, $context );
	}

	public static function warning( string $message, array $context = [] ): void {
		self::write( self::WARNING, $message, $context );
	}

	public static function error( string $message, array $context = [] ): void {
		self::write( self::ERROR, $message, $context );
	}

	public static function debug( string $message, array $context = [] ): void {
		// Only emit debug messages when WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::write( self::DEBUG, $message, $context );
		}
	}

	// ── Internal ──────────────────────────────────────────────────────────────

	private static function write( string $level, string $message, array $context ): void {
		$prefix = '[TechVaults Chat]';
		$entry  = sprintf( '%s [%s] %s', $prefix, $level, $message );

		if ( ! empty( $context ) ) {
			$safe   = self::redact( $context );
			$safe   = self::truncate( $safe );
			$entry .= ' | ' . wp_json_encode( $safe );
		}

		error_log( $entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Remove sensitive keys before logging context arrays.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private static function redact( array $context ): array {
		$sensitive = [ 'api_key', 'key', 'token', 'secret', 'password', 'auth', 'nonce' ];

		foreach ( $context as $k => $v ) {
			foreach ( $sensitive as $word ) {
				if ( str_contains( strtolower( (string) $k ), $word ) ) {
					$context[ $k ] = '[REDACTED]';
					break;
				}
			}
		}

		return $context;
	}

	/**
	 * Truncate string context values to prevent log flooding.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private static function truncate( array $context, int $max = 200 ): array {
		foreach ( $context as $k => $v ) {
			if ( is_string( $v ) && mb_strlen( $v ) > $max ) {
				$context[ $k ] = mb_substr( $v, 0, $max ) . '…[truncated]';
			}
		}
		return $context;
	}
}
