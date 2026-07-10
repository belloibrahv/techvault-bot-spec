<?php
/**
 * Google Gemini provider.
 *
 * Extends AbstractProvider — only implements the three Gemini-specific
 * methods. All HTTP infrastructure, error handling, and logging is
 * inherited and shared with every other provider.
 *
 * API reference: https://ai.google.dev/api/generate-content
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM\Providers;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\LLM\AbstractProvider;
use TechVaults\Chat\LLM\SystemPrompt;

class GeminiProvider extends AbstractProvider {

	/**
	 * Gemini uses query-param auth, not a header.
	 * The API key is appended to the URL — never echoed into JS.
	 */
	protected function buildEndpointUrl( string $apiKey ): string {
		$model = rawurlencode( Config::llmModel() );
		return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent"
			. '?key=' . rawurlencode( $apiKey );
	}

	/** @return array<string, string> */
	protected function buildHeaders( string $apiKey ): array {
		return [ 'Content-Type' => 'application/json' ];
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
			$contents[] = [
				'role'  => $turn['role'] === 'assistant' ? 'model' : 'user',
				'parts' => [ [ 'text' => $turn['content'] ] ],
			];
		}

		// Append the current turn with KB context injected into the message.
		$contents[] = [
			'role'  => 'user',
			'parts' => [ [
				'text' => "CONTEXT:\n{$context}\n\nVISITOR MESSAGE:\n{$userMessage}",
			] ],
		];

		return [
			'systemInstruction' => [
				'parts' => [ [ 'text' => SystemPrompt::TEXT ] ],
			],
			'contents'         => $contents,
			'generationConfig' => [
				'maxOutputTokens' => Config::llmMaxTokens(),
				'temperature'     => Config::llmTemperature(),
			],
		];
	}

	protected function extractReply( ?array $decoded ): string {
		return (string) ( $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}

	public function getProviderName(): string {
		return 'gemini';
	}
}
