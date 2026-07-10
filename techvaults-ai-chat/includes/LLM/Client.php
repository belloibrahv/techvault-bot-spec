<?php
/**
 * LLM Client — provider factory and single entry point for all LLM calls.
 *
 * The rest of the plugin calls Client::respond() and never touches
 * a concrete provider class. Adding a new provider is:
 *   1. Create Providers/NewProvider.php extending AbstractProvider.
 *   2. Add one case here.
 *   3. Done.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\LLM;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\Core\Logger;
use TechVaults\Chat\LLM\Providers\GeminiProvider;

class Client {

	/**
	 * Resolve the active provider and return its response.
	 *
	 * @param string                                                              $userMessage
	 * @param array<int, array{title: string, answer: string, category: string}> $contextEntries
	 * @param array<int, array{role: string, content: string}>                   $history
	 */
	public static function respond(
		string $userMessage,
		array  $contextEntries,
		array  $history
	): string {
		return self::resolveProvider()->getResponse( $userMessage, $contextEntries, $history );
	}

	private static function resolveProvider(): ProviderInterface {
		$slug = Config::llmProvider();

		switch ( $slug ) {
			case 'gemini':
				return new GeminiProvider();

			// Uncomment as providers are implemented:
			// case 'claude':
			//     return new Providers\ClaudeProvider();
			// case 'openai':
			//     return new Providers\OpenAIProvider();

			default:
				Logger::warning( 'Unknown LLM provider slug, falling back to Gemini.', [ 'slug' => $slug ] );
				return new GeminiProvider();
		}
	}
}
