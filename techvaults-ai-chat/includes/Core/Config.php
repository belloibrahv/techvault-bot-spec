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
		return (string) get_option( 'tva_chat_llm_api_key', '' );
	}

	public static function llmModel(): string {
		return (string) get_option( 'tva_chat_llm_model', 'gemini-2.0-flash' );
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
