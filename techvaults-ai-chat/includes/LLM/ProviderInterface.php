<?php
/**
 * LLM Provider contract.
 *
 * Every provider (Gemini, Claude, OpenAI, …) must implement this interface.
 * The rest of the plugin interacts only with this contract — never with a
 * concrete provider class. Switching providers is one Config change.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM;

interface ProviderInterface {

	/**
	 * Generate a reply from the LLM.
	 *
	 * @param string                                                         $userMessage   Sanitised visitor text.
	 * @param array<int, array{title: string, answer: string, category: string}> $contextEntries KB entries returned by Retriever.
	 * @param array<int, array{role: string, content: string}>              $history        Conversation history (last N turns).
	 *
	 * @return string The assistant's reply. Never empty — providers must return
	 *                a human-readable fallback on failure.
	 */
	public function getResponse( string $userMessage, array $contextEntries, array $history ): string;

	/**
	 * Provider identifier — used in logs and settings UI.
	 * Example: 'gemini', 'claude', 'openai'
	 */
	public function getProviderName(): string;
}
