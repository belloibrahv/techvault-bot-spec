<?php
/**
 * LLM Provider Interface.
 *
 * Every LLM provider (Gemini, Claude, OpenAI, …) must implement this contract.
 * The REST API and the rest of the plugin only depend on this interface,
 * never on a concrete provider class — this is what makes switching providers
 * a one-option-change rather than a codebase refactor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TVA_LLM_Provider_Interface {

	/**
	 * Send the user message, KB context, and conversation history to the LLM
	 * and return the assistant's reply as a plain string.
	 *
	 * @param string $user_message    The sanitised visitor message.
	 * @param array  $context_entries Knowledge base entries returned by TVA_Knowledge_Base::retrieve().
	 *                                Each entry: [ 'title' => string, 'answer' => string, 'category' => string ]
	 * @param array  $history         Conversation history, each turn: [ 'role' => 'user'|'assistant', 'content' => string ]
	 *
	 * @return string The assistant's reply. Must never be empty. Fall back to the
	 *                WhatsApp redirect message if the API call fails.
	 */
	public function get_response( string $user_message, array $context_entries, array $history ): string;

	/**
	 * Return the provider slug used in the settings UI and error logs.
	 * Example: 'gemini', 'claude', 'openai'
	 */
	public function get_provider_name(): string;
}
