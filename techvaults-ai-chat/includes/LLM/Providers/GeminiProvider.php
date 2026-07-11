<?php
/**
 * Google Gemini provider.
 *
 * Supports gemini-flash-latest (resolves to gemini-3.5-flash) and all other
 * Gemini models via the v1beta API.
 * API reference: https://ai.google.dev/api/generate-content
 *
 * Recommended model for new API keys: gemini-flash-latest
 *   → resolves to gemini-3.5-flash
 *   → thinkingBudget=0 disables slow thinking for fast chat responses
 *   → systemInstruction passed as top-level field (not in contents)
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM\Providers;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\LLM\AbstractProvider;
use TechVaults\Chat\LLM\SystemPrompt;

class GeminiProvider extends AbstractProvider {

	/** v1beta supports all models including gemini-2.5-flash */
	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	protected function buildEndpointUrl( string $apiKey ): string {
		$model = rawurlencode( Config::llmModel() );
		// Use query-param style — works alongside the header for compatibility.
		return self::API_BASE . $model . ':generateContent?key=' . rawurlencode( $apiKey );
	}

	/** @return array<string, string> */
	protected function buildHeaders( string $apiKey ): array {
		return [
			'Content-Type'    => 'application/json',
			'x-goog-api-key'  => $apiKey,  // preferred header-based auth (docs-recommended)
		];
	}

	/** @return array<string, mixed> */
	protected function buildRequestBody(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): array {
		$context  = $this->buildContextBlock( $contextEntries );
		$contents = [];

		// Map conversation history — Gemini uses 'model' not 'assistant'.
		foreach ( $history as $turn ) {
			$role = $turn['role'] === 'assistant' ? 'model' : 'user';
			// Skip invalid roles and empty content.
			if ( empty( $turn['content'] ) ) continue;
			$contents[] = [
				'role'  => $role,
				'parts' => [ [ 'text' => (string) $turn['content'] ] ],
			];
		}

		// Gemini requires alternating user/model turns.
		// If history ends on 'model', that's fine. But we must never have two
		// consecutive turns of the same role — sanitise just in case.
		$contents = self::sanitiseHistory( $contents );

		// Append the current user message with KB context injected.
		$contents[] = [
			'role'  => 'user',
			'parts' => [ [
				'text' => "CONTEXT:\n{$context}\n\nVISITOR MESSAGE:\n{$userMessage}",
			] ],
		];

		$body = [
			'systemInstruction' => [
				'parts' => [ [ 'text' => SystemPrompt::TEXT ] ],
			],
			'contents'         => $contents,
			'generationConfig' => [
				'maxOutputTokens' => Config::llmMaxTokens(),
				'temperature'     => Config::llmTemperature(),
			],
		];

		// Disable thinking mode for chat latency on models that support thinkingConfig.
		// gemini-flash-latest (3.5-flash), gemini-2.5-flash, gemini-2.5-pro etc all support it.
		// thinkingConfig is silently ignored by models that don't support it, so safe to always set.
		$body['generationConfig']['thinkingConfig'] = [
			'thinkingBudget' => 0,
		];

		return $body;
	}

	protected function extractReply( ?array $decoded ): string {
		// Handle blocked/safety-filtered responses.
		$finishReason = $decoded['candidates'][0]['finishReason'] ?? '';
		if ( in_array( $finishReason, [ 'SAFETY', 'RECITATION', 'BLOCKLIST' ], true ) ) {
			return "I can't respond to that. Can I help you with something about TechVaults services instead?";
		}

		return (string) ( $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}

	public function getProviderName(): string {
		return 'gemini';
	}

	/**
	 * Ensure no two consecutive turns share the same role.
	 * Gemini rejects requests where this happens.
	 *
	 * @param array<int, array{role: string, parts: array}> $contents
	 * @return array<int, array{role: string, parts: array}>
	 */
	private static function sanitiseHistory( array $contents ): array {
		if ( empty( $contents ) ) return [];

		$clean = [ $contents[0] ];
		for ( $i = 1; $i < count( $contents ); $i++ ) {
			if ( $contents[ $i ]['role'] !== end( $clean )['role'] ) {
				$clean[] = $contents[ $i ];
			}
			// Duplicate role — skip; the message is lost but the request won't fail.
		}
		return $clean;
	}
}
