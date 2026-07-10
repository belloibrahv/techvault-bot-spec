<?php
/**
 * Google Gemini LLM Provider.
 *
 * Implements TVA_LLM_Provider_Interface for Google's Gemini API.
 * API key is stored in wp_options (tva_chat_llm_api_key) — never in code.
 *
 * Gemini API reference:
 * https://ai.google.dev/api/generate-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Gemini_Provider implements TVA_LLM_Provider_Interface {

	/** Default model. Can be overridden in Settings. */
	const DEFAULT_MODEL = 'gemini-1.5-flash';

	/** Gemini generateContent endpoint (model is interpolated at call time). */
	const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	public function get_provider_name(): string {
		return 'gemini';
	}

	public function get_response(
		string $user_message,
		array  $context_entries,
		array  $history
	): string {
		$api_key = get_option( 'tva_chat_llm_api_key', '' );
		$model   = get_option( 'tva_chat_llm_model', self::DEFAULT_MODEL );

		if ( empty( $api_key ) ) {
			error_log( 'TVA Chat Gemini: API key is not configured.' );
			return $this->fallback_message();
		}

		// ── Build the context block ────────────────────────────────────────────
		$context_text = '';
		foreach ( $context_entries as $entry ) {
			$context_text .= "- {$entry['title']}: {$entry['answer']}\n";
		}
		if ( empty( $context_text ) ) {
			$context_text = '(No matching knowledge base entry found for this question.)';
		}

		// ── Build Gemini contents array ────────────────────────────────────────
		// Gemini uses a flat contents array with role = 'user' | 'model'.
		// We prepend the system prompt as a user/model exchange so it is
		// always first in the conversation, even though Gemini also supports
		// a dedicated systemInstruction field (used below).
		$contents = [];

		foreach ( $history as $turn ) {
			// Map 'assistant' role to Gemini's 'model' role.
			$role       = ( $turn['role'] === 'assistant' ) ? 'model' : 'user';
			$contents[] = [
				'role'  => $role,
				'parts' => [ [ 'text' => $turn['content'] ] ],
			];
		}

		// Append the current user message with KB context injected.
		$contents[] = [
			'role'  => 'user',
			'parts' => [ [
				'text' => "CONTEXT:\n{$context_text}\n\nVISITOR MESSAGE:\n{$user_message}",
			] ],
		];

		// ── HTTP request ──────────────────────────────────────────────────────
		$url      = sprintf( self::API_ENDPOINT, rawurlencode( $model ) );
		$url     .= '?key=' . rawurlencode( $api_key );

		$response = wp_remote_post( $url, [
			'timeout' => 20,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'systemInstruction' => [
					'parts' => [ [ 'text' => TVA_LLM_Client::SYSTEM_PROMPT ] ],
				],
				'contents'          => $contents,
				'generationConfig'  => [
					'maxOutputTokens' => 400,
					'temperature'     => 0.4,
				],
			] ),
		] );

		// ── Error handling ────────────────────────────────────────────────────
		if ( is_wp_error( $response ) ) {
			error_log( 'TVA Chat Gemini HTTP error: ' . $response->get_error_message() );
			return $this->fallback_message();
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( $http_code !== 200 ) {
			error_log( "TVA Chat Gemini API returned HTTP {$http_code}: " . wp_remote_retrieve_body( $response ) );
			return $this->fallback_message();
		}

		// ── Parse response ────────────────────────────────────────────────────
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ( empty( $text ) ) {
			error_log( 'TVA Chat Gemini: Empty or unexpected response structure.' );
			return "I'm not sure about that one. Would you like me to connect you to someone on WhatsApp?";
		}

		return trim( $text );
	}

	private function fallback_message(): string {
		return "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
	}
}
