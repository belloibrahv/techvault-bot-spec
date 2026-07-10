<?php
/**
 * Knowledge Base meta field key constants.
 *
 * Centralising the keys means a typo is a compile-time error,
 * not a silent empty-string bug in production.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\KnowledgeBase;

final class MetaFields {

	public const ANSWER_KEY   = '_tva_kb_answer';
	public const CATEGORY_KEY = '_tva_kb_category';

	private function __construct() {} // Constants-only class.
}
