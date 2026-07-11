<?php
/**
 * Abstract LLM provider — shared HTTP infrastructure for all providers.
 *
 * Security responsibilities:
 *  - Validates API key is present and non-trivially short before sending.
 *  - Caps the reply to a safe maximum length to prevent token-flooding.
 *  - Never logs the API key value — only boolean presence.
 *  - Logs errors structurally (no raw user input in error logs).
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\Core\Logger;

abstract class AbstractProvider implements ProviderInterface {

	/** Hard cap on reply length returned to the client (chars). */
	private const MAX_REPLY_LENGTH = 2000;

	/** Minimum plausible API key length — rejects empty strings and placeholders. */
	private const MIN_KEY_LENGTH = 20;

	final public function getResponse(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): string {
		$apiKey = Config::llmApiKey();

		// Validate key before making any outbound request.
		if ( strlen( $apiKey ) < self::MIN_KEY_LENGTH ) {
			Logger::error( 'LLM API key is missing or too short.', [ 'provider' => $this->getProviderName() ] );
			return $this->fallback();
		}

		$url     = $this->buildEndpointUrl( $apiKey );
		$headers = $this->buildHeaders( $apiKey );
		$body    = $this->buildRequestBody( $userMessage, $contextEntries, $history );

		Logger::debug( 'LLM request dispatched.', [
			'provider' => $this->getProviderName(),
			'model'    => Config::llmModel(),
		] );

		$response = wp_remote_post( $url, [
			'timeout'    => Config::llmTimeoutSeconds(),
			'headers'    => $headers,
			'body'       => wp_json_encode( $body ),
			'user-agent' => 'TechVaults-AI-Chat/' . TVC_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
		] );

		if ( is_wp_error( $response ) ) {
			// Log error code, not the message (which might echo user input back).
			Logger::error( 'LLM HTTP request failed.', [
				'provider'   => $this->getProviderName(),
				'error_code' => $response->get_error_code(),
			] );
			return $this->fallback();
		}

		$statusCode = (int) wp_remote_retrieve_response_code( $response );
		if ( $statusCode !== 200 ) {
			Logger::error( 'LLM API returned non-200 status.', [
				'provider' => $this->getProviderName(),
				'status'   => $statusCode,
				// Log only first 200 chars of body — avoids dumping large payloads.
				'body_preview' => mb_substr( wp_remote_retrieve_body( $response ), 0, 200 ),
			] );
			return $this->fallback();
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$reply   = $this->extractReply( $decoded );

		if ( empty( $reply ) ) {
			Logger::warning( 'LLM response was empty or unparseable.', [
				'provider' => $this->getProviderName(),
			] );
			return "I'm not sure about that one. Would you like me to connect you to someone on WhatsApp?";
		}

		Logger::debug( 'LLM response received.', [ 'provider' => $this->getProviderName() ] );

		// Cap reply length — prevents runaway responses filling client storage.
		$reply = mb_substr( trim( $reply ), 0, self::MAX_REPLY_LENGTH );

		return $reply;
	}

	abstract protected function buildEndpointUrl( string $apiKey ): string;

	/** @return array<string, string> */
	abstract protected function buildHeaders( string $apiKey ): array;

	/**
	 * @param array<int, array{title: string, answer: string, category: string}> $contextEntries
	 * @param array<int, array{role: string, content: string}>                   $history
	 * @return array<string, mixed>
	 */
	abstract protected function buildRequestBody(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): array;

	/** @param array<string, mixed>|null $decoded */
	abstract protected function extractReply( ?array $decoded ): string;

	// ── Shared helpers ────────────────────────────────────────────────────────

	protected function buildContextBlock( array $contextEntries ): string {
		if ( empty( $contextEntries ) ) {
			return '(No matching knowledge base entry found for this question.)';
		}

		$lines = [];
		foreach ( $contextEntries as $entry ) {
			// Escape any special characters in KB content before injecting into prompt.
			$title  = str_replace( [ "\r", "\n" ], ' ', $entry['title']  ?? '' );
			$answer = str_replace( [ "\r", "\n" ], ' ', $entry['answer'] ?? '' );
			$lines[] = "- {$title}: {$answer}";
		}

		return implode( "\n", $lines );
	}

	protected function fallback(): string {
		return "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
	}
}
