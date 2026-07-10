<?php
/**
 * Knowledge Base Repository — fetches all published KB entries from WordPress.
 *
 * Single responsibility: data access only.
 * Scoring and ranking logic lives in Retriever.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\KnowledgeBase;

class Repository {

	/**
	 * Return all published KB entries as plain arrays.
	 *
	 * @return array<int, array{id: int, title: string, answer: string, category: string}>
	 */
	public function findAll(): array {
		$posts = get_posts( [
			'post_type'   => CPT::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		$entries = [];
		foreach ( $posts as $post ) {
			$entries[] = [
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'answer'   => (string) get_post_meta( $post->ID, MetaFields::ANSWER_KEY,   true ),
				'category' => (string) get_post_meta( $post->ID, MetaFields::CATEGORY_KEY, true ),
			];
		}

		return $entries;
	}
}
