<?php
/**
 * Knowledge Base Retriever — scores and ranks entries against a user query.
 *
 * Single responsibility: relevance scoring only.
 * It depends on Repository for data — it never queries WordPress directly.
 *
 * Phase 1 strategy: keyword overlap scoring.
 * Works reliably for a KB of 20–80 entries with no operational overhead.
 *
 * Upgrade path: replace the score() method with an embeddings-based
 * cosine-similarity lookup. The REST API controller and LLM layer
 * don't change — only this class.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\KnowledgeBase;

class Retriever {

	/** Minimum word length to consider — filters noise words. */
	private const MIN_WORD_LENGTH = 3;

	public function __construct( private readonly Repository $repository ) {}

	/**
	 * Return the top $limit KB entries most relevant to $query.
	 *
	 * @param string $query  The sanitised visitor message.
	 * @param int    $limit  Max entries to return.
	 *
	 * @return array<int, array{title: string, answer: string, category: string}>
	 */
	public function retrieve( string $query, int $limit = 4 ): array {
		$entries = $this->repository->findAll();

		if ( empty( $entries ) ) {
			return [];
		}

		$words = $this->tokenise( $query );

		$scored = [];
		foreach ( $entries as $entry ) {
			$score = $this->score( $words, $entry );
			if ( $score > 0 ) {
				$scored[] = array_merge( $entry, [ 'score' => $score ] );
			}
		}

		usort( $scored, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		// Strip the internal score key before returning.
		return array_map(
			fn( $e ) => [
				'title'    => $e['title'],
				'answer'   => $e['answer'],
				'category' => $e['category'],
			],
			array_slice( $scored, 0, $limit )
		);
	}

	// ── Private ───────────────────────────────────────────────────────────────

	/**
	 * Tokenise a string into unique lowercase words above the minimum length.
	 *
	 * @return string[]
	 */
	private function tokenise( string $text ): array {
		$words = preg_split( '/\W+/u', strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return array_unique(
			array_filter( $words ?? [], fn( $w ) => mb_strlen( $w ) >= self::MIN_WORD_LENGTH )
		);
	}

	/**
	 * Count how many query words appear in the entry's title + answer.
	 *
	 * @param string[]                                               $words
	 * @param array{title: string, answer: string, category: string} $entry
	 */
	private function score( array $words, array $entry ): int {
		$haystack = strtolower( $entry['title'] . ' ' . $entry['answer'] );
		$score    = 0;

		foreach ( $words as $word ) {
			if ( str_contains( $haystack, $word ) ) {
				$score++;
			}
		}

		return $score;
	}
}
