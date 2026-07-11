<?php
/**
 * AgentRouter provider — OpenAI-compatible API gateway.
 *
 * AgentRouter (agentrouter.org) is an OpenAI-compatible proxy that routes
 * requests to Claude, GPT, Gemini, DeepSeek, and other models through a
 * single endpoint with unified billing.
 *
 * API reference: https://agentrouter.org/v1
 * Auth:          Authorization: Bearer sk-...
 * Format:        OpenAI Chat Completions (/v1/chat/completions)
 * Default model: gemini-2.0-pro  (good free-tier balance for chat)
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM\Providers;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\LLM\AbstractProvider;
use TechVaults\Chat\LLM\SystemPrompt;

class AgentRouterProvider extends AbstractProvider {

	private const API_URL = 'https://agentrouter.org/v1/chat/completions';

	protected function buildEndpointUrl( string $apiKey ): string {
		return self::API_URL;
	}

	/** @return array<string, string> */
	protected function buildHeaders( string $apiKey ): array {
		return [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $apiKey,
		];
	}

	/** @return array<string, mixed> */
	protected function buildRequestBody(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): array {
		$context  = $this->buildContextBlock( $contextEntries );
		$messages = [];

		// System prompt as first message.
		$messages[] = [
			'role'    => 'system',
			'content' => SystemPrompt::TEXT,
		];

		// Map conversation history — OpenAI uses 'assistant' not 'model'.
		foreach ( $history as $turn ) {
			if ( empty( $turn['content'] ) ) continue;
			$role = $turn['role'] === 'model' ? 'assistant' : $turn['role'];
			$messages[] = [
				'role'    => $role,
				'content' => (string) $turn['content'],
			];
		}

		// Current user message with KB context injected.
		$messages[] = [
			'role'    => 'user',
			'content' => "CONTEXT:\n{$context}\n\nVISITOR MESSAGE:\n{$userMessage}",
		];

		return [
			'model'      => Config::llmModel(),
			'messages'   => $messages,
			'max_tokens' => Config::llmMaxTokens(),
			'temperature' => Config::llmTemperature(),
		];
	}

	protected function extractReply( ?array $decoded ): string {
		// Handle error responses from the gateway.
		if ( isset( $decoded['error']['message'] ) ) {
			return '';  // AbstractProvider will log and return fallback.
		}

		return (string) ( $decoded['choices'][0]['message']['content'] ?? '' );
	}

	public function getProviderName(): string {
		return 'agentrouter';
	}
}
