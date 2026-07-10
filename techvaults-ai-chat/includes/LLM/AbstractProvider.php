<?php
/**
 * Abstract LLM provider — shared HTTP infrastructure for all providers.
 *
 * Handles: wp_remote_post, timeout, HTTP error detection, JSON decode,
 * and structured logging. Concrete providers implement buildRequestBody()
 * and extractReply() — nothing else.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\Core\Logger;

abstract class AbstractProvider implements ProviderInterface {

	// ── Template method ───────────────────────────────────────────────────────

	/**
	 * Orchestrates the full HTTP request/response cycle.
	 * Concrete providers only need to implement buildRequestBody() and extractReply().
	 */
	final public function getResponse(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): string {
		$apiKey = Config::llmApiKey();

		if ( empty( $apiKey ) ) {
			Logger::error( 'LLM API key is not configured.', [ 'provider' => $this->getProviderName() ] );
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
			'timeout' => Config::llmTimeoutSeconds(),
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			Logger::error( 'LLM HTTP request failed.', [
				'provider' => $this->getProviderName(),
				'error'    => $response->get_error_message(),
			] );
			return $this->fallback();
		}

		$statusCode = (int) wp_remote_retrieve_response_code( $response );
		if ( $statusCode !== 200 ) {
			Logger::error( 'LLM API returned non-200 status.', [
				'provider' => $this->getProviderName(),
				'status'   => $statusCode,
				'body'     => wp_remote_retrieve_body( $response ),
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

		return trim( $reply );
	}

	// ── Abstract — provider-specific ─────────────────────────────────────────

	/**
	 * Build the full API endpoint URL (with API key if query-param style).
	 */
	abstract protected function buildEndpointUrl( string $apiKey ): string;

	/**
	 * Build the HTTP request headers. API key should go here if header-style.
	 *
	 * @return array<string, string>
	 */
	abstract protected function buildHeaders( string $apiKey ): array;

	/**
	 * Build the JSON request body.
	 *
	 * @param array<int, array{title: string, answer: string, category: string}> $contextEntries
	 * @param array<int, array{role: string, content: string}>                  $history
	 * @return array<string, mixed>
	 */
	abstract protected function buildRequestBody(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): array;

	/**
	 * Extract the reply text from the decoded API response.
	 *
	 * @param array<string, mixed>|null $decoded
	 */
	abstract protected function extractReply( ?array $decoded ): string;

	// ── Shared helpers ────────────────────────────────────────────────────────

	/**
	 * Build a plain-text context block from KB entries.
	 */
	protected function buildContextBlock( array $contextEntries ): string {
		if ( empty( $contextEntries ) ) {
			return '(No matching knowledge base entry found for this question.)';
		}

		$lines = [];
		foreach ( $contextEntries as $entry ) {
			$lines[] = "- {$entry['title']}: {$entry['answer']}";
		}

		return implode( "\n", $lines );
	}

	protected function fallback(): string {
		return "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
	}
}
