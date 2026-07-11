<?php
/**
 * Centralised configuration.
 *
 * Every get_option() call in the plugin goes through this class.
 * The rest of the codebase never touches get_option() directly, which means:
 *   – Changing where config is stored (env vars, JSON file, etc.) is one edit.
 *   – Defaults are documented in one place.
 *   – Type safety is enforced at the boundary.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Core;

final class Config {

	// ── LLM ───────────────────────────────────────────────────────────────────

	public static function llmProvider(): string {
		return (string) get_option( 'tva_chat_llm_provider', 'gemini' );
	}

	public static function llmApiKey(): string {
		// Environment variable takes priority — allows the key to be stored
		// outside the database entirely (e.g. in server env or wp-config.php).
		// Set:  define( 'TVC_LLM_API_KEY', 'AIzaSy...' );  in wp-config.php
		// or:   export TVC_LLM_API_KEY=AIzaSy...  in the server environment.
		if ( defined( 'TVC_LLM_API_KEY' ) && ! empty( TVC_LLM_API_KEY ) ) {
			return (string) TVC_LLM_API_KEY;
		}

		$env = getenv( 'TVC_LLM_API_KEY' );
		if ( ! empty( $env ) ) {
			return (string) $env;
		}

		return (string) get_option( 'tva_chat_llm_api_key', '' );
	}

	public static function llmModel(): string {
		$stored = (string) get_option( 'tva_chat_llm_model', '' );

		// Correct only truly deprecated/removed model names.
		// gemini-flash-lite-latest → resolves to gemini-3.1-flash-lite (confirmed 782ms, no thinking).
		// gemini-flash-latest → resolves to gemini-3.5-flash (works but higher quota/slower at times).
		$deprecated = [
			'gemini-1.5-flash'        => 'gemini-flash-lite-latest',
			'gemini-1.5-flash-latest' => 'gemini-flash-lite-latest',
			'gemini-1.5-pro'          => 'gemini-flash-lite-latest',
			'gemini-1.5-pro-latest'   => 'gemini-flash-lite-latest',
			'gemini-pro'              => 'gemini-flash-lite-latest',
			'gemini-1.0-pro'          => 'gemini-flash-lite-latest',
			'gemini-2.5-flash'        => 'gemini-flash-lite-latest',
		];

		if ( isset( $deprecated[ $stored ] ) ) {
			$corrected = $deprecated[ $stored ];
			update_option( 'tva_chat_llm_model', $corrected );
			return $corrected;
		}

		// Default: gemini-flash-lite-latest resolves to gemini-3.1-flash-lite — fast, no thinking overhead.
		// gemini-flash-latest resolves to gemini-3.5-flash but requires higher quota/billing.
		return $stored ?: 'gemini-flash-lite-latest';
	}

	public static function llmMaxTokens(): int {
		return 400;
	}

	public static function llmTemperature(): float {
		return 0.4;
	}

	public static function llmTimeoutSeconds(): int {
		return 20;
	}

	// ── Widget ────────────────────────────────────────────────────────────────

	public static function whatsappNumber(): string {
		return (string) get_option( 'tva_chat_whatsapp_number', '2348034048178' );
	}

	public static function greeting(): string {
		return (string) get_option(
			'tva_chat_greeting',
			"Hi, I'm the TechVaults assistant. Ask me about our services, pricing, or past projects."
		);
	}

	// ── Leads ─────────────────────────────────────────────────────────────────

	public static function notifyEmail(): string {
		return (string) get_option( 'tva_chat_notify_email', (string) get_option( 'admin_email', '' ) );
	}

	// ── Rate limiting ─────────────────────────────────────────────────────────

	/** Max messages per rolling hour per IP (SRS §8.4). */
	public static function rateLimitPerHour(): int {
		return 30;
	}

	// ── DB schema version ─────────────────────────────────────────────────────

	public static function dbVersion(): string {
		return '1.0.0';
	}

	// ── All option keys (used by uninstall.php for cleanup) ───────────────────

	/** @return string[] */
	public static function allOptionKeys(): array {
		return [
			'tva_chat_llm_provider',
			'tva_chat_llm_api_key',
			'tva_chat_llm_model',
			'tva_chat_whatsapp_number',
			'tva_chat_greeting',
			'tva_chat_notify_email',
			'tva_chat_db_version',
		];
	}
}
